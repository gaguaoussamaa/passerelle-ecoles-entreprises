#!/bin/bash
# ============================================================================
# Passerelle — PRODUCTION SIMULÉE : lance la MÊME application en mode production
# (APP_DEBUG=false, cookies Secure, sessions chiffrées) derrière un reverse-proxy
# Caddy en HTTPS auto-signé LOCAL, exécute une batterie de vérifications, puis
# RESTAURE automatiquement le mode local de démonstration.
#
# Prérequis : la démo doit tourner (`bash demarrer.sh`) — on réutilise sa base.
# Aucune donnée n'est modifiée (connexion + consultation + erreur volontaire).
# Rien n'est laissé en place : le trap remet la démo locale même en cas d'échec.
# ============================================================================
set -uo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"; cd "$ROOT"
PROD="docker compose -f docker-compose.yml -f docker-compose.production.yml"
BASE="docker compose -f docker-compose.yml"
LOCAL="http://localhost:8000"
HTTPS="https://localhost:8443"
TMP="$(mktemp -d)"
OK=0; KO=0
verdict() { if [ "$1" = "ok" ]; then OK=$((OK+1)); echo "  ✅ $2"; else KO=$((KO+1)); echo "  ❌ $2"; fi; }

restaurer() {
  echo; echo "— Restauration du mode local (démo)…"
  docker rm -f passerelle-caddy >/dev/null 2>&1 || true
  $BASE up -d >/dev/null 2>&1
  rm -rf "$TMP"
  echo "— Démo locale restaurée (http://localhost:8000, APP_DEBUG=true)."
}
trap restaurer EXIT

# Connexion (gère le CSRF) : $1=base_url  $2=cookiejar  -> renvoie 0 si 302
login() {
  local base="$1" cj="$2"; rm -f "$cj"
  local tok; tok=$(curl -sk -c "$cj" "$base/connexion" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
  curl -sk -b "$cj" -c "$cj" -o /dev/null -w '%{http_code}' -X POST "$base/connexion" \
    --data-urlencode "_token=$tok" --data-urlencode "email=responsable.inl@passerelle.demo" \
    --data-urlencode "mot_de_passe=Passerelle2026!"
}
# Marqueurs d'une page de debug (trace exposée) — ne doivent PAS fuiter en prod
TRACE='TypeError|Stack trace|vendor/|Ignition|Whoops|BadMethodCall|/var/www/html/app'

echo "============================================================"
echo " Passerelle — vérification de la PRODUCTION SIMULÉE"
echo "============================================================"

# --- Référence AVANT : en mode LOCAL (debug=true), une erreur applicative expose la trace
echo; echo "[Référence] Mode LOCAL (APP_DEBUG=true) — erreur volontaire :"
login "$LOCAL" "$TMP/cl" >/dev/null
curl -sk -b "$TMP/cl" "$LOCAL/conventions/abc/pdf" -o "$TMP/err_local.html" -w "" 2>/dev/null
if grep -qE "$TRACE" "$TMP/err_local.html"; then echo "  ⚠️  trace d'exception exposée (attendu en local, à NE PAS faire en prod)"; else echo "  (pas de trace détectée)"; fi

# --- Bascule en PRODUCTION SIMULÉE
echo; echo "— Démarrage du profil production (app en mode production + Caddy HTTPS)…"
$PROD up -d >/dev/null 2>&1
echo -n "— Attente du service HTTPS"
for i in $(seq 1 30); do
  code=$(curl -sk -o /dev/null -w '%{http_code}' "$HTTPS/connexion" 2>/dev/null || echo 000)
  if [ "$code" = "200" ]; then echo " → prêt."; break; fi
  echo -n "."; sleep 1
done

echo; echo "[Vérifications] Mode PRODUCTION (https://localhost:8443)"

# 1) HTTPS opérationnel (certificat auto-signé accepté avec -k)
code=$(curl -sk -o /dev/null -w '%{http_code}' "$HTTPS/connexion")
[ "$code" = "200" ] && verdict ok "HTTPS opérationnel (page de connexion 200)" || verdict ko "HTTPS (code $code)"

# 2) En-têtes de sécurité (dont HSTS, actif seulement en HTTPS/prod)
H=$(curl -sk -D - -o /dev/null "$HTTPS/connexion")
echo "$H" | grep -qi '^strict-transport-security:' && verdict ok "HSTS présent (Strict-Transport-Security)" || verdict ko "HSTS absent"
echo "$H" | grep -qi '^x-frame-options: *DENY' && verdict ok "X-Frame-Options: DENY" || verdict ko "X-Frame-Options"
echo "$H" | grep -qi '^x-content-type-options: *nosniff' && verdict ok "X-Content-Type-Options: nosniff" || verdict ko "nosniff"
echo "$H" | grep -qi '^referrer-policy:' && verdict ok "Referrer-Policy présent" || verdict ko "Referrer-Policy"

# 3) Cookie de session durci : Secure + HttpOnly + SameSite
SC=$(curl -sk -c "$TMP/cp" -D - -o /dev/null "$HTTPS/connexion" | grep -i '^set-cookie:.*session' | head -1)
echo "$SC" | grep -qi 'secure'   && verdict ok "Cookie de session : Secure"   || verdict ko "Cookie sans Secure"
echo "$SC" | grep -qi 'httponly' && verdict ok "Cookie de session : HttpOnly" || verdict ko "Cookie sans HttpOnly"
echo "$SC" | grep -qi 'samesite' && verdict ok "Cookie de session : SameSite" || verdict ko "Cookie sans SameSite"

# 4) APP_DEBUG réellement désactivé côté application
DBG=$(docker exec passerelle-app php artisan tinker --execute="echo config('app.debug') ? 'true':'false';" 2>/dev/null | tail -1)
[ "$DBG" = "false" ] && verdict ok "APP_DEBUG = false (config applicative)" || verdict ko "APP_DEBUG = $DBG"

# 5) Une erreur applicative n'expose AUCUNE trace (page générique)
login "$HTTPS" "$TMP/cp" >/dev/null
scode=$(curl -sk -b "$TMP/cp" "$HTTPS/conventions/abc/pdf" -o "$TMP/err_prod.html" -w '%{http_code}')
if grep -qE "$TRACE" "$TMP/err_prod.html"; then
  verdict ko "Erreur applicative : trace exposée (code $scode)"
else
  verdict ok "Erreur applicative : page générique sans trace (code $scode)"
fi

# 6) Connexion fonctionnelle en HTTPS (l'app reste utilisable)
lc=$(login "$HTTPS" "$TMP/cok")
[ "$lc" = "302" ] && verdict ok "Connexion HTTPS fonctionnelle (302 → tableau de bord)" || verdict ko "Connexion HTTPS (code $lc)"

echo; echo "============================================================"
echo " Résultat : $OK réussite(s), $KO échec(s)."
echo "============================================================"
# (le trap restaure ensuite automatiquement le mode local)
[ "$KO" -eq 0 ]

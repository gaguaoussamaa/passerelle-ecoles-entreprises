#!/bin/bash
# ============================================================================
# Passerelle — démarrage reproductible de l'environnement de démonstration
# Prérequis : Docker + Docker Compose uniquement (aucun PHP/Composer local).
#
#   bash demarrer.sh                 démarrage complet depuis une copie propre
#   bash demarrer.sh --reinitialiser réinitialise uniquement les données de démo
#
# Application : http://localhost:8000 — Boîte e-mails : http://localhost:8025
# Comptes de démonstration (mot de passe commun : Passerelle2026!) :
#   admin@passerelle.demo, responsable.inl@passerelle.demo,
#   responsable.horizon@passerelle.demo, contact@technova.demo,
#   contact@studiokumo.demo
# ============================================================================
set -e
cd "$(dirname "$0")"

if ! docker info >/dev/null 2>&1; then
  echo "Docker n'est pas démarré. Lancez Docker Desktop puis relancez ce script." >&2
  exit 1
fi

attendre_mysql() {
  echo "— Attente de MySQL…"
  until docker exec passerelle-mysql mysql -upasserelle -ppasserelle-dev -e "SELECT 1" passerelle >/dev/null 2>&1; do
    sleep 3
  done
}

if [ "${1:-}" = "--reinitialiser" ]; then
  attendre_mysql
  docker exec passerelle-app php artisan migrate:fresh --seed --force
  echo "Jeu de démonstration réinitialisé."
  exit 0
fi

# 1) Dépendances PHP (via conteneur Composer : rien à installer sur le poste)
if [ ! -d passerelle/vendor ]; then
  echo "— Installation des dépendances PHP (conteneur Composer)…"
  docker run --rm -v "$PWD/passerelle:/app" -w /app composer:2 composer install --no-interaction --prefer-dist
fi

# 2) Configuration d'environnement
if [ ! -f passerelle/.env ]; then
  cp passerelle/.env.example passerelle/.env
  echo "— .env créé depuis .env.example"
fi

# 3) Conteneurs
echo "— Démarrage des conteneurs…"
docker compose up -d --build
attendre_mysql

# 4) Clé applicative
if ! grep -q "^APP_KEY=base64" passerelle/.env; then
  docker exec passerelle-app php artisan key:generate --force
fi

# 5) Droits d'écriture Laravel (journaux, caches, fichiers déposés)
docker exec passerelle-app chmod -R ug+rwX storage bootstrap/cache

# 6) Base de test PHPUnit (contraintes MySQL réelles dans les tests)
docker exec passerelle-mysql mysql -uroot -proot-dev -e \
  "CREATE DATABASE IF NOT EXISTS passerelle_test CHARACTER SET utf8mb4; \
   GRANT ALL PRIVILEGES ON passerelle_test.* TO 'passerelle'@'%'; FLUSH PRIVILEGES;" 2>/dev/null

# 7) Schéma (28 tables du MPD) et jeu de démonstration
docker exec passerelle-app php artisan migrate:fresh --seed --force

echo ""
echo "Passerelle est prête :"
echo "  Application : http://localhost:8000"
echo "  E-mails     : http://localhost:8025"
echo "  Comptes     : voir l'en-tête de ce script (mot de passe : Passerelle2026!)"

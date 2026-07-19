<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Table mère de l'héritage {complète, disjointe} des comptes (conception
 * détaillée §3) : identité d'authentification unique, rôle discriminant.
 * RG-03 (e-mail unique), RG-04 (mot de passe haché), RG-05/ENF-04 (accès).
 */
class Compte extends Authenticatable
{
    protected $table = 'comptes';

    public const ROLES = ['etudiant', 'tuteur_pedagogique', 'responsable', 'entreprise', 'super_admin'];

    protected $fillable = ['email', 'mot_de_passe', 'role', 'actif'];

    /** Miroir du défaut SQL : une instance non rafraîchie reste cohérente. */
    protected $attributes = ['actif' => true];

    protected $hidden = ['mot_de_passe'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'mot_de_passe' => 'hashed'];
    }

    /** Colonne du mot de passe (le schéma est en français). */
    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    /** Pas de jeton « se souvenir de moi » dans le périmètre Must. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    // ------------------------------------------------------------ Profils
    public function etudiant(): HasOne
    {
        return $this->hasOne(Etudiant::class, 'compte_id');
    }

    public function tuteurPedagogique(): HasOne
    {
        return $this->hasOne(TuteurPedagogique::class, 'compte_id');
    }

    public function responsable(): HasOne
    {
        return $this->hasOne(Responsable::class, 'compte_id');
    }

    public function entreprise(): HasOne
    {
        return $this->hasOne(Entreprise::class, 'compte_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'compte_id');
    }

    /** Profil métier du compte, selon le rôle discriminant. */
    public function profil(): Etudiant|TuteurPedagogique|Responsable|Entreprise|null
    {
        return match ($this->role) {
            'etudiant' => $this->etudiant,
            'tuteur_pedagogique' => $this->tuteurPedagogique,
            'responsable' => $this->responsable,
            'entreprise' => $this->entreprise,
            default => null,
        };
    }

    /** Établissement de rattachement (rôles école), pour le cloisonnement. */
    public function etablissementId(): ?int
    {
        return match ($this->role) {
            'responsable' => $this->responsable?->etablissement_id,
            'tuteur_pedagogique' => $this->tuteurPedagogique?->etablissement_id,
            'etudiant' => $this->etudiant?->promotion?->formation?->etablissement_id,
            default => null,
        };
    }

    /**
     * RG-05 : l'accès d'un étudiant découle de son statut de scolarité ;
     * les autres rôles dépendent du drapeau « actif » (ENF-04).
     */
    public function accesAutorise(): bool
    {
        if ($this->role === 'etudiant') {
            return $this->etudiant?->statut_scolarite === 'actif';
        }

        return $this->actif;
    }
}

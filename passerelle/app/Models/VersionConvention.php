<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Version numérotée et immuable d'une convention (RG-32) : PDF + empreinte
 * SHA-256. Circuit séquentiel de validation (RG-33), approbations en ordre
 * libre (RG-35), annulation-remplacement d'une version approuvée (RG-37).
 */
class VersionConvention extends Model
{
    protected $table = 'versions_convention';

    /** Ordre imposé du circuit de validation (RG-33). */
    public const ORDRE_VALIDATION = ['etudiant', 'entreprise', 'tuteur_pedagogique', 'responsable'];

    /** États où la version circule encore (une seule à la fois par mission). */
    public const EN_CIRCULATION = ['emise', 'en_validation', 'validee', 'en_approbation'];

    public const LIBELLES = [
        'emise' => 'émise', 'en_validation' => 'en validation', 'validee' => 'validée',
        'en_approbation' => 'en approbation', 'approuvee' => 'approuvée',
        'refusee_correction' => 'refusée (à corriger)', 'remplacee' => 'remplacée', 'annulee' => 'annulée',
    ];

    protected $attributes = ['statut' => 'emise'];          // défaut SQL reflété (décision n°7)

    protected $fillable = ['mission_id', 'numero', 'fichier_pdf', 'empreinte', 'statut'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActionConvention::class, 'version_id');
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    /** Parties ayant validé cette version (le refus réinitialise via une nouvelle version). */
    public function validationsEffectuees(): Collection
    {
        return $this->actions()->where('type', 'validation')->pluck('role_partie');
    }

    /** RG-33 : prochaine partie attendue dans le circuit séquentiel, sinon null. */
    public function prochainValideur(): ?string
    {
        $faites = $this->validationsEffectuees();

        foreach (self::ORDRE_VALIDATION as $role) {
            if (! $faites->contains($role)) {
                return $role;
            }
        }

        return null;
    }

    public function approbationsEffectuees(): Collection
    {
        return $this->actions()->where('type', 'approbation')->pluck('role_partie');
    }

    /** Action attendue de cette partie sur cette version : 'valider', 'approuver' ou null. */
    public function actionAttendueDe(string $role): ?string
    {
        if (in_array($this->statut, ['emise', 'en_validation'], true)) {
            return $this->prochainValideur() === $role ? 'valider' : null;
        }
        if (in_array($this->statut, ['validee', 'en_approbation'], true)) {
            return $this->approbationsEffectuees()->contains($role) ? null : 'approuver';
        }

        return null;
    }
}

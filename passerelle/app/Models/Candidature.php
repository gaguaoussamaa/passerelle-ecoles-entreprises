<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Candidature extends Model
{
    protected $table = 'candidatures';

    /** États où l'entreprise instruit encore le dossier (TU-04). */
    public const EN_EXAMEN = ['recue', 'preselectionnee', 'entretien'];

    /** États non terminaux — retirés d'office à la confirmation d'une autre (RG-25). */
    public const ACTIFS = ['recue', 'preselectionnee', 'entretien', 'retenue'];

    public const LIBELLES = [
        'recue' => 'reçue', 'preselectionnee' => 'présélectionnée', 'entretien' => 'entretien',
        'retenue' => 'retenue', 'confirmee' => 'confirmée', 'declinee' => 'déclinée',
        'refusee' => 'refusée', 'retiree' => 'retirée',
    ];

    protected $attributes = ['statut' => 'recue'];          // défaut SQL reflété (décision n°7)

    protected $fillable = ['etudiant_id', 'offre_id', 'statut', 'message', 'cv_depose'];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'etudiant_id');
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_id');
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'candidature_id');
    }

    /** RG-24 : retrait par l'étudiant tant que la candidature n'est pas « retenue ». */
    public function retirable(): bool
    {
        return in_array($this->statut, self::EN_EXAMEN, true);
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }
}

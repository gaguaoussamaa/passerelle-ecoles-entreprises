<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Déclaration d'une mission trouvée hors plateforme (RG-46) — objet distinct
 * de la mission : soumise, refusée (corrigeable), abandonnée, recevable
 * (terminal : la mission « en montage » est créée, chemin B — RG-26/27).
 */
class Declaration extends Model
{
    protected $table = 'declarations';

    /** États où le dossier est encore ouvert — un seul à la fois par étudiant (RG-46). */
    public const EN_COURS = ['soumise', 'refusee'];

    public const LIBELLES = [
        'soumise' => 'soumise', 'recevable' => 'recevable',
        'refusee' => 'refusée', 'abandonnee' => 'abandonnée',
    ];

    protected $attributes = ['statut' => 'soumise'];        // défaut SQL reflété (décision n°7)

    protected $fillable = [
        'etudiant_id', 'type_mission', 'date_debut_prevue', 'date_fin_prevue',
        'entreprise_saisie', 'siret_saisi', 'contact_nom', 'contact_email',
        'description', 'statut', 'motif_refus',
    ];

    protected function casts(): array
    {
        return ['date_debut_prevue' => 'date', 'date_fin_prevue' => 'date'];
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'etudiant_id');
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class, 'declaration_id');
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }
}

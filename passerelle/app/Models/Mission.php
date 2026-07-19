<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mission née d'une candidature confirmée (chemin A) XOR d'une déclaration
 * recevable (chemin B) — exclusivité garantie par CHECK en base. Les états
 * temporels (active, en évaluation, retards) sont calculés depuis les dates,
 * jamais stockés : le statut ne porte que les jalons décidés par des acteurs.
 */
class Mission extends Model
{
    protected $table = 'missions';

    protected $attributes = ['statut' => 'en_montage'];     // défaut SQL reflété (décision n°7)

    protected $fillable = [
        'etudiant_id', 'entreprise_id', 'candidature_id', 'declaration_id',
        'tuteur_pedagogique_id', 'tuteur_entreprise_id', 'type',
        'date_debut', 'date_fin', 'statut', 'motif_arret', 'date_effet_arret',
    ];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date', 'date_effet_arret' => 'date'];
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'etudiant_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class, 'candidature_id');
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class, 'declaration_id');
    }

    public function tuteurPedagogique(): BelongsTo
    {
        return $this->belongsTo(TuteurPedagogique::class, 'tuteur_pedagogique_id');
    }

    public function tuteurEntreprise(): BelongsTo
    {
        return $this->belongsTo(TuteurEntreprise::class, 'tuteur_entreprise_id');
    }

    /** États où l'étudiant est engagé — bloquent une nouvelle déclaration (RG-30 a contrario). */
    public const EN_COURS = ['en_montage', 'en_contractualisation', 'contractualisee'];

    /**
     * RG-29 : « active » et « en évaluation » sont calculés à la consultation
     * depuis les dates — le statut stocké plafonne à « contractualisée ».
     */
    public function statutCalcule(): string
    {
        if ($this->statut === 'contractualisee') {
            if (now()->greaterThan($this->date_fin->endOfDay())) {
                return 'en_evaluation';
            }
            if (now()->greaterThanOrEqualTo($this->date_debut->startOfDay())) {
                return 'active';
            }
        }

        return $this->statut;
    }

    public function libelleStatutCalcule(): string
    {
        return ['en_montage' => 'en montage', 'en_contractualisation' => 'en contractualisation',
            'contractualisee' => 'contractualisée (à venir)', 'active' => 'active',
            'en_evaluation' => 'en évaluation', 'cloturee' => 'clôturée',
            'annulee' => 'annulée', 'interrompue' => 'interrompue'][$this->statutCalcule()] ?? $this->statutCalcule();
    }

    public function signalements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Signalement::class, 'mission_id');
    }

    public function versionsConvention(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VersionConvention::class, 'mission_id');
    }

    public function jalons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Jalon::class, 'mission_id');
    }

    /** Version en circulation ou approuvée la plus récente (une seule à la fois). */
    public function conventionCourante(): ?VersionConvention
    {
        return $this->versionsConvention()->latest('numero')->first();
    }

    /** RG-28 : les deux tuteurs désignés ⇒ la mission passe « en contractualisation ». */
    public function contractualiserSiEncadree(): void
    {
        if ($this->statut === 'en_montage' && $this->tuteur_pedagogique_id && $this->tuteur_entreprise_id) {
            $this->update(['statut' => 'en_contractualisation']);
            JournalAudit::tracer('mission_en_contractualisation', 'mission', $this->id);
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Évaluation de fin de mission (RG-41) : grille standard notée sur 5 +
 * commentaire, remplie par le tuteur en entreprise — au plus une par mission.
 */
class Evaluation extends Model
{
    protected $table = 'evaluations';

    protected $fillable = ['mission_id', 'tuteur_entreprise_id', 'commentaire'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function tuteurEntreprise(): BelongsTo
    {
        return $this->belongsTo(TuteurEntreprise::class, 'tuteur_entreprise_id');
    }

    /** Notes par critère (table notes_criteres, note 0..5). */
    public function criteres(): BelongsToMany
    {
        return $this->belongsToMany(Critere::class, 'notes_criteres', 'evaluation_id', 'critere_id')
            ->withPivot('note')->withTimestamps();
    }
}

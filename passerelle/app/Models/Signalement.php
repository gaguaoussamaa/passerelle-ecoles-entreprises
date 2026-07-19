<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Signalement de difficulté (RG-40) : émis par l'étudiant ou l'entreprise,
 * alerte prioritaire pour le tuteur pédagogique et le responsable ;
 * ouvert → en cours (traitant) → clos (issue consignée).
 */
class Signalement extends Model
{
    protected $table = 'signalements';

    public const LIBELLES = ['ouvert' => 'ouvert', 'en_cours' => 'en cours', 'clos' => 'clos'];

    protected $attributes = ['statut' => 'ouvert'];         // défaut SQL reflété (décision n°7)

    protected $fillable = ['mission_id', 'emetteur_id', 'traitant_id', 'description', 'statut', 'issue'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'emetteur_id');
    }

    public function traitant(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'traitant_id');
    }
}

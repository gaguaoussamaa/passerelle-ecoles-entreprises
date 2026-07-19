<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Modération d'une offre par une école (RG-17/18) — indépendante par école. */
class Diffusion extends Model
{
    protected $table = 'diffusions';
    protected $fillable = ['offre_id', 'etablissement_id', 'statut', 'motif_refus'];

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lien école ↔ entreprise (RG-16, RG-47) : en_attente → actif à l'activation du compte. */
class Partenariat extends Model
{
    protected $table = 'partenariats';
    protected $fillable = ['etablissement_id', 'entreprise_id', 'statut'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }
}

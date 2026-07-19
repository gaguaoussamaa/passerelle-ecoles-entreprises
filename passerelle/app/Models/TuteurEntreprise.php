<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuteurEntreprise extends Model
{
    protected $table = 'tuteurs_entreprise';

    protected $fillable = ['entreprise_id', 'nom', 'prenom', 'email'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entreprise extends Model
{
    protected $table = 'entreprises';
    protected $primaryKey = 'compte_id';
    public $incrementing = false;

    protected $fillable = ['compte_id', 'raison_sociale', 'siret', 'ville'];

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }

    public function tuteurs(): HasMany
    {
        return $this->hasMany(TuteurEntreprise::class, 'entreprise_id');
    }
}

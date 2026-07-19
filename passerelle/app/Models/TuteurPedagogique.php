<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TuteurPedagogique extends Model
{
    protected $table = 'tuteurs_pedagogiques';
    protected $primaryKey = 'compte_id';
    public $incrementing = false;

    protected $fillable = ['compte_id', 'etablissement_id', 'nom', 'prenom', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(Formation::class, 'rattachements', 'tuteur_id', 'formation_id')->withTimestamps();
    }
}

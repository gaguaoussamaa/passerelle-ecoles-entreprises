<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offre extends Model
{
    protected $table = 'offres';

    protected $fillable = [
        'entreprise_id', 'domaine_id', 'intitule', 'description', 'type',
        'niveau', 'lieu', 'date_debut_prevue', 'date_fin_prevue', 'nb_postes', 'statut',
    ];

    protected function casts(): array
    {
        return ['date_debut_prevue' => 'date', 'date_fin_prevue' => 'date'];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function domaine(): BelongsTo
    {
        return $this->belongsTo(Domaine::class, 'domaine_id');
    }

    public function diffusions(): HasMany
    {
        return $this->hasMany(Diffusion::class, 'offre_id');
    }

    /** Promotions ciblées à la modération (RG-19). */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'affectations')->withTimestamps();
    }

    /** RG-20 : modifiable/retirable tant qu'aucune école n'a validé. */
    public function modifiable(): bool
    {
        return $this->diffusions()->where('statut', 'validee')->doesntExist();
    }
}

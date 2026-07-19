<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    protected $table = 'etablissements';

    protected $fillable = [
        'nom', 'siret', 'ville', 'logo',
        'plan_abonnement', 'debut_abonnement', 'fin_abonnement',
    ];

    protected function casts(): array
    {
        return ['debut_abonnement' => 'date', 'fin_abonnement' => 'date'];
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class, 'etablissement_id');
    }

    public function responsables(): HasMany
    {
        return $this->hasMany(Responsable::class, 'etablissement_id');
    }
}

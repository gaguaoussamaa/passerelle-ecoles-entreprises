<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    protected $table = 'formations';

    protected $fillable = ['etablissement_id', 'intitule', 'niveau', 'type_mission', 'archivee'];

    protected function casts(): array
    {
        return ['archivee' => 'boolean'];
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'formation_id');
    }

    public function domaines(): BelongsToMany
    {
        return $this->belongsToMany(Domaine::class, 'formation_domaine')->withTimestamps();
    }
}

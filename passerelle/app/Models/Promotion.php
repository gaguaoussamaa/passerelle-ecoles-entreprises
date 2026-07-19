<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $table = 'promotions';

    protected $fillable = ['formation_id', 'libelle', 'annee_universitaire', 'archivee'];

    protected function casts(): array
    {
        return ['archivee' => 'boolean'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'formation_id');
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class, 'promotion_id');
    }
}

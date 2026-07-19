<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Critère de la grille d'évaluation standard de la plateforme (RG-41). */
class Critere extends Model
{
    protected $table = 'criteres';

    protected $fillable = ['code', 'libelle'];
}

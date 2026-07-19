<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Etudiant extends Model
{
    protected $table = 'etudiants';
    protected $primaryKey = 'compte_id';
    public $incrementing = false;

    public const STATUTS = ['invite', 'actif', 'diplome', 'sorti'];

    protected $fillable = ['compte_id', 'promotion_id', 'nom', 'prenom', 'statut_scolarite', 'cv_profil'];

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }
}

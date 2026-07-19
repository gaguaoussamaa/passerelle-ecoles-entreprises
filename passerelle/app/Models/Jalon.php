<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jalon de l'échéancier (RG-38), ancré sur les dates de la mission.
 * « Rendu » = fichier déposé ; « en retard » = échéance passée sans dépôt (calculé, RG-39).
 */
class Jalon extends Model
{
    protected $table = 'jalons';

    protected $fillable = ['mission_id', 'type', 'date_echeance', 'fichier_depose', 'date_depot'];

    protected function casts(): array
    {
        return ['date_echeance' => 'date', 'date_depot' => 'datetime'];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }
}

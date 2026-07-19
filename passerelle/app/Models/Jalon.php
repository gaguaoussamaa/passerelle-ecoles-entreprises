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

    /** État calculé (RG-39, rien n'est stocké) : rendu, rendu en retard, en retard, à venir. */
    public function etat(): string
    {
        if ($this->fichier_depose !== null) {
            return $this->date_depot->greaterThan($this->date_echeance->endOfDay()) ? 'rendu_tardif' : 'rendu';
        }

        return $this->date_echeance->isPast() ? 'en_retard' : 'a_venir';
    }

    public function libelleEtat(): string
    {
        return ['rendu' => 'rendu', 'rendu_tardif' => 'rendu (en retard)',
            'en_retard' => 'en retard', 'a_venir' => 'à venir'][$this->etat()];
    }
}

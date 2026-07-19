<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton d'activation d'un compte (RG-01, RG-02) : usage unique, 72 heures,
 * le renvoi invalide le jeton précédent. Seule l'empreinte du jeton est stockée.
 */
class Invitation extends Model
{
    protected $table = 'invitations';

    protected $fillable = ['compte_id', 'jeton_hash', 'expire_le', 'utilisee_le', 'statut'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'utilisee_le' => 'datetime'];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }

    public function estValide(): bool
    {
        return $this->statut === 'active' && $this->expire_le->isFuture();
    }
}

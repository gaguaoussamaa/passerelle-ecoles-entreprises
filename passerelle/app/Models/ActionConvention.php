<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Action d'une partie sur une version de convention — l'auteur réel est tracé (compte_id). */
class ActionConvention extends Model
{
    protected $table = 'actions_convention';

    protected $fillable = ['version_id', 'compte_id', 'type', 'role_partie', 'motif'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionConvention::class, 'version_id');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }
}

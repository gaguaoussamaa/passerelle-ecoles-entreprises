<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal d'audit transversal (ENF-05) — l'horodatage est created_at. */
class JournalAudit extends Model
{
    protected $table = 'journal_audit';

    protected $fillable = ['compte_id', 'action', 'objet_type', 'objet_id', 'details'];

    public static function tracer(string $action, string $objetType, int $objetId, ?int $compteId = null, ?string $details = null): void
    {
        static::create([
            'compte_id' => $compteId,
            'action' => $action,
            'objet_type' => $objetType,
            'objet_id' => $objetId,
            'details' => $details,
        ]);
    }
}

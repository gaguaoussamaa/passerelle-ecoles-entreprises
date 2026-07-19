<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offre extends Model
{
    protected $table = 'offres';

    protected $fillable = [
        'entreprise_id', 'domaine_id', 'intitule', 'description', 'type',
        'niveau', 'lieu', 'date_debut_prevue', 'date_fin_prevue', 'nb_postes', 'statut',
    ];

    protected function casts(): array
    {
        return ['date_debut_prevue' => 'date', 'date_fin_prevue' => 'date'];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function domaine(): BelongsTo
    {
        return $this->belongsTo(Domaine::class, 'domaine_id');
    }

    public function diffusions(): HasMany
    {
        return $this->hasMany(Diffusion::class, 'offre_id');
    }

    /** Promotions ciblées à la modération (RG-19). */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'affectations')->withTimestamps();
    }

    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class, 'offre_id');
    }

    /** RG-20 : modifiable/retirable tant qu'aucune école n'a validé. */
    public function modifiable(): bool
    {
        return $this->diffusions()->where('statut', 'validee')->doesntExist();
    }

    /**
     * Visibilité étudiante (RG-19) : offre publiée + diffusion VALIDÉE dans
     * l'école de l'étudiant + affectation à SA promotion. Tout le reste est invisible.
     */
    public function scopeVisiblesPar(Builder $query, Etudiant $etudiant): void
    {
        $etabId = $etudiant->promotion->formation->etablissement_id;

        $query->where('statut', 'publiee')
            ->whereHas('diffusions', fn ($q) => $q->where('etablissement_id', $etabId)->where('statut', 'validee'))
            ->whereHas('promotions', fn ($q) => $q->where('promotions.id', $etudiant->promotion_id));
    }
}

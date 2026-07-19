<?php

namespace App\Http\Controllers\Ecole;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base des écrans « espace école » (rôle responsable).
 * Cloisonnement (EF-02/RG-07) : toute donnée est atteinte À TRAVERS
 * l'établissement du responsable connecté — un identifiant d'une autre
 * école aboutit à un 404, jamais à une fuite.
 */
abstract class ControleurEcole extends Controller
{
    protected function etablissement(): Etablissement
    {
        return auth()->user()->responsable->etablissement;
    }

    protected function promotionsEtablissement(): Builder
    {
        return Promotion::whereHas('formation', fn ($q) => $q->where('etablissement_id', $this->etablissement()->id));
    }

    protected function etudiantsEtablissement(): Builder
    {
        return Etudiant::whereHas('promotion.formation', fn ($q) => $q->where('etablissement_id', $this->etablissement()->id));
    }
}

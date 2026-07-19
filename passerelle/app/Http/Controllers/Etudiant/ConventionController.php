<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\View\View;

/** Espace étudiant : suivi de la convention de sa mission (validation, approbation). */
class ConventionController extends Controller
{
    public function index(): View
    {
        return view('etudiant.convention.index', [
            'missions' => Mission::where('etudiant_id', auth()->id())
                ->with(['entreprise', 'versionsConvention.actions'])
                ->latest()->get(),
        ]);
    }
}

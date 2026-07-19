<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\Compte;
use App\Models\Invitation;
use App\Models\JournalAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Invitations et activation des comptes (RG-01, RG-02, RG-05).
 * Le jeton en clair n'est jamais stocké : seule son empreinte SHA-256 l'est.
 */
class InvitationService
{
    public const VALIDITE_HEURES = 72;

    /** Crée (ou renvoie) une invitation : le jeton précédent est invalidé (RG-02). */
    public function inviter(Compte $compte): string
    {
        $jeton = Str::random(48);

        DB::transaction(function () use ($compte, $jeton) {
            $compte->invitations()
                ->where('statut', 'active')
                ->update(['statut' => 'invalidee']);

            Invitation::create([
                'compte_id' => $compte->id,
                'jeton_hash' => hash('sha256', $jeton),
                'expire_le' => now()->addHours(self::VALIDITE_HEURES),
                'statut' => 'active',
            ]);

            JournalAudit::tracer('invitation_envoyee', 'compte', $compte->id);
        });

        Mail::to($compte->email)->send(new InvitationMail($compte, $jeton));

        return $jeton;
    }

    /** Retrouve l'invitation valide correspondant au jeton en clair, sinon null. */
    public function valider(string $jeton): ?Invitation
    {
        $invitation = Invitation::where('jeton_hash', hash('sha256', $jeton))->first();

        return ($invitation && $invitation->estValide()) ? $invitation : null;
    }

    /**
     * Active le compte : mot de passe défini (RG-04), jeton consommé (RG-02),
     * l'étudiant invité devient « actif » (RG-05).
     */
    public function activer(Invitation $invitation, string $motDePasse): Compte
    {
        return DB::transaction(function () use ($invitation, $motDePasse) {
            $compte = $invitation->compte;
            $compte->mot_de_passe = $motDePasse; // cast « hashed »
            $compte->save();

            $invitation->update(['statut' => 'utilisee', 'utilisee_le' => now()]);

            if ($compte->role === 'etudiant' && $compte->etudiant?->statut_scolarite === 'invite') {
                $compte->etudiant->update(['statut_scolarite' => 'actif']);
            }

            if ($compte->role === 'entreprise') {           // RG-47 : partenariats en attente → actifs
                \App\Models\Partenariat::where('entreprise_id', $compte->id)
                    ->where('statut', 'en_attente')->update(['statut' => 'actif']);
            }

            JournalAudit::tracer('compte_active', 'compte', $compte->id, $compte->id);

            return $compte;
        });
    }
}

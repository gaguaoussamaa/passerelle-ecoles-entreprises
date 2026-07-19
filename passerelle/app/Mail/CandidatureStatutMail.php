<?php

namespace App\Mail;

use App\Models\Candidature;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** RG-23 : l'étudiant est notifié de chaque changement de statut de sa candidature. */
class CandidatureStatutMail extends Mailable
{
    public function __construct(public Candidature $candidature) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Passerelle — votre candidature « '
            .$this->candidature->offre->intitule.' » : '.$this->candidature->libelleStatut());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.candidature-statut');
    }
}

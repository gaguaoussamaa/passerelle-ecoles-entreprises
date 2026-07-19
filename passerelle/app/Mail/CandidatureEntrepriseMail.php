<?php

namespace App\Mail;

use App\Models\Candidature;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Notification à l'entreprise : dépôt (TI-06), confirmation ou déclinaison. */
class CandidatureEntrepriseMail extends Mailable
{
    public const SUJETS = [
        'deposee' => 'nouvelle candidature reçue',
        'confirmee' => 'le candidat confirme son engagement',
        'declinee' => 'le candidat décline',
    ];

    public function __construct(
        public Candidature $candidature,
        public string $evenement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Passerelle — « '.$this->candidature->offre->intitule
            .' » : '.self::SUJETS[$this->evenement]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.candidature-entreprise');
    }
}

<?php

namespace App\Mail;

use App\Models\Compte;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(
        public Compte $compte,
        public string $jeton,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Passerelle — activez votre compte');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation', with: [
            'lien' => route('activation.afficher', ['jeton' => $this->jeton]),
        ]);
    }
}

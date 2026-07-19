<?php

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Notifications liées au cycle de vie d'une mission (raccordement UC-08, annulation RG-30). */
class MissionMail extends Mailable
{
    public const SUJETS = [
        'entreprise_raccordee' => 'une mission vous est rattachée',
        'annulee' => 'mission annulée',
        'interrompue' => 'mission interrompue',
    ];

    public function __construct(
        public Mission $mission,
        public string $evenement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Passerelle — '.self::SUJETS[$this->evenement]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mission');
    }
}

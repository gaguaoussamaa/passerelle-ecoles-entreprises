<p>Bonjour,</p>

<p>Un compte <strong>Passerelle</strong> ({{ $compte->role === 'entreprise' ? 'entreprise' : 'utilisateur' }})
vient d'être créé pour l'adresse {{ $compte->email }}.</p>

<p>Pour l'activer et définir votre mot de passe, suivez ce lien
(valable {{ \App\Services\InvitationService::VALIDITE_HEURES }} heures, à usage unique) :</p>

<p><a href="{{ $lien }}">{{ $lien }}</a></p>

<p>Si vous n'êtes pas à l'origine de cette invitation, ignorez ce message.</p>

<p>— La plateforme Passerelle</p>

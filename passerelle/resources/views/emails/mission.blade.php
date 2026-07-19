<p>Bonjour,</p>

@if ($evenement === 'entreprise_raccordee')
    <p>Une mission ({{ $mission->type }}) avec
    <strong>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }}</strong>
    vient d'être rattachée à votre compte Passerelle à la suite d'une déclaration
    validée par son établissement
    ({{ $mission->etudiant->promotion->formation->etablissement->nom }}).</p>
    <p>Période prévue : {{ $mission->date_debut->format('d/m/Y') }} →
    {{ $mission->date_fin->format('d/m/Y') }}. Vous pourrez désigner le tuteur en
    entreprise depuis votre espace (page « Missions »).</p>
@else
    <p>La mission ({{ $mission->type }}) de
    <strong>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }}</strong>
    prévue du {{ $mission->date_debut->format('d/m/Y') }} au {{ $mission->date_fin->format('d/m/Y') }}
    est <strong>annulée</strong> au {{ $mission->date_effet_arret->format('d/m/Y') }}.</p>
    <p>Motif : {{ $mission->motif_arret }}</p>
    <p>Le dossier est archivé en l'état ; l'étudiant peut de nouveau candidater ou déclarer une mission.</p>
@endif

<p>— La plateforme Passerelle</p>

<p>Bonjour,</p>

@if ($evenement === 'deposee')
    <p><strong>{{ $candidature->etudiant->prenom }} {{ $candidature->etudiant->nom }}</strong>
    vient de candidater à votre offre <strong>{{ $candidature->offre->intitule }}</strong>.</p>
    <p>Retrouvez son CV et son message de motivation dans votre espace Passerelle
    (page « Candidatures »).</p>
@elseif ($evenement === 'confirmee')
    <p><strong>{{ $candidature->etudiant->prenom }} {{ $candidature->etudiant->nom }}</strong>
    confirme son engagement sur l'offre <strong>{{ $candidature->offre->intitule }}</strong> :
    la mission est créée (en montage). Vous pouvez clôturer l'offre si tous les postes sont pourvus.</p>
@else
    <p><strong>{{ $candidature->etudiant->prenom }} {{ $candidature->etudiant->nom }}</strong>
    décline finalement l'offre <strong>{{ $candidature->offre->intitule }}</strong> ;
    l'offre reste ouverte aux autres candidats.</p>
@endif

<p>— La plateforme Passerelle</p>

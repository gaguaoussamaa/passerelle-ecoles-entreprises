<p>Bonjour {{ $candidature->etudiant->prenom }},</p>

<p>Le statut de votre candidature à l'offre
<strong>{{ $candidature->offre->intitule }}</strong>
({{ $candidature->offre->entreprise->raison_sociale }}) vient de changer :
<strong>{{ $candidature->libelleStatut() }}</strong>.</p>

@if ($candidature->statut === 'retenue')
    <p>Vous avez été retenu(e) : connectez-vous à Passerelle pour
    <strong>confirmer votre engagement</strong> ou décliner (page « Mes candidatures »).</p>
@endif

<p>— La plateforme Passerelle</p>

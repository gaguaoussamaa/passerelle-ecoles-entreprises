@php($etab = $mission->etudiant->promotion->formation->etablissement)
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a2733; margin: 36px; }
        .entete { border-bottom: 3px solid #1f4e79; padding-bottom: 10px; margin-bottom: 22px; }
        .logo { font-size: 20px; font-weight: bold; color: #1f4e79; }
        h1 { font-size: 17px; text-align: center; margin: 18px 0; text-transform: uppercase; }
        h2 { font-size: 12px; color: #1f4e79; border-bottom: 1px solid #ccd6e0; padding-bottom: 3px; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 6px; vertical-align: top; }
        .cle { width: 34%; color: #566573; }
        .signatures td { border: 1px solid #ccd6e0; height: 70px; width: 25%; font-size: 10px; }
        .pied { margin-top: 26px; font-size: 9px; color: #566573; border-top: 1px solid #ccd6e0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="entete">
        <div class="logo">{{ $etab->nom }}</div>
        <div>{{ $etab->ville }} — SIRET {{ $etab->siret }} · document édité via la plateforme Passerelle</div>
    </div>

    <h1>{{ $titre }} — version {{ $numero }}</h1>

    <h2>Parties</h2>
    <table>
        <tr><td class="cle">Établissement</td><td>{{ $etab->nom }}, {{ $etab->ville }} (SIRET {{ $etab->siret }})</td></tr>
        <tr><td class="cle">Entreprise d'accueil</td><td>{{ $mission->entreprise->raison_sociale }},
            {{ $mission->entreprise->ville }} (SIRET {{ $mission->entreprise->siret }})</td></tr>
        <tr><td class="cle">Étudiant(e)</td><td>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }} —
            {{ $mission->etudiant->promotion->formation->intitule }} ({{ $mission->etudiant->promotion->libelle }})</td></tr>
        <tr><td class="cle">Tuteur pédagogique</td><td>{{ $mission->tuteurPedagogique->prenom }} {{ $mission->tuteurPedagogique->nom }}</td></tr>
        <tr><td class="cle">Tuteur en entreprise</td><td>{{ $mission->tuteurEntreprise->prenom }} {{ $mission->tuteurEntreprise->nom }}
            @if ($mission->tuteurEntreprise->email) ({{ $mission->tuteurEntreprise->email }}) @endif</td></tr>
    </table>

    <h2>Mission</h2>
    <table>
        <tr><td class="cle">Type</td><td>{{ $mission->type === 'stage' ? 'Stage' : 'Alternance' }}</td></tr>
        <tr><td class="cle">Période</td><td>du {{ $mission->date_debut->format('d/m/Y') }} au {{ $mission->date_fin->format('d/m/Y') }}</td></tr>
        <tr><td class="cle">Origine</td><td>{{ $mission->candidature_id ? 'candidature à une offre diffusée sur la plateforme' : 'mission déclarée par l\'étudiant, jugée recevable par l\'établissement' }}</td></tr>
        <tr><td class="cle">Description</td><td>{{ $mission->candidature?->offre?->description ?? $mission->declaration?->description }}</td></tr>
    </table>

    <h2>Engagements</h2>
    <p>L'entreprise accueille l'étudiant(e) et désigne un tuteur chargé de son encadrement.
        L'établissement assure le suivi pédagogique par le tuteur désigné.
        L'étudiant(e) réalise la mission décrite et rend compte selon l'échéancier fixé
        (rapports mensuels et, le cas échéant, point de mi-parcours).
        Toute interruption ou annulation est signalée au responsable d'établissement, qui la trace.</p>

    <h2>Signatures (approbation en ligne — Passerelle)</h2>
    <table class="signatures">
        <tr><td>Étudiant(e)</td><td>Entreprise</td><td>Tuteur pédagogique</td><td>Responsable d'établissement</td></tr>
    </table>
    <p>Les approbations sont recueillies en ligne : auteur, horodatage et empreinte du document
        sont consignés au journal d'audit de la plateforme et font foi.</p>

    <div class="pied">
        {{ $titre }} — mission n°{{ $mission->id }} — version {{ $numero }} générée le {{ now()->format('d/m/Y à H:i') }}.
        Document immuable : toute correction donne lieu à une nouvelle version numérotée.
    </div>
</body>
</html>

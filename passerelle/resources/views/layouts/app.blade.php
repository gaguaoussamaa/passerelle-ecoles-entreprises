<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', 'Passerelle')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<header class="entete">
    <div class="entete-int">
        <span class="marque">Passerelle</span>
        @auth
            <nav class="nav">
                <a href="{{ route('tableau-de-bord') }}">Tableau de bord</a>
                @if (auth()->user()->role === 'responsable')
                    <a href="{{ route('ecole.formations') }}">Formations</a>
                    <a href="{{ route('ecole.promotions') }}">Promotions</a>
                    <a href="{{ route('ecole.etudiants') }}">Étudiants</a>
                    <a href="{{ route('ecole.tuteurs') }}">Tuteurs</a>
                    <a href="{{ route('ecole.partenaires') }}">Partenaires</a>
                    <a href="{{ route('ecole.offres') }}">Offres reçues</a>
                    <a href="{{ route('ecole.declarations') }}">Déclarations</a>
                    <a href="{{ route('ecole.missions') }}">Missions</a>
                @endif
                @if (auth()->user()->role === 'entreprise')
                    <a href="{{ route('entreprise.offres') }}">Mes offres</a>
                    <a href="{{ route('entreprise.candidatures') }}">Candidatures</a>
                    <a href="{{ route('entreprise.missions') }}">Missions</a>
                @endif
                @if (auth()->user()->role === 'etudiant')
                    <a href="{{ route('etudiant.offres') }}">Offres</a>
                    <a href="{{ route('etudiant.candidatures') }}">Mes candidatures</a>
                    <a href="{{ route('etudiant.declaration') }}">Ma déclaration</a>
                    <a href="{{ route('etudiant.convention') }}">Ma convention</a>
                    <a href="{{ route('etudiant.suivi') }}">Mon suivi</a>
                @endif
                @if (auth()->user()->role === 'super_admin')
                    <a href="{{ route('admin.etablissements') }}">Établissements</a>
                @endif
                @if (auth()->user()->role === 'tuteur_pedagogique')
                    <a href="{{ route('tuteur.conventions') }}">Conventions</a>
                    <a href="{{ route('tuteur.suivi') }}">Suivi</a>
                @endif
            </nav>
            <div class="session">
                <span class="pastille">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                <span class="courriel">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" class="lien">Se déconnecter</button>
                </form>
            </div>
        @endauth
    </div>
</header>

<main class="contenu">
    @if (session('statut'))
        <div class="message message-succes">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">
            @foreach ($errors->all() as $erreur)
                <div>{{ $erreur }}</div>
            @endforeach
        </div>
    @endif

    @yield('contenu')
</main>

<footer class="pied">Passerelle — plateforme de gestion des stages et alternances (environnement de démonstration)</footer>
</body>
</html>

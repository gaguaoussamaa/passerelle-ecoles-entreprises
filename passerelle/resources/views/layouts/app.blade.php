<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', 'Passerelle')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
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
                @endif
                @if (auth()->user()->role === 'entreprise')
                    <a href="{{ route('entreprise.offres') }}">Mes offres</a>
                @endif
                @if (auth()->user()->role === 'etudiant')
                    <a href="{{ route('etudiant.offres') }}">Offres</a>
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

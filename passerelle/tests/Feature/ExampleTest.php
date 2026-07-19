<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_racine_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/connexion');
    }

    public function test_la_sonde_de_sante_repond(): void
    {
        $this->get('/up')->assertOk();
    }
}

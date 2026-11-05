<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_the_login_page_is_in_spanish(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="es"', escape: false)
            ->assertSeeText('Entre a su cuenta');
    }
}

<?php

namespace Tests\Feature\Models;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_by_name_phone_email_and_tax_id(): void
    {
        $client = Client::factory()->create([
            'name' => 'Juana Pérez',
            'phone' => '6141234567',
            'email' => 'juana@example.com',
            'tax_id' => 'PEJJ800101AB1',
        ]);
        Client::factory()->create(['name' => 'Otro']);

        foreach (['Juana', '123456', 'juana@', 'PEJJ'] as $term) {
            $this->assertSame([$client->id], Client::query()->matching($term)->pluck('id')->all(), $term);
        }
    }

    public function test_the_select_label_adds_the_phone_when_there_is_one(): void
    {
        $this->assertSame('Juana · 614', Client::factory()->make(['name' => 'Juana', 'phone' => '614'])->select_label);
        $this->assertSame('Juana', Client::factory()->make(['name' => 'Juana', 'phone' => null])->select_label);
    }

    public function test_the_tax_id_is_stored_clean_and_uppercase(): void
    {
        $this->assertSame('PEJJ800101AB1', Client::factory()->make(['tax_id' => ' pejj800101ab1 '])->tax_id);
        $this->assertNull(Client::factory()->make(['tax_id' => ''])->tax_id);
    }
}

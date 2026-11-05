<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Filament\Resources\BranchProducts\BranchProductResource;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentBranchControllerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $centro;

    private Branch $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centro = Branch::factory()->create(['name' => 'Centro']);
        $this->norte = Branch::factory()->create(['name' => 'Norte']);
        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach([$this->centro->id, $this->norte->id]);
        $this->actingAs($manager);
    }

    private function switchFrom(string $url): mixed
    {
        return $this->from($url)->post(route('filament.admin.current-branch'), ['branch_id' => $this->norte->id]);
    }

    public function test_switching_returns_to_a_list_page(): void
    {
        $url = BranchProductResource::getUrl('index');

        $this->switchFrom($url)->assertRedirect($url);
    }

    public function test_switching_on_a_record_of_a_branch_module_goes_to_the_module_list(): void
    {
        $row = BranchProduct::factory()->create(['branch_id' => $this->centro->id]);

        foreach (['view', 'history'] as $page) {
            $this->switchFrom(BranchProductResource::getUrl($page, ['record' => $row]))
                ->assertRedirect(BranchProductResource::getUrl('index'));
        }
    }

    public function test_switching_on_a_global_record_stays_there(): void
    {
        $url = SettingResource::getUrl('view', ['record' => Setting::factory()->create()]);

        $this->switchFrom($url)->assertRedirect($url);
    }

    public function test_switching_from_an_unknown_url_still_redirects(): void
    {
        $this->switchFrom('/somewhere-that-does-not-exist')->assertRedirect('/somewhere-that-does-not-exist');
    }
}

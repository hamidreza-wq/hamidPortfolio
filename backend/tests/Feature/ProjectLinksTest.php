<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function privateProjects(): array
    {
        return [
            ['oneprayer', 'Bible'],
            ['home-ledger', 'myAccountingHome'],
            ['interpreters-hub', 'interpurting-laravel'],
            ['betweenwords', 'meisaPortfolio'],
        ];
    }

    #[DataProvider('privateProjects')]
    public function test_private_source_is_labelled_without_a_public_url(string $slug, string $repo): void
    {
        $url = 'https://github.com/hamidreza-wq/'.$repo;
        $project = Project::where('slug', $slug)->firstOrFail();
        $this->assertSame($url, $project->repository_url);
        $this->assertFalse($project->repository_is_public);
        $this->get('/')->assertOk()->assertSee('Private source code')->assertDontSee($url, false);
        $this->get('/projects/'.$slug)->assertOk()->assertSee('Private source code')->assertDontSee($url, false);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/projects')->assertOk()->assertSee($url, false)
            ->assertSee('Source repository is publicly accessible without signing in.');
    }

    public function test_public_source_and_only_verified_demo_links_are_presented(): void
    {
        $project = Project::where('slug', 'office-converters')->firstOrFail();
        $this->get('/projects/'.$project->slug)->assertOk()->assertSee('href="'.$project->repository_url.'"', false);
        $project->update(['live_url' => 'https://demo.example.test/', 'live_url_verified' => false]);
        $this->get('/')->assertDontSee('https://demo.example.test/', false);
        $this->get('/projects/'.$project->slug)->assertDontSee('https://demo.example.test/', false);
        $project->update(['live_url_verified' => true]);
        $this->get('/')->assertSee(json_encode('https://demo.example.test/', JSON_THROW_ON_ERROR), false);
        $this->get('/projects/'.$project->slug)->assertSee('href="https://demo.example.test/"', false);
    }
}

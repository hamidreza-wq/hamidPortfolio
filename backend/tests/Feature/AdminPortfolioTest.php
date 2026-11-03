<?php

namespace Tests\Feature;

use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_pages_require_an_administrator_account(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/preview')->assertRedirect('/admin/login');
    }

    public function test_administrator_can_sign_in_and_sign_out(): void
    {
        $admin = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'Long-test-password-2026!',
            'is_admin' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Long-test-password-2026!',
        ])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);

        $this->post('/admin/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_administrator_can_open_the_integrated_editor_and_preview(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Good to see you');

        $this->actingAs($admin)->get('/admin/site-editor')
            ->assertOk()
            ->assertSee('Live website preview')
            ->assertSee('portfolio-preview')
            ->assertSee('Skills & categories', false);

        $this->actingAs($admin)->get('/preview')
            ->assertOk()
            ->assertSee('window.portfolioPreview = true;', false)
            ->assertSee('window.portfolioPayload', false);

        $this->actingAs($admin)->get('/admin/projects')
            ->assertOk()
            ->assertSee('Add a project')
            ->assertSee('Live website preview');

        $this->actingAs($admin)->get('/admin/media')
            ->assertOk()
            ->assertSee('Upload an image');

        $this->actingAs($admin)->get('/admin/inbox')
            ->assertOk()
            ->assertSee('Contact inbox');
    }

    public function test_editor_publishes_content_and_theme_to_the_public_portfolio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = PortfolioSetting::findOrFail(1);
        $payload = [
            'settings' => [
                'name' => 'Hamid Bayat',
                'professional_title' => 'Product Engineer',
                'github_url' => $settings->github_url,
                'email' => '',
                'linkedin_url' => '',
                'site_title' => 'Hamid Bayat — Product Engineer',
                'site_description' => 'A portfolio of useful software.',
                'canonical_url' => '',
                'maintenance_mode' => '0',
                'color_background' => '#101018',
                'color_foreground' => '#f3f1f8',
                'color_primary' => '#ad91ff',
                'color_accent' => '#c4b1ff',
                'color_cyan' => '#92d8ee',
                'font_body' => 'Inter',
                'font_heading' => 'Inter',
                'max_width' => '1200',
                'section_spacing' => '140',
                'border_radius' => '12',
                'animations_enabled' => '1',
                'particles_enabled' => '1',
                'footer_text' => 'Test footer',
            ],
            'sections' => [],
            'skill_categories' => [],
        ];

        foreach (PortfolioSection::all() as $section) {
            $payload['sections'][$section->key] = [
                'title' => $section->key === 'about' ? 'A new introduction' : $section->title,
                'subtitle' => $section->subtitle ?? '',
                'body' => $section->body ?? '',
                'is_visible' => '1',
                'sort_order' => (string) $section->sort_order,
            ];
        }

        $skillCategories = SkillCategory::with('skills')->get();
        foreach ($skillCategories as $category) {
            $payload['skill_categories'][$category->id] = [
                'title' => $category->title,
                'skills' => $category->skills->pluck('name')->implode("\n"),
                'is_visible' => '1',
                'sort_order' => (string) $category->sort_order,
            ];
        }
        $firstCategory = $skillCategories->firstOrFail();
        $payload['skill_categories'][$firstCategory->id]['title'] = 'Portfolio craft update';
        $payload['skill_categories'][$firstCategory->id]['skills'] = "Laravel\nMySQL";

        $this->actingAs($admin)->put('/admin/site-editor', $payload)
            ->assertRedirect('/admin/site-editor')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('portfolio_settings', [
            'id' => 1,
            'name' => 'Hamid Bayat',
            'color_background' => '#101018',
        ]);
        $this->assertDatabaseHas('portfolio_sections', [
            'key' => 'about',
            'title' => 'A new introduction',
        ]);
        $this->assertDatabaseHas('skill_categories', [
            'id' => $firstCategory->id,
            'title' => 'Portfolio craft update',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('<title>Hamid Bayat — Product Engineer</title>', false)
            ->assertSee('A portfolio of useful software.')
            ->assertSee('name":"Hamid Bayat"', false)
            ->assertSee('Portfolio craft update', false);
    }

    public function test_project_crud_changes_the_portfolio_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::firstOrFail();

        $this->actingAs($admin)->put('/admin/projects/'.$project->id, [
            'name' => $project->name,
            'category' => $project->category,
            'category_label' => $project->category_label,
            'repository_url' => $project->repository_url,
            'live_url' => '',
            'description' => 'Updated project description.',
            'details' => $project->details,
            'technologies' => implode("\n", $project->technologies),
            'features' => implode("\n", $project->features),
            'visual' => $project->visual,
            'visual_label' => $project->visual_label,
            'status' => $project->status,
            'is_featured' => '1',
            'is_published' => '1',
            'sort_order' => (string) $project->sort_order,
            'seo_title' => '',
            'seo_description' => '',
        ])->assertRedirect('/admin/projects');

        $this->get('/')->assertOk()->assertSee('Updated project description.');
    }

    public function test_administrator_can_add_a_published_project(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/projects', [
            'name' => 'Preview Project',
            'category' => 'web',
            'category_label' => 'WEB APPLICATION',
            'repository_url' => 'https://github.com/hamidreza-wq/preview-project',
            'live_url' => '',
            'description' => 'A newly published project.',
            'details' => 'Verified details for the newly added project.',
            'technologies' => "Laravel\nMySQL",
            'features' => "A useful feature\nA second feature",
            'visual' => 'planet',
            'visual_label' => 'WEB / PRODUCT',
            'status' => 'active',
            'is_featured' => '0',
            'is_published' => '1',
            'sort_order' => '9',
            'seo_title' => '',
            'seo_description' => '',
        ])->assertRedirect('/admin/projects');

        $this->get('/')->assertOk()->assertSee('A newly published project.');
    }
}

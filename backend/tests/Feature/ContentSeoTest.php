<?php

namespace Tests\Feature;

use App\Models\ContentRevision;
use App\Models\MediaAsset;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_published_project_has_server_rendered_content_metadata_and_schema(): void
    {
        $settings = PortfolioSetting::findOrFail(1);
        $settings->update(['canonical_url' => 'https://portfolio.example', 'language' => 'fa-IR']);
        $project = Project::firstOrFail();
        $project->update([
            'seo_title' => 'Verified project title',
            'seo_description' => 'Verified project description.',
            'details' => 'Detailed verified content <script>alert(1)</script>',
        ]);
        $response = $this->get('/projects/'.$project->slug)->assertOk()
            ->assertSee('<html lang="fa-IR">', false)
            ->assertSee('<title>Verified project title</title>', false)
            ->assertSee('name="description" content="Verified project description."', false)
            ->assertSee('rel="canonical" href="https://portfolio.example/projects/'.$project->slug.'"', false)
            ->assertSee('property="og:title" content="Verified project title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('Detailed verified content &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertHeader('X-Robots-Tag', 'index, follow');
        preg_match('/<script\\b[^>]*type="application\\/ld\\+json"[^>]*>(.*?)<\\/script>/s', $response->getContent(), $matches);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('CreativeWork', $schema['@type']);
        $this->assertSame($project->name, $schema['name']);
        $this->assertSame('https://portfolio.example/projects/'.$project->slug, $schema['url']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_drafts_are_absent_from_public_html_schema_sitemap_and_detail_routes(): void
    {
        $project = Project::firstOrFail();
        $project->update(['name' => 'PRIVATE PROJECT CONTENT', 'slug' => 'private-project', 'is_published' => false]);
        $this->get('/')->assertOk()->assertDontSee('PRIVATE PROJECT CONTENT');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('private-project');
        $this->get('/projects/private-project')->assertNotFound();
        $this->get('/projects/no-such-project')->assertNotFound();
        $this->get('/projects/Invalid!')->assertNotFound();
    }

    public function test_indexing_preferences_apply_to_home_projects_robots_and_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static robots file bypasses the dynamic indexing policy on real web servers.');
        $settings = PortfolioSetting::findOrFail(1);
        $settings->update(['indexing_enabled' => false, 'robots_follow' => false, 'canonical_url' => 'https://portfolio.example/']);
        $project = Project::firstOrFail();
        $this->get('/')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/projects/'.$project->slug)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertSee('https://portfolio.example/sitemap.xml');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<url>', false)->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_homepage_is_server_rendered_and_private_contact_markup_is_not_shared_cached(): void
    {
        PortfolioSection::where('key', 'about')->update(['title' => 'Actual database introduction']);
        $response = $this->get('/')->assertOk()->assertSee('Actual database introduction')
            ->assertSee('href="http://localhost/projects/', false)
            ->assertSee('contact_submission_token', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('max-age', $response->headers->get('Cache-Control'));
        $html = $this->document($response->getContent());
        $this->assertSame('Actual database introduction', $html->getElementById('about-title')->textContent);
        $this->assertGreaterThan(0, (new \DOMXPath($html))->query('//*[@id="project-grid"]//a[contains(@href,"/projects/")]')->length);
    }

    public function test_hero_renders_the_profile_lead_in_only_once_and_preserves_cosmic_title(): void
    {
        PortfolioSetting::findOrFail(1)->update(['name' => 'Verified Builder', 'github_url' => 'https://github.com/verified-builder']);
        $document = $this->document($this->get('/')->assertOk()->getContent());
        $xpath = new \DOMXPath($document);
        $intro = $xpath->query('//*[contains(@class,"hero-intro")]')->item(0)->textContent;
        $this->assertSame(1, substr_count($intro, "I'm"));
        $this->assertStringContainsString('Verified Builder', $intro);
        $this->assertStringNotContainsString('Hamid Reza Bayat', $intro);
        $this->assertSame(1, $xpath->query('//*[@id="hero-title"]//span[@class="gradient-text"]')->length);
        $this->assertSame(2, $xpath->query('//*[contains(@class,"brand-name") and text()="Verified Builder"]')->length);
        $this->assertSame(2, $xpath->query('//a[@aria-label="Verified Builder, home"]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-managed-initial="profile.name" and text()="V"]')->length);
        $this->assertSame(1, $xpath->query('//a[@data-managed-href="profile.github.repositories" and @href="https://github.com/verified-builder?tab=repositories"]')->length);
    }

    public function test_repositories_link_uses_github_profile_with_query_and_fragment_normalized(): void
    {
        PortfolioSetting::findOrFail(1)->update(['github_url' => 'https://github.com/verified-builder?tab=stars#top']);
        $xpath = new \DOMXPath($this->document($this->get('/')->assertOk()->getContent()));
        $this->assertSame(1, $xpath->query('//a[@data-managed-href="profile.github.repositories" and @href="https://github.com/verified-builder?tab=repositories"]')->length);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"hamidreza-wq?tab=repositories")]')->length);
    }

    public function test_custom_section_crud_updates_navigation_order_visibility_and_escaped_content(): void
    {
        PortfolioSection::where('key', 'home')->update(['sort_order' => 1]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/sections', $this->sectionFields([
            'title' => 'Verified services',
            'label' => 'Services',
            'sort_order' => 0,
            'items_json' => '[{"title":"Engineering","body":"<script>alert(1)</script>"}]',
        ]))->assertRedirect('/admin/sections')->assertSessionHasNoErrors();
        $section = PortfolioSection::where('title', 'Verified services')->firstOrFail();
        $response = $this->get('/')->assertOk()->assertSee('Verified services')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $document = $this->document($response->getContent());
        $this->assertSame('#'.$section->key, $document->getElementById('primary-navigation')->firstChild->getAttribute('href'));
        $this->assertSame('Verified services', $document->getElementById($section->key)->getElementsByTagName('h2')->item(0)->textContent);
        $this->actingAs($admin)->put('/admin/sections/'.$section->id, $this->sectionFields(['is_visible' => 0]))->assertSessionHasNoErrors();
        $response = $this->get('/')->assertOk();
        $this->assertStringNotContainsString('href="#'.$section->key.'"', $response->getContent());
        $this->actingAs($admin)->delete('/admin/sections/'.$section->id)->assertRedirect('/admin/sections');
        $this->assertDatabaseMissing('portfolio_sections', ['id' => $section->id]);
        $this->assertDatabaseCount('content_revisions', 3);
    }

    public function test_section_validation_rejects_unknown_types_items_and_css_and_protects_builtins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach ([
            ['type' => 'html'],
            ['items_json' => '[{"title":"Unsafe","html":"<img onerror=alert(1)>"}]'],
            ['items_json' => '{"title":"Not an array"}'],
            ['items_json' => '{broken'],
            ['spacing_top' => 'expression(alert(1))'],
            ['background_effect' => 'url(javascript:alert(1))'],
            ['image_media_id' => 9999999],
        ] as $invalid) {
            $this->actingAs($admin)->post('/admin/sections', $this->sectionFields($invalid))->assertSessionHasErrors();
        }
        $this->actingAs($admin)->delete('/admin/sections/'.PortfolioSection::where('key', 'home')->value('id'))->assertStatus(422);
        $this->assertDatabaseCount('portfolio_sections', 6);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_empty_section_items_are_allowed_and_new_section_can_remain_hidden(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/sections', $this->sectionFields(['is_visible' => 0]))->assertSessionHasNoErrors();
        $section = PortfolioSection::where('label', 'Verified block')->firstOrFail();
        $document = $this->document($this->get('/')->assertOk()->getContent());
        $this->assertNull($document->getElementById($section->key));
        $this->assertStringNotContainsString('href="#'.$section->key.'"', $document->saveHTML());
    }

    public function test_navigation_can_be_hidden_independently_from_section_visibility(): void
    {
        PortfolioSection::where('key', 'about')->update(['show_in_navigation' => false]);
        $response = $this->get('/')->assertOk();
        $document = $this->document($response->getContent());
        $this->assertNotNull($document->getElementById('about'));
        $this->assertSame(0, (new \DOMXPath($document))->query('//*[@id="primary-navigation"]//a[@href="#about"]')->length);
    }

    public function test_unpublished_section_content_is_private_in_html_and_serialized_payload(): void
    {
        PortfolioSection::where('key', 'about')->update([
            'is_visible' => false,
            'title' => 'Private built-in heading',
            'body' => 'Private built-in body',
            'items' => [['title' => 'Private structured item', 'body' => 'Private item body']],
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/sections', $this->sectionFields([
                'is_visible' => 0,
                'title' => 'Private custom heading',
                'body' => 'Private custom body',
                'items_json' => '[{"title":"Private custom item","body":"Private custom item body"}]',
            ]))->assertSessionHasNoErrors();
        $response = $this->get('/')->assertOk();
        foreach (['Private built-in', 'Private structured item', 'Private item body', 'Private custom'] as $privateText) {
            $response->assertDontSee($privateText, false);
        }
        $this->assertNull($this->document($response->getContent())->getElementById('about'));
        $this->get('/preview')->assertOk()->assertSee('Private built-in heading')->assertSee('Private custom heading');
    }

    public function test_all_builtin_sections_can_be_unpublished_without_leaking_static_fallback_content(): void
    {
        PortfolioSection::query()->update(['is_visible' => false]);
        $response = $this->get('/')->assertOk();
        $document = $this->document($response->getContent());
        foreach (PortfolioSection::BUILT_IN_KEYS as $key) {
            $this->assertNull($document->getElementById($key));
        }
        $this->assertSame(0, (new \DOMXPath($document))->query('//*[contains(@class,"intro-strip")]')->length);
        $response->assertSee('"sections":[]', false)->assertDontSee('A universe of', false)->assertDontSee('href="#home"', false);
    }

    public function test_preview_is_private_noindex_and_draft_posts_do_not_modify_published_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get('/preview')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('window.portfolioPreview = true;', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $title = PortfolioSection::where('key', 'home')->value('title');
        $this->actingAs($admin)->get('/admin/sections')->assertOk()->assertSee('postMessage', false);
        $this->get('/')->assertOk()->assertDontSee('section-draft');
        $this->assertSame($title, PortfolioSection::where('key', 'home')->value('title'));
    }

    public function test_project_slug_is_explicit_stable_and_old_slug_redirects_only_while_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::firstOrFail();
        $original = $project->slug;
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['name' => 'Renamed verified project']))
            ->assertSessionHasNoErrors();
        $this->assertSame($original, $project->fresh()->slug);
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project->fresh(), ['slug' => 'verified-new-slug']))
            ->assertSessionHasNoErrors();
        $this->get('/projects/'.$original)->assertStatus(301)->assertRedirect('/projects/verified-new-slug');
        $project->update(['is_published' => false]);
        $this->get('/projects/'.$original)->assertNotFound();
    }

    public function test_project_duplicate_slug_and_unsafe_urls_are_rejected(): void
    {
        $project = Project::firstOrFail();
        $other = Project::whereKeyNot($project->id)->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['slug' => $other->slug]))
            ->assertSessionHasErrors('slug');
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['live_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('live_url');
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['gallery_media_ids' => [9999999]]))
            ->assertSessionHasErrors('gallery_media_ids.0');
    }

    public function test_project_media_fields_produce_images_alt_text_and_social_metadata(): void
    {
        Storage::fake('local');
        $media = $this->image();
        $project = Project::firstOrFail();
        $project->update(['media_asset_id' => $media->id, 'gallery_media_ids' => [$media->id], 'is_featured' => true, 'status' => 'Verified release']);
        $this->get('/projects/'.$project->slug)->assertOk()
            ->assertSee('alt="Verified screenshot"', false)
            ->assertSee('property="og:image" content="http://localhost/media/'.$media->id.'"', false)
            ->assertSee('Featured')->assertSee('Verified release');
        $this->get('/')->assertOk()->assertSee('"featured":true', false)->assertSee('"gallery":[', false);
    }

    public function test_project_revision_restores_deleted_content_without_deleting_media(): void
    {
        Storage::fake('local');
        $media = $this->image();
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::firstOrFail();
        $project->update(['media_asset_id' => $media->id, 'gallery_media_ids' => [$media->id]]);
        $id = $project->id;
        $name = $project->name;
        $this->actingAs($admin)->delete('/admin/projects/'.$id)->assertRedirect('/admin/projects');
        $revision = ContentRevision::where('scope', 'project')->firstOrFail();
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
        $this->assertTrue(Storage::disk('local')->exists($media->path));
        $this->actingAs($admin)->post('/admin/revisions/'.$revision->id.'/restore', ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['id' => $id, 'name' => $name, 'media_asset_id' => $media->id]);
        $this->get('/projects/'.$project->slug)->assertOk();
    }

    public function test_site_revision_restores_settings_sections_and_skills_atomically(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = PortfolioSetting::findOrFail(1);
        $originalName = $settings->name;
        $values = $this->siteFields();
        $values['settings']['name'] = 'Changed verified name';
        $values['sections']['about']['title'] = 'Changed about';
        $category = SkillCategory::firstOrFail();
        $values['skill_categories'][$category->id]['skills'] = 'Verified skill';
        $this->actingAs($admin)->put('/admin/site-editor', $values)->assertSessionHasNoErrors();
        $revision = ContentRevision::where('scope', 'site')->firstOrFail();
        $this->actingAs($admin)->post('/admin/revisions/'.$revision->id.'/restore', ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame($originalName, $settings->fresh()->name);
        $this->assertNotSame('Changed about', PortfolioSection::where('key', 'about')->value('title'));
        $this->assertDatabaseMissing('skills', ['name' => 'Verified skill']);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    public function test_failed_revision_restore_rolls_back_and_requires_confirmation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::firstOrFail();
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['description' => 'Current version']))
            ->assertSessionHasNoErrors();
        $revision = ContentRevision::firstOrFail();
        $this->actingAs($admin)->post('/admin/revisions/'.$revision->id.'/restore')->assertSessionHasErrors('confirm');
        $revision->update(['snapshot' => [...$revision->snapshot, 'media_asset_id' => 9999999]]);
        $this->actingAs($admin)->post('/admin/revisions/'.$revision->id.'/restore', ['confirm' => 1])->assertSessionHasErrors('media_asset_id');
        $this->assertSame('Current version', $project->fresh()->description);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_restore_rejects_a_missing_gallery_file_even_if_its_database_record_exists(): void
    {
        Storage::fake('local');
        $media = $this->image();
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::firstOrFail();
        $project->update(['gallery_media_ids' => [$media->id]]);
        $this->actingAs($admin)->put('/admin/projects/'.$project->id, $this->projectFields($project, ['description' => 'Current version', 'gallery_media_ids' => []]))
            ->assertSessionHasNoErrors();
        $revision = ContentRevision::firstOrFail();
        Storage::disk('local')->delete($media->path);
        $this->actingAs($admin)->post('/admin/revisions/'.$revision->id.'/restore', ['confirm' => 1])
            ->assertSessionHasErrors('gallery_media_ids.0');
        $this->assertSame('Current version', $project->fresh()->description);
        $this->assertSame([], $project->fresh()->gallery_media_ids);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_maintenance_uses_503_and_excludes_home_and_projects_from_sitemap(): void
    {
        PortfolioSetting::findOrFail(1)->update(['maintenance_mode' => true]);
        $this->get('/')->assertStatus(503)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/projects/'.Project::firstOrFail()->slug)->assertStatus(503)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<url>', false);
    }

    public function test_public_search_controls_and_admin_project_search_are_operational(): void
    {
        $xpath = new \DOMXPath($this->document($this->get('/')->assertOk()->getContent()));
        $this->assertSame(1, $xpath->query('//input[@type="search" and @aria-label="Search projects"]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class,"empty-state") and @role="status"]')->length);
        $this->assertGreaterThan(1, $xpath->query('//*[contains(@class,"filter-button") and @data-filter]')->length);

        $match = Project::firstOrFail();
        $match->update(['name' => 'Verified searchable project', 'technologies' => ['Verified100%Engine']]);
        $other = Project::whereKeyNot($match->id)->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        // The page also embeds the unfiltered live-preview payload, so assert on the rendered editor list only.
        $listedNames = function (string $query) use ($admin): array {
            $html = $this->actingAs($admin)->get('/admin/projects?q='.rawurlencode($query))->assertOk()->getContent();
            $nodes = (new \DOMXPath($this->document($html)))->query('//*[@id="project-list"]//*[contains(@class,"project-title-row")]/h2');

            return collect(iterator_to_array($nodes))->map(fn ($node) => trim($node->textContent))->all();
        };
        $this->assertSame(['Verified searchable project'], $listedNames('Verified100%Engine'));
        $this->assertNotContains($other->name, $listedNames('Verified100%Engine'));
        $this->assertSame(['Verified searchable project'], $listedNames('100%'));
        $this->assertSame(['Verified searchable project'], $listedNames('%'));
        $this->actingAs($admin)->get('/admin/projects?q=Verified100%25Engine')->assertSee('1 project found', false);
        $this->actingAs($admin)->get('/admin/projects?q=no-such-verified-project')->assertOk()->assertSee('No projects match that search.');
        $this->actingAs($admin)->get('/admin/projects?q='.str_repeat('x', 101))->assertSessionHasErrors('q');
    }

    public function test_content_routes_require_administrator_authorization(): void
    {
        $this->get('/admin/sections')->assertRedirect('/admin/login');
        $this->get('/admin/revisions')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/sections')->assertForbidden();
        $this->get('/admin/revisions')->assertForbidden();
    }

    private function sectionFields(array $overrides = []): array
    {
        return [...[
            'type' => 'custom', 'label' => 'Verified block', 'title' => 'Verified content',
            'subtitle' => '', 'body' => 'Verified plain text', 'items_json' => '[]',
            'is_visible' => 1, 'show_in_navigation' => 1, 'sort_order' => 50,
            'spacing_top' => 100, 'spacing_bottom' => 100, 'background_effect' => 'cosmic',
        ], ...$overrides];
    }

    private function projectFields(Project $project, array $overrides = []): array
    {
        return [
            ...collect($project->toArray())->except(['id', 'created_at', 'updated_at', 'created_by'])->all(),
            'technologies' => implode("\n", $project->technologies),
            'features' => implode("\n", $project->features),
            ...$overrides,
        ];
    }

    private function siteFields(): array
    {
        return [
            'settings' => PortfolioSetting::findOrFail(1)->toArray(),
            'sections' => PortfolioSection::all()->mapWithKeys(fn ($section) => [$section->key => collect($section->toArray())->only(['title', 'subtitle', 'body', 'is_visible', 'sort_order', 'label', 'show_in_navigation', 'spacing_top', 'spacing_bottom', 'background_effect', 'image_media_id', 'background_media_id'])->all()])->all(),
            'skill_categories' => SkillCategory::with('skills')->get()->mapWithKeys(fn ($category) => [$category->id => [
                'title' => $category->title, 'skills' => $category->skills->pluck('name')->implode("\n"),
                'is_visible' => $category->is_visible, 'sort_order' => $category->sort_order,
            ]])->all(),
        ];
    }

    private function image(): MediaAsset
    {
        Storage::disk('local')->put('media/verified.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6Z3sAAAAASUVORK5CYII='));

        return MediaAsset::create([
            'label' => 'Verified image', 'original_name' => 'verified.png', 'disk' => 'local',
            'path' => 'media/verified.png', 'mime_type' => 'image/png', 'size_bytes' => 68,
            'width' => 1, 'height' => 1, 'alt_text' => 'Verified screenshot',
        ]);
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }
}

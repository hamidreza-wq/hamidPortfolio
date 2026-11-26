<?php

namespace Tests\Feature;

use App\Models\ContentRevision;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Services\AssetUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function create(array $data = [])
    {
        return $this->actingAs($this->admin)->post('/admin/pages', $data + [
            'title' => 'Services', 'body' => "## What I do\n\nLaravel and React work.", 'status' => 'published',
        ]);
    }

    public function test_create_publish_and_view_at_its_own_address(): void
    {
        $this->create(['summary' => 'Help with web apps.'])->assertRedirect();
        $page = Page::sole();
        $this->assertSame('services', $page->slug);

        $this->get('/services')->assertOk()
            ->assertSee('<h1>Services</h1>', false)->assertSee('<h2>What I do</h2>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/services">', false)
            ->assertSee('Help with web apps.');
        $this->get('/sitemap.xml')->assertSee('http://localhost/services');
    }

    public function test_drafts_and_scheduled_pages_are_hidden_but_previewable(): void
    {
        $this->create(['title' => 'Draft one', 'status' => 'draft']);
        $this->create(['title' => 'Later', 'status' => 'published', 'publish_at' => now()->addDay()->format('Y-m-d H:i:s')]);

        $this->get('/draft-one')->assertNotFound();
        $this->get('/later')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/later');

        $later = Page::where('slug', 'later')->sole();
        $this->assertTrue($later->isScheduled());
        $this->actingAs($this->admin)->get("/admin/pages/{$later->id}/preview")->assertOk()
            ->assertSee('Preview: Scheduled for')->assertSee('noindex', false);
        auth()->logout();
        $this->get("/admin/pages/{$later->id}/preview")->assertRedirect('/admin/login');

        $this->travel(2)->days();
        $this->get('/later')->assertOk();
    }

    public function test_reserved_and_duplicate_addresses_are_rejected_and_existing_routes_still_work(): void
    {
        foreach (['admin', 'privacy', 'projects', 'up', 'sitemap', 'testimonials'] as $slug) {
            $this->create(['title' => 'X', 'slug' => $slug])->assertSessionHasErrors('slug');
        }
        $this->create(['title' => 'Bad', 'slug' => '../etc'])->assertSessionHas('status');
        $this->assertSame('etc', Page::sole()->slug, 'addresses are normalised to safe slugs');
        $this->create(['title' => 'Again', 'slug' => 'etc'])->assertSessionHasErrors('slug');

        $this->get('/up')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/robots.txt')->assertOk();
        $this->get('/styles.css')->assertOk();
    }

    public function test_content_is_safe_markdown_with_library_images_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('portfolio-media/test.png', 'png');
        $media = MediaAsset::create(['label' => 'Test', 'original_name' => 'test.png', 'disk' => 'public', 'path' => 'portfolio-media/test.png',
            'mime_type' => 'image/png', 'size_bytes' => 3, 'width' => 1, 'height' => 1, 'alt_text' => 'Test', 'reencoded' => true]);
        $body = "<script>alert(1)</script>\n\n[bad](javascript:alert(1)) [ok](https://example.org)\n\n"
            ."![remote](https://evil.example/x.png)\n\n![ours](/media/{$media->id})";
        $this->create(['title' => 'Safe', 'body' => $body]);

        $html = $this->get('/safe')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('<img loading="lazy" src="/media/'.$media->id.'"', $html);
        $this->assertStringContainsString('href="https://example.org" target="_blank" rel="noopener noreferrer"', $html);

        $this->assertContains('pages #'.Page::sole()->id, app(AssetUsage::class)->media($media), 'images used in pages cannot be deleted');
    }

    public function test_edit_records_history_and_restore_brings_it_back(): void
    {
        $this->create();
        $page = Page::sole();
        $this->actingAs($this->admin)->put("/admin/pages/{$page->id}", ['title' => 'Services v2', 'slug' => 'services', 'body' => 'New text', 'status' => 'published'])
            ->assertSessionHas('status');
        $this->get('/services')->assertSee('New text');

        $revision = ContentRevision::where('scope', 'page')->latest('id')->first();
        $this->assertSame('Services', $revision->snapshot['title']);
        $this->actingAs($this->admin)->post("/admin/revisions/{$revision->id}/restore", ['confirm' => '1'])->assertSessionHas('status');
        $this->get('/services')->assertSee('Laravel and React work.');
    }

    public function test_trash_restore_duplicate_and_permanent_delete(): void
    {
        $this->create(['menu_footer' => '1']);
        $page = Page::sole();
        $this->assertSame(1, MenuItem::where('type', 'custom_page')->count());
        $this->assertStringContainsString('href="/services"', $this->get('/')->getContent());

        $this->actingAs($this->admin)->post("/admin/pages/{$page->id}/duplicate")->assertRedirect();
        $copy = Page::where('slug', 'services-copy')->sole();
        $this->assertSame('draft', $copy->status);

        $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');
        $this->get('/services')->assertNotFound();
        $this->assertStringNotContainsString('href="/services"', $this->get('/')->getContent(), 'menu links to trashed pages disappear');

        $this->actingAs($this->admin)->post("/admin/pages/{$page->id}/restore")->assertRedirect();
        $this->get('/services')->assertOk();

        $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}");
        $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}/permanently")->assertSessionHasErrors('confirm');
        $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}/permanently", ['confirm' => '1'])->assertSessionHas('status');
        $this->assertNull(Page::withTrashed()->find($page->id));
        $this->assertSame(0, MenuItem::where('type', 'custom_page')->count());
    }

    public function test_admin_pages_list_search_and_access(): void
    {
        $this->get('/admin/pages')->assertRedirect('/admin/login');
        $this->create(['title' => 'Speaking', 'status' => 'draft']);

        $this->actingAs($this->admin)->get('/admin/pages')->assertOk()->assertSee('Speaking')->assertSee('Drafts · 1');
        $this->actingAs($this->admin)->get('/admin/pages?view=published')->assertOk()->assertDontSee('/speaking');
        $this->actingAs($this->admin)->get('/admin/pages/create')->assertOk()->assertSee('customise the header menu');
        $groups = $this->actingAs($this->admin)->getJson('/admin/search?q=Speaking')->json('groups');
        $this->assertSame('Speaking', collect($groups)->firstWhere('type', 'site_pages')['items'][0]['title']);
    }
}

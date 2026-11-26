<?php

namespace Tests\Feature;

use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_seeded_from_the_existing_github_profile(): void
    {
        $github = SocialLink::where('platform', 'github')->sole();
        $this->assertSame('https://github.com/hamidreza-wq', $github->url);
        $this->assertTrue($github->fresh()->show_in_contact);
    }

    public function test_page_requires_an_administrator(): void
    {
        $this->get('/admin/social')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/social')->assertForbidden();
    }

    public function test_urls_must_be_https_and_match_the_platform(): void
    {
        $admin = $this->admin();
        $add = fn (array $data) => $this->actingAs($admin)->post('/admin/social', $data + ['is_enabled' => 1, 'show_in_footer' => 1]);

        $add(['platform' => 'linkedin', 'url' => 'https://evil.example/in/me'])->assertSessionHasErrors('url');
        $add(['platform' => 'linkedin', 'url' => 'https://linkedin.com.evil.example/in/me'])->assertSessionHasErrors('url');
        $add(['platform' => 'instagram', 'url' => 'http://instagram.com/me'])->assertSessionHasErrors('url');
        $add(['platform' => 'website', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $add(['platform' => 'x', 'url' => 'https://user:pass@x.com/me'])->assertSessionHasErrors('url');
        $add(['platform' => 'nope', 'url' => 'https://example.com'])->assertSessionHasErrors('platform');

        $add(['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/in/me'])->assertSessionHas('status');
        $add(['platform' => 'mastodon', 'url' => 'https://hachyderm.io/@me', 'label' => 'Mastodon (hachyderm)'])->assertSessionHas('status');
        $this->assertSame(3, SocialLink::count());
    }

    public function test_placement_controls_where_links_render_on_the_public_site(): void
    {
        SocialLink::query()->delete();
        SocialLink::create(['platform' => 'bluesky', 'url' => 'https://bsky.app/profile/me', 'show_in_header' => true, 'show_in_footer' => false, 'show_in_contact' => false, 'sort_order' => 1]);
        SocialLink::create(['platform' => 'youtube', 'url' => 'https://www.youtube.com/@me', 'show_in_header' => false, 'show_in_footer' => true, 'show_in_contact' => true, 'sort_order' => 2]);
        SocialLink::create(['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@hidden', 'is_enabled' => false, 'sort_order' => 3]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a class="nav-social" data-social="" href="https://bsky.app/profile/me" target="_blank" rel="me noopener noreferrer">Bluesky</a>~', $html);
        $this->assertMatchesRegularExpression('~<nav class="footer-social"[^>]*><a data-social="" href="https://www.youtube.com/@me"[^>]*>YouTube</a></nav>~', $html);
        $this->assertStringNotContainsString('tiktok.com/@hidden', $html, 'disabled links are not published, not even in the payload');
        $this->assertStringContainsString('"sameAs":["https://github.com/hamidreza-wq","https://bsky.app/profile/me","https://www.youtube.com/@me"]', $html);
    }

    public function test_edit_reorder_and_delete(): void
    {
        $admin = $this->admin();
        $github = SocialLink::where('platform', 'github')->sole();
        $linkedin = SocialLink::create(['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/in/me', 'sort_order' => 99]);

        $this->actingAs($admin)->patch("/admin/social/{$linkedin->id}/move", ['direction' => 'up']);
        $this->assertSame([$linkedin->id, $github->id], SocialLink::orderBy('sort_order')->pluck('id')->all());
        $this->actingAs($admin)->patch("/admin/social/{$linkedin->id}/move", ['direction' => 'up']); // already first
        $this->assertSame([$linkedin->id, $github->id], SocialLink::orderBy('sort_order')->pluck('id')->all());

        $this->actingAs($admin)->put("/admin/social/{$github->id}", ['platform' => 'github', 'url' => 'https://github.com/someone-else', 'label' => 'Code'])
            ->assertSessionHas('status');
        $fresh = $github->fresh();
        $this->assertSame('Code', $fresh->displayLabel());
        $this->assertFalse($fresh->is_enabled, 'unticked checkboxes are saved as off');

        $this->actingAs($admin)->delete("/admin/social/{$linkedin->id}")->assertSessionHas('status');
        $this->assertSame(1, SocialLink::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'social.deleted']);
        $this->actingAs($admin)->get('/admin/social')->assertOk()->assertSee('Code')->assertSee('Not shown anywhere');
    }

    public function test_migration_seeds_existing_profiles(): void
    {
        SocialLink::query()->delete();
        \DB::table('portfolio_settings')->where('id', 1)->update(['linkedin_url' => 'https://www.linkedin.com/in/existing']);
        \Schema::drop('social_links');
        \DB::table('migrations')->where('migration', '2026_11_28_000000_create_social_links_table')->delete();

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_000000_create_social_links_table.php'])->assertSuccessful();

        $this->assertSame(['github', 'linkedin'], SocialLink::orderBy('sort_order')->pluck('platform')->all());
    }
}

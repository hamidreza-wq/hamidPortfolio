<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\PortfolioSection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // One test posts a backup; keep it off the real storage folder.
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_search_requires_an_administrator(): void
    {
        $this->get('/admin/search?q=test')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/search?q=test')->assertForbidden();
    }

    public function test_finds_projects_messages_media_sections_and_pages_with_edit_links(): void
    {
        $admin = $this->admin();
        $project = Project::query()->first();
        $message = ContactMessage::create(['name' => 'Ada Quixote', 'email' => 'ada@example.test', 'subject' => 'Commission', 'message' => 'Hello', 'status' => 'unread']);
        $media = MediaAsset::query()->first();
        $section = PortfolioSection::where('key', 'about')->first();

        $json = fn (string $q) => $this->actingAs($admin)->getJson('/admin/search?q='.urlencode($q))->assertOk()->json('groups');
        $find = fn (array $groups, string $type) => collect($groups)->firstWhere('type', $type)['items'] ?? [];

        $this->assertSame($project->name, $find($json($project->name), 'projects')[0]['title']);
        $this->assertStringContainsString('#project-'.$project->id.'-name', $find($json($project->name), 'projects')[0]['url']);
        $this->assertSame('Commission', $find($json('Quixote'), 'messages')[0]['title']);
        $this->assertSame('Security', $find($json('2fa'), 'pages')[0]['title']);
        $this->assertContains('Backups', array_column($find($json('restore'), 'pages'), 'title'));
        $this->assertStringContainsString('#section-about-label', $find($json($section->title), 'sections')[0]['url']);
        if ($media) {
            $this->assertNotEmpty($find($json($media->label), 'media'));
        }
    }

    public function test_like_wildcards_are_matched_literally(): void
    {
        $admin = $this->admin();
        $groups = $this->actingAs($admin)->getJson('/admin/search?q=%25%25')->assertOk()->json('groups');
        $this->assertSame([], $groups, '"%%" must not match everything');

        $groups = $this->actingAs($admin)->getJson('/admin/search?q=__')->assertOk()->json('groups');
        $this->assertSame([], $groups, '"__" must not match every two-character value');
    }

    public function test_short_and_overlong_queries(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/admin/search?q=a')->assertOk()->assertJson(['groups' => []]);
        $this->actingAs($admin)->getJson('/admin/search?q='.str_repeat('a', 101))->assertUnprocessable();
        $this->actingAs($admin)->get('/admin/search?q=a')->assertOk()->assertSee('Type at least two characters');
    }

    public function test_results_page_escapes_content_and_filters_by_type(): void
    {
        $admin = $this->admin();
        ContactMessage::create(['name' => '<script>alert(1)</script>', 'email' => 'x@example.test', 'subject' => 'Scripted <b>hello</b>', 'message' => 'x', 'status' => 'unread']);

        $this->actingAs($admin)->get('/admin/search?q=Scripted')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Scripted &lt;b&gt;hello&lt;/b&gt;', false);

        $this->actingAs($admin)->get('/admin/search?q=Scripted&type=projects')
            ->assertOk()->assertSee('Nothing matches');
        $this->actingAs($admin)->get('/admin/search?q=Scripted&type=bogus')->assertSessionHasErrors('type');
    }

    public function test_layout_has_the_quick_search_and_search_has_its_own_rate_limit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('id="quick-search"', false)->assertSee('Ctrl K');

        foreach (range(1, 20) as $i) {
            $this->actingAs($admin)->getJson('/admin/search?q=project')->assertOk();
        }
        // Typing many searches must not use up the shared limit other admin actions rely on.
        $this->actingAs($admin)->post('/admin/backups')->assertStatus(302);
    }
}

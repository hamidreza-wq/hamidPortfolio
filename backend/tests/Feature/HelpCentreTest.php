<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HelpCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_every_admin_page_has_a_help_article(): void
    {
        // Pages that are not screens of their own.
        $skip = ['admin.analytics.google.callback', 'admin.media.download', 'admin.help.index', 'admin.help.show'];
        $help = app(HelpCentre::class);
        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && str_starts_with((string) $route->getName(), 'admin.')
                && in_array('admin', $route->gatherMiddleware(), true) && ! in_array($route->getName(), $skip, true))
            ->map->getName()
            ->reject(fn ($name) => $help->topicForRoute($name) !== null)
            ->values()->all();

        $this->assertSame([], $missing, 'Add a help topic for these admin pages in HelpCentre::TOPICS.');
    }

    public function test_every_topic_has_an_article_that_renders(): void
    {
        $help = app(HelpCentre::class);
        foreach (array_keys(HelpCentre::TOPICS) as $slug) {
            $this->assertTrue($help->exists($slug), "resources/help/{$slug}.md is missing");
            $article = $help->render($slug);
            $this->assertGreaterThanOrEqual(2, count($article['headings']), "{$slug} needs at least two sections");
            $this->assertDoesNotMatchRegularExpression('~<(script|iframe|img|style)\b~i', $article['html'], $slug);
            foreach (route_links($article['html']) as $link) {
                $this->assertTrue(Route::getRoutes()->match(Request::create($link))->getName() !== null, "{$slug} links to {$link}, which is not a page");
            }
        }
    }

    public function test_markdown_options_escape_html_and_drop_unsafe_links(): void
    {
        $html = Str::markdown("<script>alert(1)</script>\n\n[x](javascript:alert(1))", HelpCentre::MARKDOWN);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_pages_search_and_contextual_link(): void
    {
        $this->get('/admin/help')->assertRedirect('/admin/login');

        $this->actingAs($this->admin)->get('/admin/help')->assertOk()->assertSee('Getting started')->assertSee('Running the site');
        $this->actingAs($this->admin)->get('/admin/help/backups')->assertOk()
            ->assertSee('portfolio:backup:restore')->assertSee('id="restoring"', false)->assertSee(route('admin.backups.index'), false);
        $this->actingAs($this->admin)->get('/admin/help/no-such-topic')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/help/..%2F..%2F.env')->assertNotFound();

        $this->actingAs($this->admin)->get('/admin/help?q=recovery')->assertOk()->assertSee('Security');
        $this->actingAs($this->admin)->get('/admin/backups')->assertOk()->assertSee(route('admin.help.show', 'backups'), false);

        $groups = $this->actingAs($this->admin)->getJson('/admin/search?q=recovery+codes')->json('groups');
        $this->assertContains('Security', array_column(collect($groups)->firstWhere('type', 'help')['items'] ?? [], 'title'));
    }
}

/** Internal dashboard links in rendered help HTML. */
function route_links(string $html): array
{
    preg_match_all('~href="(/admin[^"#]*)~', $html, $matches);

    return array_unique($matches[1]);
}

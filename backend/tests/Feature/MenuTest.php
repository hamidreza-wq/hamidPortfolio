<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function navLinks(string $html): array
    {
        preg_match('~<nav class="primary-navigation"[^>]*>(.*?)</nav>~s', $html, $nav);
        preg_match_all('~<a\b([^>]*)>([^<]*)</a>~', $nav[1] ?? '', $links, PREG_SET_ORDER);

        return array_map(fn ($link) => [trim($link[2]), preg_match('~href="([^"]*)"~', $link[1], $h) ? $h[1] : null], $links);
    }

    private function add(array $data)
    {
        return $this->actingAs($this->admin)->post('/admin/menus/items', $data + ['is_enabled' => 1]);
    }

    public function test_header_is_automatic_until_customised(): void
    {
        $before = $this->navLinks($this->get('/')->getContent());
        $this->assertNotEmpty($before);

        $this->add(['menu' => 'header', 'type' => 'page', 'target_page' => 'privacy'])->assertSessionHasErrors('menu');

        $this->actingAs($this->admin)->post('/admin/menus/customise')->assertSessionHas('status');
        $this->assertSame($before, $this->navLinks($this->get('/')->getContent()), 'customising starts from the same menu');

        // Sections no longer add themselves once the menu is custom.
        PortfolioSection::create(['key' => 'extra', 'type' => 'custom', 'label' => 'Extra', 'title' => 'Extra', 'items' => [], 'is_visible' => true, 'show_in_navigation' => true, 'sort_order' => 99]);
        $this->assertNotContains(['Extra', '#extra'], $this->navLinks($this->get('/')->getContent()));

        $this->actingAs($this->admin)->post('/admin/menus/automatic')->assertSessionHas('status');
        $this->assertContains(['Extra', '#extra'], $this->navLinks($this->get('/')->getContent()));
    }

    public function test_custom_items_link_projects_pages_and_websites_and_skip_dead_targets(): void
    {
        $this->actingAs($this->admin)->post('/admin/menus/customise');
        MenuItem::where('menu', 'header')->delete();
        $project = Project::where('is_published', true)->first();
        MenuItem::create(['menu' => 'header', 'type' => 'page', 'target' => 'home', 'sort_order' => 1]);

        $this->add(['menu' => 'header', 'type' => 'project', 'target_project' => $project->id])->assertSessionHas('status');
        $this->add(['menu' => 'header', 'type' => 'url', 'target_url' => 'https://blog.example/', 'label' => 'Blog', 'open_in_new_tab' => 1])->assertSessionHas('status');
        $this->add(['menu' => 'header', 'type' => 'section', 'target_section' => 'contact', 'label' => "Let's talk", 'is_button' => 1])->assertSessionHas('status');
        $this->add(['menu' => 'header', 'type' => 'page', 'target_page' => 'testimonials'])->assertSessionHas('status');

        $html = $this->get('/')->getContent();
        $this->assertSame([
            ['Home', '#home'],
            [$project->name, '/projects/'.$project->slug],
            ['Blog', 'https://blog.example/'],
            ["Let's talk", '#contact'],
        ], array_map(fn ($link) => [html_entity_decode($link[0], ENT_QUOTES), $link[1]], $this->navLinks($html)), 'the closed testimonial form is left out');
        $this->assertStringContainsString('href="https://blog.example/" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertMatchesRegularExpression('~<a class="nav-contact" data-menu="" href="#contact">~', $html);

        $project->update(['is_published' => false]);
        PortfolioSetting::findOrFail(1)->forceFill(['testimonial_submissions_open' => true])->save();
        $labels = array_column($this->navLinks($this->get('/')->getContent()), 0);
        $this->assertNotContains($project->name, $labels, 'unpublished project is left out');
        $this->assertContains('Leave a testimonial', $labels, 'open form is shown');
        $this->actingAs($this->admin)->get('/admin/menus')->assertOk()->assertSee('Hidden: that project is not published');
    }

    public function test_footer_menu_renders_beside_privacy(): void
    {
        $this->add(['menu' => 'footer', 'type' => 'url', 'target_url' => 'mailto:hello@example.com', 'label' => 'Email'])->assertSessionHas('status');

        $this->assertMatchesRegularExpression('~<nav class="footer-menu" data-menu="" aria-label="Footer"><a data-menu="" href="mailto:hello@example.com">Email</a></nav>.*?class="privacy-link"~s', $this->get('/')->getContent());
    }

    public function test_unsafe_or_incomplete_items_are_rejected(): void
    {
        foreach (['javascript:alert(1)', 'http://plain.example', 'https://user:pw@x.example', 'mailto:not-an-email', '//evil.example'] as $url) {
            $this->add(['menu' => 'footer', 'type' => 'url', 'target_url' => $url, 'label' => 'Bad'])->assertSessionHasErrors('target_url');
        }
        $this->add(['menu' => 'footer', 'type' => 'url', 'target_url' => 'https://ok.example'])->assertSessionHasErrors('label');
        $this->add(['menu' => 'footer', 'type' => 'section', 'target_section' => 'no-such-section'])->assertSessionHasErrors('target_section');
        $this->add(['menu' => 'footer', 'type' => 'page', 'target_page' => '../admin'])->assertSessionHasErrors('target_page');
        $this->add(['menu' => 'sidebar', 'type' => 'page', 'target_page' => 'home'])->assertSessionHasErrors('menu');
        $this->assertSame(0, MenuItem::count());
    }

    public function test_reorder_edit_and_last_header_item_guard(): void
    {
        $this->actingAs($this->admin)->post('/admin/menus/customise');
        $items = MenuItem::where('menu', 'header')->orderBy('sort_order')->get();
        $second = $items[1];

        $this->actingAs($this->admin)->patch("/admin/menus/items/{$second->id}/move", ['direction' => 'up']);
        $this->assertSame($second->id, MenuItem::where('menu', 'header')->orderBy('sort_order')->first()->id);

        $this->actingAs($this->admin)->put("/admin/menus/items/{$second->id}", ['type' => 'section', 'target_section' => $second->target, 'label' => 'Renamed', 'is_enabled' => 1])
            ->assertSessionHas('status');
        $this->assertSame('Renamed', $this->navLinks($this->get('/')->getContent())[0][0]);

        MenuItem::where('menu', 'header')->where('id', '!=', $second->id)->delete();
        $this->actingAs($this->admin)->delete("/admin/menus/items/{$second->id}")->assertSessionHasErrors('menu');
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.customised']);
    }

    public function test_page_requires_an_administrator(): void
    {
        $this->get('/admin/menus')->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get('/admin/menus')->assertOk()->assertSee('Automatic')->assertSee('Customise the header menu');
    }
}

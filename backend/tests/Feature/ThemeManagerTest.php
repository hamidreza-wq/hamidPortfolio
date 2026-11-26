<?php

namespace Tests\Feature;

use App\Models\ContentRevision;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\ThemePreset;
use App\Models\User;
use App\Services\Themes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ThemeManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function settings(): PortfolioSetting
    {
        return PortfolioSetting::findOrFail(1);
    }

    public function test_built_in_themes_are_all_valid(): void
    {
        $themes = app(Themes::class);
        foreach ($themes->builtIn() as $key => $theme) {
            $this->assertSame(Themes::FIELDS, array_keys($themes->validate($theme['values'])), $key);
        }
        $this->assertSame($themes->builtIn()['cosmic']['values']['color_background'], $this->settings()->color_background, 'Cosmic matches the seeded default');
    }

    public function test_applying_a_theme_changes_only_the_look_and_records_history(): void
    {
        $before = [
            'title' => $this->settings()->site_title,
            'sections' => PortfolioSection::orderBy('id')->get(['key', 'title', 'body'])->toArray(),
            'projects' => Project::orderBy('id')->get(['name', 'description'])->toArray(),
        ];
        $revisions = ContentRevision::count();

        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'built-in:terminal'])->assertSessionHas('status');

        $settings = $this->settings();
        $this->assertSame('#050805', $settings->color_background);
        $this->assertSame('monospace', $settings->font_heading);
        $this->assertSame(2, (int) $settings->border_radius);
        $this->assertFalse((bool) $settings->particles_enabled);
        $this->assertSame($before['title'], $settings->site_title);
        $this->assertSame($before['sections'], PortfolioSection::orderBy('id')->get(['key', 'title', 'body'])->toArray());
        $this->assertSame($before['projects'], Project::orderBy('id')->get(['name', 'description'])->toArray());
        $this->assertSame($revisions + 1, ContentRevision::count(), 'the previous look is saved to Content history');
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.applied']);

        $this->get('/')->assertOk()->assertSee('"background":"#050805"', false);

        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'built-in:cosmic']);
        $this->assertSame('#070811', $this->settings()->color_background, 'restore default');
    }

    public function test_save_duplicate_edit_and_apply_a_custom_theme(): void
    {
        $this->actingAs($this->admin)->post('/admin/themes/save', ['name' => 'Mine', 'source' => 'built-in:ember'])->assertSessionHas('status');
        $preset = ThemePreset::where('name', 'Mine')->sole();
        $this->assertSame('#ff9b5e', $preset->color_primary);
        $this->assertSame(10, $preset->settings['border_radius']);
        $this->actingAs($this->admin)->post('/admin/themes/save', ['name' => 'Mine'])->assertSessionHasErrors('name');

        $values = app(Themes::class)->valuesOf($preset);
        $this->actingAs($this->admin)->put("/admin/themes/{$preset->id}", ['name' => 'Mine v2', 'theme' => ['color_primary' => '#123456', 'border_radius' => 20, 'particles_enabled' => '1'] + array_diff_key($values, ['animations_enabled' => true])])
            ->assertSessionHas('status');
        $preset->refresh();
        $this->assertSame(['Mine v2', '#123456', 20], [$preset->name, $preset->color_primary, $preset->settings['border_radius']]);
        $this->assertTrue($preset->settings['particles_enabled']);
        $this->assertFalse($preset->settings['animations_enabled'], 'unticked checkbox saves as off');

        $this->actingAs($this->admin)->put("/admin/themes/{$preset->id}", ['name' => 'Bad', 'theme' => ['border_radius' => 99] + $values])->assertSessionHasErrors('border_radius');
        $this->actingAs($this->admin)->put("/admin/themes/{$preset->id}", ['name' => 'Bad', 'theme' => ['color_primary' => 'red;}body{'] + $values])->assertSessionHasErrors('color_primary');
        $this->actingAs($this->admin)->put("/admin/themes/{$preset->id}", ['name' => 'Bad', 'theme' => ['font_body' => 'PortfolioFont999'] + $values])->assertSessionHasErrors('font_body');

        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'saved:'.$preset->id]);
        $this->assertSame('#123456', $this->settings()->color_primary);
        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'saved:999999'])->assertNotFound();
        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'built-in:nope'])->assertNotFound();
    }

    public function test_colour_only_presets_keep_the_current_layout(): void
    {
        $legacy = ThemePreset::where('name', 'Cosmic default')->sole();
        $this->assertNull($legacy->settings);
        $this->settings()->update(['border_radius' => 25]);

        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'saved:'.$legacy->id]);
        $this->assertSame(25, (int) $this->settings()->border_radius);
        $this->actingAs($this->admin)->get('/admin/themes')->assertOk()->assertSee('Colours only');
    }

    public function test_export_and_import_round_trip_with_strict_validation(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/themes/export', ['source' => 'built-in:rose'])->assertOk();
        $this->assertStringContainsString('rose.theme.json', $response->headers->get('Content-Disposition'));
        $json = $response->getContent();
        $this->assertSame(Themes::FORMAT, json_decode($json, true)['format']);

        $file = fn (string $content) => UploadedFile::fake()->createWithContent('theme.json', $content);
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file($json)])->assertSessionHas('status');
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file($json)])->assertSessionHas('status');
        $this->assertTrue(ThemePreset::where('name', 'Rose')->exists());
        $this->assertTrue(ThemePreset::where('name', 'Rose (2)')->exists(), 'name clashes get a suffix');

        $bad = json_decode($json, true);
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file('not json')])->assertSessionHasErrors('theme_file');
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file(json_encode(['format' => 'other'] + $bad))])->assertSessionHasErrors('theme_file');
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file(json_encode(array_replace_recursive($bad, ['theme' => ['color_background' => 'url(evil)']])))])->assertSessionHasErrors('theme_file');
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file(json_encode(array_replace_recursive($bad, ['theme' => ['font_body' => 'x;}</style><script>']])))])->assertSessionHasErrors('theme_file');

        $extra = $bad;
        $extra['theme']['site_title'] = 'Hijacked';
        $this->actingAs($this->admin)->post('/admin/themes/import', ['theme_file' => $file(json_encode(['name' => 'Extra'] + $extra))])->assertSessionHas('status');
        $imported = ThemePreset::where('name', 'Extra')->sole();
        $this->actingAs($this->admin)->post('/admin/themes/apply', ['source' => 'saved:'.$imported->id]);
        $this->assertNotSame('Hijacked', $this->settings()->site_title, 'unknown keys in a theme file are ignored');
    }

    public function test_page_lists_themes_and_requires_an_administrator(): void
    {
        $this->get('/admin/themes')->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get('/admin/themes')->assertOk()
            ->assertSee('Ready-made themes')->assertSee('Restore default')->assertSee('Terminal')
            ->assertSee('id="theme-preview"', false)->assertSee('Live');
    }
}

<?php

namespace Tests\Feature;

use App\Models\FontAsset;
use App\Models\User;
use App\Services\FontLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleFontsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const FILE = 'https://fonts.gstatic.com/s/playfairdisplay/v40/abc123.ttf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::preventStrayRequests();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** The smallest TrueType file the upload validator accepts: one table. */
    private function ttf(): string
    {
        return "\x00\x01\x00\x00".pack('nnnn', 1, 16, 0, 0).'head'.pack('NNN', 0, 28, 4).'data';
    }

    private function fakeGoogle(int $cssStatus = 200, ?string $fileUrl = null, ?string $fileBody = null): void
    {
        $url = $fileUrl ?? self::FILE;
        Http::fake([
            'fonts.googleapis.com/*' => Http::response($cssStatus === 200
                ? "@font-face {\n  font-family: 'Playfair Display';\n  font-style: normal;\n  font-weight: 700;\n  src: url({$url}) format('truetype');\n}\n" : 'Bad request', $cssStatus),
            'fonts.gstatic.com/*' => Http::response($fileBody ?? $this->ttf(), 200, ['Content-Type' => 'font/ttf']),
            '*' => Http::response('unexpected host', 500),
        ]);
    }

    public function test_import_copies_the_font_onto_the_site_and_it_becomes_selectable(): void
    {
        $this->fakeGoogle();
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Playfair Display', 'weights' => [700], 'style' => 'normal'])
            ->assertRedirect('/admin/fonts')->assertSessionHas('status');

        $font = FontAsset::sole();
        $this->assertSame(['Playfair Display', '700', 'normal', 'ttf'], [$font->family, $font->weight, $font->style, $font->format]);
        $this->assertTrue($font->is_active && $font->license_confirmed);
        $this->assertSame('https://fonts.google.com/specimen/Playfair+Display', $font->source_url);
        Storage::disk('public')->assertExists($font->path);
        $this->assertTrue(app(FontLibrary::class)->activeOptions()->contains('id', $font->id), 'selectable in the editor and themes');

        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://fonts.googleapis.com/css2?')
            && str_contains(urldecode($request->url()), 'family=Playfair Display:wght@700'));
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::FILE);
        $this->assertDatabaseHas('audit_logs', ['action' => 'font.google_imported']);

        // Public pages load it from this site only.
        $this->get('/fonts/'.$font->id)->assertOk()->assertHeader('Content-Type', 'font/ttf');
        $this->actingAs($this->admin)->get('/admin/fonts')->assertSee("font-family:'PortfolioFont{$font->id}'", false);
    }

    public function test_existing_weights_are_skipped_and_missing_weights_reported(): void
    {
        $this->fakeGoogle();
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Playfair Display', 'weights' => [700], 'style' => 'normal']);
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Playfair Display', 'weights' => [700], 'style' => 'normal'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Already in the library: 700 normal'));
        $this->assertSame(1, FontAsset::count());
    }

    public function test_a_weight_google_does_not_offer_is_reported(): void
    {
        $this->fakeGoogle(cssStatus: 400);
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Playfair Display', 'weights' => [100], 'style' => 'italic'])
            ->assertSessionHasErrors(['font' => 'Playfair Display could not be imported for: 100 italic: not available from Google Fonts in this weight and style']);
        $this->assertSame(0, FontAsset::count());
    }

    public function test_only_google_hosts_and_valid_font_files_are_accepted(): void
    {
        $this->fakeGoogle(fileUrl: 'https://evil.example/x.ttf');
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Inter', 'weights' => [400], 'style' => 'normal'])->assertSessionHasErrors('font');
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'evil.example'));

        $this->fakeGoogle(fileBody: '<?php echo "not a font";');
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Inter', 'weights' => [400], 'style' => 'normal'])->assertSessionHasErrors('font');
        $this->assertSame(0, FontAsset::count());
    }

    public function test_family_and_weights_are_validated_before_any_request(): void
    {
        foreach (['Inter&family=Evil', '../../etc', 'Inter:wght@400', str_repeat('A', 61)] as $family) {
            $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => $family, 'weights' => [400], 'style' => 'normal'])
                ->assertSessionHasErrors();
        }
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Inter', 'weights' => [450], 'style' => 'normal'])->assertSessionHasErrors('weights.0');
        $this->actingAs($this->admin)->post('/admin/fonts/google', ['family' => 'Inter', 'weights' => [400], 'style' => 'oblique'])->assertSessionHasErrors('style');
        Http::assertNothingSent();
    }

    public function test_search_offers_popular_families_without_a_key_and_exact_names(): void
    {
        config(['services.google_fonts.api_key' => null]);
        $this->actingAs($this->admin)->get('/admin/fonts?google=playfair')->assertOk()->assertSee('Playfair Display')->assertDontSee('Roboto');
        $this->actingAs($this->admin)->get('/admin/fonts?google=Anybody')->assertOk()->assertSee('Import by exact name')->assertSee('value="Anybody"', false);
        $this->get('/admin/fonts')->assertOk();
        Http::assertNothingSent();
    }

    public function test_search_uses_the_api_catalogue_when_a_key_is_set(): void
    {
        config(['services.google_fonts.api_key' => 'test-key']);
        Http::fake(['www.googleapis.com/webfonts/*' => Http::response(['items' => [
            ['family' => 'Zilla Slab', 'category' => 'serif'], ['family' => 'bad<script>', 'category' => 'x'],
        ]])]);

        $this->actingAs($this->admin)->get('/admin/fonts?google=zilla')->assertOk()->assertSee('Zilla Slab');
        $this->actingAs($this->admin)->get('/admin/fonts?google=script')->assertDontSee('bad<script>', false);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://www.googleapis.com/webfonts/v1/webfonts?key=test-key'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\PortfolioSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaGalleryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
        MediaAsset::query()->delete();
        PortfolioSetting::findOrFail(1)->update(['og_image_media_id' => null]);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_several_files_upload_in_one_request_and_bad_ones_are_reported(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/media', [
            'files' => [
                UploadedFile::fake()->image('team_photo-2026.png', 40, 30),
                UploadedFile::fake()->image('cover.jpg', 60, 40),
                UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect('/admin/media')->assertSessionHasErrors('files');
        $this->assertStringContainsString('notes.pdf', session('errors')->first('files'));
        $this->assertSame(['Cover', 'Team photo 2026'], MediaAsset::orderBy('label')->pluck('label')->all());
        $this->assertSame([$this->admin->id], MediaAsset::distinct()->pluck('uploaded_by')->all());
    }

    public function test_json_upload_one_file_per_request_and_limits(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/media', ['files' => [UploadedFile::fake()->image('a.webp', 10, 10)], 'alt_text' => 'A square'])
            ->assertOk()->assertJsonPath('uploaded.0.label', 'A')->assertJsonPath('failed', []);
        $this->assertSame('A square', MediaAsset::sole()->alt_text);

        $this->actingAs($this->admin)->postJson('/admin/media', ['files' => [UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')]])
            ->assertStatus(422)->assertJsonPath('failed.0.name', 'evil.svg');

        $eleven = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.png", 4, 4), range(1, 11));
        $this->actingAs($this->admin)->postJson('/admin/media', ['files' => $eleven])->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_single_file_form_still_works_and_label_defaults_to_the_file_name(): void
    {
        $this->actingAs($this->admin)->post('/admin/media', ['file' => UploadedFile::fake()->image('my-logo.png', 20, 20)])
            ->assertRedirect('/admin/media');
        $this->assertSame('My logo', MediaAsset::sole()->label);
    }

    public function test_filters_and_sorting(): void
    {
        $this->actingAs($this->admin)->post('/admin/media', ['files' => [
            UploadedFile::fake()->image('alpha.png', 400, 400),
            UploadedFile::fake()->image('beta.jpg', 20, 20),
        ]]);
        [$alpha, $beta] = [MediaAsset::where('label', 'Alpha')->sole(), MediaAsset::where('label', 'Beta')->sole()];
        $beta->update(['alt_text' => 'Described']);
        PortfolioSetting::findOrFail(1)->update(['og_image_media_id' => $alpha->id]);

        $page = fn (string $query) => $this->actingAs($this->admin)->get('/admin/media?'.$query)->assertOk();

        $page('usage=used')->assertSee('id="media-'.$alpha->id.'"', false)->assertDontSee('id="media-'.$beta->id.'"', false);
        $page('usage=unused')->assertSee('id="media-'.$beta->id.'"', false)->assertDontSee('id="media-'.$alpha->id.'"', false);
        $page('alt=missing')->assertSee('id="media-'.$alpha->id.'"', false)->assertDontSee('id="media-'.$beta->id.'"', false);
        $page('type=image/jpeg')->assertSee('id="media-'.$beta->id.'"', false)->assertDontSee('id="media-'.$alpha->id.'"', false);
        $page('sort=name')->assertSeeInOrder(['id="media-'.$alpha->id.'"', 'id="media-'.$beta->id.'"'], false);
        $page('q=%25')->assertSee('No images match these filters.');
        $this->actingAs($this->admin)->get('/admin/media?sort=bogus')->assertSessionHasErrors('sort');
    }

    public function test_download_uses_the_original_name_and_requires_an_admin(): void
    {
        $this->actingAs($this->admin)->post('/admin/media', ['file' => UploadedFile::fake()->image('Holiday Photo.png', 8, 8), 'label' => 'Holiday']);
        $asset = MediaAsset::sole();

        $this->actingAs($this->admin)->get("/admin/media/{$asset->id}/download")
            ->assertOk()->assertDownload('Holiday Photo.png');

        auth()->logout();
        $this->get("/admin/media/{$asset->id}/download")->assertRedirect('/admin/login');
    }

    public function test_page_shows_copy_and_download_controls(): void
    {
        $this->actingAs($this->admin)->post('/admin/media', ['file' => UploadedFile::fake()->image('card.png', 8, 8)]);
        $asset = MediaAsset::sole();

        $this->actingAs($this->admin)->get('/admin/media')->assertOk()
            ->assertSee('data-copy="#media-url-'.$asset->id.'"', false)
            ->assertSee(route('admin.media.download', $asset), false)
            ->assertSee('Missing alt text')
            ->assertSee('by '.$this->admin->name);
    }
}

<?php

namespace Tests\Feature;

use App\Models\FontAsset;
use App\Models\MediaAsset;
use App\Models\PortfolioSetting;
use App\Models\User;
use App\Services\AssetStorage;
use App\Services\AssetUsage;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $directory = storage_path('framework/testing/asset-fixtures');
        if (is_dir($directory) && count(scandir($directory)) === 2) {
            rmdir($directory);
        }
        parent::tearDown();
    }

    public function test_font_upload_formats_and_public_css_are_operational(): void
    {
        foreach (['ttf', 'otf', 'woff', 'woff2'] as $format) {
            $this->post('/admin/fonts', $this->fontData($this->fixture('family.'.$format, $this->fontBytes($format))))
                ->assertRedirect('/admin/fonts');
            $asset = FontAsset::latest('id')->firstOrFail();
            $this->assertTrue($asset->is_active);
            $this->assertTrue($asset->license_confirmed);
            $this->assertMatchesRegularExpression('~^portfolio-fonts/[a-f0-9]{48}\.'.$format.'$~', $asset->path);
            Storage::disk('public')->assertExists($asset->path);
            $this->get('/fonts/'.$asset->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString($asset->cssFamily(), app(FontLibrary::class)->css());
        }
        $this->assertCount(4, app(FontLibrary::class)->payload());
        $this->get('/admin/fonts')->assertOk()->assertSee('Upload a font');
    }

    public function test_font_signatures_limits_and_license_attestation_are_required(): void
    {
        $this->post('/admin/fonts', $this->fontData($this->fixture('bad.woff2', str_repeat('x', 60))))
            ->assertSessionHasErrors('file');
        $data = $this->fontData($this->fixture('license.ttf', $this->fontBytes('ttf')));
        $data['license_confirmed'] = '0';
        $this->post('/admin/fonts', $data)->assertSessionHasErrors('license_confirmed');
        $data['license_confirmed'] = '1';
        $data['source_url'] = 'javascript:alert(1)';
        $this->post('/admin/fonts', $data)->assertSessionHasErrors('source_url');
        $this->post('/admin/fonts', $this->fontData($this->fixture('huge.ttf', str_repeat('x', 2097153))))
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('font_assets', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_selected_font_cannot_be_deactivated_or_deleted(): void
    {
        $this->post('/admin/fonts', $this->fontData($this->fixture('selected.ttf', $this->fontBytes('ttf'))));
        $font = FontAsset::firstOrFail();
        PortfolioSetting::forceCreate(['id' => 1, 'font_body' => $font->cssFamily()]);
        $this->put('/admin/fonts/'.$font->id, ['is_active' => '0'])->assertSessionHasErrors('font');
        $this->delete('/admin/fonts/'.$font->id)->assertSessionHasErrors('font');
        Storage::disk('public')->assertExists($font->path);
        PortfolioSetting::query()->update(['font_body' => 'Inter']);
        $this->put('/admin/fonts/'.$font->id, ['is_active' => '0'])->assertRedirect('/admin/fonts');
        $this->get('/fonts/'.$font->id)->assertNotFound();
        $this->delete('/admin/fonts/'.$font->id)->assertRedirect('/admin/fonts');
        Storage::disk('public')->assertMissing($font->path);
    }

    public function test_images_are_reencoded_editable_replaceable_and_searchable(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Run this image success test with php -d extension=gd.');
        }
        $original = $this->png().'APPENDED_UNTRUSTED_PAYLOAD';
        $this->post('/admin/media', ['file' => $this->fixture('photo.png', $original), 'label' => 'Galaxy', 'alt_text' => 'Star field'])
            ->assertRedirect('/admin/media');
        $asset = MediaAsset::firstOrFail();
        $oldPath = $asset->path;
        $this->assertTrue($asset->reencoded);
        $this->assertSame(1, $asset->width);
        $this->assertStringNotContainsString('APPENDED_UNTRUSTED_PAYLOAD', Storage::disk('public')->get($oldPath));
        $this->get('/media/'.$asset->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->put('/admin/media/'.$asset->id, ['label' => 'Nebula', 'alt_text' => 'Cloud'])->assertRedirect('/admin/media');
        $this->get('/admin/media?q=Nebula')->assertOk()->assertSee('Nebula');
        $this->put('/admin/media/'.$asset->id, ['file' => $this->fixture('replacement.png', $this->png()), 'label' => 'Nebula'])
            ->assertRedirect('/admin/media');
        $asset->refresh();
        $this->assertNotSame($oldPath, $asset->path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertSame(route('media.show', $asset), app(MediaLibrary::class)->publicPayload($asset->id)['url']);
        $this->delete('/admin/media/'.$asset->id)->assertRedirect('/admin/media');
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_invalid_images_dimensions_and_size_are_rejected(): void
    {
        foreach ([
            ['bad.png', str_repeat('invalid', 30)],
            ['mismatch.jpg', $this->png()],
            ['huge.png', str_repeat('x', 5242881)],
            ['pixels.png', substr_replace($this->png(), pack('N', 9000), 16, 4)],
            ['pixel-count.png', substr_replace($this->png(), pack('N2', 5000, 5000), 16, 8)],
        ] as [$name, $bytes]) {
            $this->post('/admin/media', ['file' => $this->fixture($name, $bytes), 'label' => 'Invalid'])
                ->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_jpeg_and_webp_images_are_decoded_and_reencoded(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Run image success tests with php -d extension=gd.');
        }
        foreach (['jpg' => 'image/jpeg', 'webp' => 'image/webp'] as $extension => $mime) {
            $image = imagecreatefromstring($this->png());
            ob_start();
            if ($extension === 'jpg') {
                imagejpeg($image);
            } else {
                imagewebp($image);
            }
            $bytes = ob_get_clean();
            unset($image);
            $this->post('/admin/media', ['file' => $this->fixture('image.'.$extension, $bytes), 'label' => $extension])
                ->assertRedirect('/admin/media');
            $asset = MediaAsset::latest('id')->firstOrFail();
            $this->assertTrue($asset->reencoded);
            $this->assertSame($mime, $asset->mime_type);
            $this->get('/media/'.$asset->id)->assertOk()->assertHeader('Content-Type', $mime);
        }
    }

    public function test_missing_gd_fails_closed(): void
    {
        if (extension_loaded('gd')) {
            $this->markTestSkipped('This assertion is exercised with the default PHP runtime without GD.');
        }
        $this->post('/admin/media', ['file' => $this->fixture('requires-gd.png', $this->png()), 'label' => 'GD missing'])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_media_usage_in_settings_and_revisions_blocks_deletion(): void
    {
        $asset = $this->asset();
        PortfolioSetting::forceCreate(['id' => 1]);
        $settings = PortfolioSetting::findOrFail(1);
        DB::table('portfolio_settings')->where('id', $settings->id)->update(['og_image_media_id' => $asset->id]);
        $this->delete('/admin/media/'.$asset->id)->assertSessionHasErrors('media');
        Storage::disk('public')->assertExists($asset->path);
        $this->assertNotEmpty(app(AssetUsage::class)->media($asset));
        DB::table('portfolio_settings')->where('id', $settings->id)->update(['og_image_media_id' => null]);
        if (Schema::hasTable('content_revisions')) {
            $columns = Schema::getColumnListing('content_revisions');
            $row = ['scope' => 'site', 'subject_id' => $settings->id, 'label' => 'Asset usage fixture',
                'snapshot' => json_encode(['sections' => [['image_media_id' => $asset->id]]]),
                'created_at' => now(), 'updated_at' => now()];
            DB::table('content_revisions')->insert(array_intersect_key($row, array_flip($columns)));
            $this->delete('/admin/media/'.$asset->id)->assertSessionHasErrors('media');
        }
    }

    public function test_legacy_storage_url_references_block_replacement_without_breaking_the_file(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Run replacement success tests with php -d extension=gd.');
        }
        $asset = $this->asset();
        PortfolioSetting::forceCreate(['id' => 1, 'og_image' => '/storage/'.$asset->path]);
        $this->put('/admin/media/'.$asset->id, ['file' => $this->fixture('new.png', $this->png()), 'label' => 'Replacement'])
            ->assertSessionHasErrors('media');
        $this->assertSame($asset->path, $asset->fresh()->path);
        Storage::disk('public')->assertExists($asset->path);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_database_failure_restores_original_storage_and_cleans_new_file(): void
    {
        $asset = $this->asset();
        $replacement = app(AssetStorage::class)->put('portfolio-media', 'png', $this->png());
        try {
            app(AssetStorage::class)->remove('public', $asset->path, function () use ($asset) {
                $asset->delete();
                throw new RuntimeException('Simulated transaction failure');
            }, $replacement);
            $this->fail('The simulated failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated transaction failure', $exception->getMessage());
        }
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($replacement);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_failed_database_insert_removes_the_uploaded_file(): void
    {
        $path = app(AssetStorage::class)->put('portfolio-fonts', 'ttf', $this->fontBytes('ttf'));
        try {
            app(AssetStorage::class)->create($path, function () use ($path) {
                FontAsset::create(['family' => 'Rollback', 'format' => 'ttf', 'path' => $path]);
                throw new RuntimeException('Simulated create failure');
            });
            $this->fail('The simulated failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated create failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('font_assets', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_admin_cannot_upload_assets(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->post('/admin/fonts', [])->assertForbidden();
        $this->post('/admin/media', [])->assertForbidden();
    }

    public function test_missing_storage_assets_are_not_valid_selections(): void
    {
        $asset = $this->asset();
        Storage::disk('public')->delete($asset->path);
        $validator = Validator::make(['image' => $asset->id], ['image' => app(MediaLibrary::class)->selectionRules()]);
        $this->assertTrue($validator->fails());
        $font = FontAsset::create(['family' => 'Missing font', 'format' => 'ttf', 'path' => 'missing.ttf',
            'disk' => 'public', 'is_active' => true, 'license_confirmed' => true]);
        $this->assertFalse(app(FontLibrary::class)->activeOptions()->contains('id', $font->id));
        $this->expectException(ValidationException::class);
        app(FontLibrary::class)->validateSelection($font->cssFamily(), 'font_body');
    }

    private function asset(): MediaAsset
    {
        $path = app(AssetStorage::class)->put('portfolio-media', 'png', $this->png());

        return MediaAsset::create(['label' => 'Used image', 'original_name' => 'original.png',
            'disk' => 'public', 'path' => $path, 'mime_type' => 'image/png', 'size_bytes' => strlen($this->png()),
            'width' => 1, 'height' => 1]);
    }

    private function fixture(string $name, string $bytes): UploadedFile
    {
        $directory = storage_path('framework/testing/asset-fixtures');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(12));
        file_put_contents($path, $bytes);
        $this->fixtures[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function fontData(UploadedFile $file): array
    {
        return ['file' => $file, 'family' => 'Test Family', 'weight' => '400', 'style' => 'normal',
            'license_note' => 'Open Font License', 'source_url' => 'https://example.test/font', 'license_confirmed' => '1'];
    }

    private function fontBytes(string $format): string
    {
        if (in_array($format, ['ttf', 'otf'], true)) {
            return ($format === 'ttf' ? "\0\1\0\0" : 'OTTO').pack('n4', 1, 16, 0, 0)
                .'head'.pack('N3', 0, 28, 4).'DATA';
        }
        if ($format === 'woff') {
            return 'wOFF'."\0\1\0\0".pack('NnnNnnNNNNN', 68, 1, 0, 32, 1, 0, 0, 0, 0, 0, 0)
                .'head'.pack('N4', 64, 4, 4, 0).'DATA';
        }

        return 'wOF2'."\0\1\0\0".pack('NnnNNnnNNNNN', 52, 1, 0, 32, 4, 1, 0, 0, 0, 0, 0, 0).'DATA';
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}

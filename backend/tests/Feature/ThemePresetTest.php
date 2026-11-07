<?php

namespace Tests\Feature;

use App\Models\PortfolioSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_palette_does_not_publish_it(): void
    {
        $this->seed();
        $settings = PortfolioSetting::findOrFail(1);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/themes', [
            'name' => 'Readable light',
            'color_background' => '#ffffff',
            'color_foreground' => '#111111',
            'color_primary' => '#552266',
            'color_accent' => '#223344',
            'color_cyan' => '#114455',
        ])->assertRedirect('/admin/site-editor');
        $this->assertDatabaseHas('theme_presets', ['name' => 'Readable light', 'color_background' => '#ffffff']);
        $this->assertSame($settings->color_background, $settings->fresh()->color_background);
    }

    public function test_palette_rejects_css_injection_and_requires_admin(): void
    {
        $this->seed();
        $this->post('/admin/themes', [])->assertRedirect('/admin/login');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/themes', [
            'name' => 'Unsafe',
            'color_background' => 'url(javascript:alert(1))',
        ])->assertSessionHasErrors('color_background');
        $this->assertDatabaseMissing('theme_presets', ['name' => 'Unsafe']);
    }
}

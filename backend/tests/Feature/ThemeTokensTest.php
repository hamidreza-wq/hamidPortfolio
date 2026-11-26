<?php

namespace Tests\Feature;

use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Services\PortfolioRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_pages_carry_the_theme_colours_on_the_html_tag(): void
    {
        PortfolioSetting::findOrFail(1)->forceFill([
            'color_background' => '#0a0b14', 'color_foreground' => '#f0f0f5', 'color_primary' => '#8877ff',
            'color_accent' => '#aa99ff', 'color_cyan' => '#77ccee', 'border_radius' => 99,
        ])->save();
        $project = Project::query()->where('is_published', true)->firstOrFail();

        foreach (['/', '/projects/'.$project->slug, '/privacy'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match('/<html\b[^>]*>/', $html, $tag);
            $this->assertStringContainsString('--night:#0a0b14;--ink:#f0f0f5;--violet:#8877ff;--accent:#aa99ff;--cyan:#77ccee', $tag[0], $url);
            $this->assertStringContainsString('--card-radius:32px', $tag[0], $url);
            $this->assertStringNotContainsString('data-scheme', $tag[0], $url);
        }
    }

    public function test_a_light_background_switches_the_stylesheet_to_its_light_scheme(): void
    {
        PortfolioSetting::findOrFail(1)->forceFill(['color_background' => '#f7f5fc', 'color_foreground' => '#1b1830'])->save();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html\b[^>]*data-scheme="light"/', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#f7f5fc">', $html);
    }

    public function test_colour_scheme_follows_background_luminance(): void
    {
        $this->assertSame('dark', PortfolioRenderer::colorScheme('#070811'));
        $this->assertSame('dark', PortfolioRenderer::colorScheme('#777777'));
        $this->assertSame('light', PortfolioRenderer::colorScheme('#faf7f1'));
        $this->assertSame('light', PortfolioRenderer::colorScheme('#BBBBBB'));
        $this->assertSame('dark', PortfolioRenderer::colorScheme('not-a-colour'));
    }
}

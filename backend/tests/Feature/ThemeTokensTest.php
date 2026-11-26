<?php

namespace Tests\Feature;

use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Services\PortfolioRenderer;
use App\Services\Themes;
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

    /** Mirrors the token maths in styles.css (:root and :root[data-scheme="light"]). */
    public function test_every_built_in_theme_meets_wcag_aa_for_text_and_buttons(): void
    {
        $hex = fn (string $value) => array_map('hexdec', str_split(substr($value, 1), 2));
        $mix = fn (array $a, array $b, float $share) => array_map(fn ($x, $y) => $x * $share + $y * (1 - $share), $a, $b);
        $luminance = function (array $rgb) {
            $c = array_map(fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $contrast = function (array $a, array $b) use ($luminance) {
            [$high, $low] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

            return ($high + 0.05) / ($low + 0.05);
        };

        foreach (app(Themes::class)->builtIn() as $key => $theme) {
            $v = $theme['values'];
            [$night, $ink, $violet, $accent, $cyan] = array_map($hex, [$v['color_background'], $v['color_foreground'], $v['color_primary'], $v['color_accent'], $v['color_cyan']]);
            $light = PortfolioRenderer::colorScheme($v['color_background']) === 'light';
            $tone = $mix($night, $violet, 0.75);
            $muted = $mix($ink, $tone, $light ? 0.76 : 0.52);
            $card = $light ? $mix($night, [255, 255, 255], 0.25) : $mix($night, $violet, 0.90);
            $checks = [
                'body text' => [$ink, $night],
                'body text on cards' => [$ink, $card],
                'muted paragraphs' => [$muted, $night],
                'muted text on cards' => [$muted, $card],
                'primary-coloured text' => [$violet, $night],
                'accent links' => [$accent, $night],
                'primary button over accent' => [$night, $accent],
                'primary button over its middle' => [$night, $mix($accent, $ink, 0.40)],
                'primary button over secondary accent' => [$night, $cyan],
                'quiet button' => [$mix($ink, $tone, $light ? 0.92 : 0.88), $night],
            ];
            foreach ($checks as $label => [$foreground, $background]) {
                $this->assertGreaterThanOrEqual(4.5, round($contrast($foreground, $background), 2), "{$key}: {$label}");
            }
        }
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

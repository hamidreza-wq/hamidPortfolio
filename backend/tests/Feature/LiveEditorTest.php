<?php

namespace Tests\Feature;

use App\Models\PortfolioSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LiveEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_editor_has_live_editing_controls(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/site-editor')->assertOk()->getContent();

        foreach (['data-device="desktop"', 'data-device="tablet"', 'data-device="phone"', 'id="click-to-edit"', 'id="draft-undo"', 'id="draft-redo"', 'id="draft-status"'] as $control) {
            $this->assertStringContainsString($control, $html);
        }
        foreach (PortfolioSection::pluck('key') as $key) {
            if (str_contains($html, 'data-section-key="'.$key.'"')) {
                $this->assertMatchesRegularExpression('~data-section-key="'.preg_quote($key, '~').'">\s*<div class="section-editor-head">\s*<div[^>]*><span class="drag-handle" draggable="true"~', $html);
            }
        }
        $this->assertStringContainsString("type: 'portfolio:edit-mode'", $html);
    }

    public function test_preview_script_reports_clicks_only_in_preview_mode(): void
    {
        $script = file_get_contents(base_path('../app.mjs'));
        $guard = strpos($script, 'if (window.portfolioPreview === true) {', strpos($script, 'function showProject'));
        $this->assertNotFalse($guard);
        $this->assertGreaterThan($guard, strpos($script, '"portfolio:edit"'), 'click-to-edit only runs inside the preview guard');
        $this->assertStringContainsString('event.source !== window.parent || event.origin !== window.location.origin', substr($script, $guard));
    }

    public function test_editor_and_preview_scripts_parse(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if (! $node) {
            $this->markTestSkipped('Node.js is not installed.');
        }
        $html = $this->actingAs($this->admin)->get('/admin/site-editor')->getContent();
        preg_match_all('~<script(?: nonce="[^"]*")?>(.*?)</script>~s', $html, $scripts);
        $file = tempnam(sys_get_temp_dir(), 'editor-js-').'.js';
        try {
            foreach ($scripts[1] as $index => $code) {
                file_put_contents($file, $code);
                $process = new Process([$node, '--check', $file]);
                $process->run();
                $this->assertTrue($process->isSuccessful(), "Inline script #{$index} has a syntax error: ".$process->getErrorOutput());
            }
            $process = new Process([$node, '--check', base_path('../app.mjs')]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        } finally {
            @unlink($file);
        }
    }
}

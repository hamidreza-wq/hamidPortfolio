<?php

namespace App\Services;

use App\Models\PortfolioSection;
use DOMDocument;
use DOMElement;
use DOMXPath;

class PortfolioRenderer
{
    public function render(string $html, array $payload): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
            foreach (iterator_to_array($document->childNodes) as $node) {
                if ($node->nodeType === XML_PI_NODE) {
                    $document->removeChild($node);
                }
            }
            $xpath = new DOMXPath($document);
            $values = [
                'profile.name' => $payload['profile']['name'],
                'profile.title' => $payload['profile']['title'],
                'profile.footer' => $payload['settings']['footer_text'] ?? '',
            ];
            foreach ($payload['sections'] as $section) {
                foreach (['title', 'subtitle', 'body'] as $field) {
                    $values['section.'.$section['key'].'.'.$field] = $section[$field] ?? '';
                }
                if ($section['key'] === 'home') {
                    $values['section.home.body'] = preg_replace('/^I[\'’]m [^—\\r\\n]{1,120}\\s*—\\s*/u', '', $section['body'] ?? '');
                }
            }
            foreach ($xpath->query('//*[@data-managed]') as $node) {
                $key = $node->getAttribute('data-managed');
                if (isset($values[$key])) {
                    $copy = $node->cloneNode(true);
                    foreach (iterator_to_array($copy->getElementsByTagName('br')) as $break) {
                        $break->parentNode->replaceChild($document->createTextNode(' '), $break);
                    }
                    if (preg_replace('/\\s+/u', ' ', trim($copy->textContent)) === preg_replace('/\\s+/u', ' ', trim($values[$key]))) {
                        continue;
                    }
                    $node->textContent = $values[$key];
                    if ($key === 'section.home.title' && preg_match('/^(.*\\s)(\\S+)$/us', $values[$key], $parts)) {
                        $node->textContent = $parts[1];
                        $accent = $document->createElement('span');
                        $accent->setAttribute('class', 'gradient-text');
                        $accent->textContent = $parts[2];
                        $node->appendChild($accent);
                    }
                }
            }
            foreach ($xpath->query('//*[@data-managed-href="profile.github"]') as $node) {
                $node->setAttribute('href', $payload['profile']['github']);
            }
            $repositories = $this->githubRepositoriesUrl((string) $payload['profile']['github']);
            if ($repositories !== null) {
                foreach ($xpath->query('//*[@data-managed-href="profile.github.repositories"]') as $node) {
                    $node->setAttribute('href', $repositories);
                }
            }
            $name = trim((string) $payload['profile']['name']);
            if ($name !== '') {
                foreach ($xpath->query('//*[@data-managed-initial="profile.name"]') as $node) {
                    $node->textContent = mb_strtoupper(mb_substr($name, 0, 1));
                }
                foreach ($xpath->query('//*[@data-managed-label="profile.name"]') as $node) {
                    $node->setAttribute('aria-label', $name.', home');
                }
            }
            $grid = $document->getElementById('project-grid');
            if ($grid) {
                $this->replaceHtml($document, $grid, view('portfolio.project-links', ['projects' => $payload['projects']])->render());
            }
            $main = $document->getElementById('main');
            $nav = $document->getElementById('primary-navigation');
            if ($main && $nav) {
                $this->replaceHtml($document, $nav, '');
                $this->appendHtml($document, $main, view('portfolio.sections', ['sections' => $payload['sections']])->render());
                $strip = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," intro-strip ")]')->item(0);
                foreach ($payload['sections'] as $section) {
                    $element = $document->getElementById($section['key']);
                    if (! $element) {
                        continue;
                    }
                    if (! $section['is_visible']) {
                        $element->setAttribute('hidden', '');
                    }
                    $spacingScale = $payload['theme']['section_spacing'] / 140;
                    $style = 'padding-top:'.round($section['spacing_top'] * $spacingScale).'px;padding-bottom:'.round($section['spacing_bottom'] * $spacingScale).'px;';
                    if ($section['background']) {
                        $style .= 'background-image:url("'.e($section['background']['url']).'");background-size:cover;background-position:center;';
                    }
                    $element->setAttribute('style', $style);
                    $element->setAttribute('data-background-effect', $section['background_effect']);
                    if ($section['image'] && in_array($section['key'], PortfolioSection::BUILT_IN_KEYS, true)) {
                        $image = $document->createElement('img');
                        $image->setAttribute('class', 'section-image');
                        $image->setAttribute('data-section-image', '');
                        $image->setAttribute('src', $section['image']['url']);
                        $image->setAttribute('alt', $section['image']['alt']);
                        $image->setAttribute('loading', 'lazy');
                        $element->appendChild($image);
                    }
                    $main->appendChild($element);
                    if ($section['key'] === 'home' && $strip) {
                        if (! $section['is_visible']) {
                            $strip->setAttribute('hidden', '');
                        }
                        $main->appendChild($strip);
                    }
                    if ($section['is_visible'] && $section['show_in_navigation']) {
                        $link = $document->createElement('a');
                        $link->setAttribute('href', '#'.$section['key']);
                        $link->textContent = $section['label'];
                        $nav->appendChild($link);
                    }
                }
            }
            $craft = $document->getElementById('craft-groups');
            if ($craft) {
                $this->replaceHtml($document, $craft, view('portfolio.skills', ['craft' => $payload['craft']])->render());
            }

            return $document->saveHTML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function githubRepositoriesUrl(string $profileUrl): ?string
    {
        $parts = parse_url($profileUrl);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'github.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        return 'https://github.com'.($parts['path'] ?? '/').'?tab=repositories';
    }

    private function replaceHtml(DOMDocument $document, DOMElement $element, string $html): void
    {
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        $this->appendHtml($document, $element, $html);
    }

    private function appendHtml(DOMDocument $document, DOMElement $element, string $html): void
    {
        if ($html === '') {
            return;
        }
        $fragment = new DOMDocument('1.0', 'UTF-8');
        $fragment->loadHTML('<?xml encoding="UTF-8"><div id="fragment-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        foreach (iterator_to_array($fragment->getElementById('fragment-root')->childNodes) as $node) {
            $element->appendChild($document->importNode($node, true));
        }
    }
}

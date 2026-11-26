<?php

namespace App\Services\SelfTest;

use App\Models\Page;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\User;
use App\Services\AdminSearch;
use App\Services\AssetUpload;
use App\Services\Health\HealthMonitor;
use App\Services\Platform\GithubApi;
use App\Services\Platform\Notices;
use App\Services\Platform\PublicDns;
use App\Support\Roles;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * An on-demand "does everything still work" run. It crawls the public site
 * (only the server-configured origin, at a pinned public IP, never following
 * redirects), checks what visitors and administrators rely on, and records a
 * report. It never changes content: test uploads are processed in memory and
 * database writes are rolled back.
 *
 * Each result: area, check, location, status (pass|warn|fail|skip),
 * severity (critical|high|medium|low), message, fix.
 */
class SelfTest
{
    public const REPORT_KEY = 'self-test:last-report';

    private const MAX_PAGES = 60;

    private array $results = [];

    private array $fetched = [];

    public function __construct(private HealthMonitor $health, private Notices $notices) {}

    public function lastReport(): ?array
    {
        return Cache::get(self::REPORT_KEY);
    }

    public function run(?int $userId = null): array
    {
        $started = microtime(true);
        $this->results = [];
        $this->fetched = [];

        $this->server();
        $this->database();
        $this->processing();
        $this->searchAndPermissions();
        $this->mail();
        $this->publicSite();
        $this->browserOnly();

        $counts = collect($this->results)->countBy('status')->all();
        $report = [
            'ran_at' => now()->utc()->toIso8601String(),
            'seconds' => round(microtime(true) - $started, 1),
            'user_id' => $userId,
            'counts' => $counts + ['pass' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0],
            'results' => $this->results,
        ];
        Cache::forever(self::REPORT_KEY, $report);

        $failures = collect($this->results)->where('status', 'fail');
        $failures->isEmpty()
            ? $this->notices->resolve('self-test:failures')
            : $this->notices->put('self-test:failures', 'health', $failures->contains('severity', 'critical') ? 'high' : 'medium',
                'Self-test found problems', $failures->count().' check(s) failed. The first: '.$failures->first()['check'].' — '.$failures->first()['message'], 'admin.self-test');

        return $report;
    }

    private function add(string $area, string $check, string $status, string $message, string $severity = 'low', ?string $location = null, ?string $fix = null): void
    {
        $this->results[] = compact('area', 'check', 'location', 'status', 'severity', 'message', 'fix');
    }

    // ---- Server and data ---------------------------------------------------

    private function server(): void
    {
        $map = ['ok' => 'pass', 'warning' => 'warn', 'critical' => 'fail', 'unknown' => 'warn'];
        foreach ($this->health->local() as $checks) {
            foreach ($checks as $check) {
                $this->add('Server', $check['label'], $map[$check['state']] ?? 'warn', $check['detail'],
                    $check['state'] === 'critical' ? 'high' : 'medium', null,
                    $check['state'] === 'ok' ? null : 'See the Health page for this check.');
            }
        }
    }

    private function database(): void
    {
        try {
            DB::beginTransaction();
            $id = DB::table('audit_logs')->insertGetId(['action' => 'self-test.probe', 'created_at' => now(), 'updated_at' => now()]);
            $read = DB::table('audit_logs')->where('id', $id)->value('action');
            DB::rollBack();
            $read === 'self-test.probe'
                ? $this->add('Database', 'Write and read', 'pass', 'A test row was written, read back and rolled back.')
                : $this->add('Database', 'Write and read', 'fail', 'A test row could not be read back.', 'critical', null, 'Check the database user has insert and select rights.');
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->add('Database', 'Write and read', 'fail', 'The database refused a test write.', 'critical', null, 'Check the database credentials and permissions in the server .env.');
        }
    }

    private function processing(): void
    {
        // Image uploads: a tiny generated PNG goes through the real upload checks and re-encoding, in memory.
        if (! function_exists('imagecreatetruecolor')) {
            $this->add('Uploads', 'Image processing', 'fail', 'PHP GD is not installed, so image uploads are refused.', 'high', '/admin/media', 'Enable the PHP GD extension in the hosting panel.');
        } else {
            $image = imagecreatetruecolor(8, 8);
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            $this->withTempUpload($png, 'self-test.png', 'image/png', function (UploadedFile $file) {
                $result = app(AssetUpload::class)->image($file);
                $this->add('Uploads', 'Image processing', $result['reencoded'] ? 'pass' : 'warn',
                    $result['reencoded'] ? 'A test image was checked and re-encoded (not saved).' : 'Images are checked but not re-encoded.', 'medium', '/admin/media');
            }, fn ($message) => $this->add('Uploads', 'Image processing', 'fail', 'A valid test image was rejected: '.$message, 'high', '/admin/media', 'Check the PHP GD extension supports PNG, JPEG and WebP.'));
        }

        $ttf = "\x00\x01\x00\x00".pack('nnnn', 1, 16, 0, 0).'head'.pack('NNN', 0, 28, 4).'data';
        $this->withTempUpload($ttf, 'self-test.ttf', 'font/ttf', function (UploadedFile $file) {
            app(AssetUpload::class)->font($file);
            $this->add('Uploads', 'Font checks', 'pass', 'A test font passed the upload checks (not saved).', 'low', '/admin/fonts');
        }, fn ($message) => $this->add('Uploads', 'Font checks', 'fail', 'A valid test font was rejected: '.$message, 'medium', '/admin/fonts'));

        try {
            $bad = false;
            $this->withTempUpload('<?php echo 1;', 'evil.png', 'image/png', fn ($file) => app(AssetUpload::class)->image($file), function () use (&$bad) {
                $bad = true;
            });
            $bad
                ? $this->add('Uploads', 'Rejects disguised files', 'pass', 'A PHP file named .png was refused.', 'high', '/admin/media')
                : $this->add('Uploads', 'Rejects disguised files', 'fail', 'A PHP file named .png was accepted as an image.', 'critical', '/admin/media', 'Do not deploy; the image checks are not working.');
        } catch (Throwable) {
            $this->add('Uploads', 'Rejects disguised files', 'pass', 'A PHP file named .png was refused.', 'high', '/admin/media');
        }
    }

    private function withTempUpload(string $bytes, string $name, string $mime, callable $accept, callable $reject): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'selftest-');
        file_put_contents($temp, $bytes);
        try {
            $accept(new UploadedFile($temp, $name, $mime, null, true));
        } catch (ValidationException $exception) {
            $reject(collect($exception->errors())->flatten()->first());
        } finally {
            @unlink($temp);
        }
    }

    private function searchAndPermissions(): void
    {
        try {
            app(AdminSearch::class)->search('a', 1);
            $this->add('Dashboard', 'Search', 'pass', 'Dashboard search runs.', 'low', '/admin/search');
        } catch (Throwable $exception) {
            $this->add('Dashboard', 'Search', 'fail', 'Dashboard search failed: '.class_basename($exception), 'medium', '/admin/search', 'Check the server error log for the full message.');
        }

        $unmapped = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('admin.permission', $route->gatherMiddleware(), true))
            ->map->getName()
            ->filter(fn ($name) => Roles::permissionForRoute($name) === 'owner-only' && ! str_starts_with((string) $name, 'admin.users.'))
            ->values();
        $unmapped->isEmpty()
            ? $this->add('Dashboard', 'Permissions', 'pass', 'Every admin page has a role permission.', 'medium', '/admin/users')
            : $this->add('Dashboard', 'Permissions', 'warn', 'Only owners can open: '.$unmapped->implode(', ').'.', 'medium', '/admin/users', 'Add these routes to App\\Support\\Roles.');

        $owners = User::where('is_admin', true)->get()->filter(fn (User $user) => $user->adminRole() === 'owner');
        $withoutTwoFactor = User::where('is_admin', true)->get()->reject->hasTwoFactorEnabled();
        $this->add('Dashboard', 'Owner account', $owners->isNotEmpty() ? 'pass' : 'fail',
            $owners->isNotEmpty() ? $owners->count().' owner(s).' : 'No owner account exists.', 'critical', '/admin/users',
            $owners->isNotEmpty() ? null : 'Run php artisan portfolio:admin:create on the server.');
        $withoutTwoFactor->isEmpty()
            ? $this->add('Dashboard', 'Two-factor sign-in', 'pass', 'Every administrator has two-factor sign-in on.', 'high', '/admin/security')
            : $this->add('Dashboard', 'Two-factor sign-in', 'warn', $withoutTwoFactor->count().' administrator(s) sign in with a password only: '.$withoutTwoFactor->pluck('name')->implode(', ').'.', 'high', '/admin/security', 'Each person turns it on from the Security page.');
    }

    private function mail(): void
    {
        $mailer = config('mail.default');
        in_array($mailer, ['log', 'array'], true)
            ? $this->add('Email', 'Mail transport', 'warn', "Mail uses the \"{$mailer}\" transport, so contact notifications and alerts never reach an inbox.", 'medium', null, 'Set MAIL_MAILER=smtp and the SMTP settings in the server .env.')
            : $this->add('Email', 'Mail transport', 'pass', "Mail is sent through \"{$mailer}\". (Delivery itself isn't tested, to avoid sending email.)", 'medium');
    }

    // ---- Public site crawl --------------------------------------------------

    private function publicSite(): void
    {
        $origin = rtrim((string) config('platform.seo_origin'), '/');
        $parts = parse_url($origin);
        if (! $origin || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || ! empty($parts['path'])) {
            $this->add('Public site', 'Crawl', 'skip', 'The public site was not checked.', 'medium', null, 'Set SEO_MONITOR_ORIGIN on the server, for example https://hamidbayat.co.uk.');

            return;
        }
        try {
            $ip = app(PublicDns::class)->ipv4($parts['host']);
        } catch (Throwable $exception) {
            $this->add('Public site', 'Crawl', 'fail', $exception->getMessage(), 'high');

            return;
        }
        $fetch = fn (string $path) => $this->fetch($origin, $parts['host'], $ip, $path);

        $home = $fetch('/');
        if ($home['status'] !== 200) {
            $this->add('Public site', 'Home page', 'fail', "The home page returned HTTP {$home['status']}.", 'critical', '/', 'Check the Health page and the server error log.');

            return;
        }
        $this->add('Public site', 'Home page', 'pass', "Loaded in {$home['ms']} ms.", 'critical', '/');
        $homeDom = $this->dom($home['body']);
        $this->headChecks($homeDom, '/');
        $this->formChecks($homeDom);

        // Links and images on the home page.
        $ids = [];
        foreach ((new DOMXPath($homeDom))->query('//*[@id]') as $node) {
            $ids[$node->getAttribute('id')] = true;
        }
        $links = $this->links($homeDom, $origin);
        foreach ($links['anchors'] as $anchor) {
            if (! isset($ids[$anchor])) {
                $this->add('Links', "#{$anchor}", 'fail', 'A link on the home page points to a section that isn\'t on the page.', 'medium', '/', 'Fix the menu item or show the section again (Menus, or the live editor).');
            }
        }

        // Everything the site should be serving: sitemap URLs, published projects and pages, and links found.
        $sitemap = $fetch('/sitemap.xml');
        $listed = [];
        if ($sitemap['status'] === 200 && preg_match_all('~<loc>([^<]+)</loc>~', $sitemap['body'], $matches)) {
            foreach ($matches[1] as $loc) {
                $path = parse_url(html_entity_decode($loc), PHP_URL_PATH) ?: '/';
                $listed[$path] = true;
            }
            $this->add('SEO', 'Sitemap', 'pass', count($listed).' address(es) listed.', 'medium', '/sitemap.xml');
        } else {
            $this->add('SEO', 'Sitemap', 'fail', 'The sitemap could not be read (HTTP '.$sitemap['status'].').', 'medium', '/sitemap.xml', 'Check the sitemap route and the server error log.');
        }
        $robots = $fetch('/robots.txt');
        $robots['status'] === 200 && str_contains($robots['body'], '/sitemap.xml')
            ? $this->add('SEO', 'robots.txt', 'pass', 'robots.txt loads and points to the sitemap.', 'low', '/robots.txt')
            : $this->add('SEO', 'robots.txt', 'warn', 'robots.txt is missing or doesn\'t mention the sitemap.', 'low', '/robots.txt');

        $expected = [];
        foreach (Project::where('is_published', true)->get() as $project) {
            $expected['/projects/'.$project->slug] = $project->name;
        }
        foreach (Page::live()->get() as $page) {
            $expected['/'.$page->slug] = $page->title;
        }
        $indexing = (bool) PortfolioSetting::find(1)?->indexing_enabled;
        foreach ($expected as $path => $name) {
            if ($indexing && $listed && ! isset($listed[$path]) && ! Page::where('slug', ltrim($path, '/'))->where('noindex', true)->exists()) {
                $this->add('SEO', 'Missing from sitemap', 'warn', "\"{$name}\" is published but not in the sitemap.", 'low', $path);
            }
        }

        $paths = collect(array_keys($expected))->merge(array_keys($links['paths']))->merge(['/privacy', '/up'])->unique()
            ->reject(fn ($path) => str_starts_with($path, '/admin') || $path === '/preview')->values()->take(self::MAX_PAGES);
        $broken = 0;
        foreach ($paths as $path) {
            $result = $fetch($path);
            if ($result['status'] !== 200) {
                $broken++;
                $this->add('Links', 'Broken page', 'fail', "Returned HTTP {$result['status']}.", isset($expected[$path]) ? 'high' : 'medium', $path,
                    isset($expected[$path]) ? 'It is published but does not load. Check its address and the error log.' : 'Remove or fix the link that points here.');
            } elseif (isset($expected[$path])) {
                $this->headChecks($this->dom($result['body']), $path);
            }
        }
        foreach (array_keys($links['images']) as $src) {
            $result = $fetch($src);
            if ($result['status'] !== 200) {
                $broken++;
                $this->add('Links', 'Broken image', 'fail', "Returned HTTP {$result['status']}.", 'medium', $src, 'Choose another image, or upload it again in the media library.');
            }
        }
        if ($broken === 0) {
            $this->add('Links', 'Pages, links and images', 'pass', count($paths).' page(s) and '.count($links['images']).' image(s) load.', 'high');
        }
        if (count($expected) + count($links['paths']) > self::MAX_PAGES) {
            $this->add('Links', 'Crawl limit', 'warn', 'Only the first '.self::MAX_PAGES.' addresses were checked.', 'low');
        }

        foreach (['/styles.css' => 'text/css', '/app.mjs' => 'javascript'] as $asset => $type) {
            $result = $fetch($asset);
            $result['status'] === 200 && str_contains(strtolower($result['type']), $type)
                ? $this->add('Front end', "{$asset} is served", 'pass', 'Loads with the right type.', 'high', $asset)
                : $this->add('Front end', "{$asset} is served", 'fail', "Returned HTTP {$result['status']} ({$result['type']}).", 'critical', $asset, 'The site will look broken or stop working. Check the deployment.');
        }
    }

    private function headChecks(DOMDocument $dom, string $path): void
    {
        $xpath = new DOMXPath($dom);
        $missing = [];
        if (trim((string) $xpath->query('//title')->item(0)?->textContent) === '') {
            $missing[] = 'title';
        }
        if (! $xpath->query('//meta[@name="description"][normalize-space(@content)]')->length) {
            $missing[] = 'description';
        }
        if (! $xpath->query('//link[@rel="canonical"]')->length) {
            $missing[] = 'canonical link';
        }
        if (! $xpath->query('//meta[@name="viewport"]')->length) {
            $missing[] = 'mobile viewport';
        }
        $missing
            ? $this->add('SEO', 'Page basics', 'warn', 'Missing: '.implode(', ', $missing).'.', 'medium', $path, 'Fill in the title and description for this page.')
            : null;
        $images = $xpath->query('//img[not(@alt)]');
        if ($images->length) {
            $this->add('Accessibility', 'Image alt text', 'warn', $images->length.' image(s) without alt text.', 'medium', $path, 'Describe each image in the media library.');
        }
    }

    private function formChecks(DOMDocument $dom): void
    {
        // The contact form is drawn by JavaScript from the page data; check the data it needs is there.
        $html = $dom->saveHTML();
        str_contains($html, 'contact_action_url') && str_contains($html, 'csrf_token') && str_contains($html, 'contact_submission_token')
            ? $this->add('Forms', 'Contact form', 'pass', 'The form has its address, CSRF token and anti-spam token.', 'high', '/#contact')
            : $this->add('Forms', 'Contact form', 'fail', 'The contact form is missing its address or security tokens.', 'high', '/#contact', 'Check the page data in PublicPortfolioController.');
        $settings = PortfolioSetting::find(1);
        if ($settings?->testimonial_submissions_open) {
            $form = $this->fetch(rtrim((string) config('platform.seo_origin'), '/'), parse_url((string) config('platform.seo_origin'), PHP_URL_HOST), app(PublicDns::class)->ipv4(parse_url((string) config('platform.seo_origin'), PHP_URL_HOST)), '/testimonials/new');
            $form['status'] === 200 && str_contains($form['body'], 'name="submission_token"') && str_contains($form['body'], 'name="_token"')
                ? $this->add('Forms', 'Testimonial form', 'pass', 'The open form loads with its security tokens.', 'medium', '/testimonials/new')
                : $this->add('Forms', 'Testimonial form', 'fail', 'The testimonial form is open but doesn\'t load correctly.', 'medium', '/testimonials/new');
        }
    }

    /** @return array{paths: array<string, true>, anchors: list<string>, images: array<string, true>} */
    private function links(DOMDocument $dom, string $origin): array
    {
        $paths = $images = [];
        $anchors = [];
        $host = parse_url($origin, PHP_URL_HOST);
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if (preg_match('~^#([A-Za-z][\w-]*)$~', $href, $match)) {
                $anchors[] = $match[1];
            } elseif (($path = $this->sameSitePath($href, $host)) !== null) {
                $paths[$path] = true;
            }
        }
        foreach ($dom->getElementsByTagName('img') as $img) {
            if (($path = $this->sameSitePath($img->getAttribute('src'), $host)) !== null) {
                $images[$path] = true;
            }
        }

        return ['paths' => $paths, 'anchors' => array_values(array_unique($anchors)), 'images' => $images];
    }

    private function sameSitePath(string $url, ?string $host): ?string
    {
        if ($url === '' || str_starts_with($url, '//') || preg_match('~^(mailto|tel|javascript|data):~i', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (isset($parts['host']) && strcasecmp($parts['host'], (string) $host) !== 0) {
            return null;
        }
        $path = $parts['path'] ?? '';

        return str_starts_with($path, '/') && preg_match('~^/[\w\-./]*$~', $path) && ! str_contains($path, '..') ? $path : null;
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /** @return array{status: int, body: string, type: string, ms: int} */
    private function fetch(string $origin, string $host, string $ip, string $path): array
    {
        if (isset($this->fetched[$path])) {
            return $this->fetched[$path];
        }
        $start = microtime(true);
        try {
            $response = app(GithubApi::class)->request($origin.$path, 3_000_000, extraOptions: [
                'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]], 'proxy' => '',
            ]);
            $result = ['status' => $response->status(), 'body' => $response->body(), 'type' => (string) $response->header('Content-Type')];
        } catch (Throwable) {
            $result = ['status' => 0, 'body' => '', 'type' => ''];
        }

        return $this->fetched[$path] = $result + ['ms' => (int) round((microtime(true) - $start) * 1000)];
    }

    private function browserOnly(): void
    {
        $this->add('Front end', 'JavaScript errors and phone layouts', 'skip',
            'These need a real browser, so the server can\'t test them.', 'medium', null,
            'Run the Playwright browser tests (npm run test:browser in backend/) before a release, or open the site on a phone.');
    }
}

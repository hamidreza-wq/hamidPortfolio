<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Long-test-password-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Each user gets a fresh session, as a real sign-in would (auth.session checks the password hash). */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function person(string $role): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => $role, 'password' => self::PASSWORD]);
    }

    public function test_every_admin_route_is_mapped_to_a_permission_or_open(): void
    {
        $unmapped = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('admin.permission', $route->gatherMiddleware(), true))
            ->map->getName()
            ->filter(fn ($name) => Roles::permissionForRoute($name) === 'owner-only' && ! str_starts_with((string) $name, 'admin.users.'))
            ->values()->all();

        $this->assertSame([], $unmapped, 'Map these routes in App\Support\Roles::ROUTES or OPEN.');
    }

    public function test_existing_admins_without_a_role_are_owners(): void
    {
        $legacy = User::factory()->create(['is_admin' => true]);
        $this->assertSame('owner', $legacy->adminRole());
        $this->as($legacy)->get('/admin/users')->assertOk();
    }

    public function test_each_role_reaches_only_its_pages(): void
    {
        $matrix = [
            'editor' => ['/admin/site-editor' => 200, '/admin/themes' => 200, '/admin/media' => 200, '/admin/inbox' => 403, '/admin/backups' => 403, '/admin/users' => 403, '/admin/privacy' => 403],
            'support' => ['/admin/inbox' => 200, '/admin/testimonials' => 200, '/admin/site-editor' => 403, '/admin/media' => 403, '/admin/analytics' => 403],
            'seo' => ['/admin/analytics' => 200, '/admin/seo-monitor' => 200, '/admin/projects' => 200, '/admin/themes' => 403, '/admin/inbox' => 403],
            'administrator' => ['/admin/backups' => 200, '/admin/privacy' => 200, '/admin/health' => 200, '/admin/users' => 403],
            'owner' => ['/admin/users' => 200, '/admin/backups' => 200],
        ];
        foreach ($matrix as $role => $pages) {
            $user = $this->person($role);
            foreach ($pages as $url => $status) {
                $this->as($user)->get($url)->assertStatus($status);
            }
            // Open to everyone.
            foreach (['/admin', '/admin/help', '/admin/security', '/admin/notifications'] as $url) {
                $this->as($user)->get($url)->assertOk();
            }
        }
    }

    public function test_actions_are_blocked_not_just_pages(): void
    {
        $editor = $this->person('editor');
        $message = ContactMessage::create(['name' => 'A', 'email' => 'a@example.test', 'subject' => 'S', 'message' => 'M', 'status' => 'unread']);

        $this->as($editor)->delete("/admin/inbox/{$message->id}")->assertForbidden();
        $this->as($editor)->post('/admin/backups')->assertForbidden();
        $this->as($editor)->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'owner', 'password' => self::PASSWORD])->assertForbidden();
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);
    }

    public function test_sidebar_and_search_follow_the_role(): void
    {
        $support = $this->person('support');
        $html = $this->as($support)->get('/admin')->getContent();
        $this->assertStringContainsString(route('admin.inbox.index'), $html);
        $this->assertStringNotContainsString('href="'.route('admin.site-editor.edit').'"', $html);
        $this->assertStringNotContainsString('href="'.route('admin.backups.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('admin.users.index').'"', $html);

        $groups = collect($this->as($support)->getJson('/admin/search?q=a')->json('groups'))->pluck('type')->all();
        $this->assertNotContains('projects', $groups);
        $this->assertNotContains('media', $groups);
        $pages = collect($this->as($support)->getJson('/admin/search?q=backup')->json('groups'))->firstWhere('type', 'pages')['items'] ?? [];
        $this->assertNotContains('Backups', array_column($pages, 'title'));
    }

    public function test_security_page_shows_own_sign_in_tools_to_everyone_but_the_log_only_with_permission(): void
    {
        $this->as($this->person('editor'))->get('/admin/security')->assertOk()
            ->assertSee('Two-factor sign-in')->assertSee('Your signed-in sessions')->assertDontSee('Activity log')->assertDontSee('Protection checks');
        $this->as($this->person('administrator'))->get('/admin/security')->assertOk()->assertSee('Activity log')->assertSee('Protection checks');
    }

    public function test_invite_link_sets_a_password_once_and_expires(): void
    {
        $owner = $this->person('owner');
        $response = $this->as($owner)->post('/admin/users', ['name' => 'Eve Editor', 'email' => 'eve@example.test', 'role' => 'editor', 'password' => self::PASSWORD]);
        $link = $response->getSession()->get('setupLink')['url'];
        $token = basename($link);
        $eve = User::where('email', 'eve@example.test')->sole();
        $this->assertSame('editor', $eve->adminRole());
        $this->assertSame(hash('sha256', $token), $eve->setup_token_hash, 'only the hash is stored');
        auth()->logout();

        $this->get($link)->assertOk()->assertSee('Welcome, Eve Editor');
        $this->post($link, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post($link, ['password' => 'Eves-new-password-2026', 'password_confirmation' => 'Eves-new-password-2026'])->assertRedirect('/admin/login');
        $this->get($link)->assertNotFound();
        $this->post('/admin/login', ['email' => 'eve@example.test', 'password' => 'Eves-new-password-2026'])->assertRedirect('/admin');

        $this->post('/admin/logout');
        $response = $this->as($owner)->post("/admin/users/{$eve->id}/link", ['password' => self::PASSWORD]);
        $second = $response->getSession()->get('setupLink')['url'];
        auth()->logout();
        $this->travel(49)->hours();
        $this->get($second)->assertNotFound()->assertSee('This link has expired');
        $this->get('/admin/setup/not-a-real-token')->assertNotFound();
    }

    public function test_roles_change_and_access_is_removed_but_the_last_owner_is_protected(): void
    {
        $owner = $this->person('owner');
        $editor = $this->person('editor');
        DB::table('sessions')->insert(['id' => str_repeat('s', 40), 'user_id' => $editor->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->as($owner)->put("/admin/users/{$editor->id}/role", ['role' => 'support', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->as($owner)->put("/admin/users/{$editor->id}/role", ['role' => 'support', 'password' => self::PASSWORD])->assertSessionHas('status');
        $this->assertSame('support', $editor->fresh()->adminRole());

        $this->as($owner)->put("/admin/users/{$owner->id}/role", ['role' => 'editor', 'password' => self::PASSWORD])->assertSessionHasErrors('user');
        $this->as($owner)->delete("/admin/users/{$owner->id}", ['password' => self::PASSWORD, 'confirm' => '1'])->assertSessionHasErrors('user');

        $other = $this->person('owner');
        $this->as($other)->put("/admin/users/{$owner->id}/role", ['role' => 'administrator', 'password' => self::PASSWORD])->assertSessionHas('status');
        $this->as($other)->put("/admin/users/{$owner->id}/role", ['role' => 'owner', 'password' => self::PASSWORD]);
        $owner->forceFill(['is_admin' => false])->save();
        $this->as($this->person('owner'))->put("/admin/users/{$other->id}/role", ['role' => 'editor', 'password' => self::PASSWORD])->assertSessionHas('status');

        $this->as($other->fresh()->forceFill(['role' => 'owner']))->delete("/admin/users/{$editor->id}", ['password' => self::PASSWORD, 'confirm' => '1'])->assertSessionHas('status');
        $this->assertFalse($editor->fresh()->is_admin);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $editor->id)->count(), 'signed out everywhere');
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $editor->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.access_removed']);
    }

    public function test_migration_makes_existing_admins_owners(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        \Schema::table('users', function ($table) {
            $table->dropUnique(['setup_token_hash']);
            $table->dropColumn(['role', 'setup_token_hash', 'setup_expires_at']);
        });
        DB::table('migrations')->where('migration', '2026_12_04_000000_add_roles_to_users')->delete();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_12_04_000000_add_roles_to_users.php'])->assertSuccessful();

        $this->assertSame('owner', DB::table('users')->where('id', $admin->id)->value('role'));
    }
}

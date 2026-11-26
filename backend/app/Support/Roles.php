<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Who can do what in the dashboard. Every admin route is mapped to a
 * permission here, by route name. A route that isn't listed is owner-only, so
 * a new page is never accidentally open to every role (a test checks the map
 * covers every admin route).
 */
final class Roles
{
    public const PERMISSIONS = [
        'content' => 'Edit site text, projects, sections, pages, menus and social links, and restore content history',
        'design' => 'Change themes and fonts',
        'media' => 'Upload, edit and delete images',
        'messages' => 'Read the contact inbox and review testimonials',
        'insight' => 'See analytics and SEO monitoring, and connect Search Console',
        'operations' => 'Check health, run checks, install updates and review GitHub discovery',
        'backups' => 'Create, download, verify and delete backups',
        'security' => 'See protection checks, failed sign-ins and the full activity log',
        'privacy' => 'Export and erase personal data, and set retention',
        'users' => 'Invite people, change roles and remove access',
    ];

    /** role => [label, summary, permissions] */
    public const ROLES = [
        'owner' => ['Owner', 'Everything, including managing who has access.', ['*']],
        'administrator' => ['Administrator', 'Everything except managing users.', ['content', 'design', 'media', 'messages', 'insight', 'operations', 'backups', 'security', 'privacy']],
        'editor' => ['Editor', 'Writes and designs the site: content, pages, menus, themes, fonts and media.', ['content', 'design', 'media']],
        'seo' => ['SEO manager', 'Content and search: edits text and SEO fields, sees analytics and SEO monitoring.', ['content', 'insight']],
        'support' => ['Support', 'Answers people: the contact inbox and testimonials.', ['messages']],
    ];

    /** Pages every signed-in administrator can use, whatever their role. */
    public const OPEN = [
        'admin.dashboard', 'admin.search', 'admin.help.*', 'admin.logout',
        'admin.platform.notifications', 'admin.platform.preferences', 'admin.platform.notice', 'admin.platform.notices.read-all',
        // Everyone manages their own sign-in security.
        'admin.security', 'admin.security.sessions.destroy', 'admin.security.two-factor.*',
    ];

    /** route-name pattern => permission */
    public const ROUTES = [
        'admin.site-editor.*' => 'content', 'admin.projects.*' => 'content', 'admin.sections.*' => 'content',
        'admin.pages.*' => 'content', 'admin.menus.*' => 'content', 'admin.revisions.*' => 'content', 'admin.social.*' => 'content',
        'admin.themes.*' => 'design', 'admin.fonts.*' => 'design',
        'admin.media.*' => 'media',
        'admin.inbox.*' => 'messages', 'admin.testimonials.*' => 'messages',
        'admin.analytics' => 'insight', 'admin.analytics.*' => 'insight', 'admin.platform.seo' => 'insight',
        'admin.health' => 'operations', 'admin.health.*' => 'operations', 'admin.self-test' => 'operations', 'admin.self-test.*' => 'operations', 'admin.platform.updates' => 'operations',
        'admin.platform.prepare' => 'operations', 'admin.platform.approve' => 'operations', 'admin.platform.deploy' => 'operations',
        'admin.platform.deployment-status' => 'operations', 'admin.platform.github' => 'operations',
        'admin.platform.review' => 'operations', 'admin.platform.scan' => 'operations',
        'admin.backups.*' => 'backups',
        'admin.privacy' => 'privacy', 'admin.privacy.*' => 'privacy',
        'admin.users.*' => 'users',
    ];

    public static function permissions(?string $role): array
    {
        $granted = self::ROLES[$role][2] ?? [];

        return $granted === ['*'] ? array_keys(self::PERMISSIONS) : $granted;
    }

    public static function allows(?string $role, string $permission): bool
    {
        return in_array($permission, self::permissions($role), true);
    }

    public static function label(?string $role): string
    {
        return self::ROLES[$role][0] ?? 'No role';
    }

    /** @return string|null  null means open to every role; 'owner-only' when unmapped. */
    public static function permissionForRoute(?string $name): ?string
    {
        if ($name === null) {
            return 'owner-only';
        }
        foreach (self::OPEN as $pattern) {
            if (Str::is($pattern, $name)) {
                return null;
            }
        }
        foreach (self::ROUTES as $pattern => $permission) {
            if (Str::is($pattern, $name)) {
                return $permission;
            }
        }

        return 'owner-only';
    }

    public static function canOpenRoute(?string $role, ?string $name): bool
    {
        $permission = self::permissionForRoute($name);

        return $permission === null || ($permission === 'owner-only' ? $role === 'owner' : self::allows($role, $permission));
    }
}

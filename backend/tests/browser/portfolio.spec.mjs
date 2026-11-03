import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

async function login(page) {
    expect(process.env.QA_CREDENTIAL_FILE, 'Provide a private QA-only credential file; passwords must not be logged.').toBeTruthy();
    const credential = JSON.parse(readFileSync(process.env.QA_CREDENTIAL_FILE, 'utf8'));
    await page.goto('/admin/login');
    await page.locator('#email').fill(credential.email);
    await page.locator('#password').fill(credential.password);
    await page.getByRole('button', { name: 'Sign in securely' }).click();
    await expect(page).toHaveURL('http://127.0.0.1:8147/admin');
}

async function publish(page) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.locator('#site-editor-form').evaluate(form => form.requestSubmit()),
    ]);
    await expect(page.locator('.error-box')).toHaveCount(0);
}

test('public search, filters, keyboard dialog and all requested viewport widths', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
    await page.locator('#work').scrollIntoViewIfNeeded();
    await page.getByRole('searchbox', { name: 'Search projects' }).fill('qa-no-such-project');
    await expect(page.locator('.empty-state')).toBeVisible();
    await page.getByRole('searchbox', { name: 'Search projects' }).fill('');
    await page.getByRole('button', { name: 'Desktop & tools', exact: true }).click();
    await expect(page.locator('.project-card')).toHaveCount(1);
    await page.getByRole('button', { name: 'All work', exact: true }).click();
    const trigger = page.getByRole('button', { name: 'Open details for OnePrayer', exact: true });
    await trigger.click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await expect(trigger).toBeFocused();
    for (const [width, height] of [[320,700],[375,812],[390,844],[430,932],[768,1024],[1024,768],[1280,800],[1366,768],[1440,900],[1920,1080]]) {
        await page.setViewportSize({ width, height });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Overflow at ${width}x${height}`).toBe(true);
    }
});

test('unpublished built-ins are absent and do not break frontend initialization', async ({ page, context }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto('/admin/site-editor');
    const visibility = page.locator('input[type=checkbox][name^="sections["][name$="[is_visible]"]');
    const original = await visibility.evaluateAll(inputs => inputs.map(input => ({ name: input.name, checked: input.checked })));
    try {
        await visibility.evaluateAll(inputs => inputs.forEach(input => { input.checked = false; }));
        await publish(page);
        const visitor = await context.newPage();
        const errors = [];
        visitor.on('pageerror', error => errors.push(error.message));
        await visitor.goto('/');
        await expect(visitor.locator('main > section')).toHaveCount(0);
        expect(await visitor.evaluate(() => window.portfolioPayload.sections)).toEqual([]);
        await expect(visitor.locator('.brand-name').first()).toHaveText('Hamid Reza Bayat');
        expect(errors).toEqual([]);
        await visitor.close();
    } finally {
        await page.goto('/admin/site-editor');
        await visibility.evaluateAll((inputs, states) => {
            for (const input of inputs) input.checked = states.find(state => state.name === input.name).checked;
        }, original);
        await publish(page);
    }
});

test('mobile dashboard drawer hides offscreen focus targets and closes with Escape', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    const sidebar = page.locator('#admin-sidebar');
    const toggle = page.getByRole('button', { name: 'Open dashboard navigation' });
    await expect(sidebar).toHaveAttribute('inert', '');
    await toggle.click();
    await expect(sidebar).not.toHaveAttribute('inert', '');
    await sidebar.getByRole('link', { name: 'Live site editor' }).focus();
    await page.keyboard.press('Escape');
    await expect(sidebar).toHaveAttribute('inert', '');
    await expect(toggle).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(sidebar).not.toHaveAttribute('inert', '');
});

test('particle work stops for disabled motion and hidden particles', async ({ page }) => {
    await page.addInitScript(() => {
        const request = window.requestAnimationFrame.bind(window);
        window.qaParticleFrames = 0;
        window.requestAnimationFrame = callback => request(time => {
            if (callback.name === 'drawParticles') window.qaParticleFrames += 1;
            callback(time);
        });
    });
    await page.goto('/');
    await expect.poll(() => page.evaluate(() => window.qaParticleFrames)).toBeGreaterThan(1);
    for (const setting of ['motion', 'particles']) {
        const before = await page.evaluate(key => {
            document.body.dataset.motion = key === 'motion' ? 'off' : 'on';
            document.querySelector('.particle-field').hidden = key === 'particles';
            document.dispatchEvent(new Event('portfolio:motionchange'));
            return window.qaParticleFrames;
        }, setting);
        const after = await page.evaluate(() => new Promise(resolve => setTimeout(() => resolve(window.qaParticleFrames), 150)));
        expect(after).toBe(before);
    }
});

test('capture correctly sized public and admin evidence without credentials or messages', async ({ page }) => {
    test.setTimeout(90000);
    const screenshot = async (name, fullPage = false) => {
        const viewport = page.viewportSize();
        const clip = fullPage ? { x: 0, y: 0, width: viewport.width, height: await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)) } : undefined;
        const png = await page.screenshot({ path: fileURLToPath(new URL(`../../../docs/qa/screenshots/${name}.png`, import.meta.url)), fullPage, clip });
        expect(png.readUInt32BE(16)).toBe(viewport.width);
        expect(png.readUInt32BE(20)).toBe(fullPage ? clip.height : viewport.height);
    };
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const [width, height, label] of [[1440,900,'desktop'],[390,844,'mobile'],[768,1024,'tablet']]) {
        await page.setViewportSize({ width, height });
        await page.goto('/');
        await screenshot(`public-${label}`);
        await screenshot(`public-${label}-full`, true);
        expect(await page.evaluate(() => innerWidth)).toBe(width);
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.locator('#work').scrollIntoViewIfNeeded();
    await screenshot('public-projects');
    await page.getByRole('button', { name: 'Open details for OnePrayer', exact: true }).click();
    await screenshot('project-dialog');
    await page.keyboard.press('Escape');
    await page.locator('#contact').scrollIntoViewIfNeeded();
    await screenshot('public-contact');
    await page.goto('/projects/oneprayer');
    await screenshot('public-project-detail');
    await page.goto('/admin/login');
    await screenshot('admin-login');
    await login(page);
    for (const [route, name] of [
        ['/admin', 'admin-dashboard'],
        ['/admin/site-editor', 'admin-editor-preview'],
        ['/admin/projects', 'admin-projects'],
        ['/admin/sections', 'admin-sections'],
        ['/admin/revisions', 'admin-revisions'],
        ['/admin/media', 'admin-media'],
        ['/admin/fonts', 'admin-fonts'],
        ['/admin/inbox?q=qa-no-such-message', 'admin-inbox-empty-search'],
        ['/admin/updates', 'admin-updates'],
        ['/admin/github-projects', 'admin-github'],
        ['/admin/seo-monitor', 'admin-seo'],
        ['/admin/notifications', 'admin-notifications'],
    ]) {
        await page.goto(route);
        await screenshot(name);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin/site-editor');
    await screenshot('admin-editor-mobile');
    await page.getByRole('button', { name: 'Open dashboard navigation' }).click();
    await screenshot('admin-navigation-mobile');
    await page.getByRole('button', { name: 'Sign out', exact: false }).click();
    await expect(page).toHaveURL('http://127.0.0.1:8147/');
    await page.goto('/preview');
    await expect(page).toHaveURL('http://127.0.0.1:8147/admin/login');
});

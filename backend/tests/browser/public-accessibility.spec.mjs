import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';

test('public semantics, private sources, forced colours and 200 percent scaling', async ({ page, browserName }) => {
    test.setTimeout(90000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await expect(page.getByRole('main')).toBeVisible();
    const scan = await new AxeBuilder({ page }).analyze();
    expect(scan.violations.filter(item => ['serious', 'critical'].includes(item.impact))).toEqual([]);
    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Skip to content' });
    const skipLinkFocused = await skipLink.evaluate(link => link === document.activeElement);
    if (browserName === 'webkit' && !skipLinkFocused) {
        await expect(page.getByRole('button', { name: 'All work', exact: true })).toBeFocused();
        await skipLink.focus();
    } else {
        await expect(skipLink).toBeFocused();
    }
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#main$/);
    for (const [name, slug, repo] of [
        ['OnePrayer', 'oneprayer', 'Bible'],
        ['Home Ledger', 'home-ledger', 'myAccountingHome'],
        ['Interpreters Hub', 'interpreters-hub', 'interpurting-laravel'],
        ['BetweenWords', 'betweenwords', 'meisaPortfolio'],
    ]) {
        await page.goto('/');
        const card = page.locator('.project-card').filter({ has: page.getByRole('button', { name: new RegExp(name) }) });
        await expect(card).toContainText('Private source code');
        await card.getByRole('button').click();
        await expect(page.getByRole('dialog')).toContainText('Private source code');
        await expect(page.getByRole('dialog').getByRole('link', { name: 'View repository' })).toHaveCount(0);
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).not.toBeVisible();
        await page.goto(`/projects/${slug}`);
        await expect(page.getByText('Private source code', { exact: true })).toBeVisible();
        expect(await page.content()).not.toContain(`https://github.com/hamidreza-wq/${repo}`);
    }
    await page.goto('/');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ forcedColors: 'active', reducedMotion: 'reduce' });
    expect(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
    expect(await page.evaluate(() => matchMedia('(forced-colors: active)').matches)).toBe(true);
    await expect(page.getByRole('button', { name: 'Open navigation' })).toBeVisible();
    await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    const clippedControls = await page.locator('a, button, input, textarea, select').evaluateAll(elements => elements
        .filter(element => !element.closest('[aria-hidden="true"], [inert]'))
        .filter(element => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0 && (rect.left < -1 || rect.right > innerWidth + 1);
        })
        .map(element => element.tagName));
    expect(clippedControls).toEqual([]);
    await expect(page.getByRole('searchbox', { name: 'Search projects' })).toBeVisible();
});

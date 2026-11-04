import { defineConfig } from '@playwright/test';

if (!/^[a-z0-9_]+_test$/i.test(process.env.QA_DATABASE ?? '')) {
    throw new Error('Browser tests require an explicit QA_DATABASE whose name ends in _test and an isolated QA server on port 8147. Never use the owner server.');
}

export default defineConfig({
    testDir: './tests/browser',
    workers: 1,
    fullyParallel: false,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8147',
        channel: 'msedge',
        headless: true,
        viewport: { width: 1440, height: 900 },
        trace: 'off',
        screenshot: 'off',
        video: 'off',
    },
});

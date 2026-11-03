import { defineConfig } from '@playwright/test';

if (process.env.QA_DATABASE !== 'hamid_portfolio_qa_test') {
    throw new Error('Browser tests require explicit QA_DATABASE=hamid_portfolio_qa_test and the isolated QA server on port 8147. Never use the owner server.');
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

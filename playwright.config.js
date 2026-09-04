import { defineConfig } from '@playwright/test';

const port = process.env.E2E_PORT || '8000';
const baseURL = process.env.E2E_BASE_URL || `http://localhost:${port}`;

export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    use: {
        baseURL,
        locale: 'fr-FR',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: process.env.E2E_NO_SERVER
        ? undefined
        : {
            command: `php artisan serve --host=localhost --port=${port}`,
            url: baseURL,
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
        },
});

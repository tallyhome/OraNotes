import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('Collaboration multi-contexte', () => {
    test('Alice et Bob ouvrent la note partagée Roadmap V1', async ({ browser }) => {
        const aliceCtx = await browser.newContext();
        const bobCtx = await browser.newContext();
        const alice = await aliceCtx.newPage();
        const bob = await bobCtx.newPage();

        await login(alice, 'alice@oranotes.test');
        await login(bob, 'bob@oranotes.test');

        await alice.goto('/dashboard');
        await alice.locator('[data-testid="workspace-card-Idées produit"]').click();
        await alice.waitForSelector('[data-testid="canvas-viewport"]');
        await alice.locator('[data-testid="sticky-Roadmap V1"]').dblclick({ force: true });
        await expect(alice.locator('.ora-editor-host')).toBeVisible({ timeout: 20_000 });

        await bob.goto('/dashboard');
        await bob.getByRole('link', { name: 'Roadmap V1' }).click();
        await bob.waitForSelector('[data-testid="canvas-viewport"]');
        const bobEditor = bob.locator('.ora-editor-host');
        if (! await bobEditor.isVisible().catch(() => false)) {
            await bob.locator('[data-testid="sticky-Roadmap V1"]').dblclick({ force: true });
        }
        await expect(bobEditor).toBeVisible({ timeout: 20_000 });

        expect(await alice.locator('.ora-editor-host').isVisible()).toBeTruthy();

        await aliceCtx.close();
        await bobCtx.close();
    });
});

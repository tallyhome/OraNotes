import { test, expect } from '@playwright/test';
import { login, openWorkspace } from './helpers.js';

test.describe('OraNotes navigateur', () => {
    test('login Alice, canvas, grille, note, alignement', async ({ page }) => {
        await login(page, 'alice@oranotes.test');
        await expect(page.getByRole('heading', { name: /vos espaces/i })).toBeVisible();

        await openWorkspace(page, 'Idées produit');
        const viewport = page.locator('[data-testid="canvas-viewport"]');
        await expect(viewport).toBeVisible();

        const gridBtn = page.locator('[data-testid="toggle-grid"]');
        const wasOn = await viewport.evaluate((el) => el.classList.contains('canvas-grid'));
        await gridBtn.click();
        await expect(viewport).toHaveClass(wasOn ? /^(?!.*canvas-grid).*$/ : /canvas-grid/);
        await gridBtn.click();
        await expect(viewport).toHaveClass(wasOn ? /canvas-grid/ : /^(?!.*canvas-grid).*$/);

        await page.locator('[data-testid="create-note"]').click();
        await expect(page.locator('.sticky-note, [class*="sticky"]').first()).toBeVisible();

        await page.locator('[data-testid="align-menu"]').click();
        await expect(page.getByRole('button', { name: 'Gauche' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Répartir horizontalement' })).toBeVisible();
    });

    test('verrouiller un bureau depuis les paramètres', async ({ page }) => {
        await login(page, 'alice@oranotes.test');
        await openWorkspace(page, 'Sprint');
        await page.locator('[data-testid="workspace-settings"]').click();
        await expect(page.locator('[data-testid="toggle-lock"]')).toBeVisible();
        await page.locator('[data-testid="toggle-lock"]').click();
        await expect(page.locator('[data-testid="workspace-locked"]')).toBeVisible();
    });

    test('ouvrir OraEditor sur une note existante', async ({ page }) => {
        await login(page, 'alice@oranotes.test');
        await openWorkspace(page, 'Sprint');
        await page.locator('[data-testid="sticky-Ticket #1"]').dblclick({ force: true });
        await expect(page.locator('.ora-editor-host')).toBeVisible({ timeout: 20_000 });
    });

    test('admin dashboard et mises à jour', async ({ page }) => {
        await login(page, 'admin@oranotes.test');
        await page.goto('/admin');
        await expect(page.getByRole('heading', { name: /tableau de bord/i })).toBeVisible();
        await page.goto('/admin/updates');
        await expect(page.getByText(/OraNotes|version|mise/i).first()).toBeVisible();
        await page.goto('/admin/health');
        await expect(page.getByText(/PHP|OK|WARNING|ERROR/i).first()).toBeVisible();
    });

    test('responsive mobile dashboard', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'alice@oranotes.test');
        await expect(page.getByRole('heading', { name: /vos espaces/i })).toBeVisible();
    });

    test('assistant /install refusé si déjà installé', async ({ page }) => {
        const response = await page.goto('/install');
        expect(response?.status()).toBe(404);
        await expect(page.getByText(/licence MIT|Étape 1/i)).toHaveCount(0);
    });
});

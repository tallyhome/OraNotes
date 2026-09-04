export async function login(page, email, password = 'password') {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL(/dashboard/);
}

export async function openWorkspace(page, name) {
    await page.goto('/dashboard');
    await page.getByRole('link', { name: new RegExp(name) }).first().click();
    await page.waitForSelector('[data-testid="canvas-viewport"]');
}

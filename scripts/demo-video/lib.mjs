// Shared helpers for the documentation scripts (screenshots and demo video).
// They reset the local database with the demo seeder, run the app on a private port,
// and drive the installed Google Chrome through Playwright.
import { execFileSync, spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const PORT = Number(process.env.DOCS_PORT || 8123);
export const BASE_URL = `http://127.0.0.1:${PORT}`;

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const artisan = (...args) => execFileSync('php', ['artisan', ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'inherit'] }).toString();

/**
 * Reset the local database (kopi_nusa) to the 30-day demo data, so every run looks the same.
 */
export function reseed() {
    console.log('Menyiapkan data demo (php artisan migrate:fresh --seed)...');
    artisan('migrate:fresh', '--seed', '--force');
    const verify = artisan('stock:verify').trim();
    console.log(`  ${verify}`);
}

/**
 * Start `php artisan serve` on PORT and wait until it answers. Returns a stop() function.
 */
export async function startServer() {
    const server = spawn('php', ['artisan', 'serve', `--port=${PORT}`, '--no-reload'], { cwd: ROOT, stdio: 'ignore' });

    for (let i = 0; i < 60; i++) {
        try {
            const res = await fetch(`${BASE_URL}/up`);
            if (res.ok) {
                return () => server.kill('SIGTERM');
            }
        } catch {
            // not up yet
        }
        await sleep(250);
    }

    server.kill('SIGTERM');
    throw new Error(`Server di ${BASE_URL} tidak merespons.`);
}

/**
 * The installed Google Chrome (no browser download needed). Set DOCS_BROWSER=chromium to use
 * Playwright's own Chromium instead (`npx playwright install chromium`).
 */
export function launchBrowser() {
    return process.env.DOCS_BROWSER === 'chromium'
        ? chromium.launch()
        : chromium.launch({ channel: 'chrome' });
}

export async function login(page, email, { typeDelay = 0 } = {}) {
    await page.goto(`${BASE_URL}/login`);
    await page.locator('#email').pressSequentially(email, { delay: typeDelay });
    await page.locator('#password').pressSequentially('password', { delay: typeDelay });
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.getByRole('button', { name: 'Masuk' }).click()]);
}

export async function logout(page) {
    await Promise.all([page.waitForURL(/\/login/), page.getByRole('button', { name: 'Keluar' }).first().click()]);
}

/**
 * Wait for Livewire round-trips and CSS/chart animations to settle.
 */
export async function settle(page, extra = 0) {
    await sleep(120); // let a click-triggered navigation start before checking
    await retryAcrossNavigation(page, async () => {
        await page.waitForLoadState('networkidle');
        // Infinite ones (a pulsing chart placeholder) never finish, so only wait for the finite ones.
        await page.evaluate(() => Promise.all(document.getAnimations()
            .filter((a) => a.effect?.getComputedTiming().iterations !== Infinity)
            .map((a) => a.finished.catch(() => {}))));
    });
    if (extra) await sleep(extra);
}

/**
 * Run fn again if the page navigated away while it ran (e.g. a form submit just started).
 */
export async function retryAcrossNavigation(page, fn, attempts = 6) {
    for (let attempt = 1; ; attempt++) {
        try {
            return await fn();
        } catch (error) {
            if (attempt >= attempts || !/context was destroyed|navigat/i.test(error.message)) throw error;
            await page.waitForLoadState('load').catch(() => {});
            await sleep(200);
        }
    }
}

/**
 * Charts below the fold are only drawn once scrolled into view (IntersectionObserver).
 */
export async function revealLazyCharts(page) {
    for (const canvas of await page.locator('canvas').all()) {
        await canvas.scrollIntoViewIfNeeded();
        await sleep(900);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
}

/**
 * Invoice number shown on the receipt after a checkout, e.g. INV-20261022-0030.
 */
export async function receiptInvoice(page) {
    const text = await page.locator('.receipt-printable').first().innerText();
    return text.match(/INV-\d{8}-\d{4}/)[0];
}

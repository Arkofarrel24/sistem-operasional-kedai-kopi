// Regenerates docs/screenshots/*.png from the demo seeder data.
// Run from the project root: npm run docs:screenshots  (resets the local database)
import { mkdirSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { BASE_URL, ROOT, launchBrowser, login, receiptInvoice, reseed, revealLazyCharts, settle, sleep, startServer } from './lib.mjs';

const OUT = join(ROOT, 'docs/screenshots');
const LAPTOP = { width: 1280, height: 800 };
const TABLET = { width: 1180, height: 820 };
const PHONE = { width: 390, height: 844 };

mkdirSync(OUT, { recursive: true });
readdirSync(OUT).filter((f) => f.endsWith('.png')).forEach((f) => rmSync(join(OUT, f)));

reseed();
const stopServer = await startServer();
const browser = await launchBrowser();

const session = async (email, viewport) => {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 2, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
    const page = await context.newPage();
    if (email) await login(page, email);
    return { context, page };
};

const shot = async (page, name, { fullPage = false } = {}) => {
    await settle(page, 300);
    const viewport = page.viewportSize();
    if (fullPage) {
        // Grow the viewport to the page height so the fixed sidebar spans the whole image.
        const height = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.setViewportSize({ width: viewport.width, height });
        await settle(page, 600);
    }
    await page.screenshot({ path: join(OUT, `${name}.png`) });
    if (fullPage) await page.setViewportSize(viewport);
    console.log(`  docs/screenshots/${name}.png`);
};

const tap = async (page, name) => {
    await page.locator('button', { hasText: name }).first().click();
    await settle(page);
};

try {
    // Cashier
    {
        const { context, page } = await session(null, LAPTOP);
        await page.goto(`${BASE_URL}/login`);
        await shot(page, '01-login');

        await login(page, 'kasir1@demo.test');
        await shot(page, '02-pos-laptop');

        await tap(page, 'Es Kopi Susu Gula Aren');
        await tap(page, 'Es Kopi Susu Gula Aren');
        await tap(page, 'Americano');
        await page.locator('button', { hasText: 'Rp100.000' }).first().click();
        await settle(page);
        await shot(page, '04-pos-keranjang');

        await page.getByRole('button', { name: /^Bayar Rp/ }).click();
        await page.getByText('Pembayaran berhasil').waitFor();
        await shot(page, '05-struk');
        await page.getByRole('button', { name: 'Transaksi baru' }).click();

        await page.goto(`${BASE_URL}/pos/history`);
        await shot(page, '06-riwayat-kasir');
        await context.close();
    }
    {
        const { context, page } = await session('kasir1@demo.test', TABLET);
        await shot(page, '03-pos-tablet');
        await context.close();
    }

    // Owner
    {
        const { context, page } = await session('owner@demo.test', LAPTOP);
        await shot(page, '07-dashboard');

        await page.goto(`${BASE_URL}/owner/reports`);
        await revealLazyCharts(page);
        await shot(page, '08-laporan', { fullPage: true });

        await page.goto(`${BASE_URL}/owner/stock`);
        await shot(page, '09-stok');

        await page.getByRole('link', { name: 'Biji Kopi Arabika' }).first().click();
        await shot(page, '10-riwayat-stok');

        // Void the cashier's sale from above, then show its detail page.
        await page.goto(`${BASE_URL}/owner/sales`);
        await page.locator('tbody tr').first().getByRole('link').first().click();
        const invoice = await receiptInvoice(page);
        await page.locator('#reason').fill('Pelanggan batal sebelum pesanan dibuat');
        await page.getByRole('button', { name: 'Void transaksi' }).first().click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Void transaksi' }).click();
        await page.getByText(`Transaksi ${invoice} di-void`).waitFor();
        await shot(page, '11-transaksi-void');

        await page.goto(`${BASE_URL}/owner/users`);
        await shot(page, '12-pengguna');
        await context.close();
    }

    // Phone widths
    {
        const { context, page } = await session('kasir1@demo.test', PHONE);
        await tap(page, 'Es Kopi Susu Gula Aren');
        await shot(page, '13-pos-ponsel');
        await context.close();
    }
    {
        const { context, page } = await session('owner@demo.test', PHONE);
        await page.goto(`${BASE_URL}/owner/stock`);
        await shot(page, '14-stok-ponsel');
        await context.close();
    }
} finally {
    await browser.close();
    stopServer();
}

console.log('Selesai.');

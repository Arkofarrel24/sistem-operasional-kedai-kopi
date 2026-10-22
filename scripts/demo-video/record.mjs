// Records docs/demo.mp4: a ~2 minute walkthrough of the cashier and owner flows on the demo data.
// Run from the project root: npm run docs:video  (resets the local database)
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';
import ffmpegPath from 'ffmpeg-static';
import { BASE_URL, ROOT, launchBrowser, receiptInvoice, reseed, retryAcrossNavigation, settle, sleep, startServer } from './lib.mjs';

const SIZE = { width: 1280, height: 800 };
const SCRATCH = join(ROOT, 'scripts/demo-video/.output');
const OUTPUT = join(ROOT, 'docs/demo.mp4');
const TYPE_DELAY = 85; // ms per key, roughly a calm human typist

/**
 * Injected into every page: a visible cursor (headless video has none) with a click ripple, and a
 * caption card in the bottom-left corner. Both survive navigations via sessionStorage.
 */
const overlay = () => {
    const install = () => {
        if (document.getElementById('demo-cursor')) return;

        const style = document.createElement('style');
        style.textContent = `
            #demo-cursor { position: fixed; width: 22px; height: 22px; margin: -11px 0 0 -11px; border-radius: 50%;
                background: rgba(28, 25, 23, .3); border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(28, 25, 23, .55), 0 2px 6px rgba(28, 25, 23, .3);
                z-index: 2147483647; pointer-events: none; transition: transform 120ms ease-out; }
            #demo-cursor.down { transform: scale(.7); }
            .demo-ripple { position: fixed; width: 44px; height: 44px; margin: -22px 0 0 -22px; border-radius: 50%; border: 3px solid #f59e0b;
                z-index: 2147483646; pointer-events: none; animation: demo-ripple 550ms cubic-bezier(.23, 1, .32, 1) forwards; }
            @keyframes demo-ripple { from { transform: scale(.3); opacity: 1; } to { transform: scale(1.5); opacity: 0; } }
            #demo-caption { position: fixed; bottom: 24px; max-width: 400px; padding: 12px 18px 13px; border-radius: 14px;
                background: rgba(28, 25, 23, .94); color: #fafaf9; font: 600 17px/1.35 Figtree, system-ui, sans-serif;
                box-shadow: 0 10px 30px rgba(28, 25, 23, .3); z-index: 2147483645; pointer-events: none;
                transition: opacity 260ms ease, transform 260ms cubic-bezier(.23, 1, .32, 1); }
            #demo-caption.hidden { opacity: 0; transform: translateY(8px); }
            #demo-caption small { display: block; margin-bottom: 3px; color: #fcd34d; font-size: 13px; font-weight: 600; }
        `;
        document.head.appendChild(style);

        const cursor = document.createElement('div');
        cursor.id = 'demo-cursor';
        const last = JSON.parse(sessionStorage.getItem('demo-cursor') || '{"x":640,"y":420}');
        cursor.style.left = `${last.x}px`;
        cursor.style.top = `${last.y}px`;
        document.body.appendChild(cursor);

        const caption = document.createElement('div');
        caption.id = 'demo-caption';
        caption.className = 'hidden';
        document.body.appendChild(caption);

        const place = () => {
            // Clear the owner sidebar on wide screens; on the POS the bottom-left is menu tiles, not actions.
            const sidebar = document.querySelector('aside');
            caption.style.left = sidebar && sidebar.getBoundingClientRect().right > 0 ? `${sidebar.getBoundingClientRect().right + 24}px` : '24px';
        };

        const render = (animate) => {
            const data = JSON.parse(sessionStorage.getItem('demo-caption') || 'null');
            if (!data) return;
            place();
            const show = () => {
                caption.innerHTML = `<small>${data.step}</small>${data.text}`;
                caption.classList.remove('hidden');
            };
            if (animate) {
                caption.classList.add('hidden');
                setTimeout(show, 200);
            } else {
                show();
            }
        };

        window.__demoCaption = (step, text) => {
            sessionStorage.setItem('demo-caption', JSON.stringify({ step, text }));
            render(true);
        };
        render(false);

        document.addEventListener('mousemove', (e) => {
            cursor.style.left = `${e.clientX}px`;
            cursor.style.top = `${e.clientY}px`;
            sessionStorage.setItem('demo-cursor', JSON.stringify({ x: e.clientX, y: e.clientY }));
        }, true);
        document.addEventListener('mousedown', (e) => {
            cursor.classList.add('down');
            const ripple = document.createElement('div');
            ripple.className = 'demo-ripple';
            ripple.style.left = `${e.clientX}px`;
            ripple.style.top = `${e.clientY}px`;
            document.body.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        }, true);
        document.addEventListener('mouseup', () => cursor.classList.remove('down'), true);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', install);
    } else {
        install();
    }
};

// --- Human-paced helpers -----------------------------------------------------------------------

let mouse = { x: 640, y: 420 };

const moveTo = async (page, x, y) => {
    const distance = Math.hypot(x - mouse.x, y - mouse.y);
    await page.mouse.move(x, y, { steps: Math.max(12, Math.round(distance / 18)) });
    mouse = { x, y };
};

const pointAt = async (page, locator) => {
    await locator.scrollIntoViewIfNeeded();
    const box = await locator.boundingBox();
    await moveTo(page, box.x + box.width / 2, box.y + box.height / 2);
};

const click = async (page, locator, pause = 700) => {
    await pointAt(page, locator);
    await sleep(220);
    await page.mouse.down();
    await sleep(70);
    await page.mouse.up();
    await settle(page);
    await sleep(pause);
};

const type = async (page, locator, text, pause = 400) => {
    await click(page, locator, 150);
    await locator.pressSequentially(text, { delay: TYPE_DELAY });
    await sleep(pause);
};

const caption = async (page, step, text, hold = 1200) => {
    await retryAcrossNavigation(page, async () => {
        await page.waitForFunction(() => typeof window.__demoCaption === 'function');
        await page.evaluate(([s, t]) => window.__demoCaption(s, t), [step, text]);
    });
    await sleep(hold);
};

const scrollTo = async (page, locator, hold = 1500) => {
    await locator.evaluate((el) => el.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    await sleep(hold);
};

const login = async (page, email) => {
    await page.goto(`${BASE_URL}/login`);
    await sleep(600);
    await type(page, page.locator('#email'), email, 200);
    await type(page, page.locator('#password'), 'password', 300);
    await click(page, page.getByRole('button', { name: 'Masuk' }), 900);
};

const logout = async (page) => {
    await click(page, page.getByRole('button', { name: 'Keluar' }).first(), 600);
};

// --- Recording ---------------------------------------------------------------------------------

rmSync(SCRATCH, { recursive: true, force: true });
mkdirSync(SCRATCH, { recursive: true });

reseed();
const stopServer = await startServer();
const browser = await launchBrowser();
let videoPath;

try {
    const context = await browser.newContext({
        viewport: SIZE,
        recordVideo: { dir: SCRATCH, size: SIZE },
        acceptDownloads: true,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
    });
    await context.addInitScript(overlay);
    const page = await context.newPage();
    videoPath = await page.video().path();

    // a. Cashier records a sale and prints the receipt.
    await page.goto(`${BASE_URL}/login`);
    await caption(page, 'Bagian 1 dari 4: Kasir', 'Kasir masuk ke aplikasi', 900);
    await login(page, 'kasir1@demo.test');
    await caption(page, 'Bagian 1 dari 4: Kasir', 'Kasir mencatat transaksi', 1200);
    await click(page, page.locator('button', { hasText: 'Es Kopi Susu Gula Aren' }).first(), 500);
    await click(page, page.locator('button', { hasText: 'Es Kopi Susu Gula Aren' }).first(), 500);
    await click(page, page.locator('button', { hasText: 'Americano' }).first(), 1000);
    await caption(page, 'Bagian 1 dari 4: Kasir', 'Bayar tunai, kembalian dihitung otomatis', 800);
    await click(page, page.locator('button', { hasText: 'Rp100.000' }).first(), 1300);
    await click(page, page.getByRole('button', { name: /^Bayar Rp/ }), 400);
    await page.getByText('Pembayaran berhasil').waitFor();
    const invoice = await receiptInvoice(page);
    await caption(page, 'Bagian 1 dari 4: Kasir', `Struk ${invoice} siap dicetak`, 2800);
    await click(page, page.getByRole('button', { name: 'Transaksi baru' }), 500);
    await logout(page);

    // b. Owner sees low stock and restocks one ingredient.
    await caption(page, 'Bagian 2 dari 4: Pemilik', 'Pemilik membuka beranda', 600);
    await login(page, 'owner@demo.test');
    await sleep(1200);
    await caption(page, 'Bagian 2 dari 4: Pemilik', 'Bahan yang menipis langsung terlihat', 600);
    await pointAt(page, page.getByRole('heading', { name: 'Perlu restock' }));
    await sleep(500);
    await pointAt(page, page.locator('li', { hasText: 'Bubuk Matcha' }).first());
    await sleep(1600);
    await click(page, page.getByRole('link', { name: 'Stok' }).first(), 1200);
    await caption(page, 'Bagian 2 dari 4: Pemilik', 'Restock: isi jumlah dan total harga nota', 600);
    await click(page, page.locator('tr', { hasText: 'Bubuk Matcha' }).getByRole('link', { name: 'Restock' }), 800);
    await type(page, page.locator('#quantity'), '500');
    await type(page, page.locator('#total_cost'), '225000');
    await type(page, page.locator('#note'), 'Toko Bahan Kue Sentosa', 500);
    await click(page, page.getByRole('button', { name: 'Simpan restock' }), 400);
    await caption(page, 'Bagian 2 dari 4: Pemilik', 'Stok bertambah dan tercatat di riwayat', 2400);

    // c. Report: gross profit, busy hours, Excel export.
    await caption(page, 'Bagian 3 dari 4: Laporan', 'Rekap penjualan bulan ini', 300);
    await click(page, page.getByRole('link', { name: 'Laporan' }).first(), 2000);
    await scrollTo(page, page.getByRole('heading', { name: 'Laba kotor' }), 600);
    await caption(page, 'Bagian 3 dari 4: Laporan', 'Laba kotor dari HPP historis', 2600);
    await scrollTo(page, page.getByRole('heading', { name: 'Transaksi per jam' }), 600);
    await caption(page, 'Bagian 3 dari 4: Laporan', 'Jam ramai terlihat per jam', 2800);
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    await sleep(900);
    await caption(page, 'Bagian 3 dari 4: Laporan', 'Ekspor laporan ke Excel', 500);
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        click(page, page.getByRole('link', { name: 'Ekspor Excel' }), 300),
    ]);
    await download.saveAs(join(SCRATCH, download.suggestedFilename()));
    await caption(page, 'Bagian 3 dari 4: Laporan', `${download.suggestedFilename()} terunduh`, 2200);

    // d. Void the cashier's sale; the stock comes back as void returns.
    await caption(page, 'Bagian 4 dari 4: Void', 'Void transaksi dengan alasan', 300);
    await click(page, page.getByRole('link', { name: 'Transaksi' }).first(), 1100);
    await click(page, page.getByRole('link', { name: invoice }), 900);
    await type(page, page.locator('#reason'), 'Pelanggan batal', 400);
    await click(page, page.getByRole('button', { name: 'Void transaksi' }).first(), 1400);
    await click(page, page.getByRole('alertdialog').getByRole('button', { name: 'Void transaksi' }), 400);
    await caption(page, 'Bagian 4 dari 4: Void', 'Transaksi tetap tersimpan dengan tanda VOID', 2400);
    await click(page, page.getByRole('link', { name: 'Stok' }).first(), 800);
    await click(page, page.locator('tr', { hasText: 'Biji Kopi Arabika' }).getByRole('link', { name: 'Biji Kopi Arabika' }), 600);
    await caption(page, 'Bagian 4 dari 4: Void', 'Stok bahan dikembalikan otomatis', 600);
    await pointAt(page, page.locator('tr', { hasText: 'Pengembalian void' }).first());
    await sleep(3200);

    await context.close(); // finalises the video file
} finally {
    await browser.close();
    stopServer();
}

console.log('Mengonversi ke MP4...');
execFileSync(ffmpegPath, [
    '-y', '-loglevel', 'error', '-i', videoPath,
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '24', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an',
    OUTPUT,
]);
rmSync(SCRATCH, { recursive: true, force: true });

const probe = (() => {
    try {
        execFileSync(ffmpegPath, ['-i', OUTPUT], { stdio: 'pipe' });
    } catch (error) {
        return error.stderr.toString();
    }
    return '';
})();
const duration = probe.match(/Duration: (\d+:\d+:\d+\.\d+)/)?.[1] ?? '?';
const megabytes = (statSync(OUTPUT).size / 1024 / 1024).toFixed(1);
console.log(`Selesai: docs/demo.mp4 (${duration}, ${megabytes} MB)`);

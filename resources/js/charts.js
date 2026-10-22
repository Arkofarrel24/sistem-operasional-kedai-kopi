// Owner charts (dashboard and reports). Loaded only on those pages via @vite in a "scripts" stack.
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip);

const colors = {
    amber: '#f59e0b',
    amberDark: '#b45309',
    amberMuted: '#fcd34d',
    ink: '#1c1917',
    grid: '#f0eeec',
    text: '#78716c',
};

// Same curve as the CSS --ease-out token.
const EASE_OUT = 'cubic-bezier(0.23, 1, 0.32, 1)';
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const rupiah = (value) => 'Rp' + Math.round(value).toLocaleString('id-ID');

// Axis ticks: 1.250.000 -> "1,3 jt", 45.000 -> "45 rb".
const compactRupiah = (value) => {
    if (value >= 1_000_000) return (value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (value >= 1_000) return Math.round(value / 1_000).toLocaleString('id-ID') + ' rb';
    return value.toLocaleString('id-ID');
};

const pad = (hour) => String(hour).padStart(2, '0');

Chart.defaults.font.family = 'Figtree, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = colors.text;

const baseOptions = () => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: colors.ink,
            padding: { x: 12, y: 10 },
            cornerRadius: 8,
            displayColors: false,
            titleFont: { weight: '600' },
            bodyFont: { size: 13 },
        },
    },
    scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14 } },
        y: { beginAtZero: true, grid: { color: colors.grid }, border: { display: false }, ticks: { callback: compactRupiah, maxTicksLimit: 5, padding: 8 } },
    },
});

/**
 * Reveal a chart left to right after its data changed. The data is swapped instantly (Chart.js
 * would otherwise morph 7 points into 30, which reads as "nothing happened"), then the canvas is
 * unclipped. Uses the Web Animations API, so it runs off the main thread and can be interrupted.
 */
const reveal = (canvas, duration) => {
    if (reducedMotion) return;
    canvas.getAnimations().forEach((animation) => animation.cancel());
    canvas.animate([{ clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0 0 0)' }], { duration, easing: EASE_OUT });
};

// Daily revenue line with a 7 / 30 day segmented control.
document.querySelectorAll('[data-revenue-chart]').forEach((wrapper) => {
    const points = JSON.parse(wrapper.dataset.revenueChart);
    const canvas = wrapper.querySelector('canvas');
    const control = wrapper.querySelector('[data-segmented]');
    const buttons = [...control.querySelectorAll('[data-range]')];
    const ctx = canvas.getContext('2d');

    const fill = ctx.createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 256);
    fill.addColorStop(0, 'rgba(245, 158, 11, 0.22)');
    fill.addColorStop(1, 'rgba(245, 158, 11, 0)');

    const options = baseOptions();
    options.animation = false;
    options.plugins.tooltip.callbacks = {
        label: (item) => [rupiah(item.raw), item.dataset.transactions[item.dataIndex] + ' transaksi'],
    };

    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                data: [],
                transactions: [],
                borderColor: colors.amber,
                backgroundColor: fill,
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointBackgroundColor: '#fff',
                pointBorderColor: colors.amber,
                pointBorderWidth: 2,
            }],
        },
        options,
    });

    let current = null;

    const show = (days, { animate = true } = {}) => {
        if (days === current) return;
        current = days;

        const slice = points.slice(-days);
        const dataset = chart.data.datasets[0];
        chart.data.labels = slice.map((p) => p.label);
        dataset.data = slice.map((p) => p.revenue);
        dataset.transactions = slice.map((p) => p.transactions);
        // Mark the latest day so "today" stands out on the line.
        dataset.pointRadius = slice.map((_, i) => (i === slice.length - 1 ? 4 : days > 14 ? 0 : 3));
        dataset.pointBackgroundColor = slice.map((_, i) => (i === slice.length - 1 ? colors.amber : '#fff'));
        chart.update('none');

        const total = slice.reduce((sum, p) => sum + p.revenue, 0);
        wrapper.querySelector('[data-range-total]').textContent = rupiah(total);
        wrapper.querySelector('[data-range-days]').textContent = days;
        wrapper.querySelector('[data-range-average]').textContent = rupiah(total / Math.max(slice.length, 1));

        const index = buttons.findIndex((b) => Number(b.dataset.range) === days);
        control.dataset.selected = String(index);
        buttons.forEach((b, i) => {
            b.setAttribute('aria-checked', String(i === index));
            b.tabIndex = i === index ? 0 : -1;
        });

        if (animate) reveal(canvas, 450);
    };

    // The whole control is the hit area: a click on its padding still picks the nearer option.
    control.addEventListener('click', (event) => {
        const button = event.target.closest('[data-range]');
        if (button) return show(Number(button.dataset.range));
        const rect = control.getBoundingClientRect();
        show(Number(buttons[event.clientX < rect.left + rect.width / 2 ? 0 : 1].dataset.range));
    });

    // Radio-group keyboard behaviour: arrow keys move the selection (no animation on keyboard input).
    control.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const next = buttons[(buttons.findIndex((b) => b.tabIndex === 0) + 1) % buttons.length];
        show(Number(next.dataset.range), { animate: false });
        next.focus();
    });

    show(7, { animate: false });
    reveal(canvas, 800);
});

// Sales per hour: <canvas data-hourly-chart="[...]" data-busiest="[8,16,17]">.
// Built when it scrolls into view, so the rising bars are actually seen (it sits below the fold).
const drawHourly = (canvas) => {
    const hours = JSON.parse(canvas.dataset.hourlyChart);
    const busiest = JSON.parse(canvas.dataset.busiest || '[]');
    const options = baseOptions();
    options.scales.y.ticks = { precision: 0, maxTicksLimit: 5, padding: 8 };
    options.plugins.tooltip.callbacks = {
        title: (items) => `Pukul ${pad(items[0].dataIndex)}.00-${pad(items[0].dataIndex)}.59`,
        label: (item) => [item.raw + ' transaksi', rupiah(hours[item.dataIndex].revenue)],
    };
    // Bars rise from the baseline one hour after another, once, when the report opens.
    options.animation = reducedMotion
        ? false
        : {
              duration: 650,
              easing: 'easeOutQuart',
              delay: (context) => (context.type === 'data' && context.mode === 'default' ? context.dataIndex * 22 : 0),
          };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: hours.map((h) => pad(h.hour)),
            datasets: [{
                data: hours.map((h) => h.transactions),
                backgroundColor: hours.map((h) => (busiest.includes(h.hour) ? colors.amberDark : colors.amberMuted)),
                hoverBackgroundColor: hours.map((h) => (busiest.includes(h.hour) ? '#92400e' : colors.amber)),
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 28,
            }],
        },
        options,
    });
};

document.querySelectorAll('[data-hourly-chart]').forEach((canvas) => {
    if (!('IntersectionObserver' in window)) return drawHourly(canvas);

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            observer.disconnect();
            drawHourly(canvas);
        }
    }, { threshold: 0.25 });
    observer.observe(canvas);
});

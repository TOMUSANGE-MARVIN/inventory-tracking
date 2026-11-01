// Dashboard charts on ApexCharts. Colours come from the validated categorical
// palette (light and dark steps validated against the card surfaces), assigned
// in fixed order so each series/location always keeps its colour.

const PALETTE = {
    light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'],
    dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
};

// Named metric colours (same hues/steps as the palette; pairings validated where they share a chart).
const NAMED = {
    light: {
        blue: '#2a78d6',
        orange: '#eb6834',
        aqua: '#1baf7a',
        yellow: '#eda100',
        magenta: '#e87ba4',
        green: '#008300',
        violet: '#6250d6',
        red: '#d03b3b',
    },
    dark: {
        blue: '#3987e5',
        orange: '#d95926',
        aqua: '#199e70',
        yellow: '#c98500',
        magenta: '#d55181',
        green: '#008300',
        violet: '#9085e9',
        red: '#d03b3b',
    },
};

const color = (mode, name, i = 0) => NAMED[mode][name] ?? PALETTE[mode][i % 8];

const INK = {
    light: { surface: '#ffffff', muted: '#64748b', grid: '#e2e8f0', text: '#0f172a' },
    dark: { surface: '#0f172a', muted: '#94a3b8', grid: '#1e293b', text: '#f1f5f9' },
};

const isDark = () => document.documentElement.classList.contains('dark');

function base(mode, height) {
    const ink = INK[mode];
    return {
        chart: {
            height,
            width: '100%',
            parentHeightOffset: 0,
            redrawOnParentResize: true,
            fontFamily: 'Inter, sans-serif',
            foreColor: ink.muted,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 350 },
        },
        theme: { mode },
        dataLabels: { enabled: false },
        grid: { borderColor: ink.grid, strokeDashArray: 3, padding: { left: 4, right: 4 } },
        tooltip: { theme: mode },
        legend: { show: false },
    };
}

const BUILDERS = {
    // Tiny trend line for a stat tile: no axes, tooltip only.
    spark: (d, mode) => ({
        ...base(mode, 56),
        chart: { ...base(mode, 56).chart, type: 'area', sparkline: { enabled: true } },
        series: [{ name: d.name, data: d.data }],
        labels: d.labels,
        colors: [color(mode, d.color)],
        stroke: { width: 2, curve: 'smooth' },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0, stops: [0, 100] },
        },
        tooltip: { theme: mode, x: { show: true }, y: { title: { formatter: () => '' } } },
    }),

    // Two series over time, one axis, shared crosshair tooltip, legend on top.
    area: (d, mode) => ({
        ...base(mode, 260),
        chart: { ...base(mode, 260).chart, type: 'area' },
        // Pass name/data only: a series-level `color` would be read as a CSS colour name ("aqua" = #00ffff).
        series: d.series.map(({ name, data }) => ({ name, data })),
        colors: d.series.map((x, i) => color(mode, x.color, i)),
        stroke: { width: 2, curve: 'smooth' },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
        markers: { size: 0, hover: { size: 5 }, strokeColors: INK[mode].surface, strokeWidth: 2 },
        xaxis: {
            categories: d.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: Math.min(d.labels.length, d.narrow ? 4 : 8),
            labels: { rotate: 0, hideOverlappingLabels: true },
        },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: { size: 5 },
            labels: { colors: INK[mode].muted },
        },
        tooltip: { theme: mode, shared: true, intersect: false },
    }),

    // Single-series columns: rounded data end, gap between bars.
    bar: (d, mode) => ({
        ...base(mode, 240),
        chart: { ...base(mode, 240).chart, type: 'bar' },
        series: [{ name: d.name, data: d.data }],
        colors: [color(mode, d.color)],
        // Same hue, fading toward the baseline – decoration only, the bar height carries the value.
        fill: {
            type: 'gradient',
            gradient: {
                type: 'vertical',
                shadeIntensity: 0.2,
                opacityFrom: 1,
                opacityTo: 0.55,
                stops: [0, 100],
            },
        },
        plotOptions: {
            bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' },
        },
        states: { hover: { filter: { type: 'darken', value: 0.85 } } },
        xaxis: {
            categories: d.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: Math.min(d.labels.length, d.narrow ? 4 : 8),
            labels: { rotate: 0, hideOverlappingLabels: true },
        },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
        tooltip: { theme: mode, intersect: false, shared: true },
    }),

    // Part-to-whole by location; total in the centre, legend rendered in HTML beside it.
    donut: (d, mode) => ({
        ...base(mode, 220),
        chart: { ...base(mode, 220).chart, type: 'donut' },
        series: d.data,
        labels: d.labels,
        colors: d.data.map((_, i) => color(mode, d.colors?.[i], i)),
        stroke: { width: 2, colors: [INK[mode].surface] },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '12px', color: INK[mode].muted, offsetY: 18 },
                        value: {
                            show: true,
                            fontSize: '26px',
                            fontWeight: 700,
                            color: INK[mode].text,
                            offsetY: -14,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: d.totalLabel,
                            fontSize: '12px',
                            color: INK[mode].muted,
                        },
                    },
                },
            },
        },
        tooltip: { theme: mode, y: { formatter: (v) => `${v} units` } },
    }),
};

export default (kind, data) => ({
    chart: null,
    observer: null,

    init() {
        // Wait for the grid to lay out so the chart measures its real card width.
        this.$nextTick(() => requestAnimationFrame(() => this.render()));
        // Re-draw with the other palette step when light/dark is switched.
        this.observer = new MutationObserver(() => this.render());
        this.observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        // Redraw when the card changes width (sidebar opening, window resize, late layout).
        let timer;
        this.resizer = new ResizeObserver(() => {
            const w = this.$el.clientWidth;
            if (this.chart && Math.abs(w - (this.width ?? w)) > 4) {
                clearTimeout(timer);
                timer = setTimeout(() => this.render(true), 150);
            }
        });
        this.resizer.observe(this.$el);
    },

    render(force = false) {
        const mode = isDark() ? 'dark' : 'light';
        if (this.chart && this.mode === mode && !force) {
            return;
        }
        this.mode = mode;
        this.width = this.$el.clientWidth;
        this.chart?.destroy();
        // Fewer date ticks on phone-width cards.
        this.chart = new window.ApexCharts(
            this.$el,
            BUILDERS[kind]({ ...data, narrow: this.$el.clientWidth < 480 }, mode)
        );
        this.chart.render();
    },

    destroy() {
        this.observer?.disconnect();
        this.resizer?.disconnect();
        this.chart?.destroy();
    },
});

export { PALETTE };

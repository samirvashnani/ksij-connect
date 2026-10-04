(() => {
    'use strict';
    const source = document.getElementById('donation-chart-data');
    if (!source) return;
    const failed = () => { document.querySelector('[data-chart-error]').hidden = false; };
    if (!window.Chart) { failed(); return; }
    try {
        const data = JSON.parse(source.textContent);
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });
        const compact = new Intl.NumberFormat('en-IN', { notation: 'compact', maximumFractionDigits: 1 });
        const colors = { actual: '#187655', demo: '#bf8428' };
        const options = {
            responsive: true, maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 350 },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 18 } },
                tooltip: { callbacks: { label: context => context.dataset.label + ': ' + money.format(context.parsed.y) } } },
            scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { beginAtZero: true, grid: { color: '#e9eeeb' }, ticks: { callback: value => compact.format(value) } } }
        };
        new Chart(document.getElementById('donation-trend'), { type: 'line', options,
            data: { labels: data.trend.map(r => r.month), datasets: [
                { label: 'Actual receipts', data: data.trend.map(r => Number(r.actual)), borderColor: colors.actual, backgroundColor: colors.actual, tension: 0.2, pointRadius: 3, borderWidth: 2 },
                { label: 'Demo donations', data: data.trend.map(r => Number(r.demo)), borderColor: colors.demo, backgroundColor: colors.demo, tension: 0.2, pointRadius: 3, borderWidth: 2, borderDash: [5, 4] }
            ] } });
        new Chart(document.getElementById('donation-categories'), { type: 'bar',
            options: { ...options, indexAxis: 'y', plugins: { ...options.plugins, tooltip: { callbacks: { label: c => c.dataset.label + ': ' + money.format(c.parsed.x) } } },
                scales: { x: { beginAtZero: true, grid: { color: '#e9eeeb' }, ticks: { callback: v => compact.format(v) } }, y: { grid: { display: false }, ticks: { autoSkip: false, callback: function(value) { const label = this.getLabelForValue(value); return label.length > 18 ? label.slice(0, 16) + '...' : label; } } } } },
            data: { labels: data.categories.map(r => r.label), datasets: [
                { label: 'Actual receipts', data: data.categories.map(r => Number(r.actual)), backgroundColor: colors.actual, borderRadius: 3 },
                { label: 'Demo donations', data: data.categories.map(r => Number(r.demo)), backgroundColor: colors.demo, borderRadius: 3 }
            ] } });
        new Chart(document.getElementById('donation-methods'), { type: 'doughnut',
            options: { responsive: true, maintainAspectRatio: false, animation: options.animation, cutout: '72%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + c.parsed + ' payments' } } } },
            data: { labels: data.methods.map(r => r.label), datasets: [{ data: data.methods.map(r => r.count), backgroundColor: ['#bf8428','#187655','#4176b7','#b85673','#6d70a6'], borderWidth: 2 }] } });
    } catch { failed(); }
})();

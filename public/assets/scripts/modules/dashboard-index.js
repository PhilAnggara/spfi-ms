document.addEventListener('DOMContentLoaded', function () {
    const dashboardData = window.dashboardData || {};

    function seriesPayload(key) {
        const payload = dashboardData[key] || {};
        return {
            labels: Array.isArray(payload.labels) ? payload.labels : [],
            series: Array.isArray(payload.series) ? payload.series : [],
        };
    }

    function renderIfPresent(selector, optionsFactory) {
        if (!window.ApexCharts) {
            return;
        }

        const el = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;

        if (!el) {
            return;
        }

        const chart = new window.ApexCharts(el, optionsFactory());
        chart.render();
    }

    const monthlyPrs = seriesPayload('monthly_prs');
    const prsStatus = seriesPayload('prs_status');
    const topSuppliers = seriesPayload('top_suppliers');
    const poStatus = seriesPayload('po_status');
    const monthlyRr = seriesPayload('monthly_rr');
    const monthlyOutbound = seriesPayload('monthly_outbound');
    const monthlyPoValue = seriesPayload('monthly_po_value');
    const usersByDepartment = seriesPayload('users_by_department');
    const deptMonthlyPrs = seriesPayload('dept_monthly_prs');
    const deptPrsStatus = seriesPayload('dept_prs_status');
    const openPrsHeatmap = dashboardData.open_prs_heatmap || {};

    renderIfPresent('#chart-profile-visit', function () {
        return {
            annotations: { position: 'back' },
            dataLabels: { enabled: false },
            chart: { type: 'bar', height: 300 },
            fill: { opacity: 1 },
            series: [{ name: 'PRS', data: monthlyPrs.series }],
            colors: '#435ebe',
            xaxis: { categories: monthlyPrs.labels },
        };
    });

    renderIfPresent('#chart-dept-monthly-prs', function () {
        return {
            annotations: { position: 'back' },
            dataLabels: { enabled: false },
            chart: { type: 'bar', height: 300 },
            fill: { opacity: 1 },
            series: [{ name: 'PRS', data: deptMonthlyPrs.series }],
            colors: '#435ebe',
            xaxis: { categories: deptMonthlyPrs.labels },
        };
    });

    renderIfPresent('#chart-visitors-profile', function () {
        return {
            series: prsStatus.series,
            labels: prsStatus.labels,
            colors: ['#435ebe', '#55c6e8', '#1f9d8f', '#f59e0b', '#ef4444', '#8b5cf6'],
            chart: { type: 'donut', width: '100%', height: '350px' },
            legend: { position: 'bottom' },
            plotOptions: {
                pie: {
                    donut: { size: '30%' },
                },
            },
        };
    });

    renderIfPresent('#chart-dept-prs-status', function () {
        return {
            series: deptPrsStatus.series,
            labels: deptPrsStatus.labels,
            colors: ['#435ebe', '#55c6e8', '#1f9d8f', '#f59e0b', '#ef4444', '#8b5cf6'],
            chart: { type: 'donut', width: '100%', height: '350px' },
            legend: { position: 'bottom' },
            plotOptions: {
                pie: {
                    donut: { size: '30%' },
                },
            },
        };
    });

    renderIfPresent('#chart-open-prs-heatmap', function () {
        const categories = Array.isArray(openPrsHeatmap.categories) ? openPrsHeatmap.categories : [];
        const series = Array.isArray(openPrsHeatmap.series) ? openPrsHeatmap.series : [];

        let maxValue = 0;
        series.forEach(function (row) {
            (row.data || []).forEach(function (value) {
                const numeric = typeof value === 'number' ? value : Number(value?.y ?? 0);
                if (numeric > maxValue) {
                    maxValue = numeric;
                }
            });
        });

        const palette = ['#f1f5f9', '#dbeafe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', '#1e3a8a'];

        function buildHeatmapRanges(max) {
            if (max <= 0) {
                return [{ from: 0, to: 0, color: palette[0], name: '0' }];
            }

            let tiers;
            if (max <= 20) {
                tiers = [0, 2, 5, 10, 15, max];
            } else if (max <= 100) {
                tiers = [0, 10, 25, 50, 75, max];
            } else if (max <= 300) {
                tiers = [0, 25, 50, 100, 175, 250, max];
            } else {
                tiers = [0, 50, 100, 200, 350, 500, max];
            }

            const edges = [];
            tiers.forEach(function (tier) {
                const value = Math.min(max, Math.max(0, tier));
                if (edges.length === 0 || value > edges[edges.length - 1]) {
                    edges.push(value);
                }
            });
            if (edges[edges.length - 1] < max) {
                edges.push(max);
            }

            const ranges = [{ from: 0, to: 0, color: palette[0], name: '0' }];
            for (let i = 1; i < edges.length; i += 1) {
                const from = edges[i - 1] + 1;
                const to = edges[i];
                if (from > to) {
                    continue;
                }
                const isLast = i === edges.length - 1;
                ranges.push({
                    from: from,
                    to: isLast ? 100000 : to,
                    color: palette[Math.min(i, palette.length - 1)],
                    name: isLast ? (from + '+') : (from === to ? String(from) : (from + '-' + to)),
                });
            }

            return ranges;
        }

        return {
            chart: {
                type: 'heatmap',
                height: Math.max(280, 48 + (series.length * 42)),
            },
            dataLabels: { enabled: true },
            colors: ['#435ebe'],
            series: series,
            xaxis: { categories: categories },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.5,
                    radius: 4,
                    colorScale: {
                        ranges: buildHeatmapRanges(maxValue),
                    },
                },
            },
        };
    });

    renderIfPresent('#chart-top-suppliers', function () {
        return {
            series: [{ name: 'PO Value', data: topSuppliers.series }],
            chart: { type: 'bar', height: 360 },
            colors: ['#1f9d8f'],
            plotOptions: {
                bar: { borderRadius: 6, horizontal: true },
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: topSuppliers.labels,
                labels: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('id-ID').format(value);
                    },
                },
            },
        };
    });

    renderIfPresent('#chart-po-status', function () {
        return {
            series: poStatus.series,
            labels: poStatus.labels,
            chart: { type: 'donut', width: '100%', height: 320 },
            legend: { position: 'bottom' },
            dataLabels: { enabled: true },
        };
    });

    renderIfPresent('#chart-monthly-rr', function () {
        return {
            chart: { type: 'area', height: 300 },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#435ebe'],
            series: [{ name: 'RR', data: monthlyRr.series }],
            xaxis: { categories: monthlyRr.labels },
        };
    });

    renderIfPresent('#chart-monthly-outbound', function () {
        return {
            chart: { type: 'bar', height: 300 },
            dataLabels: { enabled: false },
            colors: ['#1f9d8f'],
            series: [{ name: 'Outbound', data: monthlyOutbound.series }],
            xaxis: { categories: monthlyOutbound.labels },
        };
    });

    renderIfPresent('#chart-monthly-po-value', function () {
        return {
            chart: { type: 'area', height: 300 },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#16a34a'],
            series: [{ name: 'PO Value', data: monthlyPoValue.series }],
            xaxis: { categories: monthlyPoValue.labels },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('id-ID').format(value);
                    },
                },
            },
        };
    });

    renderIfPresent('#chart-users-by-department', function () {
        return {
            chart: { type: 'bar', height: 320 },
            dataLabels: { enabled: false },
            colors: ['#7c3aed'],
            series: [{ name: 'Users', data: usersByDepartment.series }],
            xaxis: { categories: usersByDepartment.labels },
        };
    });
});

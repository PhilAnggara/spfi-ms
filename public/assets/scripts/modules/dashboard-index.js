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

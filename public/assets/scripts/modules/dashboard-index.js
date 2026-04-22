document.addEventListener('DOMContentLoaded', function () {
    const dashboardData = window.dashboardData || {};
    const monthlyPrs = dashboardData.monthly_prs || {};
    const prsStatus = dashboardData.prs_status || {};
    const topSuppliers = dashboardData.top_suppliers || {};
    const poStatus = dashboardData.po_status || {};

    const monthlyPrsLabels = Array.isArray(monthlyPrs.labels) ? monthlyPrs.labels : [];
    const monthlyPrsSeries = Array.isArray(monthlyPrs.series) ? monthlyPrs.series : [];

    const prsStatusLabels = Array.isArray(prsStatus.labels) ? prsStatus.labels : [];
    const prsStatusSeries = Array.isArray(prsStatus.series) ? prsStatus.series : [];

    const topSuppliersLabels = Array.isArray(topSuppliers.labels) ? topSuppliers.labels : [];
    const topSuppliersSeries = Array.isArray(topSuppliers.series) ? topSuppliers.series : [];

    const poStatusLabels = Array.isArray(poStatus.labels) ? poStatus.labels : [];
    const poStatusSeries = Array.isArray(poStatus.series) ? poStatus.series : [];

    const optionsProfileVisit = {
        annotations: {
            position: 'back',
        },
        dataLabels: {
            enabled: false,
        },
        chart: {
            type: 'bar',
            height: 300,
        },
        fill: {
            opacity: 1,
        },
        plotOptions: {},
        series: [
            {
                name: 'PRS',
                data: monthlyPrsSeries,
            },
        ],
        colors: '#435ebe',
        xaxis: {
            categories: monthlyPrsLabels,
        },
    };

    const optionsVisitorsProfile = {
        series: prsStatusSeries,
        labels: prsStatusLabels,
        colors: ['#435ebe', '#55c6e8'],
        chart: {
            type: 'donut',
            width: '100%',
            height: '350px',
        },
        legend: {
            position: 'bottom',
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '30%',
                },
            },
        },
    };

    const optionsTopSuppliers = {
        series: [
            {
                name: 'PO Value',
                data: topSuppliersSeries,
            },
        ],
        chart: {
            type: 'bar',
            height: 360,
        },
        colors: ['#1f9d8f'],
        plotOptions: {
            bar: {
                borderRadius: 6,
                horizontal: true,
            },
        },
        dataLabels: {
            enabled: false,
        },
        xaxis: {
            categories: topSuppliersLabels,
            labels: {
                formatter: function (value) {
                    return new Intl.NumberFormat('id-ID').format(value);
                },
            },
        },
    };

    const optionsPoStatus = {
        series: poStatusSeries,
        labels: poStatusLabels,
        chart: {
            type: 'donut',
            width: '100%',
            height: 320,
        },
        legend: {
            position: 'bottom',
        },
        dataLabels: {
            enabled: true,
        },
    };

    if (window.ApexCharts) {
        const chartProfileVisitEl = document.querySelector('#chart-profile-visit');
        if (chartProfileVisitEl) {
            const chartProfileVisit = new window.ApexCharts(
                chartProfileVisitEl,
                optionsProfileVisit
            );
            chartProfileVisit.render();
        }

        const chartVisitorsProfileEl = document.getElementById('chart-visitors-profile');
        if (chartVisitorsProfileEl) {
            const chartVisitorsProfile = new window.ApexCharts(
                chartVisitorsProfileEl,
                optionsVisitorsProfile
            );
            chartVisitorsProfile.render();
        }

        const chartTopSuppliersEl = document.querySelector('#chart-top-suppliers');
        if (chartTopSuppliersEl) {
            const chartTopSuppliers = new window.ApexCharts(
                chartTopSuppliersEl,
                optionsTopSuppliers
            );
            chartTopSuppliers.render();
        }

        const chartPoStatusEl = document.querySelector('#chart-po-status');
        if (chartPoStatusEl) {
            const chartPoStatus = new window.ApexCharts(
                chartPoStatusEl,
                optionsPoStatus
            );
            chartPoStatus.render();
        }
    }
});

<style>
    @page {
        size: A4 landscape;
        margin: 26mm 10mm 8mm 10mm;
    }
    body {
        margin: 0;
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 8px;
        color: #111;
    }
    .report-header {
        position: fixed;
        top: -22mm;
        left: 0;
        right: 0;
    }
    .data-table { margin-top: 3mm; }
    .report-header-table { width: 100%; border-collapse: collapse; }
    .report-header-table td { padding: 0; vertical-align: top; border: none; }
    .logo-cell { width: 70px; padding-right: 4px !important; }
    .logo { width: 66px; height: auto; display: block; }
    .company-name { font-size: 10px; font-weight: bold; text-transform: uppercase; line-height: 1.2; }
    .doc-title { font-size: 9.5px; font-weight: bold; margin-top: 1px; line-height: 1.2; }
    .header-meta { font-size: 7.5px; line-height: 1.35; margin-top: 2px; }
    .header-meta .muted { font-size: 7px; color: #6b7280; }
    .header-right { width: 110px; }
    .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .data-table td, .data-table th { border: none; padding: 2px 4px; vertical-align: top; }
    .data-table thead { display: table-header-group; }
    .data-table th { font-size: 7px; font-weight: bold; text-align: left; text-transform: uppercase; }
    .data-table thead th.section-head {
        border-bottom: 1.5px solid #374151;
        padding-bottom: 4px;
    }
    .data-table thead th.section-col {
        border-bottom: 1.5px solid #374151;
        padding-bottom: 4px;
    }
    .data-table thead th.col-sep {
        border-right: 7px solid #fff;
        padding-right: 2px;
    }
    .data-table thead th.section-sep {
        border-right: 18px solid #fff;
        padding-right: 4px;
    }
    .data-table thead th.section-start {
        padding-left: 4px;
    }
    .data-table thead th.section-head.section-sep {
        border-right: 18px solid #fff;
        padding-right: 4px;
    }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .muted { color: #6b7280; font-size: 7px; }
    .nowrap-date { white-space: nowrap; }
    .nowrap { white-space: nowrap; }
    .text-wrap-max {
        max-width: 150px;
        white-space: normal;
        word-wrap: break-word;
    }
    .group-title td {
        font-weight: bold;
        padding-top: 6px;
        font-size: 7.5px;
    }
    .total-row td {
        font-weight: bold;
        border-top: 1px solid #374151;
        padding-top: 4px;
    }
</style>
@stack('styles')

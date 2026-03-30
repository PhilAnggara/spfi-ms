(function () {
    const el = document.getElementById('transfer-slip-prefill-data');
    if (!el) {
        return;
    }

    try {
        window.transferSlipCreatePrefill = JSON.parse(el.textContent);
    } catch (_) {
        // ignore parse errors
    }
})();

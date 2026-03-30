window.submitWithReason = function (prsItemId) {
    const reasonText = document.getElementById('reasonText-' + prsItemId).value;
    document.getElementById('reason-' + prsItemId).value = reasonText;
    document.getElementById('form-' + prsItemId).submit();
};

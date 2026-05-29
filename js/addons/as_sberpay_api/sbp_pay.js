(function () {
    'use strict';

    var config = window._asSberpaySbpConfig || {};
    var payload = config.sbpPayload || '';
    var qrWrap = document.getElementById('as_sberpay_sbp_qr_wrap');
    var qrEl = document.getElementById('as_sberpay_sbp_qr');

    if (!payload) {
        return;
    }

    function isMobile() {
        return window.matchMedia('(max-width: 767px)').matches
            || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    if (!isMobile() && qrWrap && qrEl && typeof QRCode !== 'undefined') {
        qrWrap.classList.add('is-desktop');
        new QRCode(qrEl, {
            text: payload,
            width: 220,
            height: 220,
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    if (isMobile()) {
        window.location.href = payload;
    }
})();

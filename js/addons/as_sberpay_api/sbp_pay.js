(function () {
    'use strict';

    var config = window._asSberpaySbpConfig || {};
    var payload = config.sbpPayload || '';
    var qrWrap = document.getElementById('as_sberpay_sbp_qr_wrap');
    var qrEl = document.getElementById('as_sberpay_sbp_qr');
    var statusText = document.getElementById('as_sberpay_sbp_status_text');
    var pollTimer = null;
    var pollStartedAt = 0;
    var pollIntervalMs = config.pollIntervalMs || 3000;
    var pollMaxMs = config.pollMaxMs || 900000;
    var i18n = config.i18n || {};

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

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function setStatusMessage(message) {
        if (statusText && message) {
            statusText.textContent = message;
        }
    }

    function handlePaid(redirectUrl) {
        stopPolling();
        setStatusMessage(i18n.paid || 'Payment confirmed. Redirecting…');
        window.location.href = redirectUrl || config.completeUrl || window.location.href;
    }

    function pollStatus() {
        if (!config.statusUrl) {
            return;
        }

        if (pollStartedAt && (Date.now() - pollStartedAt) > pollMaxMs) {
            stopPolling();
            return;
        }

        fetch(config.statusUrl, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.paid) {
                    return;
                }
                handlePaid(data.redirect);
            })
            .catch(function () {
                // Ignore transient network errors; next poll will retry.
            });
    }

    function startPolling() {
        if (!config.statusUrl || pollTimer !== null) {
            return;
        }

        pollStartedAt = Date.now();
        setStatusMessage(i18n.waiting || statusText && statusText.textContent);
        pollStatus();
        pollTimer = setInterval(pollStatus, pollIntervalMs);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    startPolling();
})();

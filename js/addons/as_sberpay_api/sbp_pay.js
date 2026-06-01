(function () {
    'use strict';

    var config = window._asSberpaySbpConfig || {};
    var payload = config.sbpPayload || '';
    var qrWrap = document.getElementById('as_sberpay_sbp_qr_wrap');
    var qrEl = document.getElementById('as_sberpay_sbp_qr');
    var statusRoot = document.getElementById('as_sberpay_sbp_status');
    var statusText = document.getElementById('as_sberpay_sbp_status_text');
    var pollTimer = null;
    var pollStartedAt = 0;
    var pollIntervalMs = config.pollIntervalMs || 3000;
    var pollMaxMs = config.pollMaxMs || 600000;
    var i18n = config.i18n || {};

    if (!config.statusUrl) {
        return;
    }

    function isMobile() {
        return window.matchMedia('(max-width: 767px)').matches
            || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    if (payload && !isMobile() && qrWrap && qrEl && typeof QRCode !== 'undefined') {
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

    function setStatusMessage(message, state) {
        if (statusText && message) {
            statusText.textContent = message;
        }
        if (statusRoot && state) {
            statusRoot.setAttribute('data-state', state);
        }
    }

    function parseJsonResponse(response) {
        return response.text().then(function (text) {
            if (!text) {
                return null;
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                return null;
            }
        });
    }

    function requestStatus(url) {
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }
            return parseJsonResponse(response);
        });
    }

    function handlePaid(redirectUrl) {
        stopPolling();
        setStatusMessage(i18n.paid || 'Payment confirmed. Redirecting…', 'paid');
        window.location.href = redirectUrl || config.completeUrl || window.location.href;
    }

    function handleExpired(redirectUrl) {
        stopPolling();
        setStatusMessage(i18n.expired || 'Payment time expired. Redirecting…', 'expired');
        window.location.href = redirectUrl || config.detailsUrl || window.location.href;
    }

    function pollStatus() {
        if (pollStartedAt && (Date.now() - pollStartedAt) >= pollMaxMs) {
            stopPolling();
            if (config.expireUrl) {
                requestStatus(config.expireUrl).then(function (data) {
                    if (data && data.paid) {
                        handlePaid(data.redirect);
                        return;
                    }
                    if (data && data.expired) {
                        handleExpired(data.redirect);
                    }
                });
            }
            return;
        }

        requestStatus(config.statusUrl).then(function (data) {
            if (!data) {
                return;
            }
            if (data.paid) {
                handlePaid(data.redirect);
            }
        });
    }

    function startPolling() {
        if (pollTimer !== null) {
            return;
        }

        if (!pollStartedAt) {
            pollStartedAt = Date.now();
        }

        setStatusMessage(i18n.waiting || (statusText && statusText.textContent), 'waiting');
        pollStatus();
        pollTimer = setInterval(pollStatus, pollIntervalMs);
    }

    startPolling();
})();

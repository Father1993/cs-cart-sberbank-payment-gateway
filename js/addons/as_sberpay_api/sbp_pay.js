(function () {
    'use strict';

    var config = window._asSberpaySbpConfig || {};
    var payload = config.sbpPayload || '';
    var qrWrap = document.getElementById('as_sberpay_sbp_qr_wrap');
    var qrEl = document.getElementById('as_sberpay_sbp_qr');
    var banksWrap = document.getElementById('as_sberpay_sbp_banks');
    var banksList = document.getElementById('as_sberpay_sbp_banks_list');
    var fallbackWrap = document.getElementById('as_sberpay_sbp_fallback');
    var fallbackBtn = document.getElementById('as_sberpay_sbp_fallback_btn');
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

    function detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) {
            return 'ios';
        }
        if (/Android/i.test(ua)) {
            return 'android';
        }
        return 'desktop';
    }

    function showQr() {
        if (!payload || !qrWrap || !qrEl || typeof QRCode === 'undefined') {
            return;
        }

        qrWrap.classList.add('is-visible');
        new QRCode(qrEl, {
            text: payload,
            width: isMobile() ? 180 : 220,
            height: isMobile() ? 180 : 220,
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    function showPayloadFallback() {
        if (!payload || !fallbackWrap || !fallbackBtn) {
            return;
        }

        fallbackWrap.classList.add('is-visible');
        fallbackBtn.addEventListener('click', function () {
            window.location.href = payload;
        });
    }

    function renderBanks(members) {
        if (!banksWrap || !banksList || !members || !members.length) {
            showPayloadFallback();
            return;
        }

        banksWrap.classList.add('is-visible');
        members.forEach(function (member) {
            if (!member || !member.url) {
                return;
            }

            var link = document.createElement('a');
            link.className = 'as-sbp-pay__bank';
            link.href = member.url;
            link.rel = 'noopener noreferrer';

            if (member.logo) {
                var logo = document.createElement('img');
                logo.className = 'as-sbp-pay__bank-logo';
                logo.src = member.logo;
                logo.alt = member.name || '';
                link.appendChild(logo);
            }

            var name = document.createElement('span');
            name.textContent = member.name || '';
            link.appendChild(name);
            banksList.appendChild(link);
        });
    }

    function loadBanks() {
        if (!config.membersUrl || !payload) {
            showPayloadFallback();
            return;
        }

        var url = config.membersUrl + (config.membersUrl.indexOf('?') >= 0 ? '&' : '?') + 'platform=' + encodeURIComponent(detectPlatform());

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                renderBanks(data && data.members ? data.members : []);
            })
            .catch(function () {
                showPayloadFallback();
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
            return response.text().then(function (text) {
                try {
                    return text ? JSON.parse(text) : null;
                } catch (error) {
                    return null;
                }
            });
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
            if (data && data.paid) {
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

    if (isMobile()) {
        loadBanks();
    }
    showQr();
    startPolling();
})();

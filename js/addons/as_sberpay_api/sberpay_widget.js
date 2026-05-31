(function () {
    var cfg = window._asSberpaySdkConfig || {};
    var i18n = cfg.i18n || {};
    var pw = window['payment-widget'];
    var root = document.getElementById('as_sberpay_sdk_root');
    var statusMain = document.getElementById('as_sberpay_sdk_status_main');
    var loadingHint = document.getElementById('as_sberpay_sdk_loading_hint');
    var spinner = document.getElementById('as_sberpay_sdk_spinner');
    var actions = document.getElementById('as_sberpay_sdk_actions');
    var leaveBlock = document.getElementById('as_sberpay_sdk_leave');
    var leaveLink = document.getElementById('as_sberpay_sdk_leave_link');
    var retryBtn = document.getElementById('as_sberpay_sdk_retry');
    var widget = null;
    var fallbackTimer = null;

    function clearFallbackTimer() {
        if (fallbackTimer) {
            clearTimeout(fallbackTimer);
            fallbackTimer = null;
        }
    }

    function setRetryButton(enabled, label) {
        if (!retryBtn) {
            return;
        }

        retryBtn.textContent = label || i18n.retry || 'Pay';
        retryBtn.disabled = !enabled;
        retryBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }

    function setUiState(state) {
        if (root) {
            root.setAttribute('data-ui-state', state);
        }

        if (state === 'loading') {
            if (statusMain) {
                statusMain.textContent = i18n.loading || '';
            }
            if (loadingHint) {
                loadingHint.hidden = false;
            }
            if (spinner) {
                spinner.hidden = false;
            }
            if (leaveBlock) {
                leaveBlock.hidden = false;
            }
            setRetryButton(false, i18n.opening || i18n.retry);
            return;
        }

        if (state === 'fallback') {
            if (statusMain) {
                statusMain.textContent = i18n.fallback || '';
            }
            if (loadingHint) {
                loadingHint.hidden = true;
            }
            if (spinner) {
                spinner.hidden = true;
            }
            if (leaveBlock) {
                leaveBlock.hidden = false;
            }
            setRetryButton(true, i18n.manual || i18n.retry);
            return;
        }

        if (state === 'retry') {
            if (statusMain) {
                statusMain.textContent = i18n.cancelled || i18n.fallback || '';
            }
            if (loadingHint) {
                loadingHint.hidden = true;
            }
            if (spinner) {
                spinner.hidden = true;
            }
            if (leaveBlock) {
                leaveBlock.hidden = true;
            }
            setRetryButton(true, i18n.retry);
            return;
        }

        if (state === 'error') {
            if (statusMain) {
                statusMain.textContent = i18n.fallback || '';
            }
            if (loadingHint) {
                loadingHint.hidden = true;
            }
            if (spinner) {
                spinner.hidden = true;
            }
            if (leaveBlock) {
                leaveBlock.hidden = true;
            }
            setRetryButton(true, i18n.retry);
        }
    }

    if (leaveLink) {
        leaveLink.addEventListener('click', function () {
            clearFallbackTimer();
            if (widget && typeof widget.close === 'function') {
                widget.close();
            }
        });
    }

    if (!pw || !pw.createWidget || !cfg.bankInvoiceId) {
        clearFallbackTimer();
        setUiState('error');
        return;
    }

    var params = {
        bankInvoiceId: cfg.bankInvoiceId,
        backUrl: cfg.backUrl,
        isFinishPage: false
    };

    if (cfg.phone) {
        params.phone = cfg.phone;
    }

    widget = pw.createWidget(cfg.env || 'IFT');

    function openWidget() {
        clearFallbackTimer();
        setUiState('loading');

        fallbackTimer = setTimeout(function () {
            fallbackTimer = null;
            setUiState('fallback');
        }, 6000);

        var result = widget.open(params);
        if (!result || typeof result.then !== 'function') {
            return;
        }

        result.then(function (state) {
            clearFallbackTimer();
            if (state === 'cancel') {
                setUiState('retry');
            }
        }).catch(function () {
            clearFallbackTimer();
            setUiState('retry');
        });
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (retryBtn.disabled) {
                return;
            }
            openWidget();
        });
    }

    openWidget();
})();

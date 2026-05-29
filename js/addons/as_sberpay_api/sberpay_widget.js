(function () {
    var cfg = window._asSberpaySdkConfig || {};
    var pw = window['payment-widget'];
    var loading = document.getElementById('as_sberpay_sdk_loading');
    var actions = document.getElementById('as_sberpay_sdk_actions');
    var leaveBlock = document.getElementById('as_sberpay_sdk_leave');
    var leaveLink = document.getElementById('as_sberpay_sdk_leave_link');
    var retryBtn = document.getElementById('as_sberpay_sdk_retry');
    var widget = null;

    function showActions() {
        if (loading) {
            loading.hidden = true;
        }
        if (actions) {
            actions.hidden = false;
        }
        if (leaveBlock) {
            leaveBlock.hidden = true;
        }
    }

    function showLoading() {
        if (loading) {
            loading.hidden = false;
        }
        if (actions) {
            actions.hidden = true;
        }
        if (leaveBlock) {
            leaveBlock.hidden = false;
        }
    }

    if (leaveLink) {
        leaveLink.addEventListener('click', function () {
            if (widget && typeof widget.close === 'function') {
                widget.close();
            }
        });
    }

    if (!pw || !pw.createWidget || !cfg.bankInvoiceId) {
        showActions();
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
        showLoading();

        var result = widget.open(params);
        if (!result || typeof result.then !== 'function') {
            return;
        }

        result.then(function (state) {
            if (state === 'cancel') {
                showActions();
            }
        }).catch(function () {
            showActions();
        });
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', function (event) {
            event.preventDefault();
            openWidget();
        });
    }

    openWidget();
})();

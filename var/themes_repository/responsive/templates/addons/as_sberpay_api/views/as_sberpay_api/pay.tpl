{capture name="mainbox_title"}{__("addons.as_sberpay_api.sdk_pay_title")}{/capture}

{assign var="sdk_host" value=$site_host|default:$config.http_host}
{assign var="sdk_subtitle" value=__("addons.as_sberpay_api.sdk_pay_subtitle")|replace:"[host]":$sdk_host}
{assign var="sdk_recipient" value=__("addons.as_sberpay_api.sdk_pay_recipient")|replace:"[host]":$sdk_host}
{assign var="sdk_benefit_onsite" value=__("addons.as_sberpay_api.sdk_pay_benefit_onsite")|replace:"[host]":$sdk_host}
{assign var="sdk_retry_label" value=__("addons.as_sberpay_api.sdk_pay_retry")|replace:"[amount]":$order_total_formatted}

<style>
.as-sberpay-pay { max-width: 520px; margin: 0 auto; padding: 8px 0 32px; }
.as-sberpay-pay__card { background: #fff; border: 1px solid #e8eaed; border-radius: 16px; padding: 28px 24px 24px; box-shadow: 0 8px 32px rgba(15, 23, 42, .08); }
.as-sberpay-pay__head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.as-sberpay-pay__logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #21a038 0%, #0d7a28 100%); color: #fff; display: flex; align-items: center; justify-content: center; font: 700 13px/1 Arial, sans-serif; flex-shrink: 0; }
.as-sberpay-pay__title { margin: 0; font: 600 20px/1.25 Arial, sans-serif; color: #111827; }
.as-sberpay-pay__subtitle { margin: 4px 0 0; font: 400 14px/1.4 Arial, sans-serif; color: #6b7280; }
.as-sberpay-pay__summary { padding: 16px; margin-bottom: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.as-sberpay-pay__summary-row { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.as-sberpay-pay__summary-row + .as-sberpay-pay__summary-row { margin-top: 14px; padding-top: 14px; border-top: 1px solid #eef2f7; }
.as-sberpay-pay__summary dt { margin: 0 0 4px; font: 400 13px/1.3 Arial, sans-serif; color: #6b7280; }
.as-sberpay-pay__summary dd { margin: 0; font: 600 16px/1.3 Arial, sans-serif; color: #111827; }
.as-sberpay-pay__amount { margin: 0; }
.as-sberpay-pay__amount-inner { display: inline-flex; flex-wrap: nowrap; align-items: baseline; max-width: 100%; white-space: nowrap; font-variant-numeric: tabular-nums; }
.as-sberpay-pay__amount-rubles { font: 600 26px/1.1 Arial, sans-serif; color: #111827; }
.as-sberpay-pay__amount-kopecks { font: 600 18px/1.1 Arial, sans-serif; color: #374151; }
.as-sberpay-pay__amount-currency { font: 600 20px/1.1 Arial, sans-serif; color: #111827; margin-left: 4px; }
.as-sberpay-pay__recipient { margin: 6px 0 0; font: 400 13px/1.4 Arial, sans-serif; color: #6b7280; }
.as-sberpay-pay__status { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font: 500 14px/1.4 Arial, sans-serif; }
.as-sberpay-pay__status-text { flex: 1; }
.as-sberpay-pay__status-hint { display: block; margin-top: 4px; font: 400 13px/1.45 Arial, sans-serif; color: #64748b; }
.as-sberpay-pay__spinner { width: 18px; height: 18px; margin-top: 2px; border: 2px solid #dbeafe; border-top-color: #2563eb; border-radius: 50%; animation: as-sberpay-spin .8s linear infinite; flex-shrink: 0; }
.as-sberpay-pay__benefits { margin: 16px 0 0; padding: 0; list-style: none; }
.as-sberpay-pay__benefits li { position: relative; padding: 0 0 8px 22px; font: 400 13px/1.45 Arial, sans-serif; color: #4b5563; }
.as-sberpay-pay__benefits li:before { content: ""; position: absolute; left: 0; top: 5px; width: 10px; height: 6px; border-left: 2px solid #21a038; border-bottom: 2px solid #21a038; transform: rotate(-45deg); }
.as-sberpay-pay__actions { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: stretch; }
.as-sberpay-pay__actions .ty-btn { margin: 0; min-height: 44px; }
.as-sberpay-pay__cta { background: #21a038 !important; border-color: #21a038 !important; color: #fff !important; font-weight: 600; padding-left: 20px; padding-right: 20px; }
.as-sberpay-pay__cta:hover:not(:disabled) { background: #1a8a2f !important; border-color: #1a8a2f !important; }
.as-sberpay-pay__cta:disabled { opacity: .65; cursor: not-allowed; }
.as-sberpay-pay__footer { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eef2f7; }
.as-sberpay-pay__footer .ty-btn { margin: 0; color: #64748b !important; }
.as-sberpay-pay__footer .ty-btn:hover { color: #334155 !important; }
.as-sberpay-pay__note { margin: 16px 0 0; font: 400 13px/1.5 Arial, sans-serif; color: #64748b; }
.as-sberpay-pay[data-ui-state="fallback"] .as-sberpay-pay__status,
.as-sberpay-pay[data-ui-state="retry"] .as-sberpay-pay__status,
.as-sberpay-pay[data-ui-state="error"] .as-sberpay-pay__status { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.as-sberpay-pay[data-ui-state="fallback"] .as-sberpay-pay__spinner,
.as-sberpay-pay[data-ui-state="retry"] .as-sberpay-pay__spinner,
.as-sberpay-pay[data-ui-state="error"] .as-sberpay-pay__spinner { display: none; }
.as-sberpay-pay[data-ui-state="fallback"] .as-sberpay-pay__status-hint,
.as-sberpay-pay[data-ui-state="retry"] .as-sberpay-pay__status-hint,
.as-sberpay-pay[data-ui-state="error"] .as-sberpay-pay__status-hint { display: none; }
.as-sberpay-pay[data-ui-state="retry"] .as-sberpay-pay__footer,
.as-sberpay-pay[data-ui-state="error"] .as-sberpay-pay__footer { display: none; }
[hidden] { display: none !important; }
@keyframes as-sberpay-spin { to { transform: rotate(360deg); } }
@media (max-width: 767px) {
    .as-sberpay-pay__card { padding: 22px 18px 18px; border-radius: 14px; }
    .as-sberpay-pay__summary-row { flex-direction: column; gap: 8px; }
    .as-sberpay-pay__amount-rubles { font-size: 22px; }
    .as-sberpay-pay__amount-kopecks { font-size: 16px; }
    .as-sberpay-pay__amount-currency { font-size: 18px; }
    .as-sberpay-pay__actions { flex-direction: column; }
    .as-sberpay-pay__actions .ty-btn { width: 100%; justify-content: center; }
}
</style>

<div class="as-sberpay-pay" id="as_sberpay_sdk_root" data-ui-state="loading">
    <div class="as-sberpay-pay__card">
        <div class="as-sberpay-pay__head">
            <div class="as-sberpay-pay__logo" aria-hidden="true">Pay</div>
            <div>
                <h2 class="as-sberpay-pay__title">{__("addons.as_sberpay_api.sdk_pay_title")}</h2>
                <p class="as-sberpay-pay__subtitle">{$sdk_subtitle nofilter}</p>
            </div>
        </div>

        <dl class="as-sberpay-pay__summary">
            <div class="as-sberpay-pay__summary-row">
                <div>
                    <dt>{__("addons.as_sberpay_api.sdk_pay_order")}</dt>
                    <dd>#{$order_id}</dd>
                    <p class="as-sberpay-pay__recipient">{$sdk_recipient nofilter}</p>
                </div>
            </div>
            <div class="as-sberpay-pay__summary-row">
                <div>
                    <dt>{__("addons.as_sberpay_api.sdk_pay_amount")}</dt>
                    <dd class="as-sberpay-pay__amount">
                        <span class="as-sberpay-pay__amount-inner">
                            <span class="as-sberpay-pay__amount-rubles">{$order_amount.rubles|default:$order_total_formatted}</span>{if $order_amount.has_kopecks}<span class="as-sberpay-pay__amount-kopecks">{$order_amount.kopecks}</span>{/if}<span class="as-sberpay-pay__amount-currency"> {$order_amount.currency|default:"₽"}</span>
                        </span>
                    </dd>
                </div>
            </div>
        </dl>

        <div class="as-sberpay-pay__status" id="as_sberpay_sdk_loading" aria-live="polite">
            <span class="as-sberpay-pay__spinner" id="as_sberpay_sdk_spinner" aria-hidden="true"></span>
            <span class="as-sberpay-pay__status-text">
                <span id="as_sberpay_sdk_status_main">{__("addons.as_sberpay_api.sdk_pay_loading")}</span>
                <span class="as-sberpay-pay__status-hint" id="as_sberpay_sdk_loading_hint">{__("addons.as_sberpay_api.sdk_pay_loading_hint")}</span>
            </span>
        </div>

        <ul class="as-sberpay-pay__benefits">
            <li>{$sdk_benefit_onsite nofilter}</li>
            <li>{__("addons.as_sberpay_api.sdk_pay_benefit_secure")}</li>
        </ul>

        <div class="as-sberpay-pay__actions" id="as_sberpay_sdk_actions">
            <button type="button" class="ty-btn ty-btn__primary as-sberpay-pay__cta" id="as_sberpay_sdk_retry" disabled="disabled" aria-disabled="true">{__("addons.as_sberpay_api.sdk_pay_retry_opening")}</button>
            <a href="{"orders.details?order_id=`$order_id`"|fn_url}" class="ty-btn ty-btn__secondary">{__("order_details")}</a>
        </div>

        <div class="as-sberpay-pay__footer" id="as_sberpay_sdk_leave">
            <a href="{$cancel_url}" class="ty-btn ty-btn__text" id="as_sberpay_sdk_leave_link">{__("addons.as_sberpay_api.sdk_pay_back")}</a>
        </div>

        <p class="as-sberpay-pay__note">{__("addons.as_sberpay_api.sdk_pay_note")}</p>
    </div>
</div>

<script data-no-defer="true">
    window._asSberpaySdkConfig = {
        bankInvoiceId: '{$bank_invoice_id|escape:javascript nofilter}',
        backUrl: '{$back_url|escape:javascript nofilter}',
        cancelUrl: '{$cancel_url|escape:javascript nofilter}',
        env: '{$widget_env|escape:javascript nofilter}',
        amountLabel: '{$order_total_formatted|escape:javascript nofilter}',
        siteHost: '{$sdk_host|escape:javascript nofilter}',
        i18n: {
            loading: '{__("addons.as_sberpay_api.sdk_pay_loading")|escape:javascript nofilter}',
            loadingHint: '{__("addons.as_sberpay_api.sdk_pay_loading_hint")|escape:javascript nofilter}',
            fallback: '{__("addons.as_sberpay_api.sdk_pay_fallback")|escape:javascript nofilter}',
            cancelled: '{__("addons.as_sberpay_api.sdk_pay_cancelled")|escape:javascript nofilter}',
            retry: '{$sdk_retry_label|escape:javascript nofilter}',
            opening: '{__("addons.as_sberpay_api.sdk_pay_retry_opening")|escape:javascript nofilter}',
            manual: '{__("addons.as_sberpay_api.sdk_pay_retry_manual")|escape:javascript nofilter}'
        }{if $sdk_phone},
        phone: '{$sdk_phone|escape:javascript nofilter}'{/if}
    };
</script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/vendor/sberpay-widget.umd.cjs?v={$sdk_assets_version}"></script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/sberpay_widget.js?v={$sdk_assets_version}"></script>

{capture name="mainbox_title"}{__("addons.as_sberpay_api.sdk_pay_title")}{/capture}

<style>
.as-sberpay-pay { max-width: 520px; margin: 0 auto; padding: 8px 0 32px; }
.as-sberpay-pay__card { background: #fff; border: 1px solid #e8eaed; border-radius: 16px; padding: 28px 24px 24px; box-shadow: 0 8px 32px rgba(15, 23, 42, .08); }
.as-sberpay-pay__head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.as-sberpay-pay__logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #21a038 0%, #0d7a28 100%); color: #fff; display: flex; align-items: center; justify-content: center; font: 700 13px/1 Arial, sans-serif; flex-shrink: 0; }
.as-sberpay-pay__title { margin: 0; font: 600 20px/1.25 Arial, sans-serif; color: #111827; }
.as-sberpay-pay__subtitle { margin: 4px 0 0; font: 400 14px/1.4 Arial, sans-serif; color: #6b7280; }
.as-sberpay-pay__summary { display: flex; justify-content: space-between; gap: 16px; padding: 16px; margin-bottom: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.as-sberpay-pay__summary dt { margin: 0 0 4px; font: 400 13px/1.3 Arial, sans-serif; color: #6b7280; }
.as-sberpay-pay__summary dd { margin: 0; font: 600 16px/1.3 Arial, sans-serif; color: #111827; }
.as-sberpay-pay__summary dd.as-sberpay-pay__amount { font-size: 22px; color: #111827; }
.as-sberpay-pay__loader { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font: 500 14px/1.4 Arial, sans-serif; }
.as-sberpay-pay__spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #21a038; border-radius: 50%; animation: as-sberpay-spin .8s linear infinite; flex-shrink: 0; }
.as-sberpay-pay__benefits { margin: 16px 0 0; padding: 0; list-style: none; }
.as-sberpay-pay__benefits li { position: relative; padding: 0 0 8px 22px; font: 400 13px/1.45 Arial, sans-serif; color: #4b5563; }
.as-sberpay-pay__benefits li:before { content: ""; position: absolute; left: 0; top: 5px; width: 10px; height: 6px; border-left: 2px solid #21a038; border-bottom: 2px solid #21a038; transform: rotate(-45deg); }
.as-sberpay-pay__actions { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.as-sberpay-pay__footer { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eef2f7; display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; }
.as-sberpay-pay__footer .ty-btn { margin: 0; }
.as-sberpay-pay__note { margin: 16px 0 0; font: 400 12px/1.5 Arial, sans-serif; color: #9ca3af; }
@keyframes as-sberpay-spin { to { transform: rotate(360deg); } }
@media (max-width: 767px) {
    .as-sberpay-pay__card { padding: 22px 18px 18px; border-radius: 14px; }
    .as-sberpay-pay__summary { flex-direction: column; gap: 12px; }
}
</style>

<div class="as-sberpay-pay" id="as_sberpay_sdk_root">
    <div class="as-sberpay-pay__card">
        <div class="as-sberpay-pay__head">
            <div class="as-sberpay-pay__logo" aria-hidden="true">Pay</div>
            <div>
                <h2 class="as-sberpay-pay__title">{__("addons.as_sberpay_api.sdk_pay_title")}</h2>
                <p class="as-sberpay-pay__subtitle">{__("addons.as_sberpay_api.sdk_pay_subtitle")}</p>
            </div>
        </div>

        <dl class="as-sberpay-pay__summary">
            <div>
                <dt>{__("addons.as_sberpay_api.sdk_pay_order")}</dt>
                <dd>#{$order_id}</dd>
            </div>
            <div>
                <dt>{__("addons.as_sberpay_api.sdk_pay_amount")}</dt>
                <dd class="as-sberpay-pay__amount">{$order_total_formatted}</dd>
            </div>
        </dl>

        <div class="as-sberpay-pay__loader" id="as_sberpay_sdk_loading">
            <span class="as-sberpay-pay__spinner" aria-hidden="true"></span>
            <span>{__("addons.as_sberpay_api.sdk_pay_loading")}</span>
        </div>

        <ul class="as-sberpay-pay__benefits">
            <li>{__("addons.as_sberpay_api.sdk_pay_benefit_onsite")}</li>
            <li>{__("addons.as_sberpay_api.sdk_pay_benefit_secure")}</li>
        </ul>

        <div class="as-sberpay-pay__actions" id="as_sberpay_sdk_actions" hidden="hidden">
            <button type="button" class="ty-btn ty-btn__primary" id="as_sberpay_sdk_retry">{__("addons.as_sberpay_api.sdk_pay_retry")}</button>
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
        env: '{$widget_env|escape:javascript nofilter}'{if $sdk_phone},
        phone: '{$sdk_phone|escape:javascript nofilter}'{/if}
    };
</script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/vendor/sberpay-widget.umd.cjs?v={$sdk_assets_version}"></script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/sberpay_widget.js?v={$sdk_assets_version}"></script>

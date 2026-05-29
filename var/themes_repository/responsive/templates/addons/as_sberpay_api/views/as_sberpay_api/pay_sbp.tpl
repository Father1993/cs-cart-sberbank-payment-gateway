{capture name="mainbox_title"}{__("addons.as_sberpay_api.sbp_pay_title")}{/capture}

<style>
.as-sbp-pay { max-width: 520px; margin: 0 auto; padding: 8px 0 32px; }
.as-sbp-pay__card { background: #fff; border: 1px solid #e8eaed; border-radius: 16px; padding: 28px 24px 24px; box-shadow: 0 8px 32px rgba(15, 23, 42, .08); }
.as-sbp-pay__head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.as-sbp-pay__logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #fff; display: flex; align-items: center; justify-content: center; font: 700 11px/1 Arial, sans-serif; flex-shrink: 0; letter-spacing: -.02em; }
.as-sbp-pay__title { margin: 0; font: 600 20px/1.25 Arial, sans-serif; color: #111827; }
.as-sbp-pay__subtitle { margin: 4px 0 0; font: 400 14px/1.4 Arial, sans-serif; color: #6b7280; }
.as-sbp-pay__summary { display: flex; justify-content: space-between; gap: 16px; padding: 16px; margin-bottom: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.as-sbp-pay__summary dt { margin: 0 0 4px; font: 400 13px/1.3 Arial, sans-serif; color: #6b7280; }
.as-sbp-pay__summary dd { margin: 0; font: 600 16px/1.3 Arial, sans-serif; color: #111827; }
.as-sbp-pay__summary dd.as-sbp-pay__amount { font-size: 22px; color: #111827; }
.as-sbp-pay__qr-wrap { display: none; text-align: center; margin-bottom: 20px; }
.as-sbp-pay__qr-wrap.is-desktop { display: block; }
.as-sbp-pay__qr { display: inline-block; padding: 12px; border-radius: 12px; background: #fff; border: 1px solid #eef2f7; }
.as-sbp-pay__hint { margin: 12px 0 0; font: 400 14px/1.45 Arial, sans-serif; color: #4b5563; }
.as-sbp-pay__actions { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: center; }
.as-sbp-pay__actions .ty-btn { margin: 0; }
.as-sbp-pay__footer { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eef2f7; display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; }
.as-sbp-pay__footer .ty-btn { margin: 0; }
.as-sbp-pay__note { margin: 16px 0 0; font: 400 12px/1.5 Arial, sans-serif; color: #9ca3af; }
@media (max-width: 767px) {
    .as-sbp-pay__card { padding: 22px 18px 18px; border-radius: 14px; }
    .as-sbp-pay__summary { flex-direction: column; gap: 12px; }
    .as-sbp-pay__qr-wrap.is-desktop { display: none; }
}
</style>

<div class="as-sbp-pay" id="as_sberpay_sbp_root">
    <div class="as-sbp-pay__card">
        <div class="as-sbp-pay__head">
            <div class="as-sbp-pay__logo" aria-hidden="true">СБП</div>
            <div>
                <h2 class="as-sbp-pay__title">{__("addons.as_sberpay_api.sbp_pay_title")}</h2>
                <p class="as-sbp-pay__subtitle">{__("addons.as_sberpay_api.sbp_pay_subtitle")}</p>
            </div>
        </div>

        <dl class="as-sbp-pay__summary">
            <div>
                <dt>{__("addons.as_sberpay_api.sdk_pay_order")}</dt>
                <dd>#{$order_id}</dd>
            </div>
            <div>
                <dt>{__("addons.as_sberpay_api.sdk_pay_amount")}</dt>
                <dd class="as-sbp-pay__amount">{$order_total_formatted}</dd>
            </div>
        </dl>

        <div class="as-sbp-pay__qr-wrap" id="as_sberpay_sbp_qr_wrap">
            <div class="as-sbp-pay__qr" id="as_sberpay_sbp_qr"></div>
            <p class="as-sbp-pay__hint">{__("addons.as_sberpay_api.sbp_pay_scan_hint")}</p>
        </div>

        <div class="as-sbp-pay__actions">
            <a href="{$sbp_payload|escape:url}" target="_blank" rel="noopener noreferrer" class="ty-btn ty-btn__primary" id="as_sberpay_sbp_open_link">{__("addons.as_sberpay_api.sbp_pay_open")}</a>
        </div>

        <div class="as-sbp-pay__footer">
            <a href="{$cancel_url}" class="ty-btn ty-btn__text">{__("addons.as_sberpay_api.sdk_pay_back")}</a>
        </div>

        <p class="as-sbp-pay__note">{__("addons.as_sberpay_api.sbp_pay_note")}</p>
    </div>
</div>

<script data-no-defer="true">
    window._asSberpaySbpConfig = {
        sbpPayload: '{$sbp_payload|escape:javascript nofilter}'
    };
</script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/vendor/qrcode.min.js?v={$sbp_assets_version}"></script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/sbp_pay.js?v={$sbp_assets_version}"></script>

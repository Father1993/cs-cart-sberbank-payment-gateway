{capture name="mainbox_title"}{__("addons.as_sberpay_api.sbp_pay_title")}{/capture}

<style>
.as-sbp-pay { max-width: 520px; margin: 0 auto; padding: 8px 0 32px; }
.as-sbp-pay__card { background: #fff; border: 1px solid #e8eaed; border-radius: 16px; padding: 28px 24px 24px; box-shadow: 0 8px 32px rgba(15, 23, 42, .08); }
.as-sbp-pay__head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.as-sbp-pay__logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #fff; display: flex; align-items: center; justify-content: center; font: 700 11px/1 Arial, sans-serif; flex-shrink: 0; letter-spacing: -.02em; }
.as-sbp-pay__logo--image { background: #fff; padding: 4px; border: 1px solid #eef2f7; }
.as-sbp-pay__logo-image { width: 100%; height: 100%; object-fit: contain; display: block; border-radius: 8px; }
.as-sbp-pay__title { margin: 0; font: 600 20px/1.25 Arial, sans-serif; color: #111827; }
.as-sbp-pay__subtitle { margin: 4px 0 0; font: 400 14px/1.4 Arial, sans-serif; color: #6b7280; }
.as-sbp-pay__summary { display: flex; justify-content: space-between; gap: 16px; padding: 16px; margin-bottom: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.as-sbp-pay__summary dt { margin: 0 0 4px; font: 400 13px/1.3 Arial, sans-serif; color: #6b7280; }
.as-sbp-pay__summary dd { margin: 0; font: 600 16px/1.3 Arial, sans-serif; color: #111827; }
.as-sbp-pay__summary dd.as-sbp-pay__amount { font-size: 22px; color: #111827; }
.as-sbp-pay__status { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; margin-bottom: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font: 500 14px/1.4 Arial, sans-serif; }
.as-sbp-pay__spinner { width: 18px; height: 18px; margin-top: 2px; border: 2px solid #dbeafe; border-top-color: #2563eb; border-radius: 50%; animation: as-sbp-spin .8s linear infinite; flex-shrink: 0; }
.as-sbp-pay__status[data-state="paid"] .as-sbp-pay__spinner,
.as-sbp-pay__status[data-state="expired"] .as-sbp-pay__spinner { display: none; }
.as-sbp-pay__status[data-state="paid"] { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.as-sbp-pay__status[data-state="expired"] { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.as-sbp-pay__qr-wrap { display: none; text-align: center; margin-bottom: 20px; }
.as-sbp-pay__qr-wrap.is-visible { display: block; }
.as-sbp-pay__qr { display: inline-block; padding: 12px; border-radius: 12px; background: #fff; border: 1px solid #eef2f7; }
.as-sbp-pay__hint { margin: 12px 0 0; font: 400 14px/1.45 Arial, sans-serif; color: #4b5563; }
.as-sbp-pay__banks { display: none; margin-bottom: 20px; }
.as-sbp-pay__banks.is-visible { display: block; }
.as-sbp-pay__banks-title { margin: 0 0 12px; font: 600 15px/1.3 Arial, sans-serif; color: #111827; }
.as-sbp-pay__banks-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.as-sbp-pay__bank { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; color: #111827; text-decoration: none; font: 500 13px/1.3 Arial, sans-serif; }
.as-sbp-pay__bank:hover { border-color: #2563eb; background: #f8fafc; }
.as-sbp-pay__bank-logo { width: 32px; height: 32px; border-radius: 8px; object-fit: contain; flex-shrink: 0; background: #fff; }
.as-sbp-pay__fallback { display: none; margin-bottom: 16px; }
.as-sbp-pay__fallback.is-visible { display: block; }
.as-sbp-pay__fallback-btn { display: block; width: 100%; padding: 12px 16px; border: 0; border-radius: 12px; background: #2563eb; color: #fff; font: 600 15px/1.3 Arial, sans-serif; text-align: center; cursor: pointer; }
.as-sbp-pay__footer { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eef2f7; }
.as-sbp-pay__footer .ty-btn { margin: 0; }
.as-sbp-pay__note { margin: 16px 0 0; font: 400 12px/1.5 Arial, sans-serif; color: #9ca3af; }
@media (max-width: 767px) {
    .as-sbp-pay__card { padding: 22px 18px 18px; border-radius: 14px; }
    .as-sbp-pay__summary { flex-direction: column; gap: 12px; }
    .as-sbp-pay__banks-list { grid-template-columns: 1fr; }
}
@keyframes as-sbp-spin { to { transform: rotate(360deg); } }
</style>

<div class="as-sbp-pay" id="as_sberpay_sbp_root">
    <div class="as-sbp-pay__card">
        <div class="as-sbp-pay__head">
            <div class="as-sbp-pay__logo{if $order_info.payment_method.image} as-sbp-pay__logo--image{/if}" aria-hidden="true">
                {if $order_info.payment_method.image}
                    {include file="common/image.tpl" obj_id=$order_info.payment_id images=$order_info.payment_method.image class="as-sbp-pay__logo-image" show_no_image=false}
                {else}
                    СБП
                {/if}
            </div>
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

        <div class="as-sbp-pay__status" id="as_sberpay_sbp_status" aria-live="polite">
            <span class="as-sbp-pay__spinner" aria-hidden="true"></span>
            <span class="as-sbp-pay__status-text" id="as_sberpay_sbp_status_text">{__("addons.as_sberpay_api.sbp_pay_waiting")}</span>
        </div>

        <div class="as-sbp-pay__qr-wrap" id="as_sberpay_sbp_qr_wrap">
            <div class="as-sbp-pay__qr" id="as_sberpay_sbp_qr"></div>
            <p class="as-sbp-pay__hint">{__("addons.as_sberpay_api.sbp_pay_scan_hint")}</p>
        </div>

        <div class="as-sbp-pay__banks" id="as_sberpay_sbp_banks">
            <p class="as-sbp-pay__banks-title">{__("addons.as_sberpay_api.sbp_pay_choose_bank")}</p>
            <div class="as-sbp-pay__banks-list" id="as_sberpay_sbp_banks_list"></div>
        </div>

        <div class="as-sbp-pay__fallback" id="as_sberpay_sbp_fallback">
            <button type="button" class="as-sbp-pay__fallback-btn" id="as_sberpay_sbp_fallback_btn">{__("addons.as_sberpay_api.sbp_pay_open")}</button>
        </div>

        <div class="as-sbp-pay__footer">
            <a href="{$cancel_url}" class="ty-btn ty-btn__text">{__("addons.as_sberpay_api.sdk_pay_back")}</a>
        </div>

        <p class="as-sbp-pay__note">{__("addons.as_sberpay_api.sbp_pay_desktop_note")}</p>
    </div>
</div>

<script data-no-defer="true">
    window._asSberpaySbpConfig = {
        orderId: {$order_id},
        sbpPayload: '{$sbp_payload|escape:javascript nofilter}',
        statusUrl: '{$status_url|escape:javascript nofilter}',
        membersUrl: '{$members_url|escape:javascript nofilter}',
        expireUrl: '{$expire_url|escape:javascript nofilter}',
        completeUrl: '{$complete_url|escape:javascript nofilter}',
        detailsUrl: '{"orders.details?order_id=`$order_id`"|fn_url|escape:javascript nofilter}',
        pollIntervalMs: 3000,
        pollMaxMs: 600000,
        i18n: {
            waiting: '{__("addons.as_sberpay_api.sbp_pay_waiting")|escape:javascript nofilter}',
            paid: '{__("addons.as_sberpay_api.sbp_pay_paid_redirect")|escape:javascript nofilter}',
            expired: '{__("addons.as_sberpay_api.sbp_pay_expired_redirect")|escape:javascript nofilter}',
            chooseBank: '{__("addons.as_sberpay_api.sbp_pay_choose_bank")|escape:javascript nofilter}'
        }
    };
</script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/vendor/qrcode.min.js?v={$sbp_assets_version}"></script>
<script data-no-defer="true" src="{$config.current_location}/js/addons/as_sberpay_api/sbp_pay.js?v={$sbp_assets_version}"></script>

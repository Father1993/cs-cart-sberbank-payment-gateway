{if $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && $order_info.sber_payment_meta
    && ($order_info.sber_payment_meta.fiscal_snapshot
        || $order_info.sber_payment_meta.closing_receipt
        || ($order_info.sber_payment_meta.refund.status|default:"") == "succeeded")}
    <div class="control-group shift-top">
        <div class="control-label">{__("addons.as_sberpay_api.receipt_status_title")}</div>
        <div class="controls">
            {if $order_info.sber_payment_meta.fiscal_snapshot}
                <div>{__("addons.as_sberpay_api.receipt_prepayment_registered")}</div>
            {/if}
            {assign var="closing_receipt" value=$order_info.sber_payment_meta.closing_receipt|default:[]}
            {assign var="closing_status" value=$closing_receipt.status|default:"not_sent"}
            <div>
                {__("addons.as_sberpay_api.receipt_closing_title")}:
                {if $closing_status == "succeeded"}
                    {__("addons.as_sberpay_api.receipt_status_succeeded")}
                {elseif $closing_status == "pending"}
                    {__("addons.as_sberpay_api.receipt_status_pending")}
                {elseif $closing_status == "failed"}
                    {__("addons.as_sberpay_api.receipt_status_failed")}
                {else}
                    {__("addons.as_sberpay_api.receipt_status_not_sent")}
                {/if}
            </div>
            {if $closing_receipt.ofd_receipt_status}
                <div class="muted">
                    {if $closing_receipt.ofd_receipt_status == 1}
                        {__("addons.as_sberpay_api.receipt_ofd_status_1")}
                    {elseif $closing_receipt.ofd_receipt_status == 2}
                        {__("addons.as_sberpay_api.receipt_ofd_status_2")}
                    {elseif $closing_receipt.ofd_receipt_status == 3}
                        {__("addons.as_sberpay_api.receipt_ofd_status_3")}
                    {elseif $closing_receipt.ofd_receipt_status == 4}
                        {__("addons.as_sberpay_api.receipt_ofd_status_4")}
                    {elseif $closing_receipt.ofd_receipt_status == 5}
                        {__("addons.as_sberpay_api.receipt_ofd_status_5")}
                    {/if}
                </div>
            {/if}
            {if $closing_receipt.updated_at}
                <div class="muted">{$closing_receipt.updated_at|date_format:"`$settings.Appearance.date_format`, `$settings.Appearance.time_format`"}</div>
            {/if}
            {if ($order_info.sber_payment_meta.refund.status|default:"") == "succeeded"}
                <div>{__("addons.as_sberpay_api.receipt_refund_succeeded")}</div>
            {/if}
        </div>
    </div>
{/if}

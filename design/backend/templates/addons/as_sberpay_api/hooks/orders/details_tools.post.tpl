{if $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)
    && $order_info.sber_receipt_status_view.show
    && $order_info.sber_receipt_status_view.can_refresh}
    <li>{btn type="list" text=__("addons.as_sberpay_api.refresh_receipt_status") href=$order_info.sber_receipt_status_view.refresh_href class="cm-post" method="POST"}</li>
{/if}
{if $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)
    && ($order_info.sber_payment_meta.refund.status|default:"") != "succeeded"}
    <li>{btn type="list" text=__("addons.as_sberpay_api.refund_money") href="as_sberpay_api.refund?order_id=`$order_info.order_id`" class="cm-post cm-confirm" method="POST"}</li>
{/if}

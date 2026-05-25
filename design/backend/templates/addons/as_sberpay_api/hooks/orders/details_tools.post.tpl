{$sber_tools = $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)}
{$sber_refresh = $sber_tools
    && $order_info.sber_receipt_status_view.show
    && $order_info.sber_receipt_status_view.can_refresh}
{$sber_refund = $sber_tools
    && ($order_info.sber_payment_meta.refund.status|default:"") != "succeeded"}
{if $sber_refresh || $sber_refund}
    <li class="divider"></li>
{/if}
{if $sber_refresh}
    <li>{btn type="list" text=__("addons.as_sberpay_api.refresh_receipt_status") href=$order_info.sber_receipt_status_view.refresh_href class="cm-post" method="POST"}</li>
{/if}
{if $sber_refund}
    <li>{btn type="list" text=__("addons.as_sberpay_api.refund_money") href="as_sberpay_api.refund?order_id=`$order_info.order_id`" class="cm-post cm-confirm text-danger" method="POST"}</li>
{/if}

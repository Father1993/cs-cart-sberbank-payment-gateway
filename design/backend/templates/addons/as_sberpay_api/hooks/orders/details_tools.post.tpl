{if $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)
    && $order_info.sber_payment_meta
    && ($order_info.sber_payment_meta.fiscal_snapshot
        || $order_info.sber_payment_meta.closing_receipt
        || ($order_info.sber_payment_meta.refund.status|default:"") == "succeeded")}
    {include
        file="buttons/button.tpl"
        but_role="action"
        but_href="as_sberpay_api.receipt_status?order_id=`$order_info.order_id`"
        but_text=__("addons.as_sberpay_api.refresh_receipt_status")
        but_meta="btn cm-post"
    }
{/if}
{if $order_info.payment_info.transaction_id
    && $order_info.payment_method.processor == "SberPay API"
    && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)
    && ($order_info.sber_payment_meta.refund.status|default:"") != "succeeded"}
    {include
        file="buttons/button.tpl"
        but_role="action"
        but_href="as_sberpay_api.refund?order_id=`$order_info.order_id`"
        but_text=__("addons.as_sberpay_api.refund_money")
        but_meta="btn btn-danger cm-post cm-confirm"
    }
{/if}

{if $order_info.sber_receipt_status_view.show}
    {$rv = $order_info.sber_receipt_status_view}
    <div class="control-group shift-top">
        <div class="control-label">{__("addons.as_sberpay_api.receipt_status_title")}</div>
        <div class="controls">
            {if $rv.has_prepayment}
                <div>
                    {__("addons.as_sberpay_api.receipt_prepayment_title")}:
                    <span class="label label-success">{__("addons.as_sberpay_api.receipt_status_succeeded")}</span>
                </div>
            {/if}
            {if $rv.closing}
                <div>
                    {__("addons.as_sberpay_api.receipt_closing_title")}:
                    {if $rv.closing.label_class}
                        <span class="label {$rv.closing.label_class}">{$rv.closing.status_label}</span>
                    {else}
                        {$rv.closing.status_label}
                    {/if}
                    {if $rv.closing.updated_at_formatted}
                        <span class="muted">({$rv.closing.updated_at_formatted})</span>
                    {/if}
                </div>
            {/if}
            {if $rv.has_refund_receipt}
                <div>
                    {__("addons.as_sberpay_api.receipt_refund_title")}:
                    <span class="label label-success">{__("addons.as_sberpay_api.receipt_status_succeeded")}</span>
                </div>
            {/if}
            {if $rv.closing.error_message}
                <div class="muted">{$rv.closing.error_message}</div>
            {/if}
        </div>
    </div>
{/if}

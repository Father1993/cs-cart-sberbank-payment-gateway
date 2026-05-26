{if $order_info.sber_receipt_status_view.show}
    {$rv = $order_info.sber_receipt_status_view}
    <div class="control-group shift-top">
        <div class="control-label">{__("addons.as_sberpay_api.receipt_status_title")}</div>
        <div class="controls">
            {if $rv.has_prepayment}
                <div>
                    {__("addons.as_sberpay_api.receipt_prepayment_title")}:
                    {if $rv.prepayment.label_class}
                        <span class="label {$rv.prepayment.label_class}">{$rv.prepayment.status_label}</span>
                    {else}
                        {$rv.prepayment.status_label}
                    {/if}
                    {if $rv.prepayment.updated_at_formatted}
                        <span class="muted">({$rv.prepayment.updated_at_formatted})</span>
                    {/if}
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
            {if $rv.has_refund && $rv.refund}
                <div>
                    {__("addons.as_sberpay_api.receipt_refund_title")}:
                    {if $rv.refund.label_class}
                        <span class="label {$rv.refund.label_class}">{$rv.refund.status_label}</span>
                    {else}
                        {$rv.refund.status_label}
                    {/if}
                    {if $rv.refund.updated_at_formatted}
                        <span class="muted">({$rv.refund.updated_at_formatted})</span>
                    {/if}
                </div>
            {/if}
            {if $rv.closing.error_message}
                <div class="muted">{$rv.closing.error_message}</div>
            {/if}
        </div>
    </div>
{/if}

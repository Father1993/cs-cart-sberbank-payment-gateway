{if $order_info.sber_receipt_status_view.show}
    {$receipt_view = $order_info.sber_receipt_status_view}
    <div class="control-group shift-top">
        <div class="control-label">{__("addons.as_sberpay_api.receipt_status_title")}</div>
        <div class="controls">
            {if $receipt_view.can_refresh && (!$runtime.company_id || $runtime.company_id == $order_info.company_id)}
                <p>
                    {include
                        file="buttons/button.tpl"
                        but_role="action"
                        but_href=$receipt_view.refresh_href
                        but_text=__("addons.as_sberpay_api.refresh_receipt_status")
                        but_meta="btn cm-post"
                    }
                </p>
            {/if}
            {if $receipt_view.prepayment}
                <p>
                    <strong>{__("addons.as_sberpay_api.receipt_prepayment_title")}:</strong>
                    {__("addons.as_sberpay_api.receipt_prepayment_registered")}
                    {if $receipt_view.prepayment.source_label}
                        <br /><span class="muted">{$receipt_view.prepayment.source_label}</span>
                    {/if}
                </p>
            {/if}
            {if $receipt_view.closing}
                <p>
                    <strong>{__("addons.as_sberpay_api.receipt_closing_title")}:</strong>
                    {if $receipt_view.closing.label_class}
                        <span class="label {$receipt_view.closing.label_class}">{$receipt_view.closing.status_label}</span>
                    {else}
                        {$receipt_view.closing.status_label}
                    {/if}
                    {if $receipt_view.closing.ofd_label}
                        <br /><span class="muted">{$receipt_view.closing.ofd_label}</span>
                    {/if}
                    {if $receipt_view.closing.updated_at_formatted}
                        <br /><span class="muted">{__("addons.as_sberpay_api.receipt_last_check")}: {$receipt_view.closing.updated_at_formatted}</span>
                    {/if}
                    {if $receipt_view.closing.source_label}
                        <br /><span class="muted">{__("addons.as_sberpay_api.receipt_data_source")}: {$receipt_view.closing.source_label}</span>
                    {/if}
                    {if $receipt_view.closing.error_message && $receipt_view.closing.status == "failed"}
                        <br /><span class="muted">{$receipt_view.closing.error_message}</span>
                    {/if}
                </p>
            {/if}
            {if $receipt_view.refund}
                <p>
                    <strong>{__("addons.as_sberpay_api.receipt_refund_title")}:</strong>
                    <span class="label {$receipt_view.refund.label_class}">{$receipt_view.refund.status_label}</span>
                </p>
            {/if}
            {if $receipt_view.help}
                <p class="muted">{$receipt_view.help}</p>
            {/if}
        </div>
    </div>
{/if}

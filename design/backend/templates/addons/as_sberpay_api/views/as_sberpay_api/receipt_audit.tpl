{capture name="mainbox"}
    <form action="{"as_sberpay_api.receipt_audit_refresh"|fn_url}" method="post" name="sberpay_receipt_audit_form" class="form-horizontal">
        {include file="common/subheader.tpl" title=__("addons.as_sberpay_api.receipt_audit_filters")}

        <div class="control-group">
            <label class="control-label" for="elm_sberpay_audit_order_id">{__("order_id")}:</label>
            <div class="controls">
                <input type="text" name="order_id" id="elm_sberpay_audit_order_id" value="{$audit_params.order_id|default:''}" size="12" />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="elm_sberpay_audit_date_from">{__("date")}:</label>
            <div class="controls">
                <input type="text" name="date_from" id="elm_sberpay_audit_date_from" value="{$audit_params.date_from|default:''}" size="12" placeholder="YYYY-MM-DD" />
                &nbsp;—&nbsp;
                <input type="text" name="date_to" value="{$audit_params.date_to|default:''}" size="12" placeholder="YYYY-MM-DD" />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="elm_sberpay_audit_limit">{__("limit")}:</label>
            <div class="controls">
                <input type="text" name="limit" id="elm_sberpay_audit_limit" value="{$audit_params.limit|default:50}" size="6" />
                <label class="checkbox inline" for="elm_sberpay_audit_review">
                    <input type="checkbox" name="only_review" id="elm_sberpay_audit_review" value="Y" {if $audit_params.only_review == "Y"}checked="checked"{/if} />
                    {__("addons.as_sberpay_api.receipt_audit_only_review")}
                </label>
            </div>
        </div>

        <div class="buttons-container">
            {include file="buttons/button.tpl" but_text=__("addons.as_sberpay_api.receipt_audit_run") but_role="submit-link" but_target_form="sberpay_receipt_audit_form" but_meta="btn-primary cm-confirm"}
        </div>
    </form>

    {include file="common/subheader.tpl" title=__("addons.as_sberpay_api.receipt_audit_results")}

    <p class="muted">{__("addons.as_sberpay_api.receipt_audit_help")}</p>

    {if $audit_results}
        <div class="table-responsive-wrapper">
            <table class="table table-middle table--relative table-responsive">
                <thead>
                    <tr>
                        <th>{__("order_id")}</th>
                        <th>{__("addons.as_sberpay_api.receipt_audit_role")}</th>
                        <th>receiptId</th>
                        <th>receiptType</th>
                        <th>operationType</th>
                        <th>receiptStatus</th>
                        <th>{__("addons.as_sberpay_api.receipt_audit_expected")}</th>
                        <th>{__("addons.as_sberpay_api.receipt_audit_review")}</th>
                        <th>{__("date")}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$audit_results item=row}
                        <tr>
                            <td data-th="{__("order_id")}">
                                <a href="{"orders.details?order_id=`$row.order_id`"|fn_url}">#{$row.order_id}</a>
                                <div class="muted">{$row.gateway_order_id}</div>
                            </td>
                            <td data-th="{__("addons.as_sberpay_api.receipt_audit_role")}">{$row.detected_role|default:'-'}</td>
                            <td data-th="receiptId">
                                {$row.receipt_id|default:'-'}
                                {if $row.ofd_url}
                                    <div><a href="{$row.ofd_url}" target="_blank" rel="noopener">{__("addons.as_sberpay_api.receipt_open_ofd")}</a></div>
                                {/if}
                            </td>
                            <td data-th="receiptType">{$row.receipt_type|default:'-'}</td>
                            <td data-th="operationType">{$row.operation_type|default:'-'}</td>
                            <td data-th="receiptStatus">{$row.receipt_status}</td>
                            <td data-th="{__("addons.as_sberpay_api.receipt_audit_expected")}">
                                <div>{$row.expected_receipt_type|default:'-'}</div>
                                <div class="muted">paymentMethod: {$row.expected_payment_method|default:'-'}</div>
                                <div class="muted">payment.type: {$row.expected_payment_type|default:'-'}</div>
                                {if !$row.expected_data_decoded.snapshot_found}
                                    <div class="muted">{__("addons.as_sberpay_api.receipt_audit_snapshot_missing")}</div>
                                {/if}
                            </td>
                            <td data-th="{__("addons.as_sberpay_api.receipt_audit_review")}">
                                {if $row.needs_review == "Y"}
                                    <span class="label label-warning">{__("yes")}</span>
                                {else}
                                    <span class="label label-success">{__("no")}</span>
                                {/if}
                                {if $row.error}
                                    <div class="text-error">{$row.error}</div>
                                {/if}
                            </td>
                            <td data-th="{__("date")}">{$row.checked_at|date_format:"%d.%m.%Y %H:%M:%S"}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {else}
        <p class="no-items">{__("no_data")}</p>
    {/if}
{/capture}

{include file="common/mainbox.tpl" title=__("addons.as_sberpay_api.receipt_audit_title") content=$smarty.capture.mainbox}

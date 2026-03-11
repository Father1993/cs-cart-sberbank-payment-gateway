<div class="control-group">
    <label class="control-label" for="as_sberpay_login">{__("addons.as_sberpay_api.login")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][login]" id="as_sberpay_login"
               value="{$processor_params.login|default:''}" size="60">
        <p class="muted description">{__("addons.as_sberpay_api.login_hint")}</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="as_sberpay_password">{__("addons.as_sberpay_api.password")}:</label>
    <div class="controls">
        <input type="password" name="payment_data[processor_params][password]" id="as_sberpay_password"
               value="{$processor_params.password|default:''}" size="60">
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="as_sberpay_mode">{__("test_live_mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="as_sberpay_mode">
            <option value="test" {if $processor_params.mode == "test"}selected="selected"{/if}>{__("test")}</option>
            <option value="live" {if $processor_params.mode == "live"}selected="selected"{/if}>{__("live")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="as_sberpay_two_staging">{__("addons.as_sberpay_api.two_staging")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][two_staging]" id="as_sberpay_two_staging">
            <option value="0" {if $processor_params.two_staging == 0}selected="selected"{/if}>{__("addons.as_sberpay_api.one_stage")}</option>
            <option value="1" {if $processor_params.two_staging == 1}selected="selected"{/if}>{__("addons.as_sberpay_api.two_stage")}</option>
        </select>
    </div>
</div>

{assign var="statuses" value=$smarty.const.STATUSES_ORDER|fn_get_simple_statuses}

<div class="control-group">
    <label class="control-label" for="as_sberpay_confirmed_status">{__("addons.as_sberpay_api.confirmed_order_status")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][confirmed_order_status]" id="as_sberpay_confirmed_status">
            {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if $processor_params.confirmed_order_status == $k || !$processor_params.confirmed_order_status && $k == 'P'}selected="selected"{/if}>{$s}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="as_sberpay_logging">{__("addons.as_sberpay_api.logging")}:</label>
    <div class="controls">
        <input type="checkbox" name="payment_data[processor_params][logging]" id="as_sberpay_logging"
               value="Y" {if $processor_params.logging == 'Y'}checked="checked"{/if}>
    </div>
</div>

{include file="common/subheader.tpl" title=__("addons.as_sberpay_api.fiscal_settings") target="#as_sberpay_fiscal"}
<div id="as_sberpay_fiscal" class="in collapse">

    <div class="control-group">
        <label class="control-label" for="as_sberpay_send_order">{__("addons.as_sberpay_api.send_order")}:</label>
        <div class="controls">
            <input type="checkbox" name="payment_data[processor_params][send_order]" id="as_sberpay_send_order"
                   value="Y" {if $processor_params.send_order == 'Y'}checked="checked"{/if}>
            <p class="muted description">{__("addons.as_sberpay_api.send_order_hint")}</p>
        </div>
    </div>
    
        {* Реквизиты продавца для чека 54-ФЗ (company) *}
    <div class="control-group">
    <label for="company_inn_{$payment_id}" class="control-label">{__("addons.as_sberpay_api.company_inn")}</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][company_inn]" id="company_inn_{$payment_id}"
            value="{$processor_params.company_inn}" class="input-text-large" size="20" />
    </div>
    </div>
    <div class="control-group">
    <label for="company_email_{$payment_id}" class="control-label">{__("addons.as_sberpay_api.company_email")}</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][company_email]" id="company_email_{$payment_id}"
            value="{$processor_params.company_email}" class="input-text-large" size="60" />
    </div>
    </div>
    <div class="control-group">
    <label for="company_payment_address_{$payment_id}" class="control-label">{__("addons.as_sberpay_api.company_payment_address")}</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][company_payment_address]" id="company_payment_address_{$payment_id}"
            value="{$processor_params.company_payment_address}" class="input-text-large" size="60" />
        <p class="muted">{__("addons.as_sberpay_api.company_payment_address_hint")}</p>
    </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="as_sberpay_tax_system">{__("addons.as_sberpay_api.tax_system")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][tax_system]" id="as_sberpay_tax_system">
                <option value="0" {if $processor_params.tax_system == 0}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_0")}</option>
                <option value="1" {if $processor_params.tax_system == 1}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_1")}</option>
                <option value="2" {if $processor_params.tax_system == 2}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_2")}</option>
                <option value="3" {if $processor_params.tax_system == 3}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_3")}</option>
                <option value="4" {if $processor_params.tax_system == 4}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_4")}</option>
                <option value="5" {if $processor_params.tax_system == 5}selected="selected"{/if}>{__("addons.as_sberpay_api.tax_system_5")}</option>
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="as_sberpay_tax_type">{__("addons.as_sberpay_api.tax_type")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][tax_type]" id="as_sberpay_tax_type">
                <option value="0" {if $processor_params.tax_type == 0}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_none")}</option>
                <option value="1" {if $processor_params.tax_type == 1}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_0")}</option>
                <option value="2" {if $processor_params.tax_type == 2}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_10")}</option>
                <option value="6" {if $processor_params.tax_type == 6}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_20")}</option>
                <option value="10" {if $processor_params.tax_type == 10}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_5")}</option>
                <option value="12" {if $processor_params.tax_type == 12}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_7")}</option>
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="as_sberpay_ffd">{__("addons.as_sberpay_api.ffd_version")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][ffd_version]" id="as_sberpay_ffd">
                <option value="v1_05" {if $processor_params.ffd_version == "v1_05"}selected="selected"{/if}>1.05</option>
                <option value="v1_2" {if $processor_params.ffd_version == "v1_2"}selected="selected"{/if}>1.2</option>
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="as_sberpay_payment_method">{__("addons.as_sberpay_api.payment_method_type")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][payment_method_type]" id="as_sberpay_payment_method">
                <option value="1" {if $processor_params.payment_method_type == 1}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_full_prepay")}</option>
                <option value="2" {if $processor_params.payment_method_type == 2}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_partial_prepay")}</option>
                <option value="3" {if $processor_params.payment_method_type == 3}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_advance")}</option>
                <option value="4" {if $processor_params.payment_method_type == 4}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_full_payment")}</option>
                <option value="5" {if $processor_params.payment_method_type == 5}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_partial_credit")}</option>
                <option value="6" {if $processor_params.payment_method_type == 6}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_credit_transfer")}</option>
                <option value="7" {if $processor_params.payment_method_type == 7}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_credit_payment")}</option>
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="as_sberpay_payment_object">{__("addons.as_sberpay_api.payment_object_type")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][payment_object_type]" id="as_sberpay_payment_object">
                <option value="1" {if $processor_params.payment_object_type == 1}selected="selected"{/if}>{__("addons.as_sberpay_api.po_goods")}</option>
                <option value="2" {if $processor_params.payment_object_type == 2}selected="selected"{/if}>{__("addons.as_sberpay_api.po_excise")}</option>
                <option value="3" {if $processor_params.payment_object_type == 3}selected="selected"{/if}>{__("addons.as_sberpay_api.po_job")}</option>
                <option value="4" {if $processor_params.payment_object_type == 4}selected="selected"{/if}>{__("addons.as_sberpay_api.po_service")}</option>
                <option value="7" {if $processor_params.payment_object_type == 7}selected="selected"{/if}>{__("addons.as_sberpay_api.po_lottery")}</option>
                <option value="9" {if $processor_params.payment_object_type == 9}selected="selected"{/if}>{__("addons.as_sberpay_api.po_ip")}</option>
                <option value="10" {if $processor_params.payment_object_type == 10}selected="selected"{/if}>{__("addons.as_sberpay_api.po_payment")}</option>
                <option value="11" {if $processor_params.payment_object_type == 11}selected="selected"{/if}>{__("addons.as_sberpay_api.po_commission")}</option>
                <option value="12" {if $processor_params.payment_object_type == 12}selected="selected"{/if}>{__("addons.as_sberpay_api.po_combined")}</option>
                <option value="13" {if $processor_params.payment_object_type == 13}selected="selected"{/if}>{__("addons.as_sberpay_api.po_other")}</option>
            </select>
        </div>
    </div>

</div>

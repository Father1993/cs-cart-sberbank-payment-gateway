<div class="control-group">
    <label class="control-label" for="as_sberpay_checkout_mode">{__("addons.as_sberpay_api.checkout_mode")}:</label>
    <div class="controls">
        {assign var="cur_checkout_mode" value=$processor_params.checkout_mode|default:"hosted"}
        <select name="payment_data[processor_params][checkout_mode]" id="as_sberpay_checkout_mode">
            <option value="hosted" {if $cur_checkout_mode == "hosted"}selected="selected"{/if}>{__("addons.as_sberpay_api.checkout_mode_hosted")}</option>
            <option value="sberpay_sdk" {if $cur_checkout_mode == "sberpay_sdk"}selected="selected"{/if}>{__("addons.as_sberpay_api.checkout_mode_sberpay_sdk")}</option>
        </select>
        <p class="muted description">{__("addons.as_sberpay_api.checkout_mode_hint")}</p>
    </div>
</div>

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

    {assign var="cur_vat" value=($processor_params.tax_type !== '' && $processor_params.tax_type !== null) ? $processor_params.tax_type : 12}
    <div class="control-group">
        <label class="control-label" for="as_sberpay_tax_type">{__("addons.as_sberpay_api.tax_type")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][tax_type]" id="as_sberpay_tax_type">
                <option value="0"  {if $cur_vat == 0} selected="selected"{/if}>{__("addons.as_sberpay_api.vat_none")}</option>
                <option value="1"  {if $cur_vat == 1} selected="selected"{/if}>{__("addons.as_sberpay_api.vat_0")}</option>
                <option value="2"  {if $cur_vat == 2} selected="selected"{/if}>{__("addons.as_sberpay_api.vat_10")}</option>
                <option value="6"  {if $cur_vat == 6} selected="selected"{/if}>{__("addons.as_sberpay_api.vat_20")}</option>
                <option value="8"  {if $cur_vat == 8} selected="selected"{/if}>{__("addons.as_sberpay_api.vat_5")}</option>
                <option value="10" {if $cur_vat == 10}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_7")}</option>
                <option value="12" {if $cur_vat == 12}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_22")}</option>
                <option value="13" {if $cur_vat == 13}selected="selected"{/if}>{__("addons.as_sberpay_api.vat_122")}</option>
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

    {assign var="cur_pm" value=$processor_params.payment_method|default:"full_prepayment"}
    <div class="control-group">
        <label class="control-label" for="as_sberpay_pm">{__("addons.as_sberpay_api.payment_method")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][payment_method]" id="as_sberpay_pm">
                <option value="full_prepayment" {if $cur_pm == "full_prepayment"}selected="selected"{/if}>{__("addons.as_sberpay_api.pm_full_prepayment")}</option>
                <option value="prepayment"      {if $cur_pm == "prepayment"}     selected="selected"{/if}>{__("addons.as_sberpay_api.pm_prepayment")}</option>
                <option value="advance"         {if $cur_pm == "advance"}        selected="selected"{/if}>{__("addons.as_sberpay_api.pm_advance")}</option>
                <option value="full_payment"    {if $cur_pm == "full_payment"}   selected="selected"{/if}>{__("addons.as_sberpay_api.pm_full_payment")}</option>
            </select>
            <p class="muted description">{__("addons.as_sberpay_api.payment_method_hint")}</p>
        </div>
    </div>

</div>

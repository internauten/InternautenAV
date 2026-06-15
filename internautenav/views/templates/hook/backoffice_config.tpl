<div class="panel">
    <h3>{$internautenav_backoffice_title|escape:'htmlall':'UTF-8'}</h3>
    <p>{$internautenav_backoffice_description|escape:'htmlall':'UTF-8'}</p>

    <form method="post" action="{$internautenav_form_action|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="token" value="{$internautenav_form_token|escape:'htmlall':'UTF-8'}">

        <!-- Carrier Selection -->
        <div class="form-group">
            <label>{$internautenav_backoffice_label|escape:'htmlall':'UTF-8'}</label>
            <select name="INTERNAUTENAV_REQUIRED_CARRIER_REFS[]" class="form-control" multiple size="10">
                {foreach from=$internautenav_carriers item=carrier}
                    <option value="{$carrier.id_reference|escape:'htmlall':'UTF-8'}"
                        {if in_array($carrier.id_reference, $internautenav_selected_refs)} selected{/if}>
                        {$carrier.label|escape:'htmlall':'UTF-8'}
                    </option>
                {/foreach}
            </select>
            <p class="help-block">{$internautenav_backoffice_help|escape:'htmlall':'UTF-8'}</p>
        </div>

        <!-- Privacy CMS Page Selection -->
        <div class="form-group">
            <label>{$internautenav_privacy_cms_label|escape:'htmlall':'UTF-8'}</label>
            <select name="INTERNAUTENAV_PRIVACY_CMS_ID" class="form-control">
                <option value="0" {if $internautenav_privacy_cms_id === 0} selected{/if}>
                    — {$internautenav_privacy_default_label|escape:'htmlall':'UTF-8'} —
                </option>
                {foreach from=$internautenav_cms_pages item=page}
                    <option value="{$page.id_cms|escape:'htmlall':'UTF-8'}"
                        {if $internautenav_privacy_cms_id === $page.id_cms} selected{/if}>
                        #{$page.id_cms|escape:'htmlall':'UTF-8'} – {$page.meta_title|escape:'htmlall':'UTF-8'}
                    </option>
                {/foreach}
            </select>
            <p class="help-block">
                <strong>{$internautenav_status_label|escape:'htmlall':'UTF-8'}:</strong>
                <span class="{$internautenav_privacy_cms_status_class|escape:'htmlall':'UTF-8'}">
                    {$internautenav_privacy_cms_status_message|escape:'htmlall':'UTF-8'}
                </span>
            </p>
        </div>

        <button type="submit" name="submitInternautenavConfig" class="btn btn-primary">
            {$internautenav_save_button|escape:'htmlall':'UTF-8'}
        </button>
    </form>
</div>
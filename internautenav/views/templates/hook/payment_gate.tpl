<div
  class="internautenav-payment-gate js-internautenav-payment-gate"
  data-carrier-id="{$internautenav_carrier_id|intval}"
  data-verified="{if $internautenav_is_verified}1{else}0{/if}"
>
  <div class="internautenav-payment-card">
    <p class="internautenav-payment-eyebrow">{$internautenav_payment_title|escape:'htmlall':'UTF-8'}</p>
    <p class="internautenav-payment-intro">
      {$internautenav_payment_intro|escape:'htmlall':'UTF-8'}
      <strong>{$internautenav_carrier_name|escape:'htmlall':'UTF-8'}</strong>
    </p>

    <p class="internautenav-payment-lock-note js-internautenav-lock-note" {if $internautenav_is_verified}style="display:none;"{/if}>
      {$internautenav_payment_locked|escape:'htmlall':'UTF-8'}
    </p>

    <p class="internautenav-payment-success js-internautenav-success-note" {if !$internautenav_is_verified}style="display:none;"{/if}>
      {$internautenav_payment_success|escape:'htmlall':'UTF-8'}
    </p>

    <button
      type="button"
      class="btn btn-primary internautenav-payment-link js-internautenav-open"
      {if $internautenav_is_verified}style="display:none;"{/if}
    >
      {$internautenav_payment_link|escape:'htmlall':'UTF-8'}
    </button>
  </div>

  <div class="internautenav-modal js-internautenav-modal" hidden>
    <div class="internautenav-modal-backdrop js-internautenav-close"></div>

    <div class="internautenav-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="internautenav-modal-title-{$internautenav_carrier_id|intval}">
      <button type="button" class="internautenav-modal-close js-internautenav-close" aria-label="{$internautenav_modal_close|escape:'htmlall':'UTF-8'}">&times;</button>

      <h3 id="internautenav-modal-title-{$internautenav_carrier_id|intval}" class="internautenav-modal-title">
        {$internautenav_modal_title|escape:'htmlall':'UTF-8'}
      </h3>

      <form class="internautenav-modal-form js-internautenav-modal-form" novalidate>
        <div class="form-group internautenav-group">
          <label for="internautenav_modal_doc_type_{$internautenav_carrier_id|intval}">{$internautenav_doc_label|escape:'htmlall':'UTF-8'}</label>
          <select
            id="internautenav_modal_doc_type_{$internautenav_carrier_id|intval}"
            class="form-control js-internautenav-doc-type"
            name="internautenav_modal_doc_type"
            required
          >
            <option value="">-</option>
            <option value="ch_id">{$internautenav_doc_ch_id|escape:'htmlall':'UTF-8'}</option>
            <option value="ch_pass">{$internautenav_doc_ch_pass|escape:'htmlall':'UTF-8'}</option>
            <option value="eu_pass">{$internautenav_doc_eu_pass|escape:'htmlall':'UTF-8'}</option>
            <option value="upload">{$internautenav_doc_upload|escape:'htmlall':'UTF-8'}</option>
          </select>
        </div>

        <div class="js-internautenav-doc-fields" data-doc-type="ch_id" hidden>
          <div class="internautenav-chid-fields-header">
            <h3>{$internautenav_chid_fields_header|escape:'htmlall':'UTF-8'}</h3>
          </div>
          <div class="form-group internautenav-group">
            <div class="internautenav-mrz-line1-row">
              <span class="internautenav-mrz-fixed">IDCHE</span>
              <input
                id="internautenav_modal_ch_id_line1_number_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-number"
                type="text"
                name="internautenav_modal_ch_id_line1_number"
                inputmode="text"
                pattern="[A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9]"
                minlength="8"
                maxlength="8"
                data-chid-number="1"
                autocomplete="off"
                placeholder="S1A00A00"
              >
              <span class="internautenav-mrz-fixed">&lt;</span>
              <input
                id="internautenav_modal_ch_id_line1_check_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-check"
                type="text"
                name="internautenav_modal_ch_id_line1_check"
                inputmode="numeric"
                pattern="[0-9]"
                minlength="1"
                maxlength="1"
                data-chid-check="1"
                autocomplete="off"
              >
              <span class="internautenav-mrz-fixed">&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;</span>
            </div>
            <p class="internautenav-chid-check-error js-internautenav-chid-check-error" hidden></p>
          </div>
          <div class="form-group internautenav-group">
            <div class="internautenav-mrz-line1-row">
              <input
                id="internautenav_modal_ch_id_birth7_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-date"
                type="text"
                name="internautenav_modal_ch_id_birth7"
                inputmode="numeric"
                pattern="[0-9][0-9][0-9][0-9][0-9][0-9][0-9]"
                minlength="7"
                maxlength="7"
                data-chid-birth7="1"
                autocomplete="off"
                placeholder="YYMMDDP"
              >
              <span class="internautenav-mrz-fixed" data-chid-sex="{$internautenav_customer_sex|escape:'htmlall':'UTF-8'}">{$internautenav_customer_sex|escape:'htmlall':'UTF-8'}</span>
              <input
                id="internautenav_modal_ch_id_expiry7_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-date"
                type="text"
                name="internautenav_modal_ch_id_expiry7"
                inputmode="numeric"
                pattern="[0-9][0-9][0-9][0-9][0-9][0-9][0-9]"
                minlength="7"
                maxlength="7"
                data-chid-expiry7="1"
                autocomplete="off"
                placeholder="YYMMDDP"
              >
              <span class="internautenav-mrz-fixed">CHE&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;</span>
              <input
                id="internautenav_modal_ch_id_composite_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-check"
                type="text"
                name="internautenav_modal_ch_id_composite"
                inputmode="numeric"
                pattern="[0-9]"
                minlength="1"
                maxlength="1"
                data-chid-composite="1"
                autocomplete="off"
              >
            </div>
            <p class="internautenav-chid-line2-error js-internautenav-chid-line2-error" hidden></p>
          </div>
          <div class="form-group internautenav-group">
            <div class="internautenav-mrz-fixed js-internautenav-chid-line3-text" data-line3="{$internautenav_line3_prefill|default:''|escape:'htmlall':'UTF-8'}">
              {$internautenav_line3_prefill|default:''|escape:'htmlall':'UTF-8'}
            </div>
          </div>
        </div>

        <div class="js-internautenav-doc-fields" data-doc-type="ch_pass" hidden>
          <div class="internautenav-chpass-fields-header">
            <h3>{$internautenav_pass_front_label|escape:'htmlall':'UTF-8'}</h3>
          </div>
          <div class="form-group internautenav-group">
            <div
              class="internautenav-mrz-fixed js-internautenav-chpass-line1-text"
              data-line1="{$internautenav_pass_line1_prefill|default:''|escape:'htmlall':'UTF-8'}"
            >
              {$internautenav_pass_line1_prefill|default:''|escape:'htmlall':'UTF-8'}
            </div>
          </div>
          <div class="form-group internautenav-group">
            <div class="internautenav-mrz-line1-row internautenav-chpass-line2-row">
              <input
                id="internautenav_modal_ch_pass_number_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-number"
                type="text"
                name="internautenav_modal_ch_pass_number"
                inputmode="text"
                pattern="[A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9]"
                minlength="8"
                maxlength="8"
                data-chpass-number="1"
                autocomplete="off"
                placeholder="A12B34C5"
              >
              <span class="internautenav-mrz-fixed">&lt;</span>
              <input
                id="internautenav_modal_ch_pass_number_check_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-check"
                type="text"
                name="internautenav_modal_ch_pass_number_check"
                inputmode="numeric"
                pattern="[0-9]"
                minlength="1"
                maxlength="1"
                data-chpass-number-check="1"
                autocomplete="off"
              >
              <span class="internautenav-mrz-fixed">CHE</span>
              <input
                id="internautenav_modal_ch_pass_birth7_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-date"
                type="text"
                name="internautenav_modal_ch_pass_birth7"
                inputmode="numeric"
                pattern="[0-9][0-9][0-9][0-9][0-9][0-9][0-9]"
                minlength="7"
                maxlength="7"
                data-chpass-birth7="1"
                autocomplete="off"
                placeholder="YYMMDDP"
              >
              <span class="internautenav-mrz-fixed" data-chpass-sex="{$internautenav_customer_sex|escape:'htmlall':'UTF-8'}">{$internautenav_customer_sex|escape:'htmlall':'UTF-8'}</span>
              <input
                id="internautenav_modal_ch_pass_expiry7_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-date"
                type="text"
                name="internautenav_modal_ch_pass_expiry7"
                inputmode="numeric"
                pattern="[0-9][0-9][0-9][0-9][0-9][0-9][0-9]"
                minlength="7"
                maxlength="7"
                data-chpass-expiry7="1"
                autocomplete="off"
                placeholder="YYMMDDP"
              >
              <span class="internautenav-mrz-fixed internautenav-chpass-filler">&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;X</span>
              <input
                id="internautenav_modal_ch_pass_composite_{$internautenav_carrier_id|intval}"
                class="form-control internautenav-mrz-input-check"
                type="text"
                name="internautenav_modal_ch_pass_composite"
                inputmode="numeric"
                pattern="[0-9]"
                minlength="1"
                maxlength="1"
                data-chpass-composite="1"
                autocomplete="off"
              >
            </div>
            <p class="internautenav-chpass-line2-error js-internautenav-chpass-line2-error" hidden></p>
          </div>
        </div>

        <div class="js-internautenav-doc-fields" data-doc-type="eu_pass" hidden>
          <div class="form-group internautenav-group">
            <label for="internautenav_modal_eu_pass_line1_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
            <input
              id="internautenav_modal_eu_pass_line1_{$internautenav_carrier_id|intval}"
              class="form-control"
              type="text"
              name="internautenav_modal_line1"
              autocomplete="off"
              maxlength="44"
            >
          </div>
          <div class="form-group internautenav-group">
            <label for="internautenav_modal_eu_pass_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
            <input
              id="internautenav_modal_eu_pass_line2_{$internautenav_carrier_id|intval}"
              class="form-control"
              type="text"
              name="internautenav_modal_line2"
              autocomplete="off"
              maxlength="44"
            >
          </div>
          <p class="internautenav-hint">{$internautenav_hint|escape:'htmlall':'UTF-8'}</p>
        </div>

        <div class="js-internautenav-doc-fields" data-doc-type="upload" hidden>
          <div class="form-group internautenav-group">
            <label for="internautenav_modal_upload_file_{$internautenav_carrier_id|intval}">{$internautenav_upload_label|escape:'htmlall':'UTF-8'}</label>
            <input
              id="internautenav_modal_upload_file_{$internautenav_carrier_id|intval}"
              class="form-control"
              type="file"
              name="internautenav_modal_upload_file"
              data-upload-file="1"
              accept="image/jpeg,image/png,image/bmp,image/gif,image/wmf,image/x-wmf,.jpg,.jpeg,.png,.bmp,.gif,.wmf"
              autocomplete="off"
            >
            <p class="help-block">{$internautenav_upload_hint|escape:'htmlall':'UTF-8'}</p>
          </div>
        </div>

        <p class="internautenav-modal-error js-internautenav-error" hidden></p>

        <div class="internautenav-modal-actions">
          <button type="button" class="btn btn-secondary js-internautenav-close">
            {$internautenav_modal_close|escape:'htmlall':'UTF-8'}
          </button>
          <button
            type="button"
            class="btn btn-primary js-internautenav-submit"
            data-submit-default="{$internautenav_modal_submit|escape:'htmlall':'UTF-8'}"
            data-submit-upload="{$internautenav_modal_submit_upload|escape:'htmlall':'UTF-8'}"
          >
            {$internautenav_modal_submit|escape:'htmlall':'UTF-8'}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
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
          </select>
        </div>

        <div class="form-group internautenav-group">
          <label for="internautenav_modal_line1_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
          <input
            id="internautenav_modal_line1_{$internautenav_carrier_id|intval}"
            class="form-control"
            type="text"
            name="internautenav_modal_line1"
            autocomplete="off"
            maxlength="44"
            required
          >
        </div>

        <div class="form-group internautenav-group">
          <label for="internautenav_modal_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
          <input
            id="internautenav_modal_line2_{$internautenav_carrier_id|intval}"
            class="form-control"
            type="text"
            name="internautenav_modal_line2"
            autocomplete="off"
            maxlength="44"
            required
          >
        </div>

        <div class="form-group internautenav-group js-internautenav-line3-group" style="display:none;">
          <label for="internautenav_modal_line3_{$internautenav_carrier_id|intval}">{$internautenav_line3_label|escape:'htmlall':'UTF-8'}</label>
          <input
            id="internautenav_modal_line3_{$internautenav_carrier_id|intval}"
            class="form-control"
            type="text"
            name="internautenav_modal_line3"
            autocomplete="off"
            maxlength="30"
          >
        </div>

        <p class="internautenav-hint">{$internautenav_hint|escape:'htmlall':'UTF-8'}</p>
        <p class="internautenav-modal-error js-internautenav-error" hidden></p>

        <div class="internautenav-modal-actions">
          <button type="button" class="btn btn-secondary js-internautenav-close">
            {$internautenav_modal_close|escape:'htmlall':'UTF-8'}
          </button>
          <button type="button" class="btn btn-primary js-internautenav-submit">
            {$internautenav_modal_submit|escape:'htmlall':'UTF-8'}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
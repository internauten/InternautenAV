<div class="internautenav-mrz-box" data-carrier-id="{$internautenav_carrier_id|intval}">
  <p class="internautenav-intro">{$internautenav_intro|escape:'htmlall':'UTF-8'}</p>

  <div class="form-group internautenav-group">
    <label for="internautenav_doc_type_{$internautenav_carrier_id|intval}">{$internautenav_doc_label|escape:'htmlall':'UTF-8'}</label>
    <select
      id="internautenav_doc_type_{$internautenav_carrier_id|intval}"
      class="form-control js-internautenav-doc-type"
      name="internautenav_doc_type[{$internautenav_carrier_id|intval}]"
    >
      <option value="">-</option>
      <option value="ch_id">{$internautenav_doc_ch_id|escape:'htmlall':'UTF-8'}</option>
      <option value="ch_pass">{$internautenav_doc_ch_pass|escape:'htmlall':'UTF-8'}</option>
      <option value="eu_pass">{$internautenav_doc_eu_pass|escape:'htmlall':'UTF-8'}</option>
    </select>
  </div>

  <div class="js-internautenav-doc-fields" data-doc-type="ch_id" hidden>
    <div class="form-group internautenav-group">
      <label for="internautenav_ch_id_line1_number_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
      <div class="internautenav-mrz-line1-row">
        <span class="internautenav-mrz-fixed">IDCHE</span>
        <input
          id="internautenav_ch_id_line1_number_{$internautenav_carrier_id|intval}"
          class="form-control internautenav-mrz-input-number"
          type="text"
          name="internautenav_mrz_ch_id_line1_number[{$internautenav_carrier_id|intval}]"
          inputmode="text"
          pattern="[A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9][A-Za-z0-9]"
          minlength="8"
          maxlength="8"
          data-chid-number="1"
          autocomplete="off"
        >
        <span class="internautenav-mrz-fixed">&lt;</span>
        <input
          id="internautenav_ch_id_line1_check_{$internautenav_carrier_id|intval}"
          class="form-control internautenav-mrz-input-check"
          type="text"
          name="internautenav_mrz_ch_id_line1_check[{$internautenav_carrier_id|intval}]"
          inputmode="numeric"
          pattern="[0-9]"
          minlength="1"
          maxlength="1"
          data-chid-check="1"
          autocomplete="off"
        >
      </div>
      <p class="internautenav-chid-check-error js-internautenav-chid-check-error" hidden></p>
    </div>
    <div class="form-group internautenav-group">
      <label for="internautenav_ch_id_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_ch_id_line2_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line2[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="30"
      >
    </div>
    <div class="form-group internautenav-group">
      <label for="internautenav_ch_id_line3_{$internautenav_carrier_id|intval}">{$internautenav_line3_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_ch_id_line3_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line3[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="30"
        data-prefill="{$internautenav_line3_prefill|default:''|escape:'htmlall':'UTF-8'}"
      >
    </div>
  </div>

  <div class="js-internautenav-doc-fields" data-doc-type="ch_pass" hidden>
    <div class="form-group internautenav-group">
      <label for="internautenav_ch_pass_line1_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_ch_pass_line1_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line1[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="44"
        data-prefill="{$internautenav_pass_line1_prefill|default:''|escape:'htmlall':'UTF-8'}"
      >
    </div>
    <div class="form-group internautenav-group">
      <label for="internautenav_ch_pass_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_ch_pass_line2_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line2[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="44"
      >
    </div>
  </div>

  <div class="js-internautenav-doc-fields" data-doc-type="eu_pass" hidden>
    <div class="form-group internautenav-group">
      <label for="internautenav_eu_pass_line1_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_eu_pass_line1_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line1[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="44"
      >
    </div>
    <div class="form-group internautenav-group">
      <label for="internautenav_eu_pass_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
      <input
        id="internautenav_eu_pass_line2_{$internautenav_carrier_id|intval}"
        class="form-control"
        type="text"
        name="internautenav_mrz_line2[{$internautenav_carrier_id|intval}]"
        autocomplete="off"
        maxlength="44"
      >
    </div>
  </div>

  <p class="internautenav-hint">{$internautenav_hint|escape:'htmlall':'UTF-8'}</p>
</div>

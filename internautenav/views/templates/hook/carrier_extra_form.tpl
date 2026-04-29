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

  <div class="form-group internautenav-group">
    <label for="internautenav_line1_{$internautenav_carrier_id|intval}">{$internautenav_line1_label|escape:'htmlall':'UTF-8'}</label>
    <input
      id="internautenav_line1_{$internautenav_carrier_id|intval}"
      class="form-control"
      type="text"
      name="internautenav_mrz_line1[{$internautenav_carrier_id|intval}]"
      autocomplete="off"
      maxlength="44"
    >
  </div>

  <div class="form-group internautenav-group">
    <label for="internautenav_line2_{$internautenav_carrier_id|intval}">{$internautenav_line2_label|escape:'htmlall':'UTF-8'}</label>
    <input
      id="internautenav_line2_{$internautenav_carrier_id|intval}"
      class="form-control"
      type="text"
      name="internautenav_mrz_line2[{$internautenav_carrier_id|intval}]"
      autocomplete="off"
      maxlength="44"
    >
  </div>

  <div class="form-group internautenav-group js-internautenav-line3-group" style="display:none;">
    <label for="internautenav_line3_{$internautenav_carrier_id|intval}">{$internautenav_line3_label|escape:'htmlall':'UTF-8'}</label>
    <input
      id="internautenav_line3_{$internautenav_carrier_id|intval}"
      class="form-control"
      type="text"
      name="internautenav_mrz_line3[{$internautenav_carrier_id|intval}]"
      autocomplete="off"
      maxlength="30"
    >
  </div>

  <p class="internautenav-hint">{$internautenav_hint|escape:'htmlall':'UTF-8'}</p>
</div>

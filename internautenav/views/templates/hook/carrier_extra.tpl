<div class="internautenav-mrz-box js-internautenav-box" data-carrier-id="{$internautenav_carrier_id|intval}" style="display:none;">
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

<script type="text/javascript">
// Fallback inline für JS-Registrierungsprobleme
(function() {
  function initInternautenav() {
    var boxes = document.querySelectorAll('.js-internautenav-box');
    if (!boxes.length) return;

    function getSelectedCarrierId() {
      var checked = document.querySelector('input[name^="delivery_option"]:checked');
      if (!checked || !checked.value) {
        return null;
      }
      var match = checked.value.match(/^(\d+),/);
      return match ? match[1] : null;
    }

    function setLineRules(box, docType) {
      var line1 = box.querySelector('input[name^="internautenav_mrz_line1"]');
      var line2 = box.querySelector('input[name^="internautenav_mrz_line2"]');
      var line3 = box.querySelector('input[name^="internautenav_mrz_line3"]');
      var line3Group = box.querySelector('.js-internautenav-line3-group');

      if (!line1 || !line2 || !line3 || !line3Group) {
        return;
      }

      if (docType === 'ch_id') {
        line1.maxLength = 30;
        line2.maxLength = 30;
        line3.maxLength = 30;
        line3Group.style.display = '';
        return;
      }

      line1.maxLength = 44;
      line2.maxLength = 44;
      line3Group.style.display = 'none';
      line3.value = '';
    }

    function toggleBoxes() {
      var selectedCarrierId = getSelectedCarrierId();
      boxes.forEach(function (box) {
        var carrierId = box.getAttribute('data-carrier-id');
        var isActive = selectedCarrierId !== null && selectedCarrierId === carrierId;

        box.style.display = isActive ? '' : 'none';

        if (isActive) {
          var select = box.querySelector('.js-internautenav-doc-type');
          setLineRules(box, select ? select.value : '');
        }
      });
    }

    document.body.addEventListener('change', function (event) {
      if (event.target.matches('input[name^="delivery_option"]')) {
        toggleBoxes();
        return;
      }

      if (event.target.matches('.js-internautenav-doc-type')) {
        var box = event.target.closest('.js-internautenav-box');
        if (box) {
          setLineRules(box, event.target.value);
        }
      }
    });

    toggleBoxes();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInternautenav);
  } else {
    initInternautenav();
  }
})();
</script>


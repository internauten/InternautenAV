document.addEventListener('DOMContentLoaded', function () {
    function getSelectedCarrierInput() {
        return document.querySelector('input[name^="delivery_option"]:checked');
    }

    function getSelectedCarrierId() {
        var input = getSelectedCarrierInput();
        if (!input || !input.value) {
            return null;
        }

        var match = input.value.match(/^(\d+),/);
        return match ? match[1] : null;
    }

    function setLineRules(box, docType) {
        var line3Group = box.querySelector('.js-internautenav-line3-group');
        var line1 = box.querySelector('input[name^="internautenav_mrz_line1"]');
        var line2 = box.querySelector('input[name^="internautenav_mrz_line2"]');
        var line3 = box.querySelector('input[name^="internautenav_mrz_line3"]');

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

    function toggleExistingHookBoxes() {
        var selectedCarrierId = getSelectedCarrierId();
        var boxes = document.querySelectorAll('.internautenav-mrz-box');

        boxes.forEach(function (box) {
            var carrierId = box.getAttribute('data-carrier-id');
            var isActive = selectedCarrierId !== null && carrierId === selectedCarrierId;

            box.style.display = isActive ? '' : 'none';
            if (isActive) {
                var select = box.querySelector('.js-internautenav-doc-type');
                setLineRules(box, select ? select.value : '');
            }
        });
    }

    function findCarrierTargetNode() {
        var input = getSelectedCarrierInput();
        if (!input) {
            return null;
        }

        return (
            input.closest('.delivery-option') ||
            input.closest('.carrier-item') ||
            input.closest('li') ||
            input.parentElement
        );
    }

    function ensureAjaxContainer(carrierId) {
        var id = 'internautenav-ajax-container-' + carrierId;
        var existing = document.getElementById(id);
        if (existing) {
            return existing;
        }

        var target = findCarrierTargetNode();
        if (!target) {
            return null;
        }

        var container = document.createElement('div');
        container.id = id;
        container.className = 'internautenav-ajax-container';
        target.appendChild(container);

        return container;
    }

    function hideInactiveAjaxContainers(activeCarrierId) {
        var containers = document.querySelectorAll('.internautenav-ajax-container');
        containers.forEach(function (container) {
            if (!activeCarrierId || container.id !== 'internautenav-ajax-container-' + activeCarrierId) {
                container.style.display = 'none';
            }
        });
    }

    function loadAjaxFormIfNeeded() {
        var selectedCarrierId = getSelectedCarrierId();
        if (!selectedCarrierId) {
            hideInactiveAjaxContainers(null);
            return;
        }

        var hasHookBox = document.querySelector('.internautenav-mrz-box[data-carrier-id="' + selectedCarrierId + '"]');
        if (hasHookBox) {
            hideInactiveAjaxContainers(selectedCarrierId);

            var activeContainer = document.getElementById('internautenav-ajax-container-' + selectedCarrierId);
            if (activeContainer) {
                activeContainer.style.display = '';
            }
            return;
        }

        var container = ensureAjaxContainer(selectedCarrierId);
        if (!container) {
            return;
        }

        hideInactiveAjaxContainers(selectedCarrierId);
        container.style.display = '';

        if (container.getAttribute('data-loaded') === '1') {
            return;
        }

        var baseUrl = (typeof internautenav_ajax_url !== 'undefined' && internautenav_ajax_url) ? internautenav_ajax_url : '/modules/internautenav/ajax.php';
        var url = baseUrl + '?action=get_mrz_form&carrier_id=' + encodeURIComponent(selectedCarrierId) + '&_t=' + Date.now();

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                container.innerHTML = html || '';
                container.setAttribute('data-loaded', '1');

                var box = container.querySelector('.internautenav-mrz-box');
                if (box) {
                    box.style.display = '';
                    var select = box.querySelector('.js-internautenav-doc-type');
                    setLineRules(box, select ? select.value : '');
                }
            })
            .catch(function () {
                container.innerHTML = '';
            });
    }

    function refresh() {
        toggleExistingHookBoxes();
        loadAjaxFormIfNeeded();
    }

    document.body.addEventListener('change', function (event) {
        if (event.target.matches('input[name^="delivery_option"]')) {
            refresh();
            return;
        }

        if (event.target.matches('.js-internautenav-doc-type')) {
            var box = event.target.closest('.internautenav-mrz-box');
            if (box) {
                setLineRules(box, event.target.value);
            }
        }
    });

    document.addEventListener('updatedDeliveryForm', refresh);
    document.addEventListener('updatedCart', refresh);

    refresh();
});



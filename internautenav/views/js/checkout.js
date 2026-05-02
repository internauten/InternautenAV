document.addEventListener('DOMContentLoaded', function () {
    var GATE_SELECTOR = '.js-internautenav-payment-gate';

    function getAllGates() {
        return document.querySelectorAll(GATE_SELECTOR);
    }

    function getPaymentStep(gate) {
        return gate.closest('#checkout-payment-step') || gate.closest('.checkout-step') || gate.parentElement;
    }

    function getModal(gate) {
        return gate.querySelector('.js-internautenav-modal');
    }

    function getModalForm(gate) {
        return gate.querySelector('.js-internautenav-modal-form');
    }

    function getSubmitButton(gate) {
        return gate.querySelector('.js-internautenav-submit');
    }

    function getDocTypeSelect(gate) {
        return gate.querySelector('.js-internautenav-doc-type');
    }

    function getErrorNode(gate) {
        return gate.querySelector('.js-internautenav-error');
    }

    function applyDocTypeBlocks(container, docType) {
        var blocks = container.querySelectorAll('.js-internautenav-doc-fields');
        blocks.forEach(function (block) {
            var isActive = block.getAttribute('data-doc-type') === docType;
            block.hidden = !isActive;
            var inputs = block.querySelectorAll('input');
            inputs.forEach(function (input) {
                input.required = isActive;
                if (!isActive) {
                    input.value = '';
                }
            });
            if (isActive) {
                inputs.forEach(function (input) {
                    if (!input.value && input.dataset && input.dataset.prefill) {
                        input.value = input.dataset.prefill;
                    }
                });
            }
        });
    }

    function setLineRules(gate) {
        var select = getDocTypeSelect(gate);
        if (!select) {
            return;
        }
        applyDocTypeBlocks(gate, select.value);
    }

    function openModal(gate) {
        var modal = getModal(gate);
        if (!modal) {
            return;
        }

        modal.hidden = false;
        document.body.classList.add('internautenav-modal-open');

        var select = getDocTypeSelect(gate);
        if (select) {
            select.focus();
        }
    }

    function closeModal(gate) {
        var modal = getModal(gate);
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('internautenav-modal-open');
    }

    function showError(gate, message) {
        var errorNode = getErrorNode(gate);
        if (!errorNode) {
            return;
        }

        errorNode.hidden = false;
        errorNode.textContent = message || 'Pruefung fehlgeschlagen.';
    }

    function clearError(gate) {
        var errorNode = getErrorNode(gate);
        if (!errorNode) {
            return;
        }

        errorNode.hidden = true;
        errorNode.textContent = '';
    }

    function setBusy(gate, isBusy) {
        var form = getModalForm(gate);
        if (!form) {
            return;
        }

        var controls = form.querySelectorAll('button, input, select');
        controls.forEach(function (control) {
            control.disabled = !!isBusy;
        });
    }

    function collectPayload(gate) {
        var select = getDocTypeSelect(gate);
        var docType = select ? select.value : '';
        var activeBlock = docType ? gate.querySelector('.js-internautenav-doc-fields[data-doc-type="' + docType + '"]') : null;

        function getLineValue(suffix) {
            if (!activeBlock) {
                return '';
            }
            var input = activeBlock.querySelector('input[name*="' + suffix + '"]');
            return input ? input.value : '';
        }

        return {
            carrierId: gate.getAttribute('data-carrier-id') || '',
            docType: docType,
            line1: getLineValue('line1'),
            line2: getLineValue('line2'),
            line3: getLineValue('line3')
        };
    }

    function getPaymentControls(gate) {
        var step = getPaymentStep(gate);
        if (!step) {
            return [];
        }

        return Array.prototype.slice.call(
            step.querySelectorAll('button, input:not([type="hidden"]), select, textarea')
        ).filter(function (control) {
            return !gate.contains(control);
        });
    }

    function setPaymentLocked(gate, isLocked) {
        var step = getPaymentStep(gate);
        var openButton = gate.querySelector('.js-internautenav-open');
        var lockNote = gate.querySelector('.js-internautenav-lock-note');
        var successNote = gate.querySelector('.js-internautenav-success-note');

        gate.setAttribute('data-verified', isLocked ? '0' : '1');

        if (step) {
            step.classList.toggle('internautenav-payment-step-locked', isLocked);
        }

        getPaymentControls(gate).forEach(function (control) {
            if (isLocked) {
                control.disabled = true;
                control.setAttribute('data-internautenav-locked', '1');
                return;
            }

            if (control.getAttribute('data-internautenav-locked') === '1') {
                control.disabled = false;
                control.removeAttribute('data-internautenav-locked');
            }
        });

        if (openButton) {
            openButton.style.display = isLocked ? '' : 'none';
        }
        if (lockNote) {
            lockNote.style.display = isLocked ? '' : 'none';
        }
        if (successNote) {
            successNote.style.display = isLocked ? 'none' : '';
        }
    }

    function handleModalSubmit(gate, event) {
        event.preventDefault();

        var form = getModalForm(gate);
        if (!form) {
            return;
        }

        setLineRules(gate);
        clearError(gate);

        if (!form.checkValidity()) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            return;
        }

        var payload = collectPayload(gate);
        var baseUrl = (typeof internautenav_ajax_url !== 'undefined' && internautenav_ajax_url) ? internautenav_ajax_url : '/modules/internautenav/ajax.php';
        var body = new URLSearchParams();

        body.set('action', 'validate_mrz');
        body.set('carrier_id', payload.carrierId);
        body.set('doc_type', payload.docType);
        body.set('line1', payload.line1);
        body.set('line2', payload.line2);
        body.set('line3', payload.line3);

        setBusy(gate, true);

        fetch(baseUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return null;
                });
            })
            .then(function (result) {
                if (!result || result.valid !== true) {
                    setBusy(gate, false);
                    showError(gate, result && result.message ? result.message : 'MRZ-Pruefung fehlgeschlagen.');
                    return;
                }

                setBusy(gate, false);
                setPaymentLocked(gate, false);
                closeModal(gate);
            })
            .catch(function () {
                setBusy(gate, false);
                showError(gate, 'Die Pruefung ist momentan nicht verfuegbar. Bitte erneut versuchen.');
            });
    }

    function bindGate(gate) {
        if (gate.getAttribute('data-internautenav-bound') === '1') {
            setPaymentLocked(gate, gate.getAttribute('data-verified') !== '1');
            return;
        }

        gate.setAttribute('data-internautenav-bound', '1');
        setLineRules(gate);
        setPaymentLocked(gate, gate.getAttribute('data-verified') !== '1');

        gate.addEventListener('click', function (event) {
            if (event.target.closest('.js-internautenav-open')) {
                event.preventDefault();
                openModal(gate);
                return;
            }

            if (event.target.closest('.js-internautenav-close')) {
                event.preventDefault();
                closeModal(gate);
            }
        });

        gate.addEventListener('change', function (event) {
            if (event.target.matches('.js-internautenav-doc-type')) {
                clearError(gate);
                setLineRules(gate);
                return;
            }

            if (event.target.closest('.js-internautenav-doc-fields')) {
                clearError(gate);
            }
        });

        var form = getModalForm(gate);
        if (form) {
            form.addEventListener('submit', function (event) {
                event.stopPropagation();
                handleModalSubmit(gate, event);
            });

            form.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                if (event.target && event.target.tagName === 'TEXTAREA') {
                    return;
                }

                handleModalSubmit(gate, event);
            });
        }

        var submitButton = getSubmitButton(gate);
        if (submitButton) {
            submitButton.addEventListener('click', function (event) {
                handleModalSubmit(gate, event);
            });
        }

        var step = getPaymentStep(gate);
        if (step && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function () {
                setPaymentLocked(gate, gate.getAttribute('data-verified') !== '1');
            });

            observer.observe(step, {
                childList: true,
                subtree: true
            });
        }
    }

    function refreshGates() {
        getAllGates().forEach(function (gate) {
            bindGate(gate);
        });
        document.querySelectorAll('.internautenav-mrz-box').forEach(function (box) {
            if (box.closest(GATE_SELECTOR)) {
                return;
            }
            var select = box.querySelector('.js-internautenav-doc-type');
            if (select) {
                applyDocTypeBlocks(box, select.value);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        getAllGates().forEach(function (gate) {
            closeModal(gate);
        });
    });

    document.addEventListener('updatedDeliveryForm', refreshGates);
    document.addEventListener('updatedCart', refreshGates);

    document.addEventListener('change', function (event) {
        if (!event.target.matches('.js-internautenav-doc-type')) {
            return;
        }
        if (event.target.closest(GATE_SELECTOR)) {
            return;
        }
        var box = event.target.closest('.internautenav-mrz-box');
        if (box) {
            applyDocTypeBlocks(box, event.target.value);
        }
    });

    refreshGates();
});



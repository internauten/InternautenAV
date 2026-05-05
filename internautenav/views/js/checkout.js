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
        if (errorNode) {
            errorNode.hidden = true;
            errorNode.textContent = '';
        }

        gate.querySelectorAll('.js-internautenav-chid-check-error, .js-internautenav-chid-line2-error, .js-internautenav-chpass-line2-error').forEach(function (el) {
            el.hidden = true;
            el.textContent = '';
        });
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

    function mrzCharValue(ch) {
        var code = ch.toUpperCase().charCodeAt(0);
        if (code >= 48 && code <= 57) { return code - 48; }       // 0-9
        if (code >= 65 && code <= 90) { return code - 55; }       // A-Z => 10-35
        return 0;                                                   // '<' and others
    }

    function computeMrzCheckDigit(str) {
        var weights = [7, 3, 1];
        var sum = 0;
        for (var i = 0; i < str.length; i++) {
            sum += mrzCharValue(str[i]) * weights[i % 3];
        }
        return sum % 10;
    }

    function validateChIdCheckDigit(gate) {
        var block = gate.querySelector('.js-internautenav-doc-fields[data-doc-type="ch_id"]');
        if (!block) { return true; }
        var numberInput = block.querySelector('input[data-chid-number="1"]');
        var checkInput = block.querySelector('input[data-chid-check="1"]');
        if (!numberInput || !checkInput) { return true; }

        // Document number field in the MRZ is 9 characters (8 entered + '<' filler)
        var docField = numberInput.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase().padEnd(9, '<').slice(0, 9);
        var expected = computeMrzCheckDigit(docField);
        var entered = parseInt(checkInput.value, 10);
        return expected === entered;
    }

    function validateChIdLine2(gate) {
        var block = gate.querySelector('.js-internautenav-doc-fields[data-doc-type="ch_id"]');
        if (!block) { return null; }
        var birth7Input = block.querySelector('input[data-chid-birth7="1"]');
        var expiry7Input = block.querySelector('input[data-chid-expiry7="1"]');
        var compositeInput = block.querySelector('input[data-chid-composite="1"]');
        var numberInput = block.querySelector('input[data-chid-number="1"]');
        var line1CheckInput = block.querySelector('input[data-chid-check="1"]');
        if (!birth7Input || !expiry7Input || !compositeInput) { return null; }

        var birth7 = birth7Input.value;
        var expiry7 = expiry7Input.value;

        if (birth7.length === 7) {
            var expectedBirth = computeMrzCheckDigit(birth7.slice(0, 6));
            if (expectedBirth !== parseInt(birth7.slice(6), 10)) {
                return 'Die Prüfziffer des Geburtsdatums stimmt nicht.';
            }
        }

        if (expiry7.length === 7) {
            var expectedExpiry = computeMrzCheckDigit(expiry7.slice(0, 6));
            if (expectedExpiry !== parseInt(expiry7.slice(6), 10)) {
                return 'Die Prüfziffer des Ablaufdatums stimmt nicht.';
            }
        }

        // Composite check: covers line1[5-29] + line2[0-6] + line2[8-14]
        // = docNum(9) + docCheck(1) + optionalData1(15) + birth7(7) + expiry7(7) = 39 chars
        var docNum = numberInput ? (numberInput.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase().padEnd(8, '<').slice(0, 8) + '<') : '<<<<<<<<<';
        var docCheck = line1CheckInput ? line1CheckInput.value.replace(/[^0-9]/g, '') || '0' : '0';
        var optional1 = '<<<<<<<<<<<<<<<'; // 15 chars optional data line1
        var compositeStr = docNum + docCheck + optional1 + birth7 + expiry7;
        var expectedComposite = computeMrzCheckDigit(compositeStr);
        var enteredComposite = parseInt(compositeInput.value, 10);
        if (expectedComposite !== enteredComposite) {
            return 'Die Gesamtprüfziffer stimmt nicht.';
        }

        return null;
    }

    function validateChPassLine2(gate) {
        var block = gate.querySelector('.js-internautenav-doc-fields[data-doc-type="ch_pass"]');
        if (!block) { return null; }

        var numberInput = block.querySelector('input[data-chpass-number="1"]');
        var numberCheckInput = block.querySelector('input[data-chpass-number-check="1"]');
        var birth7Input = block.querySelector('input[data-chpass-birth7="1"]');
        var sexSpan = block.querySelector('[data-chpass-sex]');
        var expiry7Input = block.querySelector('input[data-chpass-expiry7="1"]');
        var compositeInput = block.querySelector('input[data-chpass-composite="1"]');

        if (!numberInput || !numberCheckInput || !birth7Input || !sexSpan || !expiry7Input || !compositeInput) {
            return null;
        }

        var docField = numberInput.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase().padEnd(8, '<').slice(0, 8) + '<';
        var expectedDocCheck = computeMrzCheckDigit(docField);
        if (expectedDocCheck !== parseInt(numberCheckInput.value, 10)) {
            return 'Die Prüfziffer der Ausweisnummer stimmt nicht.';
        }

        var birth7 = birth7Input.value.replace(/[^0-9]/g, '');
        var birth = birth7.slice(0, 6);
        var birthCheck = birth7.slice(6, 7);
        var expectedBirthCheck = computeMrzCheckDigit(birth);
        if (expectedBirthCheck !== parseInt(birthCheck, 10)) {
            return 'Die Prüfziffer des Geburtsdatums stimmt nicht.';
        }

        var expiry7 = expiry7Input.value.replace(/[^0-9]/g, '');
        var expiry = expiry7.slice(0, 6);
        var expiryCheck = expiry7.slice(6, 7);
        var expectedExpiryCheck = computeMrzCheckDigit(expiry);
        if (expectedExpiryCheck !== parseInt(expiryCheck, 10)) {
            return 'Die Prüfziffer des Ablaufdatums stimmt nicht.';
        }

        var sex = (sexSpan.getAttribute('data-chpass-sex') || '<').toUpperCase().slice(0, 1);
        var line2CompositeInput = docField
            + numberCheckInput.value.replace(/[^0-9]/g, '')
            + birth
            + birthCheck
            + expiry
            + expiryCheck
            + '<<<<<<<<<<<<<<<';

        var expectedComposite = computeMrzCheckDigit(line2CompositeInput);
        if (expectedComposite !== parseInt(compositeInput.value, 10)) {
            return 'Die Gesamtprüfziffer stimmt nicht.';
        }

        return null;
    }

    function collectPayload(gate) {
        var select = getDocTypeSelect(gate);
        var docType = select ? select.value : '';
        var activeBlock = docType ? gate.querySelector('.js-internautenav-doc-fields[data-doc-type="' + docType + '"]') : null;

        function buildChIdLine1() {
            if (!activeBlock) {
                return '';
            }
            var numberInput = activeBlock.querySelector('input[data-chid-number="1"]');
            var checkInput = activeBlock.querySelector('input[data-chid-check="1"]');
            var docNumber = numberInput ? numberInput.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase() : '';
            var checkDigit = checkInput ? checkInput.value.replace(/[^0-9]/g, '') : '';
            var rawLine = 'IDCHE' + docNumber + '<' + checkDigit;
            return rawLine.padEnd(30, '<').slice(0, 30);
        }

        function buildChIdLine2() {
            if (!activeBlock) {
                return '';
            }
            var birth7Input = activeBlock.querySelector('input[data-chid-birth7="1"]');
            var sexSpan = activeBlock.querySelector('[data-chid-sex]');
            var expiry7Input = activeBlock.querySelector('input[data-chid-expiry7="1"]');
            var compositeInput = activeBlock.querySelector('input[data-chid-composite="1"]');

            var birth7 = birth7Input ? birth7Input.value.padEnd(7, '<').slice(0, 7) : '<<<<<<<';
            var sex = sexSpan ? (sexSpan.getAttribute('data-chid-sex') || '<') : '<';
            var expiry7 = expiry7Input ? expiry7Input.value.padEnd(7, '<').slice(0, 7) : '<<<<<<<';
            var composite = compositeInput ? compositeInput.value.replace(/[^0-9]/g, '') : '<';

            // TD1 line 2: birth7(7) + sex(1) + expiry7(7) + CHE(3) + 11*<(11) + composite(1) = 30
            return (birth7 + sex + expiry7 + 'CHE<<<<<<<<<<<' + composite).padEnd(30, '<').slice(0, 30);
        }

        function buildChIdLine3() {
            if (!activeBlock) {
                return '';
            }
            var line3Node = activeBlock.querySelector('.js-internautenav-chid-line3-text');
            if (!line3Node) {
                return '';
            }
            return (line3Node.getAttribute('data-line3') || line3Node.textContent || '').trim();
        }

        function buildChPassLine1() {
            if (!activeBlock) {
                return '';
            }
            var line1Node = activeBlock.querySelector('.js-internautenav-chpass-line1-text');
            if (!line1Node) {
                return '';
            }
            var line1 = (line1Node.getAttribute('data-line1') || line1Node.textContent || '').trim().toUpperCase();
            return line1.replace(/[^A-Z0-9<]/g, '<').padEnd(44, '<').slice(0, 44);
        }

        function buildChPassLine2() {
            if (!activeBlock) {
                return '';
            }
            var numberInput = activeBlock.querySelector('input[data-chpass-number="1"]');
            var numberCheckInput = activeBlock.querySelector('input[data-chpass-number-check="1"]');
            var birth7Input = activeBlock.querySelector('input[data-chpass-birth7="1"]');
            var sexSpan = activeBlock.querySelector('[data-chpass-sex]');
            var expiry7Input = activeBlock.querySelector('input[data-chpass-expiry7="1"]');
            var compositeInput = activeBlock.querySelector('input[data-chpass-composite="1"]');

            var docField = (numberInput ? numberInput.value : '').replace(/[^0-9A-Za-z]/g, '').toUpperCase().padEnd(8, '<').slice(0, 8) + '<';
            var docCheck = (numberCheckInput ? numberCheckInput.value : '').replace(/[^0-9]/g, '').slice(0, 1) || '<';
            var birth7 = (birth7Input ? birth7Input.value : '').replace(/[^0-9]/g, '');
            var birth = birth7.slice(0, 6).padEnd(6, '<');
            var birthCheck = birth7.slice(6, 7) || '<';
            var sex = sexSpan ? (sexSpan.getAttribute('data-chpass-sex') || '<') : '<';
            sex = sex.toUpperCase().replace(/[^MF<]/g, '<').slice(0, 1) || '<';
            var expiry7 = (expiry7Input ? expiry7Input.value : '').replace(/[^0-9]/g, '');
            var expiry = expiry7.slice(0, 6).padEnd(6, '<');
            var expiryCheck = expiry7.slice(6, 7) || '<';
            var composite = (compositeInput ? compositeInput.value : '').replace(/[^0-9]/g, '').slice(0, 1) || '<';

            return (docField + docCheck + 'CHE' + birth + birthCheck + sex + expiry + expiryCheck + '<<<<<<<<<<<<<<<' + composite)
                .padEnd(44, '<')
                .slice(0, 44);
        }

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
            line1: docType === 'ch_id' ? buildChIdLine1() : (docType === 'ch_pass' ? buildChPassLine1() : getLineValue('line1')),
            line2: docType === 'ch_id' ? buildChIdLine2() : (docType === 'ch_pass' ? buildChPassLine2() : getLineValue('line2')),
            line3: docType === 'ch_id' ? buildChIdLine3() : getLineValue('line3')
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

        if (payload.docType === 'ch_id') {
            var chIdCheckErrorNode = gate.querySelector('.js-internautenav-chid-check-error');
            if (!validateChIdCheckDigit(gate)) {
                if (chIdCheckErrorNode) {
                    chIdCheckErrorNode.hidden = false;
                    chIdCheckErrorNode.textContent = 'Die Prüfziffer stimmt nicht mit der Ausweisnummer überein.';
                } else {
                    showError(gate, 'Die Prüfziffer stimmt nicht mit der Ausweisnummer überein.');
                }
                return;
            }
            if (chIdCheckErrorNode) {
                chIdCheckErrorNode.hidden = true;
                chIdCheckErrorNode.textContent = '';
            }

            var chIdLine2ErrorNode = gate.querySelector('.js-internautenav-chid-line2-error');
            var line2Error = validateChIdLine2(gate);
            if (line2Error) {
                if (chIdLine2ErrorNode) {
                    chIdLine2ErrorNode.hidden = false;
                    chIdLine2ErrorNode.textContent = line2Error;
                } else {
                    showError(gate, line2Error);
                }
                return;
            }
            if (chIdLine2ErrorNode) {
                chIdLine2ErrorNode.hidden = true;
                chIdLine2ErrorNode.textContent = '';
            }
        }

        if (payload.docType === 'ch_pass') {
            var chPassLine2ErrorNode = gate.querySelector('.js-internautenav-chpass-line2-error');
            var chPassLine2Error = validateChPassLine2(gate);
            if (chPassLine2Error) {
                if (chPassLine2ErrorNode) {
                    chPassLine2ErrorNode.hidden = false;
                    chPassLine2ErrorNode.textContent = chPassLine2Error;
                } else {
                    showError(gate, chPassLine2Error);
                }
                return;
            }
            if (chPassLine2ErrorNode) {
                chPassLine2ErrorNode.hidden = true;
                chPassLine2ErrorNode.textContent = '';
            }
        }

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



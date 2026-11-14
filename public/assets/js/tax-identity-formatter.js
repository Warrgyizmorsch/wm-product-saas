/**
 * Tax Identity Formatter & Mask (GSTIN & PAN)
 * - Strict keystroke restriction (digits for state, letters for PAN, digits for PAN serial, etc.)
 * - Shows State badge (e.g. "State: 11 - Sikkim") as soon as first 2 digits are typed
 * - Explains EXACT reason if invalid on blur (e.g. characters count 14/15, 14th digit must be Z, etc.)
 */
(function (window, document) {
    'use strict';

    var GST_STATES = {
        '01': 'Jammu & Kashmir',
        '02': 'Himachal Pradesh',
        '03': 'Punjab',
        '04': 'Chandigarh',
        '05': 'Uttarakhand',
        '06': 'Haryana',
        '07': 'Delhi',
        '08': 'Rajasthan',
        '09': 'Uttar Pradesh',
        '10': 'Bihar',
        '11': 'Sikkim',
        '12': 'Arunachal Pradesh',
        '13': 'Nagaland',
        '14': 'Manipur',
        '15': 'Mizoram',
        '16': 'Tripura',
        '17': 'Meghalaya',
        '18': 'Assam',
        '19': 'West Bengal',
        '20': 'Jharkhand',
        '21': 'Odisha',
        '22': 'Chhattisgarh',
        '23': 'Madhya Pradesh',
        '24': 'Gujarat',
        '26': 'Dadra & Nagar Haveli and Daman & Diu',
        '27': 'Maharashtra',
        '28': 'Andhra Pradesh (Old)',
        '29': 'Karnataka',
        '30': 'Goa',
        '31': 'Lakshadweep',
        '32': 'Kerala',
        '33': 'Tamil Nadu',
        '34': 'Puducherry',
        '35': 'Andaman & Nicobar Islands',
        '36': 'Telangana',
        '37': 'Andhra Pradesh',
        '38': 'Ladakh',
        '96': 'Foreign Country',
        '97': 'Other Territory'
    };

    // Indian GSTIN structure (15 characters)
    // Pos 0, 1: 2 digits State Code (01-38)
    // Pos 2-6: 5 letters PAN entity
    // Pos 7-10: 4 digits PAN serial
    // Pos 11: 1 letter PAN checksum
    // Pos 12: 1 alphanumeric (entity code, usually 1 to 9 or A-Z)
    // Pos 13: 1 letter (default 'Z')
    // Pos 14: 1 alphanumeric check character (0-9 or A-Z)
    var GSTIN_MASKS = [
        /^[0-9]$/,       // 0
        /^[0-9]$/,       // 1
        /^[A-Za-z]$/,    // 2
        /^[A-Za-z]$/,    // 3
        /^[A-Za-z]$/,    // 4
        /^[A-Za-z]$/,    // 5
        /^[A-Za-z]$/,    // 6
        /^[0-9]$/,       // 7
        /^[0-9]$/,       // 8
        /^[0-9]$/,       // 9
        /^[0-9]$/,       // 10
        /^[A-Za-z]$/,    // 11
        /^[A-Za-z0-9]$/, // 12
        /^[A-Za-z]$/,    // 13 (Z)
        /^[A-Za-z0-9]$/  // 14
    ];

    // Indian PAN structure (10 characters: 5 letters + 4 digits + 1 letter)
    var PAN_MASKS = [
        /^[A-Za-z]$/, // 0
        /^[A-Za-z]$/, // 1
        /^[A-Za-z]$/, // 2
        /^[A-Za-z]$/, // 3
        /^[A-Za-z]$/, // 4
        /^[0-9]$/,    // 5
        /^[0-9]$/,    // 6
        /^[0-9]$/,    // 7
        /^[0-9]$/,    // 8
        /^[A-Za-z]$/  // 9
    ];

    var GSTIN_FULL_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    var PAN_FULL_REGEX = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;

    function isGstinInput(el) {
        if (!el || el.tagName !== 'INPUT') return false;
        var name = (el.getAttribute('name') || '').toLowerCase();
        var id = (el.getAttribute('id') || '').toLowerCase();
        var taxType = (el.getAttribute('data-tax-type') || '').toLowerCase();
        return taxType === 'gstin' ||
            name === 'gstin' || name === 'gst_number' || name === 'gst' || name === 'seller_gstin' || name === 'billing_gstin' ||
            id === 'gstin_input' || id === 'edit_gstin_input' || id === 'gstin';
    }

    function isPanInput(el) {
        if (!el || el.tagName !== 'INPUT') return false;
        var name = (el.getAttribute('name') || '').toLowerCase();
        var id = (el.getAttribute('id') || '').toLowerCase();
        var taxType = (el.getAttribute('data-tax-type') || '').toLowerCase();
        return taxType === 'pan' ||
            name === 'pan' || name === 'pan_number' || name === 'pan_card_number' ||
            id === 'pan_input' || id === 'pan' || id === 'pan_number';
    }

    function updateStateBadge(input, val) {
        var parent = input.closest('.flex-grow-1, .odoo-form-group, .form-group, .mb-3') || input.parentElement;
        var badge = parent.querySelector('.tax-state-badge');
        if (val && val.length >= 2) {
            var code = val.slice(0, 2);
            var name = GST_STATES[code];
            if (name) {
                if (!badge) {
                    badge = document.createElement('div');
                    badge.className = 'tax-state-badge mt-1';
                    input.insertAdjacentElement('afterend', badge);
                }
                badge.innerHTML = '<span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 fw-semibold"><i class="feather-map-pin me-1"></i>State: ' + code + ' - ' + name + '</span>';
                return;
            }
        }
        if (badge) badge.remove();
    }

    function getErrorContainer(input) {
        var parent = input.closest('.flex-grow-1, .odoo-form-group, .form-group, .mb-3') || input.parentElement;
        var errEl = parent.querySelector('.tax-error-feedback');
        if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'invalid-feedback d-block tax-error-feedback fs-11 mt-1';
            input.insertAdjacentElement('afterend', errEl);
        }
        return errEl;
    }

    function removeError(input) {
        input.classList.remove('is-invalid');
        var parent = input.closest('.flex-grow-1, .odoo-form-group, .form-group, .mb-3') || input.parentElement;
        var errEl = parent.querySelector('.tax-error-feedback');
        if (errEl) errEl.remove();
    }

    function setError(input, msg) {
        input.classList.add('is-invalid');
        var errEl = getErrorContainer(input);
        errEl.textContent = msg;
    }

    /**
     * Strict keystroke restriction on keydown
     */
    function handleKeyDown(e) {
        var input = e.target;
        var isGst = isGstinInput(input);
        var isPan = isPanInput(input);
        if (!isGst && !isPan) return;

        // Allow navigation, modifier shortcuts (Ctrl+A, Ctrl+C, Ctrl+V, etc.)
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        var navKeys = ['Backspace', 'Delete', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
        if (navKeys.indexOf(e.key) !== -1) return;
        if (e.key.length !== 1) return;

        var start = input.selectionStart;
        var end = input.selectionEnd;
        var selLen = end - start;
        var maxLen = isGst ? 15 : 10;
        var masks = isGst ? GSTIN_MASKS : PAN_MASKS;

        // Block typing beyond max length
        if (start >= maxLen && selLen === 0) {
            e.preventDefault();
            return;
        }

        var mask = masks[start];
        if (!mask) return;

        // If typed character does not match the required slot
        if (!mask.test(e.key)) {
            e.preventDefault(); // Silently block
            return;
        }
    }

    /**
     * Input listener: auto-uppercase and update state badge
     */
    function handleInput(e) {
        var input = e.target;
        var isGst = isGstinInput(input);
        var isPan = isPanInput(input);
        if (!isGst && !isPan) return;

        // Clear error while actively typing
        removeError(input);

        var maxLen = isGst ? 15 : 10;
        var masks = isGst ? GSTIN_MASKS : PAN_MASKS;
        var raw = (input.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');

        var filtered = '';
        for (var i = 0; i < raw.length && i < maxLen; i++) {
            var ch = raw[i];
            var mask = masks[i];
            if (mask && mask.test(ch)) {
                filtered += ch;
            } else {
                break;
            }
        }

        if (input.value !== filtered) {
            input.value = filtered;
        }

        if (isGst) {
            updateStateBadge(input, filtered);

            // Auto-populate PAN if 15 characters complete
            if (filtered.length === 15) {
                var form = input.closest('form');
                if (form) {
                    var panInput = form.querySelector('input[name="pan"], input[name="pan_number"], input[name="pan_card_number"], #pan, #pan_number');
                    if (panInput && (!panInput.value || panInput.getAttribute('data-auto-pan') === 'true')) {
                        var extractedPan = filtered.slice(2, 12);
                        if (PAN_FULL_REGEX.test(extractedPan)) {
                            panInput.value = extractedPan;
                            panInput.setAttribute('data-auto-pan', 'true');
                        }
                    }
                }
            }
        }
    }

    /**
     * Blur listener: Check format and explain EXACT reason if invalid
     */
    function handleBlur(e) {
        var input = e.target;
        var isGst = isGstinInput(input);
        var isPan = isPanInput(input);
        if (!isGst && !isPan) return;

        var val = (input.value || '').trim().toUpperCase();

        if (!val) {
            removeError(input);
            if (isGst) updateStateBadge(input, '');
            return;
        }

        if (isGst) {
            updateStateBadge(input, val);

            if (val.length < 15) {
                setError(input, 'GSTIN is incomplete (' + val.length + '/15 characters). Must be exactly 15 characters (e.g. 27AAAAA0000A1Z5).');
            } else if (!GST_STATES[val.slice(0, 2)]) {
                setError(input, 'Invalid State Code "' + val.slice(0, 2) + '". State code must be between 01 and 38.');
            } else if (val[13] !== 'Z') {
                setError(input, 'Invalid GSTIN: The 14th character must be "Z" (e.g. ' + val.slice(0, 13) + 'Z' + val.slice(14) + ').');
            } else if (!GSTIN_FULL_REGEX.test(val)) {
                setError(input, 'Invalid GSTIN format. Example: 27AAAAA0000A1Z5');
            } else {
                removeError(input);
            }
        } else if (isPan) {
            if (val.length < 10) {
                setError(input, 'PAN is incomplete (' + val.length + '/10 characters). Must be exactly 10 characters (e.g. AAAAA0000A).');
            } else if (!PAN_FULL_REGEX.test(val)) {
                setError(input, 'Invalid PAN format. Must be 5 letters + 4 digits + 1 letter (e.g. AAAAA0000A).');
            } else {
                removeError(input);
            }
        }
    }

    /**
     * Submit guard
     */
    function handleSubmit(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        var hasError = false;
        var firstBad = null;

        form.querySelectorAll('input').forEach(function (inp) {
            var val = (inp.value || '').trim().toUpperCase();
            if (!val) return;

            if (isGstinInput(inp)) {
                if (val.length !== 15 || !GSTIN_FULL_REGEX.test(val) || !GST_STATES[val.slice(0, 2)]) {
                    hasError = true;
                    if (val.length < 15) {
                        setError(inp, 'GSTIN is incomplete (' + val.length + '/15 characters). Must be exactly 15 characters.');
                    } else if (val[13] !== 'Z') {
                        setError(inp, 'The 14th character of GSTIN must be "Z" (e.g. ' + val.slice(0, 13) + 'Z' + val.slice(14) + ').');
                    } else {
                        setError(inp, 'Please enter a valid 15-character GSTIN (e.g. 27AAAAA0000A1Z5).');
                    }
                    if (!firstBad) firstBad = inp;
                }
            } else if (isPanInput(inp)) {
                if (val.length !== 10 || !PAN_FULL_REGEX.test(val)) {
                    hasError = true;
                    setError(inp, 'Please enter a valid 10-character PAN (e.g. AAAAA0000A).');
                    if (!firstBad) firstBad = inp;
                }
            }
        });

        if (hasError && firstBad) {
            e.preventDefault();
            e.stopPropagation();
            firstBad.focus();
            return false;
        }
    }

    function initInputs() {
        document.querySelectorAll('input').forEach(function (inp) {
            if (isGstinInput(inp)) {
                inp.setAttribute('maxlength', '15');
                inp.setAttribute('autocomplete', 'off');
                inp.classList.add('font-monospace', 'text-uppercase');
                if (inp.value) {
                    updateStateBadge(inp, inp.value);
                }
            } else if (isPanInput(inp)) {
                inp.setAttribute('maxlength', '10');
                inp.setAttribute('autocomplete', 'off');
                inp.classList.add('font-monospace', 'text-uppercase');
            }
        });
    }

    // Attach Capture Phase Listeners on document
    document.addEventListener('keydown', handleKeyDown, true);
    document.addEventListener('input', handleInput, true);
    document.addEventListener('blur', handleBlur, true);
    document.addEventListener('submit', handleSubmit, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInputs);
    } else {
        initInputs();
    }

    document.addEventListener('show.bs.modal', function () { setTimeout(initInputs, 100); }, true);
    document.addEventListener('show.bs.offcanvas', function () { setTimeout(initInputs, 100); }, true);

})(window, document);

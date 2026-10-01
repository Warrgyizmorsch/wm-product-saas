/*!
 * ERP searchable selects
 * ----------------------
 * Turns every data-driven <select> in the app into a searchable Select2
 * dropdown, without each page having to opt in.
 *
 * Which selects are enhanced
 *   - any <select> with more than SEARCH_THRESHOLD real options (party, item,
 *     account, vendor, customer, employee lists ... anything from the database),
 *   - every multi-select,
 *   - any <select data-searchable> (use this for a DB list that may still be short),
 *   and never:
 *   - <select data-no-search>, selects a page already set up with Select2,
 *     <select size="N"> list boxes, editor toolbars, DataTables "show N" boxes.
 *
 * Selects added later (item rows, modals, AJAX-filled dependent dropdowns) are
 * picked up automatically.
 *
 * Page scripts keep working unchanged:
 *   - el.addEventListener('change', ...) fires when an option is picked
 *     (Select2 only fires jQuery events on its own; we turn them into real ones),
 *   - el.value = '...' / el.selectedIndex = n updates the visible selection,
 *   - form.reset() resets the visible selection too.
 *
 * API: window.erpSearchableSelect.enhance(rootOrSelect)  — force a (re)scan
 *      window.erpSearchableSelect.refresh(select)          — re-render one select
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn || !$.fn.select2) {
        return;
    }

    var SEARCH_THRESHOLD = 5;      // > 5 options => searchable
    var FLAG = 'erpSearch';        // data-erp-search="1" on enhanced selects
    var SKIP_INSIDE = '.ql-toolbar, .dataTables_length, .dt-length, .flatpickr-calendar, .daterangepicker, .note-toolbar, .tox, .select2-container, template, [data-no-search]';

    function realOptionCount(select) {
        var n = 0;
        for (var i = 0; i < select.options.length; i++) {
            var o = select.options[i];
            if (o.value !== '' && o.value !== '__ADD_NEW__') {
                n++;
            }
        }
        return n;
    }

    function isManagedElsewhere(select) {
        // Already a live Select2 (page initialised it itself) or uses the theme's own selectors.
        return !!$(select).data('select2') || select.hasAttribute('data-select2-selector');
    }

    function shouldEnhance(select) {
        if (select.dataset[FLAG] === '1' || isManagedElsewhere(select)) {
            return false;
        }
        if (select.hasAttribute('data-no-search') || (select.size && select.size > 1)) {
            return false;
        }
        if ($(select).closest(SKIP_INSIDE).length) {
            return false;
        }
        // An element the page deliberately hides on its own (not via a hidden tab/modal).
        if (select.style.display === 'none' || select.classList.contains('d-none') || select.hidden) {
            return false;
        }
        if (select.hasAttribute('data-searchable') || select.multiple) {
            return true;
        }
        return realOptionCount(select) > SEARCH_THRESHOLD;
    }

    /** A select copied (cloneNode / jQuery clone) from an enhanced one carries dead Select2 markup — strip it. */
    function cleanClone(select) {
        if (select.classList.contains('select2-hidden-accessible') && !$(select).data('select2')) {
            var next = select.nextElementSibling;
            if (next && next.classList.contains('select2-container')) {
                next.parentNode.removeChild(next);
            }
            select.classList.remove('select2-hidden-accessible');
            select.removeAttribute('data-select2-id');
            select.removeAttribute('aria-hidden');
            select.removeAttribute('tabindex');
            $(select).find('option').removeAttr('data-select2-id');
            delete select.dataset[FLAG];
        }
    }

    function widthFor(select) {
        if (select.style.width) {
            return 'style';
        }
        var w = select.offsetWidth;
        var parent = select.parentElement;
        if (!w || !parent || !parent.clientWidth) {
            return '100%';             // hidden (tab, modal, collapse) — fill the column
        }
        // Filled its parent before → keep filling it; otherwise keep the width it had.
        return w >= parent.clientWidth - 4 ? '100%' : w + 'px';
    }

    function placeholderFor(select) {
        var first = select.options[0];
        if (first && first.value === '') {
            return first.text.trim() || 'Select…';
        }
        return select.getAttribute('data-placeholder') || null;
    }

    /** Keep the Select2 display in step when page code sets the value directly. */
    function patchValueSetters(select) {
        ['value', 'selectedIndex'].forEach(function (prop) {
            var desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, prop);
            if (!desc || !desc.set) {
                return;
            }
            Object.defineProperty(select, prop, {
                configurable: true,
                enumerable: true,
                get: function () { return desc.get.call(this); },
                set: function (v) {
                    desc.set.call(this, v);
                    if ($(this).data('select2')) {
                        $(this).trigger('change.select2');
                    }
                }
            });
        });
    }

    function enhanceOne(select) {
        cleanClone(select);
        if (!shouldEnhance(select)) {
            return;
        }

        var $select = $(select);
        var $parent = $select.closest('.modal, .offcanvas');
        var placeholder = placeholderFor(select);
        var small = select.classList.contains('form-select-sm') || select.classList.contains('form-control-sm');

        var options = {
            theme: 'bootstrap-5',
            width: widthFor(select),
            selectionCssClass: small ? 'erp-s2-sm' : '',
            dropdownCssClass: small ? 'erp-s2-sm-dropdown' : '',
            minimumResultsForSearch: 0
        };
        if ($parent.length) {
            options.dropdownParent = $parent;
        }
        if (placeholder) {
            options.placeholder = placeholder;
            options.allowClear = !select.required && !select.multiple && select.options[0] && select.options[0].value === '';
        }

        select.dataset[FLAG] = '1';
        $select.select2(options);
        patchValueSetters(select);
    }

    function enhance(root) {
        root = root || document;
        if (root.tagName === 'SELECT') {
            enhanceOne(root);
            return;
        }
        var list = root.querySelectorAll ? root.querySelectorAll('select') : [];
        for (var i = 0; i < list.length; i++) {
            enhanceOne(list[i]);
        }
    }

    function refresh(select) {
        if (select && $(select).data('select2')) {
            $(select).trigger('change.select2');
        }
    }

    /*
     * Select2 announces a pick with jQuery's .trigger('change'), which never
     * reaches listeners added with addEventListener / onchange / Alpine. For our
     * selects, turn an un-namespaced jQuery change/input into a real DOM event —
     * jQuery handlers still run (jQuery listens natively), exactly once.
     */
    ['change', 'input'].forEach(function (type) {
        var special = $.event.special[type] = $.event.special[type] || {};
        var original = special.trigger;
        special.trigger = function (event) {
            var el = this;
            if (el && el.tagName === 'SELECT' && el.dataset && el.dataset[FLAG] === '1'
                && !(event && event.namespace) && !el.__erpDispatching) {
                el.__erpDispatching = true;
                try {
                    el.dispatchEvent(new Event(type, { bubbles: true }));
                } finally {
                    el.__erpDispatching = false;
                }
                return false;
            }
            return original ? original.apply(this, arguments) : undefined;
        };
    });

    // $(select).val(x) without .trigger('change') — keep the display in step too.
    if ($.valHooks && $.valHooks.select && $.valHooks.select.set) {
        var originalSet = $.valHooks.select.set;
        $.valHooks.select.set = function (elem) {
            var result = originalSet.apply(this, arguments);
            if (elem.dataset && elem.dataset[FLAG] === '1' && $(elem).data('select2')) {
                $(elem).trigger('change.select2');
            }
            return result;
        };
    }

    // form.reset() restores the <select>, but not the Select2 display.
    document.addEventListener('reset', function (e) {
        var form = e.target;
        setTimeout(function () {
            $(form).find('select').each(function () {
                if (this.dataset[FLAG] === '1') {
                    refresh(this);
                }
            });
        }, 0);
    }, true);

    // Open search box gets the cursor straight away.
    $(document).on('select2:open', function (e) {
        if (e.target && e.target.dataset && e.target.dataset[FLAG] === '1') {
            setTimeout(function () {
                var field = document.querySelector('.select2-container--open .select2-search__field');
                if (field) {
                    field.focus();
                }
            }, 0);
        }
    });

    // New selects (rows, modals, partial reloads) and selects whose options were filled later.
    var pending = [];
    var scheduled = false;
    function queue(node) {
        pending.push(node);
        if (!scheduled) {
            scheduled = true;
            setTimeout(function () {
                var nodes = pending;
                pending = [];
                scheduled = false;
                nodes.forEach(function (n) {
                    if (n.isConnected) {
                        enhance(n);
                    }
                });
            }, 30);
        }
    }

    function start() {
        enhance(document);

        if (!window.MutationObserver) {
            return;
        }
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                var target = m.target;
                // Options added to a plain select (dependent dropdown filled by AJAX).
                if (target.tagName === 'SELECT' || (target.tagName === 'OPTGROUP' && target.parentNode && target.parentNode.tagName === 'SELECT')) {
                    var sel = target.tagName === 'SELECT' ? target : target.parentNode;
                    if (sel.dataset[FLAG] !== '1') {
                        queue(sel);
                    }
                    continue;
                }
                for (var j = 0; j < m.addedNodes.length; j++) {
                    var node = m.addedNodes[j];
                    if (node.nodeType === 1 && (node.tagName === 'SELECT' || node.querySelector('select'))) {
                        queue(node);
                    }
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.erpSearchableSelect = { enhance: enhance, refresh: refresh };

    // After the pages' own ready handlers, so selects they set up themselves are left alone.
    $(function () {
        setTimeout(start, 0);
    });
})(window.jQuery);

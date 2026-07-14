import { SelectAdapter } from './select-adapter.js';

// Same Select2 options the rest of the app uses for plain (non-modal)
// dropdowns — see initOdooComponents() in duralux.blade.php.
const SELECT2_OPTIONS = { theme: 'bootstrap-5', width: '100%' };

// Extends SelectAdapter only to reuse format() (option-value -> label
// lookup). activate()/deactivate() are fully overridden rather than
// building on SelectAdapter's native change/blur/focus wiring: opening
// Select2 hands keyboard focus to a search input it generates as a
// separate DOM node, which blurs the original (now-hidden) <select> as an
// ordinary side effect of that handoff — not because the user left the
// field. A blur listener on the original element can't tell those apart
// and would commit (and, since nothing changed yet, immediately cancel and
// destroy Select2) the moment it opens.
export class Select2Adapter extends SelectAdapter {
    activate() {
        // Defensive: guarantees a clean slate even if a previous edit cycle
        // on this same <select> didn't fully tear down (e.g. deactivate()
        // was skipped or Select2 left residual internal state), rather than
        // assuming the element is untouched since the last activate().
        if ($(this.controlEl).hasClass('select2-hidden-accessible')) {
            $(this.controlEl).select2('destroy');
        }

        $(this.controlEl).select2(SELECT2_OPTIONS);

        // select2:close fires on the original <select> whenever the
        // dropdown closes for any reason (a selection, Escape, or an
        // outside click) — commit() already no-ops back to cancelEdit()
        // when nothing actually changed, so this one event is enough to
        // cover both outcomes.
        this.handleSelect2Close = () => this.onCommit();
        $(this.controlEl).on('select2:close', this.handleSelect2Close);

        $(this.controlEl).select2('open');
    }

    deactivate() {
        $(this.controlEl).off('select2:close', this.handleSelect2Close);

        if ($(this.controlEl).hasClass('select2-hidden-accessible')) {
            $(this.controlEl).select2('destroy');
        }
    }
}

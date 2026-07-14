import { BaseAdapter } from './base-adapter.js';

export class SelectAdapter extends BaseAdapter {
    activate() {
        this.handleKeydown = (event) => {
            if (event.key === 'Escape') {
                this.onCancel();
            }
        };
        this.handleChange = () => this.onCommit();
        this.handleBlur = () => this.onCommit();

        this.controlEl.addEventListener('keydown', this.handleKeydown);
        this.controlEl.addEventListener('change', this.handleChange);
        this.controlEl.addEventListener('blur', this.handleBlur);
        this.controlEl.focus();
    }

    deactivate() {
        this.controlEl.removeEventListener('keydown', this.handleKeydown);
        this.controlEl.removeEventListener('change', this.handleChange);
        this.controlEl.removeEventListener('blur', this.handleBlur);
    }

    // The option labels are already rendered by Blade (translated); reuse
    // whichever option's text matches the value instead of duplicating labels in JS.
    format(value) {
        const option = Array.from(this.controlEl.options).find((o) => o.value === value);

        return option ? option.textContent : value;
    }
}

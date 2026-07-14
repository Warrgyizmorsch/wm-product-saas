import { BaseAdapter } from './base-adapter.js';

export class DateAdapter extends BaseAdapter {
    activate() {
        this.handleKeydown = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.onCommit();
            }
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

    // Server value is a plain Y-m-d string; render it the same way the
    // Project Details page already displays dates (d/m/Y).
    format(value) {
        if (!value) return value;

        const [year, month, day] = value.split('-');

        return `${day}/${month}/${year}`;
    }
}

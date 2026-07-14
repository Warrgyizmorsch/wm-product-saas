import { BaseAdapter } from './base-adapter.js';

export class TextAdapter extends BaseAdapter {
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
        this.handleBlur = () => this.onCommit();

        this.controlEl.addEventListener('keydown', this.handleKeydown);
        this.controlEl.addEventListener('blur', this.handleBlur);
        this.controlEl.focus();
        this.controlEl.select();
    }

    deactivate() {
        this.controlEl.removeEventListener('keydown', this.handleKeydown);
        this.controlEl.removeEventListener('blur', this.handleBlur);
    }
}

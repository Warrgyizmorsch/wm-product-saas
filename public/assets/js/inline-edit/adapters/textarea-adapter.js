import { BaseAdapter } from './base-adapter.js';

export class TextareaAdapter extends BaseAdapter {
    activate() {
        this.handleKeydown = (event) => {
            if (event.key === 'Escape') {
                this.onCancel();
                return;
            }
            // Plain Enter inserts a newline like a normal textarea; only
            // Ctrl/Cmd+Enter commits, since multi-line text needs Enter free.
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                this.onCommit();
            }
        };
        this.handleBlur = () => this.onCommit();

        this.controlEl.addEventListener('keydown', this.handleKeydown);
        this.controlEl.addEventListener('blur', this.handleBlur);
        this.controlEl.focus();
    }

    deactivate() {
        this.controlEl.removeEventListener('keydown', this.handleKeydown);
        this.controlEl.removeEventListener('blur', this.handleBlur);
    }
}

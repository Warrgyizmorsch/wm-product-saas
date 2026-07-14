import { patchField } from './api.js';
import { adapterRegistry } from './adapters/index.js';

export class InlineEditField {
    constructor(el, manager) {
        this.el = el;
        this.manager = manager;
        this.viewEl = el.querySelector('.inline-edit__view');
        this.editEl = el.querySelector('.inline-edit__edit');
        this.controlEl = el.querySelector('.inline-edit__control');
        this.errorEl = el.querySelector('.inline-edit__error');
        this.field = el.dataset.field;
        this.url = el.dataset.url;
        this.emptyPlaceholder = el.dataset.emptyPlaceholder ?? '';
        this.originalValue = this.controlEl.value;

        const Adapter = adapterRegistry[el.dataset.type] ?? adapterRegistry.text;
        this.adapter = new Adapter(this.controlEl, {
            onCommit: () => this.commit(),
            onCancel: () => this.cancelEdit(),
        });

        this.viewEl.addEventListener('click', () => this.enterEdit());
        this.viewEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') this.enterEdit();
        });
    }

    isSaving() {
        return this.el.classList.contains('is-saving');
    }

    enterEdit() {
        if (!this.manager.activate(this)) {
            return;
        }
        this.el.classList.add('is-editing');
        this.viewEl.classList.add('d-none');
        this.editEl.classList.remove('d-none');
        this.clearError();
        this.adapter.activate();
    }

    cancelEdit() {
        this.adapter.deactivate();
        this.adapter.setValue(this.originalValue);
        this.el.classList.remove('is-editing');
        this.editEl.classList.add('d-none');
        this.viewEl.classList.remove('d-none');
        this.clearError();
        this.manager.clear(this);
    }

    async commit() {
        if (this.el.classList.contains('is-saving')) {
            return;
        }

        const value = this.adapter.getValue();

        if (value === this.originalValue) {
            this.cancelEdit();
            return;
        }

        this.el.classList.add('is-saving');
        this.controlEl.disabled = true;

        try {
            const data = await patchField(this.url, this.field, value);
            const isEmpty = data.value === null || data.value === '';
            this.originalValue = isEmpty ? '' : String(data.value);
            this.adapter.setValue(this.originalValue);
            this.viewEl.textContent = isEmpty ? this.emptyPlaceholder : this.adapter.format(this.originalValue);
            this.viewEl.classList.toggle('inline-edit__view--empty', isEmpty);
            this.cancelEdit();
            window.showAppToast?.('success', 'Saved');
        } catch (error) {
            if (error.status === 422) {
                const errors = error.data?.errors ?? {};
                const message = Object.values(errors)[0]?.[0] ?? 'Invalid value';
                this.showError(message);
            } else {
                this.cancelEdit();
                window.showAppToast?.('error', error.data?.message ?? 'Could not save');
            }
        } finally {
            this.el.classList.remove('is-saving');
            this.controlEl.disabled = false;
        }
    }

    showError(message) {
        this.errorEl.textContent = message;
        this.errorEl.classList.remove('d-none');
    }

    clearError() {
        this.errorEl.textContent = '';
        this.errorEl.classList.add('d-none');
    }
}

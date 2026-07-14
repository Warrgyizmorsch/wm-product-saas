export class BaseAdapter {
    constructor(controlEl, { onCommit, onCancel }) {
        this.controlEl = controlEl;
        this.onCommit = onCommit;
        this.onCancel = onCancel;
    }

    activate() {}

    deactivate() {}

    getValue() {
        return this.controlEl.value;
    }

    setValue(value) {
        this.controlEl.value = value;
    }

    format(value) {
        return value;
    }
}

export class InlineEditManager {
    constructor() {
        this.active = null;
    }

    activate(field) {
        if (this.active && this.active !== field) {
            if (this.active.isSaving()) {
                return false;
            }
            this.active.cancelEdit();
        }
        this.active = field;
        return true;
    }

    clear(field) {
        if (this.active === field) {
            this.active = null;
        }
    }
}

import { InlineEditManager } from './manager.js';
import { InlineEditField } from './field.js';

document.addEventListener('DOMContentLoaded', () => {
    const manager = new InlineEditManager();

    document.querySelectorAll('.inline-edit').forEach((el) => {
        new InlineEditField(el, manager);
    });

    // Scoped to .inline-edit so this doesn't double-init tooltips on pages
    // that already run their own page-level Bootstrap tooltip init.
    document.querySelectorAll('.inline-edit [data-bs-toggle="tooltip"]').forEach((el) => {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
});

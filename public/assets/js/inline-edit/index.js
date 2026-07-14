import { InlineEditManager } from './manager.js';
import { InlineEditField } from './field.js';

document.addEventListener('DOMContentLoaded', () => {
    const manager = new InlineEditManager();

    document.querySelectorAll('.inline-edit').forEach((el) => {
        new InlineEditField(el, manager);
    });
});

import { TextAdapter } from './text-adapter.js';
import { NumberAdapter } from './number-adapter.js';
import { DateAdapter } from './date-adapter.js';
import { SelectAdapter } from './select-adapter.js';
import { Select2Adapter } from './select2-adapter.js';
import { TextareaAdapter } from './textarea-adapter.js';

export const adapterRegistry = {
    text: TextAdapter,
    number: NumberAdapter,
    date: DateAdapter,
    select: SelectAdapter,
    select2: Select2Adapter,
    textarea: TextareaAdapter,
};

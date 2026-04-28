import TomSelect from 'tom-select';
import './select.scss';

function initTomSelects() {
    const selects = document.querySelectorAll('[data-advanced="true"]');
    selects.forEach(select => {
        if (select.tomselect) return;
        const multiple = select.hasAttribute('multiple');
        const allowAdd = select.hasAttribute('data-allow-add') && select.getAttribute('data-allow-add') === 'true';

        new TomSelect(select, {
            plugins: multiple ? {
                remove_button: {
                    title: 'Remove',
                }
            } : {},
            create: allowAdd ? (input) => {
                return {
                    value: input.toLowerCase().replace(/\s+/g, '-'),
                    text: input,
                };
            } : false,
            sortField: [{ field: '$order' }, { field: '$score' }],
            maxItems: multiple ? null : 1,
            onChange: () => {
                select.tomselect.blur();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initTomSelects();
});
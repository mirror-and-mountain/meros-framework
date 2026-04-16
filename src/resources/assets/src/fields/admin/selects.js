import TomSelect from 'tom-select';
import './select.scss';

function initTomSelects() {
    const selects = document.querySelectorAll('.meros-select.advanced-select');
    selects.forEach(select => {
        if (select.tomselect) return;
        const multiple = select.hasAttribute('multiple');
        const allowAdd = select.classList.contains('allow-add');

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
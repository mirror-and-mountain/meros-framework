import TomSelect from 'tom-select';
import './style.scss';

function initTomSelect(el) {
    const multiple = el.hasAttribute('multiple');
    const allowAdd = el.hasAttribute('data-allow-add') && el.getAttribute('data-allow-add') === 'true';

    if (el.tomselect) {
        return;
    }

    const make = (select, multiple, allowAdd) => {
        return new TomSelect(select, {
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
    };
    
    const instance = make(el, multiple, allowAdd);
    return instance;
}

export function initTomSelects() {
    const selects = document.querySelectorAll('.meros-select-field[data-advanced="true"]');

    selects.forEach(select => {
        initTomSelect(select);
    });
}

export function updateTomSelectWrapperElements(advancedSelectFields) {
    if (advancedSelectFields) {
        advancedSelectFields.forEach((select) => {
            const el = document.getElementById(select?.id);
            if (!el) return;

            const wrapper = el.closest('.meros-field.nice-form-group');
            if (!wrapper) return;

            const label = wrapper.querySelector('label');
            if (label && select?.label) {
                label.innerText = select.label;
            }

            if (select.name) {
                const name = el.getAttribute('multiple') ? `${select.name}[]` : select.name;
                el.setAttribute('name', name);
            }

            if (select.helpText) {
                const helpTextEl = wrapper.querySelector('small');
                const newPosition = select.helpTextPosition || 'top';
                const positionClass = newPosition === 'top' ? 'field-help-text-top' : 'field-help-text-bottom';

                if (helpTextEl && select.helpText === '') {
                    helpTextEl.remove();
                    return;
                }

                const positionHelpText = (selectEl, position, element) => {
                    position === 'top'
                        ? selectEl.parentNode.insertBefore(element, selectEl)
                        : wrapper.appendChild(element);
                }

                if (helpTextEl) {
                    const currentText = helpTextEl.innerText;
                    const currentPosition = helpTextEl.classList.contains('field-help-text-top') ? 'top' : 'bottom';

                    if (currentPosition !== newPosition || currentText !== select.helpText) {
                        const newEl = document.createElement('small');
                        newEl.classList.add('description', positionClass);
                        newEl.innerText = select.helpText;
                        helpTextEl.replaceWith(newEl);
                        
                        positionHelpText(el, newPosition, newEl);
                        helpTextEl.remove();
                    }
                } else {
                    const newHelpTextEl = document.createElement('small');
                    newHelpTextEl.classList.add('description', positionClass);
                    newHelpTextEl.innerText = select.helpText;
                    positionHelpText(el, newPosition, newHelpTextEl);
                }
            } else {
                wrapper.querySelector('small')?.remove();
            }
        });
    }
}
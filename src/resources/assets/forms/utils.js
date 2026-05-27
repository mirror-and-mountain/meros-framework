export function updateIgnoredFieldWrapperElements(ignoredFields) {
    if (!ignoredFields) return;

    ignoredFields.forEach((field) => {
        const el = document.getElementById(field?.id);
        if (!el) return;

        const wrapper = el.closest('.meros-field.nice-form-group');
        if (!wrapper) return;

        const label = wrapper.querySelector('label');
        if (label && field?.label) {
            label.innerText = field.label;
        }

        if (field.name) {
            const name = el.getAttribute('multiple') ? `${field.name}[]` : field.name;
            el.setAttribute('name', name);
        }

        if (field.helpText) {
            const helpTextEl = wrapper.querySelector('small');
            const newPosition = field.helpTextPosition || 'top';
            const positionClass = newPosition === 'top' ? 'field-help-text-top' : 'field-help-text-bottom';

            if (helpTextEl && field.helpText === '') {
                helpTextEl.remove();
                return;
            }

            const positionHelpText = (fieldEl, position, element) => {
                position === 'top'
                    ? fieldEl.parentNode.insertBefore(element, label ? label.nextSibling : fieldEl)
                    : wrapper.appendChild(element);
            }

            if (helpTextEl) {
                const currentText = helpTextEl.innerText;
                const currentPosition = helpTextEl.classList.contains('field-help-text-top') ? 'top' : 'bottom';

                if (currentPosition !== newPosition || currentText !== field.helpText) {
                    const newEl = document.createElement('small');
                    newEl.classList.add('description', positionClass);
                    newEl.innerText = field.helpText;
                    helpTextEl.replaceWith(newEl);
                    
                    positionHelpText(el, newPosition, newEl);
                    helpTextEl.remove();
                }
            } else {
                const newHelpTextEl = document.createElement('small');
                newHelpTextEl.classList.add('description', positionClass);
                newHelpTextEl.innerText = field.helpText;
                positionHelpText(el, newPosition, newHelpTextEl);
            }
        } else {
            wrapper.querySelector('small')?.remove();
        }
    });
}
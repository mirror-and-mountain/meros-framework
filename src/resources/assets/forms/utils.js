export function updateIgnoredFieldWrapperElements(ignoredFields) {
    if (!ignoredFields) return;

    ignoredFields.forEach((field) => {
        const el = document.getElementById(field?.id);
        if (!el) return;

        const wrapper = el.closest('.meros-field.nice-form-group');
        if (!wrapper) return;

        // Update Label
        const label = wrapper.querySelector('.form-label');
        if (label && field?.label) {
            label.innerText = field.label;
        }

        // Update name attribute
        if (field.name) {
            const name = el.getAttribute('multiple') ? `${field.name}[]` : field.name;
            el.setAttribute('name', name);
        }

        // Update required state
        if (field.required) {
            const requiredIndicator = label?.querySelector('.required-indicator') || document.createElement('span');
            requiredIndicator.classList.add('required-indicator');
            requiredIndicator.innerText = '*';

            if (!label.querySelector('.required-indicator')) {
                label.appendChild(requiredIndicator);
            }
        } else {
            const requiredIndicator = label?.querySelector('.required-indicator');
            if (requiredIndicator) {
                requiredIndicator.remove();
            }
        }

        // Update disabled state
        if (field.disabled) {
            el.setAttribute('disabled', 'disabled');
            el.setAttribute('aria-disabled', 'true');

            if (el.dataset.fieldType === 'rich_text') {
                const quillContainer = el.querySelector('.ql-editor');
                if (quillContainer) {
                    quillContainer.setAttribute('contenteditable', 'false');
                }
            }

            if (el.tomselect) {
                el.tomselect.disable();
            }

        } else {
            el.removeAttribute('disabled');
            el.removeAttribute('aria-disabled');

            if (el.dataset.fieldType === 'rich_text') {
                const quillContainer = el.querySelector('.ql-editor');
                if (quillContainer) {
                    quillContainer.setAttribute('contenteditable', 'true');
                }
            }

            if (el.tomselect) {
                el.tomselect.enable();
            }
        }

        // Update help text and position
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
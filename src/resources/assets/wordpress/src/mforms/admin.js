
document.addEventListener('DOMContentLoaded', function () {
    const formBuilder = document.getElementById('meros-form-builder');
    if (formBuilder) {
        const addComponentButton = formBuilder.querySelector('#meros-form-add-component');

        const wrapComponent = (component) => {
            if (!component || component.closest('.meros-form-component-wrapper')) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'meros-form-component-wrapper';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button button-link-delete meros-form-component-remove';
            removeButton.textContent = 'Remove component';
            removeButton.addEventListener('click', () => wrapper.remove());

            component.parentNode.insertBefore(wrapper, component);
            wrapper.append(removeButton, component);
        };

        const getNextComponentIndex = () => {
            const components = Array.from(formBuilder.querySelectorAll('[data-meros-form-component]'));

            return components.reduce((nextIndex, component) => {
                const index = Number.parseInt(component.dataset.merosFormComponentIndex, 10);
                return Number.isInteger(index) ? Math.max(nextIndex, index + 1) : nextIndex;
            }, components.length);
        };

        formBuilder.querySelectorAll('[data-meros-form-component]').forEach(wrapComponent);

        if (addComponentButton) {
            addComponentButton.addEventListener('click', function (e) {
                e.preventDefault();

                const action = addComponentButton.getAttribute('data-action');
                const nonce  = addComponentButton.getAttribute('data-nonce');
                const componentIndex = getNextComponentIndex();

                if (!action || !nonce) {
                    console.error('Missing action or nonce for adding component.');
                    return;
                }

                const formData = new FormData();
                formData.append('action', action);
                formData.append('nonce', nonce);
                formData.append('component_index', componentIndex);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data && data.data.html) {
                            addComponentButton.insertAdjacentHTML('beforebegin', data.data.html);
                            const components = formBuilder.querySelectorAll('[data-meros-form-component]');
                            wrapComponent(components[components.length - 1]);
                            addComponentButton.style.marginTop = '1rem';
                        }

                        else if (data.data && data.data.message) {
                            console.error('Error:', data.data.message);
                        }

                        else {
                            console.error('Error fetching component.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
        }
    }
});
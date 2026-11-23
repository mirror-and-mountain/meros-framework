/**
 * Create a field via AJAX.
 *
 * @param {string} ajaxURL - The AJAX URL.
 * @param {string} nonce - The nonce for security.
 * @param {string} type - The type of field to create.
 * @param {object} config - The configuration for the field.
 * @returns {Promise<string|null>} - The HTML of the created field or null on failure.
 */
export default function __mforms_create_field(ajaxURL, nonce, type, config) {
    if (typeof ajaxURL !== 'string' || ajaxURL === '') {
        return;
    }

    if (typeof nonce !== 'string' || nonce === '') {
        return;
    }

    if (typeof type !== 'string' || type === '') {
        return;
    }

    if (typeof config !== 'object' || config === null) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'meros_create_field')
    formData.append('nonce', nonce);
    formData.append('type', type);
    formData.append('config', JSON.stringify(config));

    return fetch(ajaxURL, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.html) {
                return data.data.html;
            }

            return null;
        })
        .catch(error => {
            console.error('Error:', error);
            return null;
        });
}
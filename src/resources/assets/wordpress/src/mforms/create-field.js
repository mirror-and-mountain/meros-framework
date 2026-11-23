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
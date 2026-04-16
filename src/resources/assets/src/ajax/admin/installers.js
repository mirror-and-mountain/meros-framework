
/**
 * Handles AJAX calls for installer action buttons in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export default function handleInstallers(e) {
    const button = e.target.closest(
        "[data-action='meros_install_feature'], [data-action='meros_update_feature'], [data-action='meros_uninstall_feature']"
    );

    if (!button) return;

    button.classList.add('is-busy');

    const data = new FormData();
    data.append('action', button.dataset.action);
    data.append('installable', button.dataset.installable);
    data.append('nonce', button.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.data?.message || 'Something went wrong');
            button.classList.remove('is-busy');
            return;
        }
        
        button.classList.remove('is-busy');

        // Reload the page
        window.location.reload();
    });
}
/**
 * Handles AJAX calls for package switches in the admin interface.
 * 
 * @param {Event} e
 * @return {void}
 */
export default function handlePackageSwitches(e) {
    const toggle = e.target.closest("[data-action='meros_toggle_package']");
    if (!toggle) return;

    toggle.disabled = true;

    const data = new FormData();
    data.append('action', toggle.dataset.action);
    data.append('package', toggle.dataset.package);
    data.append('nonce', toggle.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.data?.message || 'Something went wrong');
            return;
        }

        const enabled = !!res.data.value;

        toggle.classList.toggle('checked', enabled);
        toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        toggle.querySelector('.meros-toggle-label').innerText =
            enabled ? 'Enabled' : 'Disabled';

        // rotate nonce
        if (res.data.nonce) {
            toggle.dataset.nonce = res.data.nonce;
        }
    })
    .finally(() => toggle.disabled = false);
}
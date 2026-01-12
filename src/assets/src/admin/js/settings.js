/* Handle AJAX calls for toggle buttons */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.meros-toggle-btn');
    if (!btn) return;

    e.preventDefault();
    btn.disabled = true;

    const data = new FormData();
    data.append('action', 'meros_toggle_feature');
    data.append('option', btn.dataset.option);
    data.append('nonce', btn.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(res => res.json())
    .then(res => {
        if (!res.success) {
            alert(res.data || 'Toggle failed');
            return;
        }

        btn.innerText = res.data.label;
        btn.dataset.nonce = res.data.nonce;
        btn.dataset.value = res.data.value ? '1' : '0';
        btn.title = res.data.title;
    })
    .catch(() => alert('Request failed'))
    .finally(() => btn.disabled = false);
});

/* Handle AJAX calls for toggle switches */
document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.meros-toggle-switch');
    if (!toggle) return;

    toggle.disabled = true;

    const data = new FormData();
    data.append('action', 'meros_toggle_feature');
    data.append('option', toggle.dataset.option);
    data.append('nonce', toggle.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.data || 'Toggle failed');
            return;
        }

        const enabled = !!res.data.value;

        toggle.classList.toggle('is-enabled', enabled);
        toggle.classList.toggle('is-disabled', !enabled);
        toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        toggle.querySelector('.meros-toggle-label').innerText =
            enabled ? 'Enabled' : 'Disabled';

        // rotate nonce (recommended)
        if (res.data.nonce) {
            toggle.dataset.nonce = res.data.nonce;
        }
    })
    .finally(() => toggle.disabled = false);
});
export function __meros_mail_reset_log(event) {
    event.preventDefault();

    const button = event.target;
    if (!button || !button.dataset.nonce) return;

    const action = 'meros-reset-mail-logs';
    const nonce  = button.dataset.nonce;

    const data = new FormData();
    data.append('action', action);
    data.append('nonce', nonce);

    fetch(ajaxurl, {
        method: 'POST',
        body: data,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else if (data.data && data.data.message) {
            const message = data.data.message;
            console.error('Could not reset mail log: ', message);
        } else {
            console.error('Could not reset mail log.');
        }
    })
    .catch((error) => {
        console.error('Could not reset mail log: ', error);
    });
}
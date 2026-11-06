export function __meros_send_test_email(event) {
    const { formData, formId, formName } = event.detail;

    const form = document.getElementById(formId);

    if (!form) {
        console.error('Test email form submission failed: Form not found.');
        return;
    }

    const nonce = form.getAttribute('data-meros-send-test-email-nonce');
    if (!nonce) {
        console.error('Test email form submission failed: Missing nonce.');
        return;
    }

    if (!formData || !formData.test_email) {
        console.error('Test email form submission failed: Missing test_email field.');
        return;
    }

    // Send the test email using the provided email address
    const testEmail = formData.test_email;
    const PostData = new FormData();
    PostData.append('action', 'meros_send_test_email');
    PostData.append('nonce', nonce);
    PostData.append('test_email', testEmail);

    fetch(ajaxurl, {
        method: 'POST',
        body: PostData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.message) {
                console.log('Test email sent successfully.');
            } else {
                console.error('Failed to send test email:', data.data.message);
            }
        })
        .catch(error => {
            console.error('Error sending test email:', error);
        });
}
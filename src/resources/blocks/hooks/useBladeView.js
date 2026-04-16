/**
 * Fetches a a blade.php view from the server and returns its rendered HTML.
 *
 * @param {string} view The name of the Blade view to fetch.
 * @param {string} [serialisedData='{}'] Optional JSON-encoded data payload to pass to the view.
 * @returns {Promise<string>} A promise that resolves with the rendered HTML of the Blade view.
 */
function fetchBladeView(view, serialisedData = '{}') {
    const root      = window?.wpApiSettings?.root || '/wp-json/';
    const nonce     = window?.wpApiSettings?.nonce;
    const endpoint  = `${root.replace(/\/$/, '')}/meros/v1/get-blade-view`;
    let payloadData = {};

    if (serialisedData) {
        try {
            payloadData = JSON.parse(serialisedData);
        } catch (error) {
            payloadData = {};
        }
    }

    const payload = {
        view,
        data: payloadData,
    };

    return fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    }).then(async response => {
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Failed to fetch Blade view (${response.status}): ${errorText}`);
        }

        return response.text();
    });
}

/**
 * Fetches and stores rendered Blade view HTML for editor previews.
 *
 * @param {string} view The name of the Blade view to fetch.
 * @param {Object} [data={}] Optional data to pass to the view.
 * @returns {string} The rendered HTML content.
 */
export function useBladeView(view, data = {}) {
    const serialisedData = JSON.stringify(data ?? {});
    const [bladeViewContent, setBladeViewContent] = wp.element.useState('');

    wp.element.useEffect(() => {
        fetchBladeView(view, serialisedData)
            .then(content => {
                setBladeViewContent(content);
            })
            .catch((error) => {
                // Keep editor usable while still exposing backend error details in dev tools.
                console.error(error);
                setBladeViewContent('');
            });
    }, [view, serialisedData]);

    return bladeViewContent;
}
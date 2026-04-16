/**
 * Fetches a a blade.php view from the server and returns its rendered HTML.
 *
 * @param {string} view The name of the Blade view to fetch.
 * @param {string} [serialisedData=''] Optional JSON-encoded data to pass to the view.
 * @returns {Promise<string>} A promise that resolves with the rendered HTML of the Blade view.
 */
function fetchBladeView(view, serialisedData = '') {
    const root = window?.wpApiSettings?.root || '/wp-json/';
    const nonce = window?.wpApiSettings?.nonce;
    const params = new URLSearchParams({ view });

    if (serialisedData) {
        params.set('data', serialisedData);
    }

    const endpoint = `${root.replace(/\/$/, '')}/meros/v1/get-blade-view?${params.toString()}`;

    return fetch(endpoint, {
        method: 'GET',
        headers: nonce ? { 'X-WP-Nonce': nonce } : {},
        credentials: 'same-origin',
    }).then(response => {
        if (!response.ok) {
            throw new Error(`Failed to fetch Blade view (${response.status})`);
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
            .catch(() => {
                setBladeViewContent('');
            });
    }, [view, serialisedData]);

    return bladeViewContent;
}
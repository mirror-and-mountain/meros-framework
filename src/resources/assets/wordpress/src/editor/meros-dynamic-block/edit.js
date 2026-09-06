import { useBlockProps } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';

export default function Edit({ attributes }) {
    const ajaxUrl = window.meros_script_data?.ajax_url;
    const [html, setHtml] = useState('');

    useEffect(() => {
        if (!ajaxUrl) {
            return;
        }

        let cancelled = false;

        const params = new URLSearchParams({
            action: 'meros_dynamic_block_meros-blocks/test-block',
            attributes: JSON.stringify(attributes),
        });

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
        .then((response) => response.json())
        .then((data) => {
            if (!cancelled && data.success && data.data?.html) {
                setHtml(data.data.html);
            } else if (data.success === false && data.data?.message) {
                console.error("An error occurred when retrieving the block's content: " + data.data.message);
            }
        })
        .catch((error) => {
            if (!cancelled) {
                console.error(error);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [ajaxUrl, attributes]);

    return (
        <div {...useBlockProps()} dangerouslySetInnerHTML={{ __html: html }} />
    );
}
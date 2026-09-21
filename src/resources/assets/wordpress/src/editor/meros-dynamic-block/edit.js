import { useBlockProps } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';

export default function Edit({ name, attributes }) {
    const ajaxUrl    = window.meros_script_data?.ajax_url;
    const ajaxData   = window.meros_script_data?.blocks[name];
    const ajaxAction = ajaxData?.ajaxAction;
    const [html, setHtml] = useState('');

    useEffect(() => {
        if (!ajaxUrl || !ajaxAction) {
            return;
        }

        let cancelled = false;

        const params = new URLSearchParams({
            action: ajaxAction,
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
    }, [ajaxUrl, ajaxAction, attributes]);

    return (
        <div {...useBlockProps()} dangerouslySetInnerHTML={{ __html: html }} />
    );
}
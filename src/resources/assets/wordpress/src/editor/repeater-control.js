import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

function mountFields(host, html) {
    const alpine = window.Alpine;
    if (!alpine) return false;

    alpine.mutateDom(() => {
        host.innerHTML = html;
        alpine.initTree(host);
    });
    return true;
}

function unmountFields(host) {
    window.Alpine?.destroyTree(host);
    host.replaceChildren();
}

function RepeaterRowForm({ row, onClose, action, ajaxUrl, nonce, scope }) {
    const hostRef = useRef(null);
    const heading = useRef(null);
    const formComponent = useRef(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const loadError = __('Could not load the repeater row form.', 'meros-theme');
    const saveError = __('Could not save the repeater row.', 'meros-theme');
    const value = JSON.stringify(row.value);

    useEffect(() => {
        heading.current.focus();
        const host = hostRef.current;
        const controller = new AbortController();
        const formSubmit = (event) => {
            if (event.target !== host.querySelector('form.mforms-form')) return;
            event.stopPropagation();
            try {
                if (!row.save(event.detail.formData)) {
                    setError(saveError);
                    return;
                }
                onClose();
            } catch (error) {
                console.error(error);
                setError(saveError);
            }
        };
        host.addEventListener('mforms::meros_repeater_form_submit', formSubmit);

        async function load() {
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    signal: controller.signal,
                    body: new URLSearchParams({ action, nonce, mode: 'edit_row', row: row.index, value }),
                });
                const result = await response.json();
                if (controller.signal.aborted) return;
                if (!response.ok || !result.success || typeof result.data?.html !== 'string') {
                    throw new Error(result.data?.message || loadError);
                }
                if (!mountFields(host, result.data.html)) throw new Error(loadError);

                const form = host.querySelector('form.mforms-form');
                const component = form && window.Alpine.$data(form);
                if (typeof component?.submitForm !== 'function') throw new Error(loadError);
                formComponent.current = component;
            } catch (error) {
                if (!controller.signal.aborted) setError(error.message || loadError);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }

        load();
        return () => {
            controller.abort();
            host.removeEventListener('mforms::meros_repeater_form_submit', formSubmit);
            formComponent.current = null;
            unmountFields(host);
        };
    }, [row, onClose, action, ajaxUrl, nonce, value, loadError, saveError]);

    function save() {
        setError('');
        try {
            const outcome = formComponent.current.submitForm(true);
            if (outcome?.success === false) setError(outcome.error || saveError);
        } catch (error) {
            console.error(error);
            setError(saveError);
        }
    }

    return (
        <>
            <h3 ref={heading} tabIndex={-1}>{row.title}</h3>
            {loading && <Spinner />}
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            <div
                {...scope}
                ref={hostRef}
                className="nice-form"
                data-meros-block-repeater-row={row.index}
                data-meros-block-repeater-value={value}
            />
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '16px' }}>
                <Button variant="tertiary" onClick={onClose}>{__('Cancel row edit', 'meros-theme')}</Button>
                <Button variant="primary" onClick={save} disabled={loading || !formComponent.current}>
                    {__('Save row', 'meros-theme')}
                </Button>
            </div>
        </>
    );
}

function RepeaterModal({ label, value, onChange, onClose, action, ajaxUrl, nonce }) {
    const [initialValue] = useState(() => value ?? []);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [row, setRow] = useState(null);
    const container = useRef(null);
    const repeater = useRef(null);
    const returnFocus = useRef(null);
    const loadError = __('Could not load the repeater.', 'meros-theme');
    const closeRow = useCallback(() => setRow(null), []);
    const scope = {
        'data-meros-block-repeater': action,
        'data-ajax-url': ajaxUrl,
        'data-ajax-nonce': nonce,
    };

    useEffect(() => {
        if (!row && returnFocus.current) {
            returnFocus.current.focus();
            returnFocus.current = null;
        }
    }, [row]);

    useEffect(() => {
        if (!ajaxUrl || !action || !nonce) {
            setError(loadError);
            setLoading(false);
            return;
        }

        const controller = new AbortController();
        const host = container.current;
        const editRow = ({ detail }) => {
            returnFocus.current = detail.trigger;
            setRow(detail);
        };
        host.addEventListener('meros:block-repeater-edit-row', editRow);

        async function load() {
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    signal: controller.signal,
                    body: new URLSearchParams({ action, nonce, value: JSON.stringify(initialValue) }),
                });
                const result = await response.json();
                if (controller.signal.aborted) return;
                if (!response.ok || !result.success || typeof result.data?.html !== 'string') {
                    throw new Error(result.data?.message || loadError);
                }

                if (!mountFields(host, result.data.html)) throw new Error(loadError);
                const field = host.querySelector('.meros-repeater-field');
                const component = field && window.Alpine.$data(field);
                if (typeof component?.getValue !== 'function') throw new Error(loadError);
                repeater.current = component;
            } catch (requestError) {
                if (!controller.signal.aborted) setError(requestError.message || loadError);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }

        load();

        return () => {
            controller.abort();
            host.removeEventListener('meros:block-repeater-edit-row', editRow);
            repeater.current = null;
            unmountFields(host);
        };
    }, [action, ajaxUrl, nonce, initialValue, loadError]);

    function apply() {
        try {
            const rows = repeater.current.getValue();
            onChange(rows);
            onClose();
        } catch (saveError) {
            console.error(saveError);
            setError(__('Could not read the repeater rows. Close the dialog and try again.', 'meros-theme'));
        }
    }

    return (
        <Modal title={label} onRequestClose={row ? closeRow : onClose} size="large">
            {loading && <Spinner />}
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            <div
                {...scope}
                ref={container}
                className="nice-form"
                hidden={Boolean(row)}
            />
            {row ? (
                <RepeaterRowForm {...{ row, action, ajaxUrl, nonce, scope }} onClose={closeRow} />
            ) : (
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '16px' }}>
                    <Button variant="tertiary" onClick={onClose}>{__('Cancel', 'meros-theme')}</Button>
                    <Button variant="primary" onClick={apply} disabled={loading || Boolean(error)}>
                        {__('Apply', 'meros-theme')}
                    </Button>
                </div>
            )}
        </Modal>
    );
}

export default function RepeaterControl(props) {
    const [isOpen, setOpen] = useState(false);

    return (
        <div style={{ marginBottom: '16px' }}>
            <Button variant="secondary" onClick={() => setOpen(true)}>
                {sprintf(__('Edit %s', 'meros-theme'), props.label)}
            </Button>
            {isOpen && <RepeaterModal {...props} onClose={() => setOpen(false)} />}
        </div>
    );
}

/**
 * Align form rows by inserting spacers to ensure consistent vertical alignment.
 */
function controlAnchor(field) {
    if (field.matches('.meros-choice-field')) {
        return field.querySelector(':scope > .nice-form-group');
    }
    if (field.matches('.mforms-repeater-field')) {
        return field.querySelector(':scope > .meros-repeater-scroll');
    }
    return field.querySelector(':scope > .ql-toolbar, :scope > .ts-wrapper') ||
        field.querySelector(':scope > input:not([type="hidden"]), :scope > select, :scope > textarea');
}

/**
 * Align all form rows within the given form element.
 */
export function alignFormRows(form) {
    const spacers = new Map();
    const observed = new Set();
    let frame = null;
    const resizeObserver = typeof window.ResizeObserver === 'undefined' ? null : new window.ResizeObserver(schedule);

    function schedule() {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(update);
    }

    function update() {
        frame = null;
        const fields = new Set();
        form.querySelectorAll('.mforms-row').forEach(row => {
            if (row.closest('.mforms-form') !== form) return;
            const entries = [];
            for (const field of row.children) {
                if (!field.matches('.mforms-field-wrapper')) continue;
                const anchor = controlAnchor(field);
                if (!anchor) continue;
                fields.add(field);
                if (resizeObserver && !observed.has(field)) {
                    resizeObserver.observe(field);
                    observed.add(field);
                }
                let spacer = spacers.get(field);
                if (!spacer) {
                    spacer = document.createElement('span');
                    spacer.className = 'mforms-row-alignment-spacer';
                    spacer.setAttribute('aria-hidden', 'true');
                    spacers.set(field, spacer);
                }
                if (spacer.nextElementSibling !== anchor || spacer.parentElement !== field) {
                    field.insertBefore(spacer, anchor);
                }
                if (!field.getClientRects().length || !anchor.getClientRects().length) {
                    spacer.style.height = '0px';
                    continue;
                }
                const top = field.getBoundingClientRect().top;
                // Subtract our previous adjustment so repeated resize callbacks converge.
                entries.push({ field, spacer, top, start: anchor.getBoundingClientRect().top - spacer.getBoundingClientRect().height });
            }
            for (const entry of entries) {
                const peers = entries.filter(other => Math.abs(other.top - entry.top) < 1);
                const height = peers.length > 1 ? Math.max(...peers.map(other => other.start)) - entry.start : 0;
                const value = `${Math.max(0, height)}px`;
                if (entry.spacer.style.height !== value) entry.spacer.style.height = value;
            }
        });
        for (const [field, spacer] of spacers) {
            if (fields.has(field)) continue;
            spacer.remove();
            spacers.delete(field);
            resizeObserver?.unobserve(field);
            observed.delete(field);
        }
    }

    const mutations = new MutationObserver(schedule);
    mutations.observe(form, { childList: true, subtree: true });
    window.addEventListener('resize', schedule);
    schedule();
    return () => {
        mutations.disconnect();
        resizeObserver?.disconnect();
        window.removeEventListener('resize', schedule);
        if (frame !== null) window.cancelAnimationFrame(frame);
        for (const spacer of spacers.values()) spacer.remove();
    };
}

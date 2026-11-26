const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function createForm() {
    const factories = {};
    const listeners = {};
    const context = {
        document: {
            addEventListener(name, callback) { listeners[name] = callback; },
        },
        Alpine: {
            directive() {},
            data(name, factory) { factories[name] = factory; },
        },
        window: {},
        CustomEvent: class {
            constructor(type, options) {
                this.type = type;
                Object.assign(this, options);
            }
        },
        FormData: class {
            values = new Map();
            append(name, value) { this.values.set(name, value); }
            get(name) { return this.values.get(name); }
        },
    };
    const modalBundle = path.join(__dirname, '../../src/resources/assets/wordpress/build/modal/index.js');
    vm.runInNewContext(readFileSync(modalBundle, 'utf8'), context);
    const bundle = path.join(__dirname, '../../src/resources/assets/wordpress/build/mforms/index.js');
    vm.runInNewContext(readFileSync(bundle, 'utf8'), context);
    listeners['alpine:init']();

    const form = factories.mform();
    const events = [];
    const resets = [];
    Object.assign(form, {
        id: 'login',
        name: 'login',
        onSubmit: 'login',
        $el: { tagName: 'FORM' },
        $dispatch(name, detail) { events.push({ name, detail }); },
        $nextTick: async callback => callback(),
        findInvalidControl: () => null,
        processFormData: () => ({ username: 'example-user' }),
        resetForm: focus => resets.push(focus),
    });
    return { form, events, resets, context, factories };
}

test('callback receives the form instance without automatic feedback or reset', () => {
    const { form, events, resets } = createForm();
    form.submitForm();

    assert.equal(events.length, 1);
    assert.equal(events[0].name, 'mforms::login');
    assert.equal(events[0].detail.form, form);
    assert.equal(events[0].detail.formId, 'login');
    assert.equal(events[0].detail.formName, 'login');
    assert.deepEqual(events[0].detail.formData, { username: 'example-user' });
    assert.equal(form.feedbackMessage, '');
    assert.equal(form.feedbackType, '');
    assert.deepEqual(resets, []);
});

test('synchronous callback errors are not overwritten and retain input values', () => {
    const { form, resets } = createForm();
    form.$dispatch = (name, { form: instance }) => instance.showError('Invalid credentials.');
    form.submitForm();

    assert.equal(form.feedbackMessage, 'Invalid credentials.');
    assert.equal(form.feedbackType, 'error');
    assert.deepEqual(resets, []);
});

test('callback can explicitly complete later, including confirmation-page behavior', async () => {
    const { form, events, resets } = createForm();
    form.showSuccessOnNewPage = true;
    form.$el.querySelector = () => null;
    const outcome = form.submitForm(true);
    assert.equal(outcome.success, true);
    assert.deepEqual(resets, []);

    await Promise.resolve();
    events[0].detail.form.showSuccess('Logged in.');
    assert.equal(form.feedbackMessage, 'Logged in.');
    assert.equal(form.feedbackType, 'success');
    assert.equal(form.showingConfirmation, true);
    assert.deepEqual(resets, [false]);
});

test('default AJAX forms still complete automatically', async () => {
    const { form, events, resets, context } = createForm();
    form.onSubmit = null;
    form.ajaxUrl = '/submit';
    form.ajaxNonce = 'test';
    form.ajaxAction = 'submit';
    context.fetch = async () => ({
        ok: true,
        json: async () => ({ success: true, data: { message: 'Saved.' } }),
    });
    await form.submitForm();

    assert.equal(form.feedbackMessage, 'Saved.');
    assert.equal(form.feedbackType, 'success');
    assert.equal(form.isSubmitting, false);
    assert.deepEqual(resets, [true]);
    assert.deepEqual(events, []);
});

function createRepeater(loader = 'customLoader') {
    const { factories, context } = createForm();
    const repeater = factories.mformsRepeater();
    Object.assign(repeater, {
        name: 'items',
        originalName: 'original_items',
        formId: 'custom-form',
        container: {
            dataset: {},
            closest: () => loader ? { dataset: { repeaterEditFormLoader: loader } } : null,
            dispatchEvent() {},
        },
    });
    return { repeater, context };
}

test('custom repeater loaders return promised HTML without WordPress AJAX', async () => {
    const { repeater, context } = createRepeater();
    context.fetch = () => { throw new Error('WordPress AJAX must not be used'); };
    const rowData = { title: 'First' };
    const currentValue = [rowData];
    repeater.container.dispatchEvent = event => {
        assert.equal(event.type, 'mforms::customLoader');
        assert.equal(event.detail.formId, 'custom-form');
        assert.equal(event.detail.name, 'items');
        assert.equal(event.detail.originalName, 'original_items');
        assert.equal(event.detail.rowData, rowData);
        assert.equal(event.detail.currentValue, currentValue);
        event.detail.respondWith(Promise.resolve('<form>Custom edit form</form>'));
    };
    assert.equal(await repeater.requestEditForm(rowData, currentValue), '<form>Custom edit form</form>');
});

test('missing repeater loader listeners reject instead of hanging', async () => {
    const { repeater } = createRepeater();
    await assert.rejects(repeater.requestEditForm({}, []), /No repeater edit-form loader responded/);
});

test('invalid and rejected custom repeater responses fail explicitly', async () => {
    const { repeater } = createRepeater();
    repeater.container.dispatchEvent = event => event.detail.respondWith({ success: true });
    await assert.rejects(repeater.requestEditForm({}, []), /did not return HTML/);
    repeater.container.dispatchEvent = event => event.detail.respondWith(Promise.reject(new Error('Request failed')));
    await assert.rejects(repeater.requestEditForm({}, []), /Request failed/);
});

test('repeaters without custom loaders retain the WordPress request payload', async () => {
    const { repeater, context } = createRepeater(null);
    repeater.ajaxurl = '/wp-admin/admin-ajax.php';
    repeater.ajaxAction = 'meros_open_repeater_edit_form';
    repeater.ajaxNonce = 'test-nonce';
    repeater.container.dataset.formId = '42';
    context.fetch = async (url, { body }) => {
        assert.equal(url, '/wp-admin/admin-ajax.php');
        assert.equal(body.get('action'), 'meros_open_repeater_edit_form');
        assert.equal(body.get('nonce'), 'test-nonce');
        assert.equal(body.get('form_id'), '42');
        assert.equal(body.get('repeater_name'), 'items');
        assert.equal(body.get('repeater_original_name'), 'original_items');
        assert.equal(body.get('row_data'), '{"title":"First"}');
        assert.equal(body.get('current_value'), '[{"title":"First"}]');
        return { ok: true, json: async () => ({ success: true, data: { html: '<form>WordPress edit form</form>' } }) };
    };
    assert.equal(await repeater.requestEditForm({ title: 'First' }, [{ title: 'First' }]), '<form>WordPress edit form</form>');
});

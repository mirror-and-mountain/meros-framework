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
        FormData: class {
            append() {}
        },
    };
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
    return { form, events, resets, context };
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

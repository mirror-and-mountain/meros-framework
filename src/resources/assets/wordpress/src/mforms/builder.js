import { configureConditions } from './builder-conditions.js';
import { configureRepeaterDefaults } from './builder-repeater-default.js';
import { updateTemplateLockControls, watchTemplateLockControls } from './builder-template-locks.js';
const MerosModal = window.MerosModal;

export const mformsBuilder = () => ({
    isFullscreen: false,
    draggedComponentId: null,
    draggedComponentType: null,
    fieldInstanceSequence: 0,
    groupInstanceSequence: 0,
    configureConditions,
    configureRepeaterDefaults,

    initialize(root) {
        if (!root.dataset.rowsNamePrefix) {
            root.dataset.rowsNamePrefix = 'form_components';
        }

        this.syncFieldPositions(root);
        this.syncFieldDropZones(root);
        this.syncRowDropZones(root);
        this.mountFullscreenToggle(root);
        this.updateComponentLabels(root);
        if (!root.parentElement?.closest('.meros-form-builder')) this.announce(root, '');
        if (root.hasAttribute('data-template-settings')) {
            root._stopWatchingTemplateLocks?.();
            root._stopWatchingTemplateLocks = watchTemplateLockControls(root);
        }
    },

    destroy() {
        this.$el._stopWatchingTemplateLocks?.();
    },

    mountFullscreenToggle(root) {
        const postbox = root.closest('.postbox');
        const actions = postbox?.querySelector('.postbox-header .handle-actions');

        if (postbox) {
            const open = () => postbox.classList.contains('closed') && postbox.classList.remove('closed');

            open();
            new MutationObserver(open)
                .observe(postbox, { attributes: true, attributeFilter: ['class'] });
        }

        if (!actions || actions.querySelector('.meros-fullscreen-toggle')) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'handlediv meros-fullscreen-toggle';
        button.setAttribute('aria-pressed', 'false');
        const icon = document.createElement('span');
        icon.className = 'dashicons dashicons-editor-expand';
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon);
        button.title = 'Toggle full screen';
        button.setAttribute('aria-label', 'Toggle form builder full screen');
        button.addEventListener('click', () => this.toggleFullscreen(root));
        actions.prepend(button);
        root._merosFullscreenButton = button;
    },

    toggleFullscreen(root) {
        this.isFullscreen = !this.isFullscreen;
        document.body.classList.toggle('meros-builder-fullscreen', this.isFullscreen);

        const button = root._merosFullscreenButton;

        if (button) {
            button.setAttribute('aria-pressed', this.isFullscreen ? 'true' : 'false');
            button.firstElementChild.className = 'dashicons dashicons-editor-' + (this.isFullscreen ? 'contract' : 'expand');
        }
    },

    getRows(root) {
        const body = root.querySelector(':scope > .mforms-body');

        return Array.from(body.children)
            .filter(element => element.classList.contains('mforms-row'));
    },

    getFields(row) {
        return Array.from(row.children)
            .filter(element => element.hasAttribute('data-form-field-component'));
    },

    componentName(component) {
        const group = component.matches('[data-form-group-component]')
            ? component : component.querySelector(':scope > [data-form-group-component]');
        if (group) return group.querySelector('.mforms-group-builder-header input:not([type="hidden"])')?.value || 'Group';
        for (const suffix of ['field_label', 'field_name']) {
            const input = Array.from(component.querySelectorAll(`[name$="[${suffix}]"]`))
                .find(input => input.value && !input.closest('.meros-repeater-table-row--template'));
            if (input) return input.value;
        }
        return 'Field';
    },

    updateComponentLabels(root) {
        this.getRows(root).forEach(row => {
            const group = row.querySelector(':scope > [data-form-group-component]');
            (group ? [group] : this.getFields(row)).forEach(component => {
                const name = this.componentName(component);
                const move = component.querySelector(':scope > .mforms-field-drag-handle, :scope > .mforms-group-builder-header > .mforms-field-drag-handle');
                const remove = component.querySelector(':scope > .mforms-builder-remove, :scope > .mforms-group-builder-header > .mforms-builder-remove');
                move?.setAttribute('aria-label', `Move ${name}`);
                remove?.setAttribute('aria-label', `Remove ${name}`);
            });
        });
    },

    announce(root, message) {
        while (root.parentElement?.closest('.meros-form-builder')) {
            root = root.parentElement.closest('.meros-form-builder');
        }
        let status = root.querySelector(':scope > [data-builder-status]');
        if (!status) {
            status = document.createElement('div');
            status.dataset.builderStatus = '';
            status.className = 'screen-reader-text';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-atomic', 'true');
            root.append(status);
        }
        status.textContent = message;
    },

    focusComponent(component) {
        const control = Array.from(component.querySelectorAll('input:not([type="hidden"]), select, textarea, button, [contenteditable="true"]'))
            .find(element => !element.matches(':disabled') &&
                !element.closest('[inert], [hidden], .meros-repeater-table-row--template') &&
                element.getClientRects().length > 0);
        control?.focus();
    },

    configureMove(component) {
        const group = component.matches('[data-form-group-component]');
        if (component.dataset.moveLocked === '1' || (group && component.querySelector('[data-move-locked="1"]'))) return;
        const root = component.closest('.meros-form-builder');
        const source = group ? component.closest('.mforms-row') : component;
        const setSource = () => {
            this.draggedComponentId = source.id;
            this.draggedComponentType = group ? 'group' : 'field';
        };
        setSource();
        const destinations = Array.from(root.querySelectorAll('.mforms-row-drop-zone, .mforms-field-drop-zone'))
            .filter(zone => zone.closest('.meros-form-builder') === root)
            .filter(zone => zone.hasAttribute('data-field-drop-zone') ? this.canDropOnField(zone) : this.canDropOnRow(zone));
        this.endDrag(root);
        const label = document.createElement('label');
        label.className = 'mforms-builder-move-control';
        const title = document.createElement('span');
        title.textContent = 'New position';
        const select = document.createElement('select');
        destinations.forEach((zone, index) => {
            const row = this.getRow(root, zone.dataset.targetRow);
            const rowName = row.dataset.rowType === 'section'
                ? this.componentName(row)
                : this.getFields(row).map(field => this.componentName(field)).join(', ') || 'Empty row';
            const option = document.createElement('option');
            option.value = index;
            option.textContent = zone.hasAttribute('data-field-drop-zone')
                ? `In row "${rowName}", position ${Number(zone.dataset.fieldPosition) + 1}`
                : `${zone.dataset.rowEdge === 'above' ? 'Before' : 'After'} "${rowName}"`;
            select.append(option);
        });
        label.append(title, select);
        const modal = new MerosModal(`Move ${this.componentName(component)}`, '', 'Move', 'Cancel', false);
        modal.content.append(label);
        if (!destinations.length) {
            title.textContent = 'There are no available positions for this component.';
            select.hidden = true;
            modal.hideConfirmButton();
        }
        modal.onConfirm(() => {
            setSource();
            const destination = destinations[Number(select.value)];
            if (destination.hasAttribute('data-field-drop-zone')) this.dropOnField(destination);
            else this.dropOnRow(destination);
            modal.hide();
            component.querySelector('.mforms-field-drag-handle')?.focus();
            this.announce(root, `${this.componentName(component)} moved.`);
        });
        modal.show();
        modal.focusContent();
    },

    replaceAttributes(element, replacements) {
        if (element.nodeType === 1) {
            Array.from(element.attributes).forEach(attribute => {
                replacements.forEach(([name, search, replacement, exact]) => {
                    if (attribute.name !== name) {
                        return;
                    }

                    if (exact && attribute.value !== search) {
                        return;
                    }

                    attribute.value = attribute.value.replaceAll(search, replacement);
                });
            });

            if (element.tagName === 'TEMPLATE') {
                Array.from(element.content.children).forEach(child => {
                    this.replaceAttributes(child, replacements);
                });
            }
        }

        Array.from(element.children ?? []).forEach(child => {
            this.replaceAttributes(child, replacements);
        });
    },

    startFieldDrag(event, field) {
        if (field.dataset.moveLocked === '1') {
            event.preventDefault();
            return;
        }
        this.draggedComponentId = field.id;
        this.draggedComponentType = 'field';
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', field.id);
        this.showDropZones(field.closest('.meros-form-builder'));
    },

    startGroupDrag(event, row) {
        const group = row.querySelector(':scope > [data-form-group-component]');
        if (group?.dataset.moveLocked === '1' || group?.querySelector('[data-move-locked="1"]')) {
            event.preventDefault();
            return;
        }
        this.draggedComponentId = row.id;
        this.draggedComponentType = 'group';
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.id);
        this.showDropZones(row.closest('.meros-form-builder'));
    },

    showDropZones(root) {
        root.querySelectorAll('.mforms-row-drop-zone').forEach(zone => {
            if (zone.closest('.meros-form-builder') !== root) {
                return;
            }

            zone.style.height = '.75rem';
            zone.style.opacity = '1';
            zone.style.pointerEvents = 'auto';
            zone.style.margin = '.25rem 0';
            zone.style.borderColor = '#94a3b8';
        });

        root.querySelectorAll('.mforms-field-drop-zone').forEach(zone => {
            if (zone.closest('.meros-form-builder') !== root) {
                return;
            }

            const visible = this.draggedComponentType === 'field';
            zone.style.flex = visible ? '0 0 .75rem' : '0 0 0';
            zone.style.width = visible ? '.75rem' : '0';
            zone.style.minWidth = visible ? '.75rem' : '0';
            zone.style.opacity = visible ? '1' : '0';
            zone.style.pointerEvents = visible ? 'auto' : 'none';
            zone.style.borderColor = visible ? '#94a3b8' : 'transparent';
        });
    },

    getRow(root, rowId) {
        return this.getRows(root).find(row => row.id === rowId);
    },

    getField(root, fieldId) {
        return this.getRows(root)
            .flatMap(row => this.getFields(row))
            .find(field => field.id === fieldId);
    },

    canDropOnField(zone) {
        const root = zone.closest('.meros-form-builder');
        const source = this.getField(root, this.draggedComponentId);
        const target = this.getRow(root, zone.dataset.targetRow);
        const targetFields = target ? this.getFields(target) : [];

        return this.draggedComponentType === 'field'
            && source
            && source.dataset.moveLocked !== '1'
            && target
            && (source.closest('.mforms-row') === target
                || (!source.hasAttribute('data-repeater-component')
                    && !targetFields.some(field => field.hasAttribute('data-repeater-component'))
                    && targetFields.length < 3));
    },

    canDropOnRow(zone) {
        const root = zone.closest('.meros-form-builder');
        const target = this.getRow(root, zone.dataset.targetRow);

        if (!target) {
            return false;
        }

        if (this.draggedComponentType === 'field') {
            const field = this.getField(root, this.draggedComponentId);
            return Boolean(field && field.dataset.moveLocked !== '1');
        }

        const source = this.getRow(root, this.draggedComponentId);

        return this.draggedComponentType === 'group'
            && Boolean(source)
            && source.querySelector(':scope > [data-form-group-component]')?.dataset.moveLocked !== '1'
            && !source.querySelector('[data-move-locked="1"]')
            && source !== target;
    },

    dropOnField(zone) {
        if (!this.canDropOnField(zone)) {
            return;
        }

        const root = zone.closest('.meros-form-builder');
        const source = this.getField(root, this.draggedComponentId);
        const row = this.getRow(root, zone.dataset.targetRow);
        const sourceRow = source.closest('.mforms-row');
        const fields = this.getFields(row);
        let position = Number(zone.dataset.fieldPosition);
        const sourcePosition = sourceRow === row ? fields.indexOf(source) : -1;

        if (sourcePosition >= 0 && sourcePosition < position) {
            position--;
        }

        source.remove();
        const remainingFields = this.getFields(row);

        if (remainingFields[position]) {
            row.insertBefore(source, remainingFields[position]);
        } else {
            row.appendChild(source);
        }

        if (sourceRow !== row && this.getFields(sourceRow).length === 0) {
            sourceRow.remove();
        }

        this.refreshLayout(root);
        this.endDrag(root);
    },

    dropOnRow(zone) {
        if (!this.canDropOnRow(zone)) {
            return;
        }

        const root = zone.closest('.meros-form-builder');
        const body = root.querySelector('.mforms-body');
        const target = this.getRow(root, zone.dataset.targetRow);
        const edge = zone.dataset.rowEdge;

        this.removeRowDropZones(root);

        if (this.draggedComponentType === 'field') {
            const field = this.getField(root, this.draggedComponentId);
            const sourceRow = field.closest('.mforms-row');
            const newRow = document.createElement('div');

            newRow.className = 'mforms-row';
            newRow.dataset.rowType = 'fields';
            newRow.appendChild(field);

            if (edge === 'above') {
                body.insertBefore(newRow, target);
            } else {
                body.insertBefore(newRow, target.nextElementSibling);
            }

            if (this.getFields(sourceRow).length === 0) {
                sourceRow.remove();
            }
        } else {
            const source = this.getRow(root, this.draggedComponentId);

            if (edge === 'above') {
                body.insertBefore(source, target);
            } else {
                body.insertBefore(source, target.nextElementSibling);
            }
        }

        this.refreshLayout(root);
        this.endDrag(root);
    },

    createFieldDropZone(row, position) {
        const zone = document.createElement('div');
        zone.className = 'mforms-field-drop-zone';
        zone.dataset.fieldDropZone = '';
        zone.dataset.targetRow = row.id;
        zone.dataset.fieldPosition = position;
        zone.setAttribute('aria-hidden', 'true');
        zone.style.cssText = 'flex:0 0 0;width:0;min-width:0;opacity:0;pointer-events:none;border:1px dashed transparent;border-radius:9999px;transition:all 150ms ease';
        zone.addEventListener('dragover', event => {
            event.preventDefault();
            if (this.canDropOnField(zone)) {
                zone.style.backgroundColor = '#dbeafe';
                zone.style.borderColor = '#3b82f6';
            }
        });
        zone.addEventListener('dragleave', () => {
            zone.style.backgroundColor = 'transparent';
            zone.style.borderColor = '#94a3b8';
        });
        zone.addEventListener('drop', event => {
            event.preventDefault();
            this.dropOnField(zone);
        });

        return zone;
    },

    createRowDropZone(row, edge) {
        const zone = document.createElement('div');
        zone.className = 'mforms-row-drop-zone';
        zone.dataset.rowEdge = edge;
        zone.dataset.targetRow = row.id;
        zone.setAttribute('aria-hidden', 'true');
        zone.style.cssText = 'height:0;opacity:0;pointer-events:none;margin:0;border:1px dashed transparent;border-radius:9999px;transition:all 150ms ease';
        zone.addEventListener('dragover', event => {
            event.preventDefault();
            if (this.canDropOnRow(zone)) {
                zone.style.backgroundColor = '#dbeafe';
                zone.style.borderColor = '#3b82f6';
            }
        });
        zone.addEventListener('dragleave', () => {
            zone.style.backgroundColor = 'transparent';
            zone.style.borderColor = '#94a3b8';
        });
        zone.addEventListener('drop', event => {
            event.preventDefault();
            this.dropOnRow(zone);
        });

        return zone;
    },

    syncFieldDropZones(root) {
        root.querySelectorAll('.mforms-field-drop-zone').forEach(zone => {
            if (zone.closest('.meros-form-builder') === root) {
                zone.remove();
            }
        });

        this.getRows(root)
            .filter(row => row.dataset.rowType === 'fields')
            .forEach(row => {
            const fields = this.getFields(row);

            for (let position = 0; position <= fields.length; position++) {
                row.insertBefore(
                    this.createFieldDropZone(row, position),
                    fields[position] ?? null
                );
            }
        });
    },

    removeRowDropZones(root) {
        root.querySelectorAll('.mforms-row-drop-zone').forEach(zone => {
            if (zone.closest('.meros-form-builder') === root) {
                zone.remove();
            }
        });
    },

    syncRowDropZones(root) {
        this.removeRowDropZones(root);
        const body = root.querySelector('.mforms-body');
        const rows = this.getRows(root);

        if (rows.length === 0) {
            return;
        }

        body.insertBefore(this.createRowDropZone(rows[0], 'above'), rows[0]);

        rows.forEach(row => {
            body.insertBefore(this.createRowDropZone(row, 'below'), row.nextElementSibling);
        });
    },

    refreshLayout(root) {
        this.syncFieldPositions(root);
        this.syncFieldDropZones(root);
        this.syncRowDropZones(root);
        updateTemplateLockControls(root, root);
        this.updateComponentLabels(root);
    },

    resetToTemplate(root) {
        if (!window.confirm('Reset this form to its template definition? Current builder changes will be lost. Save the post to apply the reset.')) {
            return;
        }

        const body = root.querySelector(':scope > .mforms-body');
        const template = body.querySelector(':scope > template[x-ref="resetTemplate"]');
        if (!template || !window.Alpine || typeof window.Alpine.initTree !== 'function') {
            throw new Error('The form template or Alpine is unavailable to reset the builder.');
        }

        const rows = Array.from(template.content.children, row => row.cloneNode(true));
        this.removeRowDropZones(root);
        this.getRows(root).forEach(row => row.remove());
        rows.forEach(row => {
            body.insertBefore(row, template);
            window.Alpine.initTree(row);
        });
        this.refreshLayout(root);
        this.focusComponent(root);
        this.announce(root, 'Form reset to its template. Save the post to apply the reset.');
    },

    removeField(field) {
        if (field.dataset.removeLocked === '1') return;
        if (!window.confirm('Remove this field? This cannot be undone.')) {
            return;
        }

        const root = field.closest('.meros-form-builder');
        const row = field.closest('.mforms-row');
        const fields = this.getRows(root).flatMap(item => this.getFields(item));
        const next = fields[fields.indexOf(field) + 1] || fields[fields.indexOf(field) - 1] || root;
        const name = this.componentName(field);
        field.remove();

        if (row && this.getFields(row).length === 0) {
            row.remove();
        }

        this.refreshLayout(root);
        this.focusComponent(next);
        this.announce(root, `${name} removed.`);
    },

    removeGroup(group) {
        if (group.dataset.removeLocked === '1' || group.querySelector('[data-remove-locked="1"]')) return;
        if (!window.confirm('Remove this group and all of its fields? This cannot be undone.')) {
            return;
        }

        const row = group.closest('.mforms-row');
        const root = row.closest('.meros-form-builder');
        const rows = this.getRows(root);
        const next = rows[rows.indexOf(row) + 1] || rows[rows.indexOf(row) - 1] || root;
        const name = this.componentName(group);
        row.remove();
        this.refreshLayout(root);
        this.focusComponent(next);
        this.announce(root, `${name} and its fields removed.`);
    },

    addField(root, kind = 'field') {
        const templateName = kind === 'repeater' ? 'newRepeaterTemplate' : 'newFieldTemplate';
        const template = Array.from(root.querySelectorAll(':scope > .mforms-body > template'))
            .find(element => element.getAttribute('x-ref') === templateName);

        if (!template?.content?.firstElementChild) {
            throw new Error('The new field template is unavailable.');
        }

        const rows = this.getRows(root);
        const lastRow = rows[rows.length - 1];
        const emptyLastRow = lastRow
            && lastRow.dataset.rowType === 'fields'
            && this.getFields(lastRow).length === 0
            ? lastRow
            : null;
        const rowIndex = emptyLastRow ? rows.length - 1 : rows.length;
        const row = template.content.firstElementChild.cloneNode(true);
        const field = row.querySelector('[data-form-field-component]');
        const component = row.querySelector('.meros-repeater-field');

        if (!field || !component?.id) {
            throw new Error('The new field template is missing its field component.');
        }

        const sourceComponentId = component.id;
        const instanceId = `${sourceComponentId}-instance-${++this.fieldInstanceSequence}`;
        const groupId = root.closest('[data-form-group-component]')?.id || 'form';

        row.querySelectorAll('*').forEach(element => {
            Array.from(element.attributes).forEach(attribute => {
                attribute.value = attribute.value
                    .replaceAll(sourceComponentId, instanceId)
                    .replaceAll('__ROW_INDEX__', String(rowIndex));
            });
        });

        field.id = `mforms-field-component-${groupId}-${rowIndex}-0`;
        field.dataset.rowIndex = rowIndex;
        field.dataset.fieldPosition = 0;

        let outerBuilder = root;
        while (outerBuilder.parentElement?.closest('.meros-form-builder')) {
            outerBuilder = outerBuilder.parentElement.closest('.meros-form-builder');
        }
        const existingNames = new Set(Array.from(outerBuilder.querySelectorAll('[name$="[field_name]"]'), input => input.value));
        let fieldName;
        do {
            const bytes = new Uint8Array(4);
            window.crypto.getRandomValues(bytes);
            fieldName = `field_${Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')}`;
        } while (existingNames.has(fieldName));

        const fieldNameInput = Array.from(row.querySelectorAll('[name]'))
            .find(input => input.name.endsWith('[field_name]') && !input.name.endsWith('[-1][field_name]'));

        if (!fieldNameInput) {
            throw new Error('The new field template is missing its field name input.');
        }

        fieldNameInput.value = fieldName;

        if (emptyLastRow) {
            emptyLastRow.appendChild(field);
        } else {
            const body = root.querySelector(':scope > .mforms-body');
            const actions = body.querySelector(':scope > .mforms-builder-actions');
            body.insertBefore(row, actions);
        }

        if (!window.Alpine || typeof window.Alpine.initTree !== 'function') {
            if (emptyLastRow) {
                field.remove();
            } else {
                row.remove();
            }

            throw new Error('Alpine is unavailable to initialize the new field component.');
        }

        window.Alpine.initTree(emptyLastRow || row);
        this.refreshLayout(root);
        this.focusComponent(field);
        this.announce(root, `${kind === 'repeater' ? 'Repeater' : 'Field'} added.`);
    },

    addGroup(root) {
        const template = Array.from(root.querySelectorAll(':scope > .mforms-body > template'))
            .find(element => element.getAttribute('x-ref') === 'newGroupTemplate');

        if (!template?.content?.firstElementChild) {
            throw new Error('The new group template is unavailable.');
        }

        const rowIndex = this.getRows(root).length;
        const row = template.content.firstElementChild.cloneNode(true);
        const group = row.querySelector('[data-form-group-component]');
        const sourceGroupId = group?.id;

        if (!group || !sourceGroupId) {
            throw new Error('The new group template is missing its group component.');
        }

        let groupId = `mforms-section-${rowIndex}-${++this.groupInstanceSequence}`;

        while (document.getElementById(groupId)) {
            groupId = `mforms-section-${rowIndex}-${++this.groupInstanceSequence}`;
        }
        this.replaceAttributes(row, [
            ['name', 'form_components[0]', `form_components[${rowIndex}]`],
            ['data-name', 'form_components[0]', `form_components[${rowIndex}]`],
            ['data-rows-name-prefix', 'form_components[0]', `form_components[${rowIndex}]`],
            ['id', sourceGroupId, groupId, true],
            ['id', `${sourceGroupId}-description`, `${groupId}-description`, true],
            ['value', sourceGroupId, groupId, true],
        ]);

        const existingGroupNames = new Set(
            Array.from(root.querySelectorAll('[name]'))
                .filter(input => input.closest('.meros-form-builder') === root)
                .filter(input => /\['childGroup'\]\['name'\]$/.test(input.name))
                .map(input => input.value)
        );
        let nameNumber = 1;
        let groupName = `new_group_${nameNumber}`;

        while (existingGroupNames.has(groupName)) {
            groupName = `new_group_${++nameNumber}`;
        }

        const groupNameInput = Array.from(group.querySelectorAll('[name]'))
            .find(input => /\['childGroup'\]\['name'\]$/.test(input.name));

        if (!groupNameInput) {
            throw new Error('The new group template is missing its group name input.');
        }

        groupNameInput.value = groupName;

        const nestedBuilder = row.querySelector('.meros-group-form-builder');

        if (!nestedBuilder) {
            throw new Error('The new group template is missing its nested form builder.');
        }

        nestedBuilder.dataset.rowsNamePrefix = `form_components[${rowIndex}]['childGroup']['rows']`;
        nestedBuilder.id = `${groupId}-builder`;
        row.dataset.rowIndex = rowIndex;

        const body = root.querySelector(':scope > .mforms-body');
        const actions = body.querySelector(':scope > .mforms-builder-actions');
        body.insertBefore(row, actions);

        if (!window.Alpine || typeof window.Alpine.initTree !== 'function') {
            row.remove();
            throw new Error('Alpine is unavailable to initialize the new group.');
        }

        window.Alpine.initTree(row);
        this.refreshLayout(root);
        this.focusComponent(group);
        this.announce(root, 'Group added.');
    },

    syncFieldPositions(root) {
        const rowsNamePrefix = root.dataset.rowsNamePrefix;
        const escapeRegExp = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        this.getRows(root).forEach((row, rowIndex) => {
            row.id = `${root.id || 'mforms-form-builder'}-row-${rowIndex}`;
            const previousRowIndex = row.dataset.rowIndex;
            const oldRowPattern = new RegExp(`^${escapeRegExp(rowsNamePrefix)}\\[\\d+\\]`);
            const rowPrefix = `${rowsNamePrefix}[${rowIndex}]`;

            if (previousRowIndex !== undefined) {
                this.replaceAttributes(row, [
                    ['name', `${rowsNamePrefix}[${previousRowIndex}]`, rowPrefix],
                    ['data-name', `${rowsNamePrefix}[${previousRowIndex}]`, rowPrefix],
                    ['data-rows-name-prefix', `${rowsNamePrefix}[${previousRowIndex}]`, rowPrefix],
                ]);
            }

            row.dataset.rowIndex = rowIndex;
            row.querySelectorAll('[name], [data-name]').forEach(element => {
                ['name', 'data-name'].forEach(attribute => {
                    const value = element.getAttribute(attribute);
                    if (value) {
                        element.setAttribute(attribute, value.replace(oldRowPattern, rowPrefix));
                    }
                });
            });

            this.getFields(row).forEach((field, fieldIndex) => {
                field.dataset.rowIndex = rowIndex;
                field.dataset.fieldPosition = fieldIndex;
                const fieldPrefix = `${rowPrefix}['fields'][${fieldIndex}]`;
                const oldFieldPattern = new RegExp(`^${escapeRegExp(rowPrefix)}\\['fields'\\]\\[\\d+\\]`);

                field.querySelectorAll('[name], [data-name]').forEach(element => {
                    ['name', 'data-name'].forEach(attribute => {
                        const value = element.getAttribute(attribute);
                        if (value) {
                            element.setAttribute(attribute, value.replace(oldFieldPattern, fieldPrefix));
                        }
                    });
                });
            });

            const nestedBuilder = row.querySelector(':scope > [data-form-group-component] .meros-group-form-builder');

            if (nestedBuilder) {
                nestedBuilder.dataset.rowsNamePrefix = `${rowPrefix}['childGroup']['rows']`;
            }
        });
    },

    endDrag(root) {
        this.draggedComponentId = null;
        this.draggedComponentType = null;
        root?.querySelectorAll('.mforms-field-drop-zone, .mforms-row-drop-zone').forEach(zone => {
            if (zone.closest('.meros-form-builder') !== root) {
                return;
            }

            zone.style.backgroundColor = 'transparent';
            zone.style.borderColor = 'transparent';
            if (zone.classList.contains('mforms-field-drop-zone')) {
                zone.style.flex = '0 0 0';
                zone.style.width = '0';
                zone.style.minWidth = '0';
                zone.style.opacity = '0';
                zone.style.pointerEvents = 'none';
            } else {
                zone.style.height = '0';
                zone.style.opacity = '0';
                zone.style.pointerEvents = 'none';
                zone.style.margin = '0';
            }
        });
    }
});

export default mformsBuilder;

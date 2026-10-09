<div
    class="meros-form-builder mforms-form"
    x-data="{
        draggedComponentId: null,
        draggedComponentType: null,
        fieldInstanceSequence: 0,
        groupInstanceSequence: 0,

        initialize(root) {
            if (!root.dataset.rowsNamePrefix) {
                root.dataset.rowsNamePrefix = 'form_components';
            }

            this.syncFieldPositions(root);
            this.syncFieldDropZones(root);
            this.syncRowDropZones(root);
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
            this.draggedComponentId = field.id;
            this.draggedComponentType = 'field';
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', field.id);
            this.showDropZones(field.closest('.meros-form-builder'));
        },

        startGroupDrag(event, row) {
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

            return this.draggedComponentType === 'field'
                && source
                && target
                && (source.closest('.mforms-row') === target
                    || this.getFields(target).length < 3);
        },

        canDropOnRow(zone) {
            const root = zone.closest('.meros-form-builder');
            const target = this.getRow(root, zone.dataset.targetRow);

            if (!target) {
                return false;
            }

            if (this.draggedComponentType === 'field') {
                return Boolean(this.getField(root, this.draggedComponentId));
            }

            const source = this.getRow(root, this.draggedComponentId);

            return this.draggedComponentType === 'group'
                && Boolean(source)
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
            zone.setAttribute('aria-label', 'Drop a field here to reorder it in this row');
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
            zone.setAttribute('aria-label', `${edge === 'above' ? 'Above' : 'Below'} row drop zone`);
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
        },

        removeField(field) {
            if (!window.confirm('Remove this field? This cannot be undone.')) {
                return;
            }

            const root = field.closest('.meros-form-builder');
            field.remove();
            this.refreshLayout(root);
        },

        removeGroup(group) {
            if (!window.confirm('Remove this group and all of its fields? This cannot be undone.')) {
                return;
            }

            const row = group.closest('.mforms-row');
            const root = row.closest('.meros-form-builder');
            row.remove();
            this.refreshLayout(root);
        },

        addField(root) {
            const template = Array.from(root.querySelectorAll(':scope > .mforms-body > template'))
                .find(element => element.getAttribute('x-ref') === 'newFieldTemplate');

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

            const existingNames = new Set(
                Array.from(root.querySelectorAll('[name]'))
                    .filter(input => input.closest('.meros-form-builder') === root)
                    .filter(input => input.name.endsWith('[field_name]') && !input.name.endsWith('[-1][field_name]'))
                    .map(input => input.value)
            );
            let nameNumber = 1;
            let fieldName = `new_field_${nameNumber}`;

            while (existingNames.has(fieldName)) {
                fieldName = `new_field_${++nameNumber}`;
            }

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
    }"
    x-init="initialize($el)"
>
    <div class="mforms-body">
        @foreach($rows as $index => $row)
            @php
                $childGroup = $row['childGroup'] ?? null;
                $isGroup = is_array($childGroup) && $childGroup !== [];
                $groupTitle = is_array($childGroup) ? ($childGroup['title'] ?? 'Group') : 'Group';
                $groupTitle = is_string($groupTitle) && $groupTitle !== '' ? $groupTitle : 'Group';
                $groupName = is_array($childGroup) ? ($childGroup['name'] ?? 'group_' . $index) : 'group_' . $index;
                $groupDescription = is_array($childGroup) ? ($childGroup['description'] ?? '') : '';
                $groupId = is_array($childGroup) ? ($childGroup['id'] ?? 'mforms-section-' . $index) : 'mforms-section-' . $index;
            @endphp
            <div
                class="mforms-row"
                id="mforms-row-{{ $index }}"
                data-row-index="{{ $index }}"
                data-row-type="{{ $isGroup ? 'section' : 'fields' }}"
            >
                @if ($isGroup)
                    <div class="mforms-group-component mforms-builder-group-card" data-form-group-component id="{{ $groupId }}">
                        <div
                            class="mforms-group-builder-header"
                            aria-label="Group component. Drag to reorder."
                        >
                            <span
                                class="mforms-field-drag-handle"
                                draggable="true"
                                aria-hidden="true"
                                @dragstart.stop="startGroupDrag($event, $el.closest('.mforms-row'))"
                                @dragend="endDrag($el.closest('.meros-form-builder'))"
                            >⠿</span>
                            <button
                                type="button"
                                class="mforms-builder-remove"
                                aria-label="Remove group"
                                @click.stop.prevent="removeGroup($el.closest('[data-form-group-component]'))"
                            >Remove</button>
                            <label>
                                <span>Group title</span>
                                <input
                                    type="text"
                                    name="form_components[{{ $index }}]['childGroup']['title']"
                                    value="{{ $groupTitle }}"
                                >
                            </label>
                            <input
                                type="hidden"
                                name="form_components[{{ $index }}]['childGroup']['name']"
                                value="{{ $groupName }}"
                            >
                            <input
                                type="hidden"
                                name="form_components[{{ $index }}]['childGroup']['id']"
                                value="{{ $groupId }}"
                            >
                            <input
                                type="hidden"
                                name="form_components[{{ $index }}]['childGroup']['description']"
                                value="{{ $groupDescription }}"
                            >
                        </div>

                        <div
                            id="{{ $groupId }}-builder"
                            class="meros-form-builder mforms-form meros-group-form-builder"
                            data-rows-name-prefix="form_components[{{ $index }}]['childGroup']['rows']"
                            x-data="{}"
                            x-init="initialize($el)"
                        >
                            <div class="mforms-body">
                                @foreach($childGroup['rows'] ?? [] as $groupRowIndex => $groupRow)
                                    <div
                                        class="mforms-row"
                                        id="mforms-row-{{ $groupRowIndex }}"
                                        data-row-index="{{ $groupRowIndex }}"
                                        data-row-type="fields"
                                    >
                                        @foreach($groupRow['fields'] ?? [] as $fieldIndex => $field)
                                            @php $groupFieldName = $field['name'] ?? ''; @endphp
                                            @if ($groupFieldName !== '')
                                                <div
                                                    id="mforms-group-field-component-{{ $index }}-{{ $groupRowIndex }}-{{ $fieldIndex }}"
                                                    data-form-field-component
                                                    data-row-index="{{ $groupRowIndex }}"
                                                    data-field-position="{{ $fieldIndex }}"
                                                    @dragend="endDrag($el.closest('.meros-form-builder'))"
                                                    class="mforms-builder-field-card"
                                                >
                                                    <span
                                                        class="mforms-field-drag-handle"
                                                        draggable="true"
                                                        aria-label="Drag to reorder field"
                                                        title="Drag to reorder field"
                                                        @dragstart.stop="startFieldDrag($event, $el.closest('[data-form-field-component]'))"
                                                    >⠿</span>
                                                    <div class="mforms-builder-field-content">
                                                        {!! $this->getGroupFieldComponent($index, $groupRowIndex, $fieldIndex, $field) !!}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="mforms-builder-remove"
                                                        aria-label="Remove field"
                                                        @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                                    >Remove</button>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach

                                @if (empty($childGroup['rows'] ?? []))
                                    <div class="mforms-row" data-row-index="0" data-row-type="fields"></div>
                                @endif

                                <template x-ref="newFieldTemplate">
                                    <div class="mforms-row" data-row-type="fields">
                                        <div
                                            id="mforms-group-field-component-template-0"
                                            data-form-field-component
                                            class="mforms-builder-field-card"
                                        >
                                            <span
                                                class="mforms-field-drag-handle"
                                                draggable="true"
                                                aria-label="Drag to reorder field"
                                                title="Drag to reorder field"
                                                @dragstart.stop="startFieldDrag($event, $el.closest('[data-form-field-component]'))"
                                            >⠿</span>
                                            <div class="mforms-builder-field-content">
                                                {!! $this->getNewFieldComponent("form_components[{$index}]['childGroup']['rows'][0]['fields'][0]") !!}
                                            </div>
                                            <button
                                                type="button"
                                                class="mforms-builder-remove"
                                                aria-label="Remove field"
                                                @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                            >Remove</button>
                                        </div>
                                    </div>
                                </template>

                                <div class="mforms-builder-actions">
                                    <button
                                        type="button"
                                        class="mforms-builder-add-field"
                                        @click="addField($el.closest('.meros-form-builder'))"
                                    >
                                        <span aria-hidden="true">+</span>
                                        Add field
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @foreach($row['fields'] ?? [] as $fieldIndex => $field)
                        @php $name = $field['name'] ?? ''; @endphp
                        @if ($name !== '')
                            <div
                                id="mforms-field-component-{{ $index }}-{{ $fieldIndex }}"
                                data-form-field-component
                                data-row-index="{{ $index }}"
                                data-field-position="{{ $fieldIndex }}"
                                @dragend="endDrag($el.closest('.meros-form-builder'))"
                                class="mforms-builder-field-card"
                            >
                                <span
                                    class="mforms-field-drag-handle"
                                    draggable="true"
                                    aria-label="Drag to reorder field"
                                    title="Drag to reorder field"
                                    @dragstart.stop="startFieldDrag($event, $el.closest('[data-form-field-component]'))"
                                >⠿</span>
                                <div class="mforms-builder-field-content">
                                    {!! $this->getFieldComponent($index, $name) !!}
                                </div>
                                <button
                                    type="button"
                                    class="mforms-builder-remove"
                                    aria-label="Remove field"
                                    @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                >Remove</button>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
        @endforeach

        <template x-ref="newFieldTemplate">
            <div class="mforms-row" data-row-type="fields">
                <div
                    id="mforms-field-component-template-0"
                    data-form-field-component
                    data-row-index="__ROW_INDEX__"
                    data-field-position="0"
                    class="mforms-builder-field-card"
                >
                    <span
                        class="mforms-field-drag-handle"
                        draggable="true"
                        aria-label="Drag to reorder field"
                        title="Drag to reorder field"
                        @dragstart.stop="startFieldDrag($event, $el.closest('[data-form-field-component]'))"
                    >⠿</span>
                    <div class="mforms-builder-field-content">
                        {!! $this->getNewFieldComponent() !!}
                    </div>
                    <button
                        type="button"
                        class="mforms-builder-remove"
                        aria-label="Remove field"
                        @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                    >Remove</button>
                </div>
            </div>
        </template>

        <template x-ref="newGroupTemplate">
            <div class="mforms-row" data-row-type="section">
                <div class="mforms-group-component mforms-builder-group-card" data-form-group-component id="mforms-section-template">
                    <div
                        class="mforms-group-builder-header"
                        aria-label="Group component. Drag to reorder."
                    >
                        <span
                            class="mforms-field-drag-handle"
                            draggable="true"
                            aria-hidden="true"
                            @dragstart.stop="startGroupDrag($event, $el.closest('.mforms-row'))"
                            @dragend="endDrag($el.closest('.meros-form-builder'))"
                        >⠿</span>
                        <button
                            type="button"
                            class="mforms-builder-remove"
                            aria-label="Remove group"
                            @click.stop.prevent="removeGroup($el.closest('[data-form-group-component]'))"
                        >Remove</button>
                        <label>
                            <span>Group title</span>
                            <input type="text" name="form_components[0]['childGroup']['title']" value="New Group">
                        </label>
                        <input type="hidden" name="form_components[0]['childGroup']['name']" value="new_group">
                        <input type="hidden" name="form_components[0]['childGroup']['id']" value="mforms-section-template">
                        <input type="hidden" name="form_components[0]['childGroup']['description']" value="">
                    </div>
                    <div
                        id="mforms-group-builder-template"
                        class="meros-form-builder mforms-form meros-group-form-builder"
                        data-rows-name-prefix="form_components[0]['childGroup']['rows']"
                        x-data="{}"
                        x-init="initialize($el)"
                    >
                        <div class="mforms-body">
                            <div class="mforms-row" data-row-index="0" data-row-type="fields"></div>
                            <template x-ref="newFieldTemplate">
                                <div class="mforms-row" data-row-type="fields">
                                    <div
                                        id="mforms-group-field-component-template-0"
                                        data-form-field-component
                                        class="mforms-builder-field-card"
                                    >
                                        <span
                                            class="mforms-field-drag-handle"
                                            draggable="true"
                                            aria-label="Drag to reorder field"
                                            title="Drag to reorder field"
                                            @dragstart.stop="startFieldDrag($event, $el.closest('[data-form-field-component]'))"
                                        >⠿</span>
                                        <div class="mforms-builder-field-content">
                                            {!! $this->getNewFieldComponent("form_components[0]['childGroup']['rows'][0]['fields'][0]") !!}
                                        </div>
                                        <button
                                            type="button"
                                            class="mforms-builder-remove"
                                            aria-label="Remove field"
                                            @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                        >Remove</button>
                                    </div>
                                </div>
                            </template>
                            <div class="mforms-builder-actions">
                                <button
                                    type="button"
                                    class="mforms-builder-add-field"
                                    @click="addField($el.closest('.meros-form-builder'))"
                                >
                                    <span aria-hidden="true">+</span>
                                    Add field
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="mforms-builder-actions">
            <button
                type="button"
                class="mforms-builder-add-field"
                @click="addField($el.closest('.meros-form-builder'))"
            >
                <span aria-hidden="true">+</span>
                Add field
            </button>
            <button
                type="button"
                class="mforms-builder-add-field"
                @click="addGroup($el.closest('.meros-form-builder'))"
            >
                <span aria-hidden="true">+</span>
                Add Group
            </button>
        </div>
    </div>
</div>

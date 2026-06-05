import { merosHydrateQuillContent } from '../richtext.js';
import { initTomSelect } from '../tom-select/index.js';

export default function registerFormBuilderStore() {
    const store = {
        // Schema properties
        formTitle: '',
        formDescription: '',
        formSlug: '',
        formStatus: '',
        rows: [],
        actions: {},
        actionPayloads: {},
        richTextPayloads: [],
        actionConfigContext: null,
        actionConfigDialog: null,

        // Updaters/callbacks
        settingsUpdater: null,
        rowsUpdater: null,
        actionsUpdater: null,
        actionConfigCallback: null,
        fieldConditionsEditCallback: null,
        fieldConditionOperatorMap: {},

        // Repeater properties
        repeaterEditCallback: null,
        repeaterFieldMoveCallback: null,
        repeaterFieldAddCallback: null,
        repeaterFieldUpdateCallback: null,
        repeaterUpdateValueCallback: null,
        repeaterID: null,
        repeaterFieldID: null,
        
        // State properties
        activeField: null,
        isDragging: false,
        isCanvasDrag: false,
        itemKind: null,
        itemHandle: null,
        itemLabel: null,
        fieldType: null,
        fieldLabel: null,
        sourceRowIndex: null,
        sourceFieldIndex: null,
        sourceGroupRowIndex: null,
        sourceGroupInnerRowIndex: null,

        // ======================================
        // Form Builder Property Setters
        // ======================================

        // Sets the form title
        setFormTitle(title) {
            this.formTitle = title;
            this.settingsUpdater?.('formTitle', this.formTitle);
        },

        // Sets the form description
        setFormDescription(description) {
            this.formDescription = description;
            this.settingsUpdater?.('formDescription', this.formDescription);
        },

        // Sets the form slug
        setFormSlug(slug) {
            const formatted = String(slug ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            
            this.formSlug = formatted;
            this.settingsUpdater?.('formSlug', this.formSlug);
        },

        // Sets the form status
        setFormStatus(status) {
            this.formStatus = status;
            this.settingsUpdater?.('formStatus', this.formStatus);
        },

        // Sets the rows object
        setRows(rows) {
            this.rows = rows;
        },

        // Sets the actions object
        setActions(actions) {
            this.actions = (actions && typeof actions === 'object')
                ? { ...actions }
                : {};
        },

        // Sets the rich text payloads
        setRichTextPayloads(payloads) {
            this.richTextPayloads = payloads;
        },

        // Sets the form settings updater callback
        setSettingsUpdater(updater) {
            this.settingsUpdater = typeof updater === 'function' ? updater : null;
        },

        // Sets the row updater callback
        setRowsUpdater(updater) {
            this.rowsUpdater = typeof updater === 'function' ? updater : null;
        },

        // Sets the actions updater callback
        setActionsUpdater(updater) {
            this.actionsUpdater = typeof updater === 'function' ? updater : null;
        },

        // Sets the action configuration callback
        setActionConfigCallback(callback) {
            this.actionConfigCallback = typeof callback === 'function' ? callback : null;
        },

        // Sets the callback to open the field conditions editor for a specific field
        setFieldConditionsEditCallback(callback) {
            this.fieldConditionsEditCallback = typeof callback === 'function' ? callback : null;
        },

        // Sets the canonical operator map for field-condition operator options.
        setFieldConditionOperatorMap(map) {
            this.fieldConditionOperatorMap = (map && typeof map === 'object' && !Array.isArray(map))
                ? { ...map }
                : {};
        },

        // Callbacks for repeater field editing, moving, and adding
        setRepeaterEditCallback(callback) {
            this.repeaterEditCallback = typeof callback === 'function' ? callback : null;
        },

        setRepeaterFieldMoveCallback(callback) {
            this.repeaterFieldMoveCallback = typeof callback === 'function' ? callback : null;
        },

        setRepeaterFieldAddCallback(callback) {
            this.repeaterFieldAddCallback = typeof callback === 'function' ? callback : null;
        },

        setRepeaterFieldUpdateCallback(callback) {
            this.repeaterFieldUpdateCallback = typeof callback === 'function' ? callback : null;
        },

        setRepeaterUpdateValueCallback(callback) {
            this.repeaterUpdateValueCallback = typeof callback === 'function' ? callback : null;
        },

        // ======================================
        // Form Builder Data Utilities
        // ======================================

        // Utility to create a deep clone of the rows for safe mutation
        cloneRows() {
            if (typeof structuredClone === 'function') {
                try {
                    return structuredClone(this.rows);
                } catch (error) {
                    // Some hydrated payloads can include non-cloneable values.
                }
            }

            try {
                return JSON.parse(JSON.stringify(this.rows));
            } catch (error) {
                if (Array.isArray(this.rows)) {
                    return this.rows.map(row => ({ ...row }));
                }

                return [];
            }
        },

        // Commits mutated rows to Livewire and the store.
        commitRows(mutator, closeEditingPanel = false) {
            const nextRows = this.cloneRows();
            mutator(nextRows);
            this.setRows(nextRows);

            if (this.rowsUpdater) {
                this.rowsUpdater(nextRows, closeEditingPanel);
            }
        },

        // Utility to generate unique IDs for fields and groups.
        makeId() {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return `field_${crypto.randomUUID().substring(0, 8)}`;
            }

            return `field_${Date.now().toString().substring(0, 8)}`;
        },

        // Utility to convert a handle into a human-readable label.
        humanizeHandle(handle) {
            return String(handle ?? '')
                .replace(/[-_]+/g, ' ')
                .trim()
                .replace(/\b\w/g, letter => letter.toUpperCase());
        },

        // Utility to convert a label into a slug suitable for field names.
        slugify(value) {
            return String(value ?? '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        },

        // ======================================
        // Field Payload Utilities
        // ======================================

        // Creates a new field payload based on the handle and current item label or field label.
        createFieldPayload(handle) {
            const safeHandle = String(handle ?? '').trim();
            const label  = this.fieldLabel ?? this.itemLabel ?? this.humanizeHandle(safeHandle) ?? 'Field';
            const idName = this.makeId();

            const payload = {
                handle: safeHandle,
                properties: {
                    id: idName,
                    label,
                    name: idName,
                    helpText: '',
                    helpTextPosition: 'top',
                    value: '',
                    rules: [],
                    conditions: this.getFieldConditionsDefaults(),
                    required: false,
                    disabled: false,
                },
            };

            // Adjust payload for choice fields.
            if (this.isChoiceField(safeHandle)) {
                payload.properties.options = {};
            }

            // Adjust payload for multi-value fields.
            if (this.isMultipleChoiceField(safeHandle)) {
                payload.properties.value = [];
            }

            // Adjust payload for text-based fields.
            if (this.isInputField(safeHandle)) {
                payload.properties.placeholder = '';
            }

            // Adjust payload for fields that support TomSelect.
            if (this.isSelectField(safeHandle)) {
                if (['advanced_select', 'multi_select'].includes(safeHandle)) {
                    payload.properties.advanced = true;
                    payload.properties.allowAdd = false;
                } else {
                    payload.properties.advanced = false;
                }
            }

            return payload;
        },


        // Gets default structure for field conditions.
        getFieldConditionsDefaults() {
            return {
                show: {
                    logic: 'and',
                    rules: [],
                },
                hide: {
                    logic: 'and',
                    rules: [],
                },
                require: {
                    logic: 'and',
                    rules: [],
                },
                'optional': {
                    logic: 'and',
                    rules: [],
                },
                'enable': {
                    logic: 'and',
                    rules: [],
                },
                'disable': {
                    logic: 'and',
                    rules: [],
                }
            };
        },

        isInputField(handle) {
            return ['text', 'email', 'tel', 'url', 'number'].includes(handle);
        },

        isChoiceField(handle) {
            return ['select', 'radio', 'checkboxes', 'multi_select', 'advanced_select'].includes(handle);
        },

        isSelectField(handle) {
            return ['select', 'multi_select', 'advanced_select'].includes(handle);
        },

        isSingleChoiceField(handle) {
            return ['select', 'radio'].includes(handle);
        },

        isMultipleChoiceField(handle) {
            return ['checkboxes', 'multi_select'].includes(handle);
        },

        hasTomSelectDefaultValue(handle) {
            return ['multi_select', 'advanced_select', 'checkboxes'].includes(handle);
        },

        supportsIcon(handle) {
            return [
                'email',
                'tel',
                'url',
                'date',
                'time',
                'password'
            ].includes(handle);
        },

        // ======================================
        // Row and Group Structure Utilities
        // ======================================

        // Utility to create a new top-level fields row with optional initial fields.
        createFieldsRow(fields = []) {
            return {
                type: 'fields',
                fields,
            };
        },

        // Utility to create a new group row with an optional handle to generate the title from.
        createGroupRow(handle = '') {
            const title = this.itemLabel ?? this.humanizeHandle(handle);

            return {
                type: 'group',
                group: {
                    id: this.makeId(),
                    handle,
                    title: title || 'Untitled Section',
                    description: '',
                    rows: [],
                },
            };
        },

        // Retrieves the top-level fields row at the specified index, ensuring it has the correct structure.
        getTopRow(rows, rowIndex) {
            const row = rows[rowIndex];

            if (!row || row.type === 'group') {
                return null;
            }

            if (!Array.isArray(row.fields)) {
                row.fields = [];
            }

            row.type = 'fields';

            return row;
        },

        // Retrieves the group row at the specified index, ensuring it has the correct structure.
        getGroup(rows, groupRowIndex) {
            const row = rows[groupRowIndex];

            if (!row || row.type !== 'group' || typeof row.group !== 'object' || row.group === null) {
                return null;
            }

            if (!Array.isArray(row.group.rows)) {
                row.group.rows = [];
            }

            return row.group;
        },

        // Resolves a requested index against a length, ensuring it's a valid integer within bounds, and falling back to a default if not.
        resolveIndex(length, requestedIndex, fallback = length) {
            if (!Number.isInteger(requestedIndex)) {
                return fallback;
            }

            if (requestedIndex < 0) {
                return 0;
            }

            if (requestedIndex > length) {
                return length;
            }

            return requestedIndex;
        },

        // Ensures the specified inner row exists within a group, creating it if necessary.
        ensureGroupInnerRow(group, innerRowIndex) {
            const insertIndex = this.resolveIndex(group.rows.length, innerRowIndex, group.rows.length);

            if (!group.rows[insertIndex]) {
                group.rows.splice(insertIndex, 0, { fields: [] });
            }

            if (!Array.isArray(group.rows[insertIndex].fields)) {
                group.rows[insertIndex].fields = [];
            }

            return {
                row: group.rows[insertIndex],
                index: insertIndex,
            };
        },

        // Extracts a field from a top-level row, returning the extracted field and metadata about its original location.
        extractTopField(rows, rowIndex, fieldIndex) {
            const row = this.getTopRow(rows, rowIndex);

            if (!row) {
                return null;
            }

            if (!Number.isInteger(fieldIndex) || fieldIndex < 0 || fieldIndex >= row.fields.length) {
                return null;
            }

            const [field] = row.fields.splice(fieldIndex, 1);

            if (!field) {
                return null;
            }

            const removedSourceRow = row.fields.length === 0;

            if (row.fields.length === 0) {
                rows.splice(rowIndex, 1);
            }

            return {
                field,
                removedSourceRow,
                sourceKind: 'top',
                sourceRowIndex: rowIndex,
                sourceFieldIndex: fieldIndex,
            };
        },

        // Extracts a field from a group row, returning the extracted field and metadata about its original location.
        extractGroupField(rows, groupRowIndex, innerRowIndex, fieldIndex) {
            const group = this.getGroup(rows, groupRowIndex);

            if (!group) {
                return null;
            }

            const innerRow = group.rows[innerRowIndex];

            if (!innerRow || !Array.isArray(innerRow.fields)) {
                return null;
            }

            if (!Number.isInteger(fieldIndex) || fieldIndex < 0 || fieldIndex >= innerRow.fields.length) {
                return null;
            }

            const [field] = innerRow.fields.splice(fieldIndex, 1);

            if (!field) {
                return null;
            }

            const removedSourceInnerRow = innerRow.fields.length === 0;

            if (innerRow.fields.length === 0) {
                group.rows.splice(innerRowIndex, 1);
            }

            return {
                field,
                removedSourceInnerRow,
                sourceKind: 'group',
                sourceGroupRowIndex: groupRowIndex,
                sourceGroupInnerRowIndex: innerRowIndex,
                sourceFieldIndex: fieldIndex,
            };
        },

        // Finds a top-level field without mutating rows.
        findTopField(rows, rowIndex, fieldIndex) {
            const row = this.getTopRow(rows, rowIndex);

            if (!row) {
                return null;
            }

            if (!Number.isInteger(fieldIndex) || fieldIndex < 0 || fieldIndex >= row.fields.length) {
                return null;
            }

            return row.fields[fieldIndex] ?? null;
        },

        // Finds a group field without mutating rows.
        findGroupField(rows, groupRowIndex, innerRowIndex, fieldIndex) {
            const group = this.getGroup(rows, groupRowIndex);

            if (!group) {
                return null;
            }

            const innerRow = group.rows[innerRowIndex];

            if (!innerRow || !Array.isArray(innerRow.fields)) {
                return null;
            }

            if (!Number.isInteger(fieldIndex) || fieldIndex < 0 || fieldIndex >= innerRow.fields.length) {
                return null;
            }

            return innerRow.fields[fieldIndex] ?? null;
        },

        // Normalises nested repeater sub-fields so lookups tolerate array-like payloads.
        getRepeaterSubFields(field) {
            if (Array.isArray(field?.fields)) {
                return field.fields;
            }

            if (field?.fields && typeof field.fields === 'object') {
                return Object.values(field.fields).filter(Boolean);
            }

            return [];
        },

        // Collects field payloads keyed by field id, including nested repeater fields.
        getFieldsById() {
            const fieldsById = {};

            const collectFields = fields => {
                if (!Array.isArray(fields)) {
                    return;
                }

                fields.forEach(field => {
                    const fieldId = field?.properties?.id;

                    if (typeof fieldId === 'string' && fieldId.trim() !== '') {
                        fieldsById[fieldId] = field;
                    }

                    if (field?.handle === 'repeater') {
                        collectFields(this.getRepeaterSubFields(field));
                    }
                });
            };

            (this.rows ?? []).forEach(row => {
                if (row?.type === 'group') {
                    (row.group?.rows ?? []).forEach(innerRow => {
                        collectFields(innerRow?.fields ?? []);
                    });
                    return;
                }

                if (row?.type === 'fields') {
                    collectFields(row.fields ?? []);
                }
            });

            return fieldsById;
        },

        // Inserts a field as a new top-level row at the specified index.
        insertFieldAsNewTopRow(rows, targetRowIndex, field) {
            const insertIndex = this.resolveIndex(rows.length, (targetRowIndex ?? -1) + 1, rows.length);
            rows.splice(insertIndex, 0, this.createFieldsRow([field]));
        },

        // Inserts a field as a new row within the target group at the specified group and inner row indices.
        insertFieldAsNewGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, field) {
            const targetGroup = this.getGroup(rows, targetGroupRowIndex);

            if (!targetGroup) {
                return;
            }

            const insertIndex = this.resolveIndex(targetGroup.rows.length, (targetInnerRowIndex ?? -1) + 1, targetGroup.rows.length);
            targetGroup.rows.splice(insertIndex, 0, { fields: [field] });
        },

        // Inserts a field into an existing row within the target group at the specified group, inner row, and field indices.
        insertFieldIntoGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, field) {
            const targetGroup = this.getGroup(rows, targetGroupRowIndex);

            if (!targetGroup) {
                return;
            }

            const { row: targetInnerRow } = this.ensureGroupInnerRow(targetGroup, targetInnerRowIndex);
            const insertIndex = this.resolveIndex(targetInnerRow.fields.length, targetFieldIndex, targetInnerRow.fields.length);
            targetInnerRow.fields.splice(insertIndex, 0, field);
        },

        // Adjust target group index when extracting a top-level field removes its source row.
        adjustTargetGroupRowIndexAfterTopExtraction(extracted, sourceRowIndex, targetGroupRowIndex) {
            if (
                extracted?.removedSourceRow
                && Number.isInteger(sourceRowIndex)
                && Number.isInteger(targetGroupRowIndex)
                && sourceRowIndex < targetGroupRowIndex
            ) {
                return targetGroupRowIndex - 1;
            }

            return targetGroupRowIndex;
        },

        // Inserts a field into an existing top-level row at the specified row and field indices.
        insertFieldIntoTopRow(rows, targetRowIndex, targetFieldIndex, field) {
            let targetRow = this.getTopRow(rows, targetRowIndex);

            if (!targetRow) {
                const rowInsertIndex = this.resolveIndex(rows.length, targetRowIndex, rows.length);
                rows.splice(rowInsertIndex, 0, this.createFieldsRow([]));
                targetRow = this.getTopRow(rows, rowInsertIndex);
            }

            if (!targetRow) {
                return;
            }

            const insertIndex = this.resolveIndex(targetRow.fields.length, targetFieldIndex, targetRow.fields.length);
            targetRow.fields.splice(insertIndex, 0, field);
        },

        // ======================================
        // Drag Lifecycle Handlers
        // ======================================
        startDrag(kind, handle, label, repeaterId = null) {
            this.isDragging = true;
            this.isCanvasDrag = false;
            this.itemKind = kind;
            this.itemHandle = handle;
            this.itemLabel = label;
            this.repeaterID = repeaterId;
            this.fieldType = kind === 'field' ? handle : null;
            this.fieldLabel = label;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = null;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
        },

        startCanvasDrag(rowIndex, fieldIndex) {
            this.isDragging = true;
            this.isCanvasDrag = true;
            this.itemKind = 'field';
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = rowIndex;
            this.sourceFieldIndex = fieldIndex;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
        },

        startGroupCanvasDrag(groupRowIndex, rowIndex, fieldIndex) {
            this.isDragging = true;
            this.isCanvasDrag = true;
            this.itemKind = 'field';
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = fieldIndex;
            this.sourceGroupRowIndex = groupRowIndex;
            this.sourceGroupInnerRowIndex = rowIndex;
        },

        startRepeaterFieldCanvasDrag(repeaterId, fieldId, fieldIndex) {
            this.isDragging = true;
            this.isCanvasDrag = true;
            this.itemKind = 'field';
            this.repeaterID = repeaterId;
            this.repeaterFieldID = fieldId;
            this.sourceFieldIndex = fieldIndex;
        },

        endDrag() {
            this.isDragging = false;
            this.isCanvasDrag = false;
            this.itemKind = null;
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = null;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
            this.repeaterID = null;
        },

        // ======================================
        // Drag and Drop Parsing Helpers
        // ======================================

        canDropField() {
            return this.itemKind === 'field';
        },

        hasDataTransferType(event, type) {
            return event?.dataTransfer?.types?.includes(type) ?? false;
        },

        getDataTransferNumber(event, type) {
            const value = Number(event?.dataTransfer?.getData(type));

            if (Number.isNaN(value)) {
                return null;
            }

            return value;
        },

        // ======================================
        // Visual Helpers
        // ======================================

        showInsertMarker(zoneElement) {
            const marker = zoneElement?.firstElementChild;

            if (!marker) {
                return;
            }

            marker.style.opacity = '1';
            marker.style.height = '88%';
            marker.style.boxShadow = '0 0 0 1px rgba(59,130,246,0.28), 0 8px 20px rgba(59,130,246,0.30)';
        },

        hideInsertMarker(zoneElement) {
            const marker = zoneElement?.firstElementChild;

            if (!marker) {
                return;
            }

            marker.style.opacity = '0';
            marker.style.height = '';
            marker.style.boxShadow = '';
        },

        showRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '2rem';
            gapElement.classList.add('bg-blue-200');
        },

        hideRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '';
            gapElement.classList.remove('bg-blue-200');
        },

        showEmptyCanvasHighlight(zoneElement) {
            if (!zoneElement) {
                return;
            }

            zoneElement.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-500');
            zoneElement.classList.remove('border-gray-300', 'text-gray-400');
        },

        hideEmptyCanvasHighlight(zoneElement) {
            if (!zoneElement) {
                return;
            }

            zoneElement.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500');
            zoneElement.classList.add('border-gray-300', 'text-gray-400');
        },

        showFieldDropHighlight(zoneElement) {
            if (!zoneElement || !this.canDropField()) {
                return;
            }

            zoneElement.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-500');
        },

        hideFieldDropHighlight(zoneElement) {
            if (!zoneElement) {
                return;
            }

            zoneElement.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500');
        },

        // ======================================
        // Canvas Insertion Handlers
        // ======================================

        // Handle drops on an empty canvas (no rows).
        handleEmptyCanvasDrop(zoneElement) {
            this.hideEmptyCanvasHighlight(zoneElement);

            if (this.itemKind === 'group') {
                this.commitRows(rows => {
                    rows.push(this.createGroupRow(this.itemHandle ?? ''));
                });
                return;
            }

            if (!this.canDropField()) {
                return;
            }

            this.commitRows(rows => {
                rows.push(this.createFieldsRow([this.createFieldPayload(this.fieldType)]));
            });
        },

        handleEmptyRepeaterCanvasDrop(zoneElement) {
            this.hideEmptyCanvasHighlight(zoneElement);

            if (!this.repeaterID || !this.fieldType || !this.canDropField()) {
                return;
            }

            if (this.repeaterFieldAddCallback) {
                this.repeaterFieldAddCallback(this.repeaterID, this.fieldType, 0);
            }
        },

        // Determine if the currently dragged item can be dropped on a canvas row gap.
        canDropOnCanvasRowGap(event) {
            return this.itemKind === 'group'
                || this.itemKind === 'field'
                || this.hasDataTransferType(event, 'application/x-meros-group-row');
        },

        // Handle dragovers on canvas row gaps to show a visual indicator if the dragged item can be dropped there.
        handleCanvasRowGapDragOver(event, gapElement) {
            if (!this.canDropOnCanvasRowGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        // Handle drops on canvas row gaps to insert the dragged item as a new row at the target index.
        moveFieldToCanvasNewRow(targetRowIndex) {
            if (this.sourceGroupRowIndex !== null) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        this.sourceGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    this.insertFieldAsNewTopRow(rows, targetRowIndex, extracted.field);
                });
                return;
            }

            if (this.isCanvasDrag) {
                this.moveFieldToNewRow(this.sourceRowIndex, this.sourceFieldIndex, targetRowIndex);
                return;
            }

            this.commitRows(rows => {
                this.insertFieldAsNewTopRow(rows, targetRowIndex, this.createFieldPayload(this.fieldType));
            });
        },

        // Handle drops on canvas row gaps to move an existing field into a new row at the target index.
        moveFieldToNewRow(sourceRowIndex, sourceFieldIndex, targetRowIndex) {
            this.commitRows(rows => {
                const extracted = this.extractTopField(rows, sourceRowIndex, sourceFieldIndex);

                if (!extracted) {
                    return;
                }

                let insertIndex = this.resolveIndex(rows.length, (targetRowIndex ?? -1) + 1, rows.length);

                if (extracted.removedSourceRow && sourceRowIndex < insertIndex) {
                    insertIndex -= 1;
                }

                insertIndex = this.resolveIndex(rows.length, insertIndex, rows.length);
                rows.splice(insertIndex, 0, this.createFieldsRow([extracted.field]));
            });
        },

        // Handle drops on canvas row-gap zones.
        handleCanvasRowGapDrop(event, gapElement, groupInsertIndex, fieldRowIndex) {
            this.hideRowGap(gapElement);

            if (this.itemKind === 'group' || this.hasDataTransferType(event, 'application/x-meros-group-row')) {
                if (this.itemKind === 'group') {
                    this.commitRows(rows => {
                        const insertIndex = this.resolveIndex(rows.length, groupInsertIndex, rows.length);
                        rows.splice(insertIndex, 0, this.createGroupRow(this.itemHandle ?? ''));
                    });
                    return;
                }

                const sourceGroupRow = this.getDataTransferNumber(event, 'application/x-meros-group-row');

                if (sourceGroupRow !== null) {
                    this.commitRows(rows => {
                        if (sourceGroupRow < 0 || sourceGroupRow >= rows.length) {
                            return;
                        }

                        const sourceRow = rows[sourceGroupRow];

                          if (!sourceRow || sourceRow.type !== 'group') {
                            return;
                        }

                        const [movedRow] = rows.splice(sourceGroupRow, 1);
                        let insertIndex = this.resolveIndex(rows.length, groupInsertIndex, rows.length);

                        if (sourceGroupRow < insertIndex) {
                            insertIndex -= 1;
                        }

                        insertIndex = this.resolveIndex(rows.length, insertIndex, rows.length);
                        rows.splice(insertIndex, 0, movedRow);
                    });
                }

                return;
            }

            this.moveFieldToCanvasNewRow(fieldRowIndex);
        },

        handleRepeaterFieldGapDrop(event, gapElement, newIndex) {
            this.hideRowGap(gapElement);

            if (!this.canDropField()) {
                return;
            }

            if (this.repeaterFieldMoveCallback && this.repeaterID && this.repeaterFieldID) {
                this.repeaterFieldMoveCallback(this.repeaterID, this.repeaterFieldID, newIndex);
            } 
            
            else if (this.repeaterFieldAddCallback && this.repeaterID && this.fieldType) {
                this.repeaterFieldAddCallback(this.repeaterID, this.fieldType, newIndex);
            }
        },

        // ======================================
        // Group Row Handlers
        // ======================================
        
        // Utility to move a field into a new row inside a target group
        moveFieldToGroupNewRow(targetGroupRowIndex, targetInnerRowIndex) {

            if (this.sourceGroupRowIndex === targetGroupRowIndex) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        targetGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    const group = this.getGroup(rows, targetGroupRowIndex);

                    if (!group) {
                        return;
                    }

                    const desiredIndex = (targetInnerRowIndex ?? -1) + 1;
                    let insertIndex = this.resolveIndex(group.rows.length, desiredIndex, group.rows.length);

                    if (extracted.removedSourceInnerRow && this.sourceGroupInnerRowIndex < insertIndex) {
                        insertIndex -= 1;
                    }

                    insertIndex = this.resolveIndex(group.rows.length, insertIndex, group.rows.length);
                    group.rows.splice(insertIndex, 0, { fields: [extracted.field] });
                });
                return;
            }

            if (this.sourceGroupRowIndex !== null) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        this.sourceGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    this.insertFieldAsNewGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, extracted.field);
                });
                return;
            }

            if (this.isCanvasDrag) {
                this.commitRows(rows => {
                    const extracted = this.extractTopField(rows, this.sourceRowIndex, this.sourceFieldIndex);

                    if (!extracted) {
                        return;
                    }

                    const adjustedTargetGroupRowIndex = this.adjustTargetGroupRowIndexAfterTopExtraction(
                        extracted,
                        this.sourceRowIndex,
                        targetGroupRowIndex,
                    );

                    this.insertFieldAsNewGroupRow(rows, adjustedTargetGroupRowIndex, targetInnerRowIndex, extracted.field);
                });
                return;
            }

            this.commitRows(rows => {
                this.insertFieldAsNewGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, this.createFieldPayload(this.itemHandle));
            });
        },

        // Handle drops on empty group rows
        handleGroupEmptyDrop(zoneElement, targetGroupRowIndex, targetInnerRowIndex = -1) {
            this.hideFieldDropHighlight(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldToGroupNewRow(targetGroupRowIndex, targetInnerRowIndex);
        },

        // Handle drops on group row gaps to move an existing field into a new row at the target index.
        handleGroupRowGapDrop(event, gapElement, targetGroupRowIndex, targetInnerRowIndex) {

            this.hideRowGap(gapElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldToGroupNewRow(targetGroupRowIndex, targetInnerRowIndex);
        },

        // ======================================
        // Field Insertion Handlers
        // ======================================
        
        // Utility to move or add a field into a group's row-gap target.
        moveFieldIntoGroupRow(targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex) {

            if (this.sourceGroupRowIndex === targetGroupRowIndex) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        targetGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    const group = this.getGroup(rows, targetGroupRowIndex);

                    if (!group) {
                        return;
                    }

                    let adjustedTargetInnerRowIndex = targetInnerRowIndex;

                    if (
                        extracted.removedSourceInnerRow
                        && this.sourceGroupInnerRowIndex < adjustedTargetInnerRowIndex
                    ) {
                        adjustedTargetInnerRowIndex -= 1;
                    }

                    const { row: targetInnerRow, index: actualInnerIndex } = this.ensureGroupInnerRow(group, adjustedTargetInnerRowIndex);
                    let insertIndex = targetFieldIndex;

                    if (
                        this.sourceGroupInnerRowIndex === actualInnerIndex
                        && this.sourceFieldIndex < targetFieldIndex
                    ) {
                        insertIndex -= 1;
                    }

                    insertIndex = this.resolveIndex(targetInnerRow.fields.length, insertIndex, targetInnerRow.fields.length);
                    targetInnerRow.fields.splice(insertIndex, 0, extracted.field);
                });
                return;
            }

            if (this.sourceGroupRowIndex !== null) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        this.sourceGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    this.insertFieldIntoGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, extracted.field);
                });
                return;
            }

            if (this.isCanvasDrag) {
                this.commitRows(rows => {
                    const extracted = this.extractTopField(rows, this.sourceRowIndex, this.sourceFieldIndex);

                    if (!extracted) {
                        return;
                    }

                    const adjustedTargetGroupRowIndex = this.adjustTargetGroupRowIndexAfterTopExtraction(
                        extracted,
                        this.sourceRowIndex,
                        targetGroupRowIndex,
                    );

                    this.insertFieldIntoGroupRow(
                        rows,
                        adjustedTargetGroupRowIndex,
                        targetInnerRowIndex,
                        targetFieldIndex,
                        extracted.field,
                    );
                });
                return;
            }

            this.commitRows(rows => {
                this.insertFieldIntoGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, this.createFieldPayload(this.itemHandle));
            });
        },

        // Handle drops on group row insert zones to move or add a field into the target group row.
        handleGroupFieldInsertDrop(zoneElement, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex) {
            this.hideInsertMarker(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldIntoGroupRow(targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex);
        },

        // Handle drops on top-level row insert zones to move or add a field into the target top-level row.
        moveFieldIntoTopRow(targetRowIndex, targetFieldIndex) {
            if (this.sourceGroupRowIndex !== null) {
                this.commitRows(rows => {
                    const extracted = this.extractGroupField(
                        rows,
                        this.sourceGroupRowIndex,
                        this.sourceGroupInnerRowIndex,
                        this.sourceFieldIndex,
                    );

                    if (!extracted) {
                        return;
                    }

                    this.insertFieldIntoTopRow(rows, targetRowIndex, targetFieldIndex, extracted.field);
                });
                return;
            }

            if (this.isCanvasDrag) {
                this.commitRows(rows => {
                    const extracted = this.extractTopField(rows, this.sourceRowIndex, this.sourceFieldIndex);

                    if (!extracted) {
                        return;
                    }

                    let adjustedTargetRowIndex = targetRowIndex;

                    if (extracted.removedSourceRow && this.sourceRowIndex < adjustedTargetRowIndex) {
                        adjustedTargetRowIndex -= 1;
                    }

                    let targetRow = this.getTopRow(rows, adjustedTargetRowIndex);

                    if (!targetRow) {
                        const rowInsertIndex = this.resolveIndex(rows.length, adjustedTargetRowIndex, rows.length);
                        rows.splice(rowInsertIndex, 0, this.createFieldsRow([]));
                        targetRow = this.getTopRow(rows, rowInsertIndex);
                    }

                    if (!targetRow) {
                        return;
                    }

                    let insertIndex = targetFieldIndex;

                    if (this.sourceRowIndex === targetRowIndex && this.sourceFieldIndex < targetFieldIndex) {
                        insertIndex -= 1;
                    }

                    insertIndex = this.resolveIndex(targetRow.fields.length, insertIndex, targetRow.fields.length);
                    targetRow.fields.splice(insertIndex, 0, extracted.field);
                });
                return;
            }

            this.commitRows(rows => {
                this.insertFieldIntoTopRow(rows, targetRowIndex, targetFieldIndex, this.createFieldPayload(this.fieldType));
            });
        },

        // Handle drops on top-level row insert zones to move or add a field into the target top-level row.
        handleTopFieldInsertDrop(zoneElement, targetRowIndex, targetFieldIndex) {
            this.hideInsertMarker(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldIntoTopRow(targetRowIndex, targetFieldIndex);
        },

        // ======================================
        // Field Removal Handlers
        // ======================================

        // Remove a top-level group row.
        removeGroup(groupRowIndex) {
            if (!confirm('Are you sure you want to delete this group and all of its fields? This action cannot be undone.')) {
                return;
            }

            this.activeField = null;

            this.commitRows(rows => {
                if (!Number.isInteger(groupRowIndex) || groupRowIndex < 0 || groupRowIndex >= rows.length) {
                    return;
                }

                const row = rows[groupRowIndex];

                  if (!row || row.type !== 'group') {
                    return;
                }

                rows.splice(groupRowIndex, 1);
            });
        },
        
        // Handle field removals from the canvas or groups.
        removeField(sourceRowIndex, sourceFieldIndex, sourceGroupRowIndex = null, sourceGroupInnerRowIndex = null) {
            if (!confirm('Are you sure you want to delete this field? This action cannot be undone.')) {
                return;
            }

            const field = (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null)
                ? this.findGroupField(this.rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex)
                : this.findTopField(this.rows, sourceRowIndex, sourceFieldIndex);

            const removedFieldId = String(field?.properties?.id ?? '').trim();
            const closeEditingPanel = removedFieldId !== '' && this.activeField?.id === removedFieldId;

            if (closeEditingPanel) {
                this.activeField = null;
            }

            this.commitRows(rows => {
                let extracted = null;

                if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                    extracted = this.extractGroupField(rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
                } else {
                    extracted = this.extractTopField(rows, sourceRowIndex, sourceFieldIndex);
                }

                const extractedFieldId = String(extracted?.field?.properties?.id ?? removedFieldId).trim();

                if (extractedFieldId !== '') {
                    this.purgeFieldConditionRulesByFieldId(rows, extractedFieldId);
                }
            }, closeEditingPanel);
        },

        // Removes any field-condition rules that reference a removed field id.
        purgeFieldConditionRulesByFieldId(rows, removedFieldId) {
            const targetFieldId = String(removedFieldId ?? '').trim();

            if (targetFieldId === '') {
                return;
            }

            const purgeFieldList = fields => {
                if (!Array.isArray(fields)) {
                    return;
                }

                fields.forEach(field => {
                    if (!field || typeof field !== 'object') {
                        return;
                    }

                    this.purgeFieldConditionsForSingleField(field, targetFieldId);

                    if (field.handle === 'repeater') {
                        purgeFieldList(this.getRepeaterSubFields(field));
                    }
                });
            };

            (rows ?? []).forEach(row => {
                if (row?.type === 'group') {
                    (row.group?.rows ?? []).forEach(innerRow => {
                        purgeFieldList(innerRow?.fields ?? []);
                    });
                    return;
                }

                if (row?.type === 'fields') {
                    purgeFieldList(row.fields ?? []);
                }
            });
        },

        // Purges matching rules from all condition types on a single field.
        purgeFieldConditionsForSingleField(field, removedFieldId) {
            if (!field || typeof field !== 'object') {
                return;
            }

            if (!field.properties || typeof field.properties !== 'object') {
                return;
            }

            const conditions = field.properties.conditions;

            if (!conditions || typeof conditions !== 'object') {
                return;
            }

            Object.keys(conditions).forEach(conditionType => {
                const conditionConfig = conditions[conditionType];

                if (!conditionConfig || typeof conditionConfig !== 'object') {
                    return;
                }

                if (!Array.isArray(conditionConfig.rules)) {
                    conditionConfig.rules = [];
                    return;
                }

                conditionConfig.rules = conditionConfig.rules.filter(rule => {
                    const ruleFieldId = String(rule?.field_id ?? '').trim();

                    return ruleFieldId === '' || ruleFieldId !== removedFieldId;
                });
            });
        },

        // ======================================
        // Field and Group Editing Handlers
        // ======================================

        // Set the currently editing field to a group, opening the field setting panel for that group.
        editGroup(groupRowIndex) {
            const group = this.getGroup(this.rows, groupRowIndex);

            if (!group) {
                return;
            }

            // Clear any stale repeater-inner-field context
            this.repeaterID = null;
            this.repeaterFieldID = null;

            this.activeField = {
                handle: 'group',
                title: group.title,
                description: group.description,
                sourceGroupRowIndex: groupRowIndex,
            };

            const descriptionEditorEl = document.getElementById('group-description-editor');

            if (descriptionEditorEl) {
                const description = group.description ?? '';
                merosHydrateQuillContent(descriptionEditorEl, description === '' ? JSON.stringify([{ insert: '\n' }]) : description);
            }
        },

        // Set the currently editing field, opening the field setting panel for that field (or group)
        editField(sourceRowIndex, sourceFieldIndex, sourceGroupRowIndex = null, sourceGroupInnerRowIndex = null) {
            let field = null;

            if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                field = this.findGroupField(this.rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
            } else {
                field = this.findTopField(this.rows, sourceRowIndex, sourceFieldIndex);
            }

            if (!field) {
                return;
            }

            // Clear any stale repeater-inner-field context
            this.repeaterID = null;
            this.repeaterFieldID = null;

            this.activeField = {
                handle: field.handle,
                ...field.properties,
                sourceRowIndex,
                sourceFieldIndex,
                sourceGroupRowIndex,
                sourceGroupInnerRowIndex,
            };
        },

        // Opens the repeater field settings panel for managing nested repeater fields and row default values.
        openRepeaterFieldSettings(sourceRowIndex, sourceFieldIndex, sourceGroupRowIndex = null, sourceGroupInnerRowIndex = null) {
            let field = null;

            if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                field = this.findGroupField(this.rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
            } else {
                field = this.findTopField(this.rows, sourceRowIndex, sourceFieldIndex);
            }

            if (!field || field.handle !== 'repeater') {
                return;
            }

            if (this.repeaterEditCallback) {
                this.activeField = null;
                this.repeaterEditCallback(field.properties.id);
            }
        },

        // Sets the active field to a nested repeater field.
        editRepeaterField(repeaterId, fieldId, fieldIndex) {
            this.repeaterID = repeaterId;
            this.repeaterFieldID = fieldId;

            const field = this.getRepeaterField(repeaterId, fieldId);
            
            if (!field) {
                return;
            }

            this.activeField = {
                handle: field.handle,
                ...field.properties,
                repeaterID: repeaterId,
                repeaterFieldID: fieldId,
                sourceFieldIndex: fieldIndex,
            };
        },

        // Updates the default value of a repeater.
        updateRepeaterDefaultValue(repeaterId) {
            this.repeaterID = repeaterId;
            const repeaterEl = document.getElementById(repeaterId);
            
            if (!repeaterEl || !this.repeaterUpdateValueCallback) {
                return;
            }

            const value = Alpine.store('repeaterField').getRepeaterValue(repeaterEl);

            if (value === false || !Array.isArray(value)) {
                return;
            }

            this.repeaterUpdateValueCallback(this.repeaterID, value);
        },

        // Retrieves a nested field from a given repeater by its ID.
        getRepeaterField(repeaterId, fieldId) {
            if (this.rows.length === 0) {
                return null;
            }

            for (const row of this.rows) {
                  if (row.type === 'group') {
                    const group = row.group;
                    for (const innerRow of group.rows) {
                        if (!innerRow.fields || !Array.isArray(innerRow.fields)) {
                            continue;
                        }

                        for (const field of innerRow.fields) {
                            if (field.handle === 'repeater' && field.properties.id === repeaterId) {
                                const targetField = this.getRepeaterSubFields(field).find(f => f?.properties?.id === fieldId);

                                if (targetField) {
                                    return targetField;
                                }
                            }
                        }
                    }
                  } else if (row.type === 'fields') {
                    for (const field of row.fields) {
                        if (field.handle === 'repeater' && field.properties.id === repeaterId) {
                            const targetField = this.getRepeaterSubFields(field).find(f => f?.properties?.id === fieldId);

                            if (targetField) {
                                return targetField;
                            }
                        }
                    }
                }
            }

            return null;
        },

        // Updates a property in the current active group.
        updateActiveGroupProperty(property, value) {
            if (!this.activeField || this.activeField.handle !== 'group') {
                return;
            }

            if (property !== 'title' && property !== 'description') {
                return;
            }

            const nextValue = Array.isArray(value)
                ? [...value]
                : (value && typeof value === 'object' ? { ...value } : value);

            this.activeField[property] = nextValue;

            const { sourceGroupRowIndex } = this.activeField;

            this.commitRows(rows => {
                const group = this.getGroup(rows, sourceGroupRowIndex);

                if (!group) {
                    return;
                }

                group[property] = nextValue;
            });
        },

        // Updates a property in the current active field.
        updateActiveFieldProperty(property, value) {
            if (!this.activeField) {
                return;
            }

            const nextValue = Array.isArray(value)
                ? [...value]
                : (value && typeof value === 'object' ? { ...value } : value);

            this.activeField[property] = nextValue;

            if (this.repeaterID && this.repeaterFieldID && this.repeaterFieldUpdateCallback) {
                this.repeaterFieldUpdateCallback(this.repeaterID, this.repeaterFieldID, property, nextValue);
                return;
            }

            const {
                sourceRowIndex,
                sourceFieldIndex,
                sourceGroupRowIndex,
                sourceGroupInnerRowIndex,
            } = this.activeField;

            this.commitRows(rows => {
                let field = null;

                if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                    field = this.findGroupField(rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
                } else {
                    field = this.findTopField(rows, sourceRowIndex, sourceFieldIndex);
                }

                if (!field) {
                    return;
                }

                if (typeof field.properties !== 'object' || field.properties === null) {
                    field.properties = {};
                }

                field.properties[property] = Array.isArray(nextValue)
                    ? [...nextValue]
                    : (nextValue && typeof nextValue === 'object' ? { ...nextValue } : nextValue);
            });
        },

        // ======================================
        // Field Conditions Handlers
        // ======================================

        saveFieldConditions() {
            if (!this.activeField) {
                return;
            }

            this.clearFieldConditionsValidationErrors();

            const conditionsRepeaters = document.querySelectorAll('.meros-field-conditions-repeater');
            const fieldConditions = {};

            conditionsRepeaters.forEach(repeater => {
                const conditionType = repeater.id.replace('field-conditions-', '');
                const logicSelector = document.getElementById(`field-conditions-${conditionType}-logic`);
                const logic = logicSelector ? logicSelector.value : 'and';
                const rulesValue = mforms.getFieldValue(repeater);
                const rules = Array.isArray(rulesValue) ? rulesValue : [];

                fieldConditions[conditionType] = {
                    logic,
                    rules,
                };
            });

            const nextConditions = {
                ...this.getFieldConditionsDefaults(),
                ...this.activeField.conditions,
                ...fieldConditions,
            };
            const validationResult = this.validateFieldConditions(nextConditions);

            if (!validationResult.valid) {
                this.renderFieldConditionsValidationErrors(validationResult.conflicts);
                return;
            }

            this.activeField = {
                ...this.activeField,
                conditions: nextConditions,
            };

            const {
                sourceRowIndex,
                sourceFieldIndex,
                sourceGroupRowIndex,
                sourceGroupInnerRowIndex,
            } = this.activeField;

            this.commitRows(rows => {
                if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                    const field = this.findGroupField(rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);

                    if (field) {
                        field.properties.conditions = nextConditions;
                    }
                } else {
                    const field = this.findTopField(rows, sourceRowIndex, sourceFieldIndex);

                    if (field) {
                        field.properties.conditions = nextConditions;
                    }
                }
            }, true);
        },

        // Set the currently editing field to a field, opening the field condition setting panel for that field (or group)
        editFieldConditions(sourceRowIndex, sourceFieldIndex, sourceGroupRowIndex = null, sourceGroupInnerRowIndex = null) {
            let field = null;

            if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                field = this.findGroupField(this.rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
            } else {
                field = this.findTopField(this.rows, sourceRowIndex, sourceFieldIndex);
            }

            if (!field) {
                return;
            }

            this.activeField = {
                handle: field.handle,
                ...field.properties,
                sourceRowIndex,
                sourceFieldIndex,
                sourceGroupRowIndex,
                sourceGroupInnerRowIndex,
            };

            if (this.fieldConditionsEditCallback) {
                this.fieldConditionsEditCallback(field.properties.id);
            }

            this.hydratePersistedFieldConditionRows();
        },

        // Replays row setup for persisted field conditions after the Livewire panel re-renders.
        hydratePersistedFieldConditionRows(maxAttempts = 20) {
            const attemptsLimit = Number.isInteger(maxAttempts) && maxAttempts > 0
                ? maxAttempts
                : 20;

            let attempt = 0;

            const hydrate = () => {
                const repeaters = Array.from(document.querySelectorAll('.meros-field-conditions-repeater'));

                if (repeaters.length === 0) {
                    attempt += 1;

                    if (attempt < attemptsLimit) {
                        window.setTimeout(hydrate, 75);
                    }

                    return;
                }

                repeaters.forEach(repeaterElement => {
                    this.hydrateFieldConditionLogicSelector(repeaterElement);

                    const persistedRules = this.getPersistedFieldConditionRules(repeaterElement);
                    const fieldSelectors = Array.from(
                        repeaterElement.querySelectorAll('tr.meros-repeater-row td[data-field-name="field_id"] select')
                    );

                    fieldSelectors.forEach((selector, rowIndex) => {
                        const rowElement = selector.closest('tr.meros-repeater-row');
                        const persistedRule = persistedRules[rowIndex] ?? null;

                        if (!rowElement) {
                            return;
                        }

                        const operatorSelector = rowElement.querySelector('td[data-field-name="operator"] select');

                        if (persistedRule && typeof persistedRule === 'object') {
                            const persistedFieldId = String(persistedRule.field_id ?? '').trim();
                            const persistedOperator = String(persistedRule.operator ?? '').trim();

                            if (persistedFieldId !== '') {
                                selector.value = persistedFieldId;
                            }

                            if (operatorSelector && persistedOperator !== '') {
                                operatorSelector.value = persistedOperator;
                            }
                        }

                        this.setFieldConditionsRow({ target: selector });

                        this.setFieldConditionRowValueFromPersistedRule(rowElement, persistedRule?.value);
                    });

                    this.syncFieldConditionFieldSelectionState(repeaterElement, {
                        resetDuplicateOperators: false,
                    });
                });
            };

            window.setTimeout(hydrate, 0);
        },

        // Retrieves persisted rules for a field-condition repeater from the active field payload.
        getPersistedFieldConditionRules(repeaterElement) {
            if (!repeaterElement || !this.activeField || typeof this.activeField !== 'object') {
                return [];
            }

            const repeaterId = String(repeaterElement.id ?? '').trim();

            if (repeaterId === '' || !repeaterId.startsWith('field-conditions-')) {
                return [];
            }

            const conditionType = repeaterId.replace('field-conditions-', '');
            const conditions = this.activeField.conditions;

            if (!conditions || typeof conditions !== 'object') {
                return [];
            }

            const rules = conditions?.[conditionType]?.rules;

            return Array.isArray(rules) ? rules : [];
        },

        // Restores persisted logic state and binds a one-time change listener per logic selector.
        hydrateFieldConditionLogicSelector(repeaterElement) {
            if (!repeaterElement) {
                return;
            }

            const conditionType = this.getFieldConditionTypeFromRepeater(repeaterElement);

            if (conditionType === '') {
                return;
            }

            const logicSelector = document.getElementById(`field-conditions-${conditionType}-logic`);

            if (!(logicSelector instanceof HTMLSelectElement)) {
                return;
            }

            const persistedLogic = String(this.activeField?.conditions?.[conditionType]?.logic ?? '').trim().toLowerCase();

            if (persistedLogic === 'and' || persistedLogic === 'or') {
                logicSelector.value = persistedLogic;
            }

            this.bindFieldConditionLogicSelector(logicSelector);

            const currentLogic = this.getFieldConditionRepeaterLogicValue(repeaterElement);
            repeaterElement.dataset.fieldConditionLogicState = currentLogic;
        },

        // Ensures logic-selector event listeners are attached once per rendered control.
        bindFieldConditionLogicSelector(logicSelector) {
            if (!(logicSelector instanceof HTMLSelectElement)) {
                return;
            }

            if (logicSelector.dataset.fieldConditionLogicBound === '1') {
                return;
            }

            logicSelector.addEventListener('change', event => {
                this.handleFieldConditionLogicSelectionChange(event);
            });

            logicSelector.dataset.fieldConditionLogicBound = '1';
        },

        // Recomputes operator availability when a repeater logic mode changes.
        handleFieldConditionLogicSelectionChange(event) {
            const logicSelector = event?.target;

            if (!(logicSelector instanceof HTMLSelectElement)) {
                return;
            }

            const repeaterElement = this.getFieldConditionRepeaterFromLogicSelector(logicSelector);

            if (!repeaterElement) {
                return;
            }

            const previousLogic = String(repeaterElement.dataset.fieldConditionLogicState ?? '').trim().toLowerCase();
            const currentLogic = this.getFieldConditionRepeaterLogicValue(repeaterElement);
            const resetDuplicateOperators = previousLogic === 'or' && currentLogic === 'and';

            this.syncFieldConditionFieldSelectionState(repeaterElement, {
                resetDuplicateOperators,
            });

            repeaterElement.dataset.fieldConditionLogicState = currentLogic;
        },

        // Derives the condition type key from a conditions repeater id.
        getFieldConditionTypeFromRepeater(repeaterElement) {
            const repeaterId = String(repeaterElement?.id ?? '').trim();

            if (repeaterId === '' || !repeaterId.startsWith('field-conditions-')) {
                return '';
            }

            return repeaterId.replace('field-conditions-', '');
        },

        // Resolves the sibling repeater element associated with a logic selector.
        getFieldConditionRepeaterFromLogicSelector(logicSelector) {
            const logicId = String(logicSelector?.id ?? '').trim();

            if (logicId === '' || !logicId.startsWith('field-conditions-') || !logicId.endsWith('-logic')) {
                return null;
            }

            const conditionType = logicId.replace('field-conditions-', '').replace(/-logic$/, '');

            return document.getElementById(`field-conditions-${conditionType}`);
        },

        // Returns the current logic mode for a repeater, normalized to and/or.
        getFieldConditionRepeaterLogicValue(repeaterElement) {
            const conditionType = this.getFieldConditionTypeFromRepeater(repeaterElement);

            if (conditionType === '') {
                return 'and';
            }

            const logicSelector = document.getElementById(`field-conditions-${conditionType}-logic`);
            const logicValue = String(logicSelector?.value ?? 'and').trim().toLowerCase();

            return logicValue === 'or' ? 'or' : 'and';
        },

        // Applies a persisted rule value to the currently rendered row value control.
        setFieldConditionRowValueFromPersistedRule(rowElement, persistedValue) {
            if (!rowElement) {
                return;
            }

            const valueControl = rowElement.querySelector('td[data-field-name="value"] input, td[data-field-name="value"] select, td[data-field-name="value"] textarea');

            if (!valueControl || valueControl.disabled) {
                return;
            }

            if (valueControl.tagName === 'SELECT' && valueControl.multiple) {
                let values = [];

                if (Array.isArray(persistedValue)) {
                    values = persistedValue.map(value => String(value));
                } else if (typeof persistedValue === 'string' && persistedValue.trim() !== '') {
                    try {
                        const parsed = JSON.parse(persistedValue);
                        values = Array.isArray(parsed) ? parsed.map(value => String(value)) : [String(persistedValue)];
                    } catch (error) {
                        values = [String(persistedValue)];
                    }
                }

                Array.from(valueControl.options).forEach(option => {
                    option.selected = values.includes(String(option.value));
                });

                if (valueControl.tomselect) {
                    valueControl.tomselect.setValue(values, true);
                }

                return;
            }

            if (valueControl.tagName === 'SELECT') {
                const value = Array.isArray(persistedValue)
                    ? String(persistedValue[0] ?? '')
                    : String(persistedValue ?? '');

                valueControl.value = value;

                if (valueControl.tomselect) {
                    valueControl.tomselect.setValue(value, true);
                }

                return;
            }

            const scalarValue = (Array.isArray(persistedValue) || (persistedValue && typeof persistedValue === 'object'))
                ? JSON.stringify(persistedValue)
                : String(persistedValue ?? '');

            valueControl.value = scalarValue;
        },

        // Handles field selection changes for a condition row and keeps operator/value controls in sync.
        setFieldConditionsRow({ target }) {
            const conditionsRepeater = target.closest('.meros-field-conditions-repeater');
            const rowElement = target.closest('tr.meros-repeater-row');
            const field = this.getFieldsById()[target.value];
            const fieldType = field?.handle;

            if (!conditionsRepeater || !rowElement) {
                return;
            }

            if (!field || !fieldType) {
                this.setFieldConditionOperators(null, rowElement);
                this.setFieldConditionPlaceholderInput(rowElement);
                this.syncFieldConditionFieldSelectionState(conditionsRepeater, {
                    resetDuplicateOperators: false,
                });
                return;
            }

            const operatorSelector = this.setFieldConditionOperators(fieldType, rowElement);

            if (operatorSelector) {
                this.setFieldConditionOperatorRow({ target: operatorSelector });
            } else {
                this.setFieldConditionPlaceholderInput(rowElement);
            }

            this.syncFieldConditionFieldSelectionState(conditionsRepeater, {
                resetDuplicateOperators: false,
            });
        },

        // Handles operator selection changes and only enables a value control when needed.
        setFieldConditionOperatorRow({ target }) {
            const rowElement = target?.closest?.('tr.meros-repeater-row') ?? null;

            if (!rowElement) {
                return;
            }

            const fieldSelector = rowElement.querySelector('td[data-field-name="field_id"] select');
            const selectedFieldId = String(fieldSelector?.value ?? '').trim();
            const selectedOperator = String(target?.value ?? '').trim();

            if (selectedFieldId === '' || selectedOperator === '') {
                this.setFieldConditionPlaceholderInput(rowElement);
                return;
            }

            if (this.shouldDisableFieldConditionValueInput(selectedOperator)) {
                this.setFieldConditionPlaceholderInput(rowElement);
                return;
            }

            const field = this.getFieldsById()[selectedFieldId];
            const fieldType = field?.handle;

            if (!field || !fieldType) {
                this.setFieldConditionPlaceholderInput(rowElement);
                return;
            }

            this.setFieldConditionValueInput(field, fieldType, rowElement);

            const conditionsRepeater = rowElement.closest('.meros-field-conditions-repeater');

            if (conditionsRepeater) {
                this.syncFieldConditionFieldSelectionState(conditionsRepeater, {
                    resetDuplicateOperators: false,
                });
            }
        },

        // Re-syncs field-condition selection state after repeater mutations.
        syncFieldConditionsRepeaterSelectionState(params = {}) {
            const repeaterId = String(params?.repeaterId ?? '').trim();
            const triggerElement = params?.triggerElement ?? null;

            const repeaterElement = repeaterId !== ''
                ? document.getElementById(repeaterId)
                : triggerElement?.closest?.('.meros-repeater') ?? null;

            if (!repeaterElement) {
                return;
            }

            this.syncFieldConditionFieldSelectionState(repeaterElement, {
                resetDuplicateOperators: false,
            });
        },

        // Normalises a newly added condition row to the placeholder operator/value state.
        handleFieldConditionsRepeaterAddRow(params = {}) {
            const repeaterId = String(params?.repeaterId ?? '').trim();
            const triggerElement = params?.triggerElement ?? null;
            const rowIndex = Number(params?.rowIndex);

            const repeaterElement = repeaterId !== ''
                ? document.getElementById(repeaterId)
                : triggerElement?.closest?.('.meros-repeater') ?? null;

            if (!repeaterElement) {
                return;
            }

            if (Number.isInteger(rowIndex) && rowIndex >= 0) {
                const rowElement = repeaterElement.querySelector(`tr.meros-repeater-row[data-repeater-row-index="${rowIndex}"]`);

                if (rowElement) {
                    this.resetFieldConditionRowInputs(rowElement);
                }
            }

            this.syncFieldConditionFieldSelectionState(repeaterElement, {
                resetDuplicateOperators: false,
            });
        },

        // Resets operator and value controls for a condition row to a neutral, disabled state.
        resetFieldConditionRowInputs(rowElement) {
            if (!rowElement) {
                return;
            }

            this.setFieldConditionOperators(null, rowElement);
            this.setFieldConditionPlaceholderInput(rowElement);
        },

        // Renders a single disabled text input in the value cell as a placeholder.
        setFieldConditionPlaceholderInput(rowElement) {
            const valueCell = rowElement.querySelector('td[data-field-name="value"]');

            if (!valueCell) {
                return;
            }

            const { valueInputName, valueInputWrapper } = this.clearFieldConditionValueInput(valueCell);

            if (!valueInputWrapper) {
                return;
            }

            const resetInput = document.createElement('input');
            resetInput.type = 'text';
            resetInput.name = valueInputName;
            resetInput.value = '';
            resetInput.disabled = true;
            resetInput.setAttribute('aria-disabled', 'true');

            valueInputWrapper.appendChild(resetInput);
        },

        // Operators that do not require user-provided values.
        shouldDisableFieldConditionValueInput(operator) {
            const normalisedOperator = String(operator ?? '').trim();

            return normalisedOperator === 'is_empty' || normalisedOperator === 'is_not_empty';
        },

        // Finds the canonical input name so value control swaps preserve payload keys.
        getFieldConditionValueInputName(valueCell) {
            const namedControl = valueCell?.querySelector('input[name], select[name], textarea[name]');

            if (namedControl && typeof namedControl.name === 'string' && namedControl.name.trim() !== '') {
                return namedControl.name;
            }

            return '';
        },

        // Destroys any active TomSelect instances before value-cell DOM replacement.
        destroyFieldConditionTomSelect(valueCell) {
            if (!valueCell) {
                return;
            }

            const advancedSelects = Array.from(valueCell.querySelectorAll('select[data-advanced="true"], select.meros-select-field'));

            advancedSelects.forEach(selectElement => {
                if (selectElement?.tomselect) {
                    initTomSelect(selectElement, true);
                }
            });
        },

        // Fully clears value-cell controls and TomSelect artifacts before rebuilding.
        clearFieldConditionValueInput(valueCell) {
            const valueInputName = this.getFieldConditionValueInputName(valueCell);
            const valueInputWrapper = valueCell?.querySelector('.meros-field') ?? valueCell;

            this.destroyFieldConditionTomSelect(valueCell);

            valueInputWrapper?.querySelectorAll?.('.ts-wrapper, .ts-dropdown').forEach(element => {
                element.remove();
            });

            valueInputWrapper?.querySelectorAll?.('input, select, textarea').forEach(control => {
                control.remove();
            });

            return {
                valueInputName,
                valueInputWrapper,
            };
        },

        // Rebuilds the operator list for the selected field type and preserves prior valid selection.
        setFieldConditionOperators(fieldType, rowElement) {
            const operatorSelector = rowElement.querySelector('td[data-field-name="operator"] select');

            if (!operatorSelector) {
                return null;
            }

            const previousOperator = String(operatorSelector.value ?? '').trim();

            const operatorMap = (this.fieldConditionOperatorMap && typeof this.fieldConditionOperatorMap === 'object')
                ? this.fieldConditionOperatorMap
                : {};

            const operators = fieldType
                ? (operatorMap[fieldType] || ['equals', 'not_equals', 'is_empty', 'is_not_empty'])
                : [];

            // Clear existing options
            operatorSelector.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Select operator';
            operatorSelector.appendChild(placeholderOption);

            // Add new options
            operators.forEach(operator => {
                const option = document.createElement('option');
                option.value = operator;
                option.textContent = operator.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
                operatorSelector.appendChild(option);
            });

            if (previousOperator !== '' && operators.includes(previousOperator)) {
                operatorSelector.value = previousOperator;
            } else {
                operatorSelector.value = '';
            }

            return operatorSelector;
        },

        // Rebuilds the value control based on selected field type (including TomSelect-backed controls).
        setFieldConditionValueInput(field, fieldType, rowElement) {
            const valueCell = rowElement.querySelector('td[data-field-name="value"]');

            if (!valueCell) {
                return;
            }

            const { valueInputName, valueInputWrapper } = this.clearFieldConditionValueInput(valueCell);

            if (!valueInputWrapper) {
                return;
            }

            // Create a new input based on the field type
            let newInput;
            let setTomSelect = false;

            if (['select', 'advanced_select', 'radio'].includes(fieldType)) {
                newInput = document.createElement('select');
                newInput.name = valueInputName;

                const options = field?.properties?.options ?? {};
                Object.entries(options).forEach(([value, label]) => {
                    const optionEl = document.createElement('option');
                    optionEl.value = String(value);
                    optionEl.textContent = typeof label === 'string' ? label : String(label ?? value);
                    newInput.appendChild(optionEl);
                });
            }

            else if (['multi_select', 'checkboxes'].includes(fieldType)) {
                newInput = document.createElement('select');
                newInput.name = valueInputName;
                newInput.multiple = true;
                newInput.setAttribute('data-advanced', 'true');

                const options = field?.properties?.options ?? {};
                Object.entries(options).forEach(([value, label]) => {
                    const optionEl = document.createElement('option');
                    optionEl.value = String(value);
                    optionEl.textContent = typeof label === 'string' ? label : String(label ?? value);
                    newInput.appendChild(optionEl);
                });

                setTomSelect = true;
            }
            
            else {
                newInput = document.createElement('input');
                newInput.type = field.handle === 'range' ? 'number' : field.handle
                newInput.name = valueInputName;
            }

            valueInputWrapper.appendChild(newInput);

            if (setTomSelect) {
                initTomSelect(newInput);
            }
        },

        // Synchronises field selection state across rows in a repeater and applies operator uniqueness rules when needed.
        syncFieldConditionFieldSelectionState(conditionsRepeater, options = {}) {
            if (!conditionsRepeater) {
                return;
            }

            const selectors = Array.from(
                conditionsRepeater.querySelectorAll('tr.meros-repeater-row td[data-field-name="field_id"] select')
            );

            selectors.forEach(select => {
                Array.from(select.options).forEach(option => {
                    option.disabled = false;
                });
            });

            this.syncFieldConditionOperatorSelectionState(conditionsRepeater, options);
        },

        // Applies operator uniqueness rules when logic is and, including optional duplicate resets.
        syncFieldConditionOperatorSelectionState(conditionsRepeater, options = {}) {
            if (!conditionsRepeater) {
                return;
            }

            const resetDuplicateOperators = options?.resetDuplicateOperators === true;
            const operatorSelectors = Array.from(
                conditionsRepeater.querySelectorAll('tr.meros-repeater-row td[data-field-name="operator"] select')
            );

            if (operatorSelectors.length === 0) {
                return;
            }

            const logic = this.getFieldConditionRepeaterLogicValue(conditionsRepeater);

            if (logic !== 'and') {
                operatorSelectors.forEach(select => {
                    Array.from(select.options).forEach(option => {
                        option.disabled = false;
                    });
                });

                conditionsRepeater.dataset.fieldConditionLogicState = logic;
                return;
            }

            if (resetDuplicateOperators) {
                const seenOperators = new Set();

                operatorSelectors.forEach(select => {
                    const selectedOperator = String(select.value ?? '').trim();

                    if (selectedOperator === '') {
                        return;
                    }

                    const rowElement = select.closest('tr.meros-repeater-row');

                    if (seenOperators.has(selectedOperator)) {
                        select.value = '';

                        if (rowElement) {
                            this.setFieldConditionPlaceholderInput(rowElement);
                        }

                        return;
                    }

                    seenOperators.add(selectedOperator);
                });
            }

            const selectedCounts = operatorSelectors.reduce((counts, select) => {
                const selectedOperator = String(select.value ?? '').trim();

                if (selectedOperator !== '') {
                    counts[selectedOperator] = (counts[selectedOperator] ?? 0) + 1;
                }

                return counts;
            }, {});

            operatorSelectors.forEach(select => {
                const currentValue = String(select.value ?? '').trim();

                Array.from(select.options).forEach(option => {
                    const optionValue = String(option.value ?? '').trim();

                    if (optionValue === '') {
                        option.disabled = false;
                        return;
                    }

                    const selectedElsewhere = optionValue !== currentValue && (selectedCounts[optionValue] ?? 0) > 0;
                    option.disabled = selectedElsewhere;
                });
            });

            conditionsRepeater.dataset.fieldConditionLogicState = logic;
        },

        // Runs all field-condition validation passes and returns a combined result payload.
        validateFieldConditions(nextConditions) {
            const conflicts = [];

            this.collectIncompleteConditionRowConflicts(nextConditions, conflicts);
            this.collectOpposingConditionTypeConflicts(nextConditions, conflicts);
            this.collectInvalidConditionTypeCombinationConflicts(nextConditions, conflicts);

            return {
                valid: conflicts.length === 0,
                conflicts,
            };
        },

        // Locates the field-conditions validation container rendered in the canvas settings panel.
        getFieldConditionsErrorsContainer() {
            const container = document.getElementById('field-conditions-errors');

            return container instanceof HTMLElement ? container : null;
        },

        // Clears any previously rendered field-conditions validation messages.
        clearFieldConditionsValidationErrors() {
            const container = this.getFieldConditionsErrorsContainer();

            if (!container) {
                return;
            }

            container.innerHTML = '';
        },

        // Renders deduplicated validation messages into the field-conditions error container.
        renderFieldConditionsValidationErrors(conflicts = []) {
            const container = this.getFieldConditionsErrorsContainer();

            if (!container) {
                return;
            }

            if (!Array.isArray(conflicts) || conflicts.length === 0) {
                container.innerHTML = '';
                return;
            }

            const messageList = conflicts.map(conflict => {
                const reason = String(conflict?.reason ?? 'Validation conflict detected.');
                const pair = Array.isArray(conflict?.pair) ? conflict.pair : [];
                const leftType = String(pair[0] ?? conflict?.left?.conditionType ?? '').trim();
                const rightType = String(pair[1] ?? conflict?.right?.conditionType ?? '').trim();
                const leftRowNumber = Number(conflict?.left?.rowNumber);
                const rightRowNumber = Number(conflict?.right?.rowNumber);

                const leftTypeLabel = leftType !== '' ? leftType.replace(/_/g, ' ') : 'left condition';
                const rightTypeLabel = rightType !== '' ? rightType.replace(/_/g, ' ') : '';

                const leftDetails = Number.isInteger(leftRowNumber) && leftRowNumber > 0
                    ? `${leftTypeLabel} row ${leftRowNumber}`
                    : leftTypeLabel;

                const hasRightSide = rightTypeLabel !== ''
                    || (Number.isInteger(rightRowNumber) && rightRowNumber > 0);

                const rightDetails = hasRightSide
                    ? (Number.isInteger(rightRowNumber) && rightRowNumber > 0
                        ? `${rightTypeLabel !== '' ? rightTypeLabel : 'right condition'} row ${rightRowNumber}`
                        : (rightTypeLabel !== '' ? rightTypeLabel : 'right condition'))
                    : '';

                const rowDetails = hasRightSide
                    ? `${leftDetails} vs ${rightDetails}`
                    : leftDetails;

                return `${reason} (${rowDetails}).`;
            });

            const uniqueMessages = Array.from(new Set(messageList));
            const itemsHtml = uniqueMessages
                .map(message => `<li>${this.escapeFieldConditionsErrorHtml(message)}</li>`)
                .join('');

            container.innerHTML = `
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert" aria-live="polite">
                    <p class="font-semibold">Field conditions could not be saved:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        ${itemsHtml}
                    </ul>
                </div>
            `;
        },

        // Escapes dynamic validation strings before interpolating into error HTML.
        escapeFieldConditionsErrorHtml(value) {
            const text = String(value ?? '');
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            };

            return text.replace(/[&<>"']/g, match => map[match] ?? match);
        },

        // Detects rule-level conflicts between opposing condition-type pairs.
        collectOpposingConditionTypeConflicts(nextConditions, conflicts) {
            const pairings = [
                ['show', 'hide'],
                ['require', 'optional'],
                ['enable', 'disable'],
            ];

            pairings.forEach(([leftType, rightType]) => {
                const leftRules = this.getValidatedFieldConditionRules(nextConditions, leftType);
                const rightRules = this.getValidatedFieldConditionRules(nextConditions, rightType);

                if (leftRules.length === 0 || rightRules.length === 0) {
                    return;
                }

                const rightRulesBySignature = rightRules.reduce((rulesBySignature, rowData) => {
                    const signature = this.makeFieldConditionRuleSignature(rowData.rule);

                    if (signature === '') {
                        return rulesBySignature;
                    }

                    if (!Array.isArray(rulesBySignature[signature])) {
                        rulesBySignature[signature] = [];
                    }

                    rulesBySignature[signature].push(rowData);
                    return rulesBySignature;
                }, {});

                const rightRulesByFieldId = rightRules.reduce((rulesByFieldId, rowData) => {
                    const fieldId = this.getFieldConditionRuleFieldId(rowData.rule);

                    if (fieldId === '') {
                        return rulesByFieldId;
                    }

                    if (!Array.isArray(rulesByFieldId[fieldId])) {
                        rulesByFieldId[fieldId] = [];
                    }

                    rulesByFieldId[fieldId].push(rowData);
                    return rulesByFieldId;
                }, {});

                leftRules.forEach(leftRowData => {
                    const leftSignature = this.makeFieldConditionRuleSignature(leftRowData.rule);

                    if (leftSignature !== '') {
                        const matchingRightRows = rightRulesBySignature[leftSignature] ?? [];

                        matchingRightRows.forEach(rightRowData => {
                            conflicts.push({
                                reason: 'Opposing condition types contain identical rule definitions.',
                                pair: [leftType, rightType],
                                left: leftRowData,
                                right: rightRowData,
                            });
                        });
                    }

                    const leftFieldId = this.getFieldConditionRuleFieldId(leftRowData.rule);

                    if (leftFieldId === '') {
                        return;
                    }

                    const matchingFieldRows = rightRulesByFieldId[leftFieldId] ?? [];

                    matchingFieldRows.forEach(rightRowData => {
                        conflicts.push({
                            reason: 'Opposing condition types target the same field.',
                            pair: [leftType, rightType],
                            left: leftRowData,
                            right: rightRowData,
                        });
                    });
                });
            });
        },

        // Validates each condition row has required operator/value inputs based on operator semantics.
        collectIncompleteConditionRowConflicts(nextConditions, conflicts) {
            const conditionTypes = Object.keys(nextConditions ?? {});

            conditionTypes.forEach(conditionType => {
                const rows = this.getValidatedFieldConditionRules(nextConditions, conditionType);

                rows.forEach(rowData => {
                    const operator = String(rowData?.rule?.operator ?? '').trim();

                    if (operator === '') {
                        conflicts.push({
                            reason: 'Each condition row must have an operator selected.',
                            pair: [conditionType],
                            left: rowData,
                            mode: 'incomplete_condition_row',
                        });
                        return;
                    }

                    if (this.shouldDisableFieldConditionValueInput(operator)) {
                        return;
                    }

                    if (!this.isFieldConditionRuleValueProvided(rowData.rule?.value)) {
                        conflicts.push({
                            reason: 'Each condition row must include a value for the selected operator.',
                            pair: [conditionType],
                            left: rowData,
                            mode: 'incomplete_condition_row',
                        });
                    }
                });
            });
        },

        // Enforces higher-level invalid condition-type combinations (for example hidden + required).
        collectInvalidConditionTypeCombinationConflicts(nextConditions, conflicts) {
            const invalidPairs = [
                {
                    leftType: 'hide',
                    rightType: 'require',
                    reason: 'A field cannot be required while hidden.',
                },
                {
                    leftType: 'disable',
                    rightType: 'require',
                    reason: 'A field cannot be required while disabled.',
                },
            ];

            invalidPairs.forEach(({ leftType, rightType, reason }) => {
                const leftRules = this.getValidatedFieldConditionRules(nextConditions, leftType);
                const rightRules = this.getValidatedFieldConditionRules(nextConditions, rightType);

                if (leftRules.length === 0 || rightRules.length === 0) {
                    return;
                }

                conflicts.push({
                    reason,
                    pair: [leftType, rightType],
                    left: leftRules[0],
                    right: rightRules[0],
                    mode: 'invalid_condition_type_combination',
                });
            });
        },

        // Normalizes condition rules into row metadata used by validator passes.
        getValidatedFieldConditionRules(nextConditions, conditionType) {
            const rules = nextConditions?.[conditionType]?.rules;

            if (!Array.isArray(rules) || rules.length === 0) {
                return [];
            }

            return rules
                .map((rule, index) => ({
                    conditionType,
                    rowIndex: index,
                    rowNumber: index + 1,
                    rule,
                }))
                .filter(rowData => rowData.rule && typeof rowData.rule === 'object');
        },

            // Builds a stable, comparable signature for rule conflict matching.
        makeFieldConditionRuleSignature(rule) {
            if (!rule || typeof rule !== 'object') {
                return '';
            }

            const fieldId = this.getFieldConditionRuleFieldId(rule);
            const operator = String(rule.operator ?? '').trim();

            if (fieldId === '' || operator === '') {
                return '';
            }

            if (this.shouldDisableFieldConditionValueInput(operator)) {
                return `${fieldId}::${operator}::`;
            }

            const value = this.normaliseFieldConditionRuleValue(rule.value);

            return `${fieldId}::${operator}::${value}`;
        },

        // Extracts and normalizes the rule field_id used for cross-type comparisons.
        getFieldConditionRuleFieldId(rule) {
            if (!rule || typeof rule !== 'object') {
                return '';
            }

            return String(rule.field_id ?? '').trim();
        },

        // Canonicalizes rule values so semantically equal values compare consistently.
        normaliseFieldConditionRuleValue(value) {
            if (Array.isArray(value)) {
                return value.map(item => String(item)).sort().join('|');
            }

            if (value && typeof value === 'object') {
                const orderedObject = Object.keys(value)
                    .sort()
                    .reduce((result, key) => {
                        result[key] = value[key];
                        return result;
                    }, {});

                return JSON.stringify(orderedObject);
            }

            return String(value ?? '').trim();
        },

        // Determines whether a rule value should be treated as present for validation.
        isFieldConditionRuleValueProvided(value) {
            if (Array.isArray(value)) {
                return value.length > 0;
            }

            if (value && typeof value === 'object') {
                return Object.keys(value).length > 0;
            }

            return String(value ?? '').trim() !== '';
        },

        // ======================================
        // Action Configuration Handlers
        // ======================================

        // Calls the configured action updater callback with the current actions from the store to persist them outside of the store.
        saveActions() {
            const storeRef = Alpine.store('formBuilder');

            if (!storeRef.actionsUpdater) {
                return;
            }

            const repeaterStore = Alpine.store('repeaterField');
            const value = repeaterStore ? repeaterStore.getRepeaterValue('meros-form-actions-repeater') : null;

            if (!Array.isArray(value)) {
                storeRef.actionsUpdater(storeRef.actions);
                return;
            }

            const nextActions = {};

            value.forEach((actionEntry, index) => {
                const actionType = String(actionEntry?.action_type ?? '').trim();
                const actionId = String(actionEntry?.action_id ?? '').trim();

                if (actionType === '') {
                    return;
                }

                const uniqueHandle = storeRef.getActionUniqueHandle(actionType, actionId, index);
                const parsedActionId = String(uniqueHandle.split('__').slice(1).join('__') ?? '').trim();
                const storedActionId = actionId !== '' ? actionId : parsedActionId;
                const existingConfig = storeRef.actions?.[uniqueHandle]?.config;

                nextActions[uniqueHandle] = {
                    label: typeof actionEntry?.action_label === 'string' ? actionEntry.action_label : '',
                    config: (existingConfig && typeof existingConfig === 'object' && !Array.isArray(existingConfig))
                        ? { ...existingConfig }
                        : {},
                    action_id: storedActionId,
                };
            });

            storeRef.actions = nextActions;
            storeRef.actionsUpdater(nextActions);
        },

        // Builds a stable unique action key from action type + action id.
        getActionUniqueHandle(actionType, actionId, fallbackIndex = null) {
            const type = String(actionType ?? '').trim();
            const id = String(actionId ?? '').trim();

            if (type === '') {
                return '';
            }

            if (id !== '') {
                return `${type}__${id}`;
            }

            return Number.isInteger(fallbackIndex)
                ? `${type}__${fallbackIndex}`
                : type;
        },

        // Handles action-row additions from repeater callbacks.
        onActionRowAdded(params = {}) {
            const repeaterId = typeof params?.repeaterId === 'string' ? params.repeaterId : '';
            const rowIndex = Number.isInteger(params?.rowIndex) ? params.rowIndex : null;
            const repeaterStore = Alpine.store('repeaterField');

            if (repeaterId !== '' && rowIndex !== null && typeof repeaterStore?.setCellValue === 'function') {
                const randomDigits = String(Math.floor(Math.random() * 100000000)).padStart(8, '0');
                repeaterStore.setCellValue(repeaterId, rowIndex, 'action_id', `action_${randomDigits}`);
            }

            this.saveActions();
        },

        // Handles action-row removals from repeater callbacks.
        onActionRowRemoved(params = {}) {
            this.saveActions();
        },

        // Handles action-row reordering from repeater callbacks.
        onActionRowMoved(params = {}) {
            this.saveActions();
        },

        // Handles action-row configure callbacks from repeater rows.
        async onActionRowConfigure(params = null) {
            if (typeof this.actionConfigCallback !== 'function') {
                return;
            }

            // Get the action handle
            const resolvedActionHandle = typeof params === 'string'
                ? String(params).split('__')[0]
                : params?.rowValue?.action_type;

            // Get the action label
            const resolvedActionLabel = typeof params === 'string'
                ? (this.actions?.[params]?.label ?? '')
                : params?.rowValue?.action_label || '';

            const resolvedActionId = typeof params === 'string'
                ? String(this.actions?.[params]?.action_id ?? params.split('__').slice(1).join('__') ?? '').trim()
                : String(params?.rowValue?.action_id ?? '').trim();

            const resolvedRowIndex = Number.isInteger(params?.rowIndex)
                ? params.rowIndex
                : null;

            // Bail if we don't have a valid action handle to work with
            if (typeof resolvedActionHandle !== 'string' || resolvedActionHandle.trim() === '') {
                return;
            }

            // Use a stable per-row key so reordering rows doesn't remap configs.
            const actionHandle = resolvedActionHandle;
            const uniqueHandle = this.getActionUniqueHandle(actionHandle, resolvedActionId, resolvedRowIndex);

            if (uniqueHandle === '') {
                return;
            }

            // Get the current configuration for this action from the store.
            const actionEntry = (this.actions && typeof this.actions === 'object' && !Array.isArray(this.actions))
                ? this.actions[uniqueHandle]
                : null;
            const currentConfig = (actionEntry && typeof actionEntry === 'object' && !Array.isArray(actionEntry))
                ? { ...(actionEntry.config ?? {}) }
                : {};

            // Store the context for this action configuration to be used in future updates to the dialog content.
            this.actionConfigContext = {
                actionHandle,
                uniqueHandle,
                rowIndex: resolvedRowIndex,
            };

            // Generate the dialog content by calling the configured callback with the action handle, field labels, and current configuration values for this action instance.
            const dialogContent = await this.actionConfigCallback(uniqueHandle, this.getFieldLabelsById(), currentConfig);

            // Parse the returned dialog content
            const html = typeof dialogContent === 'string'
                ? dialogContent
                : (typeof dialogContent?.html === 'string' ? dialogContent.html : '');

            // Bail if we don't have a valid HTML string to show in the dialog
            if (typeof html !== 'string' || html.trim() === '') {
                return;
            }

            // Initialise the repeater field for managing form actions
            const repeaterFieldStore = Alpine.store('repeaterField');

            if (typeof repeaterFieldStore?.openRepeaterDialogFromHtml !== 'function') {
                return;
            }

            // Callback after the dialog is submitted to update the action configuration values in the store and trigger the action updater callback.
            this.actionConfigDialog = repeaterFieldStore.openRepeaterDialogFromHtml(html, async ({ dialog, shell, body }) => {
                const nextActions = (this.actions && typeof this.actions === 'object' && !Array.isArray(this.actions))
                    ? { ...this.actions }
                    : {};

                nextActions[uniqueHandle] = {
                    label: resolvedActionLabel,
                    config: this.getActionConfigurationDialogGroupFieldValues(dialog),
                    action_id: resolvedActionId,
                };

                this.actions = nextActions;
                this.actionsUpdater?.(nextActions);

                return true;
            }, 'form-action-configuration-dialog');

            if (this.actionConfigDialog instanceof HTMLDialogElement) {
                this.actionConfigDialog.addEventListener('close', () => {
                    this.actionConfigDialog = null;
                }, { once: true });
            }
        },

        // Retrieves the configuration dialog content for a given action handle, using the configured callback.
        async getActionConfigurationDialog(params = null) {
            return this.onActionRowConfigure(params);
        },

        // Refreshes the content of the currently open action configuration dialog, if it exists, by re-calling the configuration callback with the current context and updated field values from the dialog.
        refreshActionConfigurationDialog() {
            const formBuilderStore = Alpine.store('formBuilder');
            const storeRef = formBuilderStore && typeof formBuilderStore === 'object'
                ? formBuilderStore
                : this;

            if (typeof storeRef?.actionConfigCallback !== 'function') {
                return;
            }

            const dialog = storeRef.actionConfigDialog instanceof HTMLDialogElement
                ? storeRef.actionConfigDialog
                : document.getElementById('form-action-configuration-dialog');
            if (!dialog) {
                return;
            }

            const context = storeRef.actionConfigContext;

            if (!context?.actionHandle || !context?.uniqueHandle) {
                return;
            }

            const body = dialog.querySelector('.meros-repeater-config-dialog__body');

            if (!body) {
                return;
            }

            const storedConfig = (storeRef.actions && typeof storeRef.actions === 'object' && !Array.isArray(storeRef.actions))
                ? { ...(storeRef.actions[context.uniqueHandle]?.config ?? {}) }
                : {};
            const dialogConfig = storeRef.getActionConfigurationDialogGroupFieldValues(dialog);
            const currentConfig = {
                ...storedConfig,
                ...dialogConfig,
            };

            Promise.resolve(storeRef.actionConfigCallback(context.uniqueHandle, storeRef.getFieldLabelsById(), currentConfig))
                .then(dialogContent => {
                    const html = typeof dialogContent === 'string'
                        ? dialogContent
                        : (typeof dialogContent?.html === 'string' ? dialogContent.html : '');

                    if (typeof html !== 'string' || html.trim() === '') {
                        return;
                    }

                    body.innerHTML = html;

                    const firstField = body.querySelector('input, select, textarea, button');

                    if (firstField instanceof HTMLElement) {
                        firstField.focus();
                    }
                })
                .catch(() => {
                    // Ignore refresh failures and keep the current dialog content.
                });
        },

        // Parses the field values from the action configuration dialog and returns them as an object.
        getActionConfigurationDialogGroupFieldValues(dialog) {
            if (!dialog) {
                return {};
            }

            const fieldGroup = dialog.querySelector('.meros-form-group');
            if (!fieldGroup) {
                return {};
            }

            const fields = fieldGroup.querySelectorAll('.meros-field');
            const values = {};

            fields.forEach(field => {
                if (field.closest('.meros-repeater-field')) {
                    return;
                }

                const input = field.querySelector('[data-field-type]');
                
                if (!input) {
                    return;
                }

                const name = input.classList.contains('meros-repeater-field') 
                    ? input.getAttribute('id') 
                    : input.getAttribute('name');

                const fieldType = input.getAttribute('data-field-type');
                
                if (!name || !fieldType) {
                    return;
                }

                if (this.isInputField(fieldType)) {
                    values[name] = input.value;
                }

                if (fieldType === 'radio') {
                    const checked = field.querySelector('input[type="radio"]:checked');
                    if (checked) {
                        values[name] = checked.value;
                    }
                }

                if (fieldType === 'checkbox') {
                    const checked = field.querySelector('input[type="checkbox"]:checked');
                    if (checked) {
                        values[name] = checked.value ? true : false;
                    }
                }

                if (fieldType === 'checkboxes') {
                    const checked = field.querySelectorAll('input[type="checkbox"]:checked');
                    if (checked) {
                        values[name] = Array.from(checked).map(c => c.value);
                    }
                }

                if (fieldType === 'select') {
                    const select = field.querySelector('select');
                    if (select) {
                        const selectedOption = select.options[select.selectedIndex];
                        if (selectedOption) {
                            values[name] = selectedOption.value;
                        }
                    }
                }

                if (fieldType === 'multi_select' || fieldType === 'advanced_select') {
                    const select = field.querySelector('select');
                    if (select && select.tomselect) {
                        values[name] = select.tomselect.getValue();
                    }
                }

                if (fieldType === 'repeater') {
                    values[name] = Alpine.store('repeaterField').getRepeaterValue(name);
                }
            });

            return values;
        }
    };

    Alpine.store('formBuilder', store);

    const formStore = Alpine.store('formStore');

    if (typeof formStore?.registerCallbacks === 'function') {
        formStore.registerCallbacks({
            '$store.formBuilder.saveActions': store.saveActions.bind(store),
            '$store.formBuilder.refreshActionConfigurationDialog': store.refreshActionConfigurationDialog.bind(store),
            '$store.formBuilder.onActionRowAdded': store.onActionRowAdded.bind(store),
            '$store.formBuilder.onActionRowRemoved': store.onActionRowRemoved.bind(store),
            '$store.formBuilder.onActionRowMoved': store.onActionRowMoved.bind(store),
            '$store.formBuilder.onActionRowConfigure': store.onActionRowConfigure.bind(store),
            '$store.formBuilder.getActionConfigurationDialog': store.getActionConfigurationDialog.bind(store),
        });
    }
}
export function registerFormBuilderStore() {
    const store = {
        rowsUpdater: null,
        rows: [],
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

        // Sets the row updater callback
        setRowsUpdater(updater) {
            this.rowsUpdater = typeof updater === 'function' ? updater : null;
        },

        // Sets the rows object
        setRows(rows) {
            this.rows = rows;
        },

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

        // Commits y
        commitRows(mutator) {
            const nextRows = this.cloneRows();
            mutator(nextRows);
            this.setRows(nextRows);

            if (this.rowsUpdater) {
                this.rowsUpdater(nextRows);
            }
        },

        makeId() {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }

            return `id-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
        },

        humanizeHandle(handle) {
            return String(handle ?? '')
                .replace(/[-_]+/g, ' ')
                .trim()
                .replace(/\b\w/g, letter => letter.toUpperCase());
        },

        slugify(value) {
            return String(value ?? '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        },

        createFieldPayload(handle) {
            const safeHandle = String(handle ?? '').trim();
            const label = this.fieldLabel ?? this.itemLabel ?? this.humanizeHandle(safeHandle) ?? 'Field';

            const payload = {
                handle: safeHandle,
                properties: {
                    id: this.makeId(),
                    label,
                    name: this.slugify(label) || this.slugify(safeHandle) || this.makeId(),
                    helpText: '',
                    helpTextPosition: 'bottom',
                    value: '',
                    required: false,
                    disabled: false,
                    width: 'full',
                },
            };

            // Make a default field payload for repeaters.
            if (safeHandle === 'repeater') {
                payload.properties.rows = [];
                payload.fields = [
                    {
                        handle: 'text', properties: {
                            'label': 'Text',
                            id: this.makeId(),
                            name: 'text'
                        }
                    },
                    {
                        handle: 'number', properties: {
                            'label': 'Number',
                            id: this.makeId(),
                            name: 'number'
                        }
                    },
                    {
                        handle: 'checkbox', properties: {
                            'label': 'Checkbox',
                            id: this.makeId(),
                            name: 'checkbox'
                        }
                    }
                ];
            }

            // Adjust payload for selection-based fields.
            if (safeHandle === 'select' || safeHandle === 'radio' || safeHandle === 'checkboxes' || safeHandle === 'multiselect') {
                payload.properties.options = [];
            }

            // Adjust payload for multi-value fields.
            if (safeHandle === 'multiselect' || safeHandle === 'checkboxes') {
                payload.properties.value = [];
            }

            // Adjust payload for text-based fields.
            if (safeHandle === 'text' || safeHandle === 'email' || safeHandle === 'tel' || safeHandle === 'url' || safeHandle === 'number') {
                payload.properties.placeholder = '';
            }

            return payload;
        },

        createFieldsRow(fields = []) {
            return {
                _type: 'fields',
                fields,
            };
        },

        createGroupRow(handle = '') {
            const title = this.itemLabel ?? this.humanizeHandle(handle);

            return {
                _type: 'group',
                group: {
                    id: this.makeId(),
                    handle,
                    title: title || 'Untitled Section',
                    description: '',
                    rows: [],
                },
            };
        },

        getTopRow(rows, rowIndex) {
            const row = rows[rowIndex];

            if (!row || row._type === 'group') {
                return null;
            }

            if (!Array.isArray(row.fields)) {
                row.fields = [];
            }

            row._type = 'fields';

            return row;
        },

        getGroup(rows, groupRowIndex) {
            const row = rows[groupRowIndex];

            if (!row || row._type !== 'group' || typeof row.group !== 'object' || row.group === null) {
                return null;
            }

            if (!Array.isArray(row.group.rows)) {
                row.group.rows = [];
            }

            return row.group;
        },

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

        insertFieldAsNewTopRow(rows, targetRowIndex, field) {
            const insertIndex = this.resolveIndex(rows.length, (targetRowIndex ?? -1) + 1, rows.length);
            rows.splice(insertIndex, 0, this.createFieldsRow([field]));
        },

        insertFieldAsNewGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, field) {
            const targetGroup = this.getGroup(rows, targetGroupRowIndex);

            if (!targetGroup) {
                return;
            }

            const insertIndex = this.resolveIndex(targetGroup.rows.length, (targetInnerRowIndex ?? -1) + 1, targetGroup.rows.length);
            targetGroup.rows.splice(insertIndex, 0, { fields: [field] });
        },

        insertFieldIntoGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, field) {
            const targetGroup = this.getGroup(rows, targetGroupRowIndex);

            if (!targetGroup) {
                return;
            }

            const { row: targetInnerRow } = this.ensureGroupInnerRow(targetGroup, targetInnerRowIndex);
            const insertIndex = this.resolveIndex(targetInnerRow.fields.length, targetFieldIndex, targetInnerRow.fields.length);
            targetInnerRow.fields.splice(insertIndex, 0, field);
        },

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

        // -----------------------------------------------------------------
        // Drag lifecycle handlers
        // -----------------------------------------------------------------
        startDrag(kind, handle, label) {
            this.isDragging = true;
            this.isCanvasDrag = false;
            this.itemKind = kind;
            this.itemHandle = handle;
            this.itemLabel = label;
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
        },

        // -----------------------------------------------------------------
        // Drag/Drop parsing helpers
        // -----------------------------------------------------------------

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

        // -----------------------------------------------------------------
        // Visual helpers
        // -----------------------------------------------------------------

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

        // -----------------------------------------------------------------
        // Canvas insertion handlers
        // -----------------------------------------------------------------

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

                        if (!sourceRow || sourceRow._type !== 'group') {
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

        // -----------------------------------------------------------------
        // Group row handlers
        // -----------------------------------------------------------------
        
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

                    this.insertFieldAsNewGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, extracted.field);
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

        // -----------------------------------------------------------------
        // Field insertion handlers
        // -----------------------------------------------------------------
        
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

                    this.insertFieldIntoGroupRow(rows, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, extracted.field);
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

                    if (this.sourceRowIndex < adjustedTargetRowIndex) {
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

        // -----------------------------------------------------------------
        // Field removal handlers
        // -----------------------------------------------------------------

        // Remove a top-level group row.
        removeGroup(groupRowIndex) {
            this.commitRows(rows => {
                if (!Number.isInteger(groupRowIndex) || groupRowIndex < 0 || groupRowIndex >= rows.length) {
                    return;
                }

                const row = rows[groupRowIndex];

                if (!row || row._type !== 'group') {
                    return;
                }

                rows.splice(groupRowIndex, 1);
            });
        },
        
        // Handle field removals from the canvas or groups.
        removeField(sourceRowIndex, sourceFieldIndex, sourceGroupRowIndex = null, sourceGroupInnerRowIndex = null) {
            this.commitRows(rows => {
                if (sourceGroupRowIndex !== null && sourceGroupInnerRowIndex !== null) {
                    this.extractGroupField(rows, sourceGroupRowIndex, sourceGroupInnerRowIndex, sourceFieldIndex);
                } else {
                    this.extractTopField(rows, sourceRowIndex, sourceFieldIndex);
                }
            });
        },
    };

    Alpine.store('formBuilder', store);
}

export function registerRepeaterFieldStore() {
    const store = {
        parseIndex(value) {
            if (value === null || value === undefined || value === '' || value === 'null') {
                return null;
            }

            const parsedValue = Number(value);

            return Number.isInteger(parsedValue) ? parsedValue : null;
        },

        getFormBuilderStore() {
            return Alpine.store('formBuilder') ?? null;
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

        startRowDrag(event, rowIndex) {
            const safeRowIndex = this.parseIndex(rowIndex);

            if (!event?.dataTransfer || safeRowIndex === null) {
                return;
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-meros-field-repeater-row', String(safeRowIndex));
            event.dataTransfer.setData('text/plain', String(safeRowIndex));
        },

        getRowIndexFromTransfer(event) {
            if (!event?.dataTransfer) {
                return null;
            }

            const transferTypes = [
                'application/x-meros-field-repeater-row',
                'text/plain',
            ];

            for (const type of transferTypes) {
                const value = Number(event.dataTransfer.getData(type));

                if (!Number.isNaN(value)) {
                    return value;
                }
            }

            return null;
        },

        normalizeLocation(rowIndex, fieldIndex, groupRowIndex) {
            return {
                rowIndex: this.parseIndex(rowIndex),
                fieldIndex: this.parseIndex(fieldIndex),
                groupRowIndex: this.parseIndex(groupRowIndex),
            };
        },

        resolveRepeaterField(rows, location) {
            const { rowIndex, fieldIndex, groupRowIndex } = location;

            if (fieldIndex === null) {
                return null;
            }

            let fieldPayload = null;

            if (groupRowIndex !== null) {
                const topRow = rows[groupRowIndex];

                if (!topRow || topRow._type !== 'group' || !topRow.group || !Array.isArray(topRow.group.rows)) {
                    return null;
                }

                if (rowIndex === null) {
                    return null;
                }

                const groupInnerRow = topRow.group.rows[rowIndex];

                if (!groupInnerRow || !Array.isArray(groupInnerRow.fields)) {
                    return null;
                }

                fieldPayload = groupInnerRow.fields[fieldIndex] ?? null;
            } else {
                if (rowIndex === null) {
                    return null;
                }

                const topRow = rows[rowIndex];

                if (!topRow || topRow._type === 'group' || !Array.isArray(topRow.fields)) {
                    return null;
                }

                fieldPayload = topRow.fields[fieldIndex] ?? null;
            }

            if (!fieldPayload || fieldPayload.handle !== 'repeater') {
                return null;
            }

            if (!fieldPayload.properties || typeof fieldPayload.properties !== 'object') {
                fieldPayload.properties = {};
            }

            if (!Array.isArray(fieldPayload.properties.value)) {
                fieldPayload.properties.value = [];
            }

            return fieldPayload;
        },

        extractRowValue(cellElement, event) {
            const target = event?.target;

            if (!target) {
                return null;
            }

            if (target.type === 'checkbox') {
                const checkboxInputs = cellElement
                    ? Array.from(cellElement.querySelectorAll('input[type="checkbox"]'))
                    : [];

                if (checkboxInputs.length > 1) {
                    return checkboxInputs
                        .filter(checkboxInput => checkboxInput.checked)
                        .map(checkboxInput => checkboxInput.value);
                }

                return target.checked;
            }

            if (target.type === 'radio') {
                const checkedRadio = cellElement
                    ? cellElement.querySelector('input[type="radio"]:checked')
                    : null;

                return checkedRadio ? checkedRadio.value : null;
            }

            if (target.multiple) {
                return Array.from(target.options)
                    .filter(option => option.selected)
                    .map(option => option.value);
            }

            return target.value;
        },

        createRowKey() {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return `rk-${crypto.randomUUID()}`;
            }

            return `rk-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
        },

        createEmptyRepeaterRow(fieldPayload) {
            const row = {
                __rowKey: this.createRowKey(),
            };

            const columns = Array.isArray(fieldPayload.fields) ? fieldPayload.fields : [];

            columns.forEach(column => {
                const columnName = column?.properties?.name;

                if (typeof columnName === 'string' && columnName.trim() !== '') {
                    row[columnName] = null;
                }
            });

            return row;
        },

        addRow(rowIndex, fieldIndex, groupRowIndex = null) {
            const formBuilder = this.getFormBuilderStore();

            if (!formBuilder) {
                return;
            }

            const location = this.normalizeLocation(rowIndex, fieldIndex, groupRowIndex);

            formBuilder.commitRows(rows => {
                const repeaterField = this.resolveRepeaterField(rows, location);

                if (!repeaterField) {
                    return;
                }

                repeaterField.properties.value.push(this.createEmptyRepeaterRow(repeaterField));
            });
        },

        removeRow(rowIndex, fieldIndex, groupRowIndex = null, repeaterRowIndex = null) {
            const formBuilder = this.getFormBuilderStore();

            if (!formBuilder) {
                return;
            }

            const location = this.normalizeLocation(rowIndex, fieldIndex, groupRowIndex);
            const safeRepeaterRowIndex = this.parseIndex(repeaterRowIndex);

            if (safeRepeaterRowIndex === null) {
                return;
            }

            formBuilder.commitRows(rows => {
                const repeaterField = this.resolveRepeaterField(rows, location);

                if (!repeaterField) {
                    return;
                }

                if (safeRepeaterRowIndex < 0 || safeRepeaterRowIndex >= repeaterField.properties.value.length) {
                    return;
                }

                repeaterField.properties.value.splice(safeRepeaterRowIndex, 1);
            });
        },

        updateRowValue(rowIndex, fieldIndex, groupRowIndex = null, repeaterRowIndex = null, fieldName = '', cellElement = null, event = null) {
            const formBuilder = this.getFormBuilderStore();

            if (!formBuilder) {
                return;
            }

            const location = this.normalizeLocation(rowIndex, fieldIndex, groupRowIndex);
            const safeRepeaterRowIndex = this.parseIndex(repeaterRowIndex);

            if (safeRepeaterRowIndex === null || typeof fieldName !== 'string' || fieldName.trim() === '') {
                return;
            }

            const value = this.extractRowValue(cellElement, event);

            formBuilder.commitRows(rows => {
                const repeaterField = this.resolveRepeaterField(rows, location);

                if (!repeaterField) {
                    return;
                }

                const repeaterRows = repeaterField.properties.value;

                if (safeRepeaterRowIndex < 0 || safeRepeaterRowIndex >= repeaterRows.length) {
                    return;
                }

                const existingRow = repeaterRows[safeRepeaterRowIndex];

                if (!existingRow || typeof existingRow !== 'object') {
                    return;
                }

                existingRow[fieldName] = value;
            });
        },

        handleRowGapDragOver(gapElement) {
            this.showRowGap(gapElement);
        },

        handleRowGapDrop(event, gapElement, rowIndex, fieldIndex, groupRowIndex = null, targetIndex = null) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getRowIndexFromTransfer(event);
            const safeTargetIndex = this.parseIndex(targetIndex);

            if (sourceIndex === null || safeTargetIndex === null) {
                return;
            }

            this.moveRow(rowIndex, fieldIndex, groupRowIndex, sourceIndex, safeTargetIndex);
        },

        moveRow(rowIndex, fieldIndex, groupRowIndex = null, sourceIndex = null, targetIndex = null) {
            const formBuilder = this.getFormBuilderStore();

            if (!formBuilder) {
                return;
            }

            const location = this.normalizeLocation(rowIndex, fieldIndex, groupRowIndex);
            const safeSourceIndex = this.parseIndex(sourceIndex);
            const safeTargetIndex = this.parseIndex(targetIndex);

            if (safeSourceIndex === null || safeTargetIndex === null) {
                return;
            }

            formBuilder.commitRows(rows => {
                const repeaterField = this.resolveRepeaterField(rows, location);

                if (!repeaterField) {
                    return;
                }

                const repeaterRows = repeaterField.properties.value;

                if (safeSourceIndex < 0 || safeSourceIndex >= repeaterRows.length) {
                    return;
                }

                const [movingRow] = repeaterRows.splice(safeSourceIndex, 1);

                if (!movingRow) {
                    return;
                }

                let insertAt = Math.max(0, Math.min(safeTargetIndex, repeaterRows.length + 1));

                if (safeTargetIndex > safeSourceIndex) {
                    insertAt -= 1;
                }

                insertAt = Math.max(0, Math.min(insertAt, repeaterRows.length));
                repeaterRows.splice(insertAt, 0, movingRow);
            });
        },
    };

    Alpine.store('repeaterField', store);
}
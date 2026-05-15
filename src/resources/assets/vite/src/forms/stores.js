/**
 * Register an Alpine store that tracks the currently dragged field's type and label.
 * The store is used by the form builder sidebar (@dragstart) and canvas drop zones (@drop).
 */
export function registerFormDragStore() {
    Alpine.store('formDrag', {
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

        // -----------------------------------------------------------------
        // Drag state lifecycle
        // Used in:
        // - src/resources/views/toolbox/form-builder/sidebar.blade.php
        // - src/resources/views/toolbox/form-builder/canvas/field.blade.php
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
        // Shared drag/drop parsing helpers
        // Used in most drop handlers in this store.
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

        getDataTransferNumberFromTypes(event, types) {
            for (const type of types) {
                const value = this.getDataTransferNumber(event, type);

                if (value !== null) {
                    return value;
                }
            }

            return null;
        },

        // -----------------------------------------------------------------
        // Shared visual helpers
        // Used in:
        // - canvas.blade.php
        // - canvas/group-row.blade.php
        // - canvas/field.blade.php
        // - repeater-builder.blade.php
        // - fields/repeater.blade.php
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

        showRepeaterColumnDropHighlight(zoneElement) {
            if (!zoneElement || !this.canDropField()) {
                return;
            }

            zoneElement.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-600');
        },

        hideRepeaterColumnDropHighlight(zoneElement) {
            if (!zoneElement) {
                return;
            }

            zoneElement.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-600');
        },

        // -----------------------------------------------------------------
        // Canvas root handlers
        // Used in: src/resources/views/toolbox/form-builder/canvas.blade.php
        // -----------------------------------------------------------------
        handleEmptyCanvasDrop(zoneElement, wire) {
            this.hideEmptyCanvasHighlight(zoneElement);

            if (this.itemKind === 'group') {
                wire.addGroupToCanvas(this.itemHandle ?? '');
                return;
            }

            wire.addFieldToNewRow(-1, this.fieldType);
        },

        canDropOnCanvasRowGap(event) {
            return this.itemKind === 'group'
                || this.itemKind === 'field'
                || this.hasDataTransferType(event, 'application/x-meros-group-row');
        },

        handleCanvasRowGapDragOver(event, gapElement) {
            if (!this.canDropOnCanvasRowGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        moveFieldToCanvasNewRow(wire, targetRowIndex) {
            if (this.sourceGroupRowIndex !== null) {
                wire.moveFieldFromGroupToNewRow(
                    this.sourceGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetRowIndex,
                );
                return;
            }

            if (this.isCanvasDrag) {
                wire.moveFieldToNewRow(this.sourceRowIndex, this.sourceFieldIndex, targetRowIndex);
                return;
            }

            wire.addFieldToNewRow(targetRowIndex, this.fieldType);
        },

        /**
         * Handle drops on canvas row-gap zones.
         * Route order:
         * 1) Group sidebar drag -> insert group before row.
         * 2) Existing group-row drag -> move group row before row.
         * 3) Field drag (from group/canvas/sidebar) -> create/move field row.
         */
        handleCanvasRowGapDrop(event, gapElement, wire, groupInsertIndex, fieldRowIndex) {
            this.hideRowGap(gapElement);

            if (this.itemKind === 'group' || this.hasDataTransferType(event, 'application/x-meros-group-row')) {
                if (this.itemKind === 'group') {
                    wire.addGroupBeforeRow(groupInsertIndex, this.itemHandle ?? '');
                    return;
                }

                const sourceGroupRow = this.getDataTransferNumber(event, 'application/x-meros-group-row');

                if (sourceGroupRow !== null) {
                    wire.moveGroupRowBefore(sourceGroupRow, groupInsertIndex);
                }

                return;
            }

            this.moveFieldToCanvasNewRow(wire, fieldRowIndex);
        },

        // -----------------------------------------------------------------
        // Group row handlers
        // Used in: src/resources/views/toolbox/form-builder/canvas/group-row.blade.php
        // -----------------------------------------------------------------
        /**
         * Move or add a field into a group's row-gap target.
         * Route order:
         * 1) Same group reorder.
         * 2) Cross-group move.
         * 3) Top-level canvas -> group move.
         * 4) Sidebar field -> add new field in target group row.
         */
        moveFieldToGroupNewRow(wire, targetGroupRowIndex, targetInnerRowIndex) {
            if (this.sourceGroupRowIndex === targetGroupRowIndex) {
                wire.moveFieldToGroupNewRow(
                    targetGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetInnerRowIndex,
                );
                return;
            }

            if (this.sourceGroupRowIndex !== null) {
                wire.moveFieldBetweenGroupsToNewRow(
                    this.sourceGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetGroupRowIndex,
                    targetInnerRowIndex,
                );
                return;
            }

            if (this.isCanvasDrag) {
                wire.moveFieldToGroupNewRowFromTopLevel(
                    this.sourceRowIndex,
                    this.sourceFieldIndex,
                    targetGroupRowIndex,
                    targetInnerRowIndex,
                );
                return;
            }

            wire.addFieldToGroupNewRow(targetGroupRowIndex, targetInnerRowIndex, this.itemHandle);
        },

        handleGroupEmptyDrop(zoneElement, wire, targetGroupRowIndex, targetInnerRowIndex = -1) {
            this.hideFieldDropHighlight(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldToGroupNewRow(wire, targetGroupRowIndex, targetInnerRowIndex);
        },

        handleGroupRowGapDrop(gapElement, wire, targetGroupRowIndex, targetInnerRowIndex) {
            this.hideRowGap(gapElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldToGroupNewRow(wire, targetGroupRowIndex, targetInnerRowIndex);
        },

        // -----------------------------------------------------------------
        // Field insertion handlers
        // Used in: src/resources/views/toolbox/form-builder/canvas/field.blade.php
        // -----------------------------------------------------------------
        /**
         * Insert/move a field at an exact index inside a group row.
         * Route order:
         * 1) Same-group relocate.
         * 2) Cross-group relocate.
         * 3) Top-level canvas -> group row move.
         * 4) Sidebar field -> insert into group row.
         */
        moveFieldIntoGroupRow(wire, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex) {
            if (this.sourceGroupRowIndex === targetGroupRowIndex) {
                wire.relocateFieldInGroup(
                    targetGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetInnerRowIndex,
                    targetFieldIndex,
                );
                return;
            }

            if (this.sourceGroupRowIndex !== null) {
                wire.moveFieldBetweenGroups(
                    this.sourceGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetGroupRowIndex,
                    targetInnerRowIndex,
                    targetFieldIndex,
                );
                return;
            }

            if (this.isCanvasDrag) {
                wire.moveFieldToGroupRow(
                    this.sourceRowIndex,
                    this.sourceFieldIndex,
                    targetGroupRowIndex,
                    targetInnerRowIndex,
                    targetFieldIndex,
                );
                return;
            }

            wire.insertFieldIntoGroupRow(targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex, this.itemHandle);
        },

        handleGroupFieldInsertDrop(zoneElement, wire, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex) {
            this.hideInsertMarker(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldIntoGroupRow(wire, targetGroupRowIndex, targetInnerRowIndex, targetFieldIndex);
        },

        moveFieldIntoTopRow(wire, targetRowIndex, targetFieldIndex) {
            if (this.sourceGroupRowIndex !== null) {
                wire.moveFieldFromGroupToRow(
                    this.sourceGroupRowIndex,
                    this.sourceGroupInnerRowIndex,
                    this.sourceFieldIndex,
                    targetRowIndex,
                    targetFieldIndex,
                );
                return;
            }

            if (this.isCanvasDrag) {
                wire.relocateField(this.sourceRowIndex, this.sourceFieldIndex, targetRowIndex, targetFieldIndex);
                return;
            }

            wire.insertFieldIntoRow(targetRowIndex, targetFieldIndex, this.fieldType);
        },

        handleTopFieldInsertDrop(zoneElement, wire, targetRowIndex, targetFieldIndex) {
            this.hideInsertMarker(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            this.moveFieldIntoTopRow(wire, targetRowIndex, targetFieldIndex);
        },

        // -----------------------------------------------------------------
        // Repeater builder handlers
        // Used in: src/resources/views/toolbox/form-builder/repeater-builder.blade.php
        // -----------------------------------------------------------------
        canDropOnRepeaterColumnGap(event) {
            return this.canDropField() || this.hasDataTransferType(event, 'application/x-meros-repeater-column');
        },

        handleRepeaterColumnGapDragOver(event, gapElement) {
            if (!this.canDropOnRepeaterColumnGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        handleRepeaterColumnDrop(zoneElement, wire, targetIndex) {
            this.hideRepeaterColumnDropHighlight(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            wire.addRepeaterColumnAt(targetIndex, this.itemHandle ?? '');
        },

        handleRepeaterColumnGapDrop(event, gapElement, wire, targetIndex) {
            this.hideRowGap(gapElement);

            if (this.canDropField()) {
                wire.addRepeaterColumnAt(targetIndex, this.itemHandle ?? '');
                return;
            }

            const sourceIndex = this.getDataTransferNumber(event, 'application/x-meros-repeater-column');

            if (sourceIndex !== null) {
                wire.moveRepeaterColumn(sourceIndex, targetIndex);
            }
        },

        canDropOnRepeaterRowGap(event) {
            return this.hasDataTransferType(event, 'application/x-meros-repeater-row');
        },

        handleRepeaterRowGapDragOver(event, gapElement) {
            if (!this.canDropOnRepeaterRowGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        handleRepeaterRowGapDrop(event, gapElement, wire, targetIndex) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getDataTransferNumber(event, 'application/x-meros-repeater-row');

            if (sourceIndex !== null) {
                wire.moveRepeaterDefaultRow(sourceIndex, targetIndex);
            }
        },

        // -----------------------------------------------------------------
        // Field repeater handlers
        // Used in: src/resources/views/fields/repeater.blade.php
        // -----------------------------------------------------------------
        handleFieldRepeaterRowGapDragOver(gapElement) {
            this.showRowGap(gapElement);
        },

        handleFieldRepeaterRowGapDrop(event, gapElement, wire, rowIndex, fieldIndex, groupRowIndex, targetIndex) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getDataTransferNumberFromTypes(event, [
                'application/x-meros-field-repeater-row',
                'text/plain',
            ]);

            if (sourceIndex !== null) {
                wire.moveFieldRepeaterRow(rowIndex, fieldIndex, groupRowIndex, sourceIndex, targetIndex);
            }
        },
    });
}
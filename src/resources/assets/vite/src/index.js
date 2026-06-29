import { setFieldValue, getFieldValue } from '../../forms/alpine/helpers.js';
import '../../forms/alpine/field-data.js';
import './style.css';

document.addEventListener('alpine:init', () => {
    const stopPanelResize = (context) => {
        context.isResizing = false;

        if (context.__onPanelResizeMouseMove) {
            window.removeEventListener('mousemove', context.__onPanelResizeMouseMove);
            context.__onPanelResizeMouseMove = null;
        }

        if (context.__onPanelResizeMouseUp) {
            window.removeEventListener('mouseup', context.__onPanelResizeMouseUp);
            context.__onPanelResizeMouseUp = null;
        }
    };

    const startPanelResize = (context, event, { widthKey = 'sidebarWidth', direction = 1, minWidth = 260, maxWidth = 520 } = {}) => {
        stopPanelResize(context);

        context.isResizing = true;
        context.resizeStartX = event.clientX;
        context.resizeStartWidth = context[widthKey];

        context.__onPanelResizeMouseMove = (moveEvent) => {
            if (!context.isResizing) {
                return;
            }

            const deltaX = (moveEvent.clientX - context.resizeStartX) * direction;
            const nextWidth = context.resizeStartWidth + deltaX;
            context[widthKey] = Math.max(minWidth, Math.min(maxWidth, nextWidth));
        };

        context.__onPanelResizeMouseUp = () => {
            stopPanelResize(context);
        };

        window.addEventListener('mousemove', context.__onPanelResizeMouseMove);
        window.addEventListener('mouseup', context.__onPanelResizeMouseUp);
    };

    /**
     * Alpine component for the form builder canvas, handling drag-and-drop of fields and groups, 
     * as well as communicating with Livewire via a callback for schema updates.
     */
    Alpine.data('canvas', (wireCallback) => ({
        isEditor: true,
        isDragging: false,
        draggingElementType: null,
        draggingGroupId: null,
        draggingPayload: null,
        wireCallback: typeof wireCallback === 'function' ? wireCallback : null,

        handleCanvasDragOver(event) {
            if (this.draggingElementType === null) {
                this.draggingElementType = this.__resolveDraggingElementType(event);
            }

            if (this.draggingPayload === null) {
                this.draggingPayload = this.__resolveDraggingPayload(event);
            }

            if (this.draggingElementType === null && this.draggingPayload === null) {
                return;
            }

            this.isDragging = true;
        },

        isGroupDrag(event) {
            if (this.draggingElementType === null) {
                this.draggingElementType = this.__resolveDraggingElementType(event);
            }

            return this.draggingElementType === 'group';
        },

        isSelfGroupDrag(zoneElement) {
            if (this.draggingElementType !== 'group' || !this.draggingGroupId || !zoneElement) {
                return false;
            }

            const zoneGroupId = zoneElement.closest('.canvas-field-group')?.dataset.groupId ?? null;
            return zoneGroupId !== null && zoneGroupId === this.draggingGroupId;
        },

        shouldShowFieldDropZone(zoneElement) {
            return this.__canDropFieldIntoZone(zoneElement, this.draggingPayload, this.draggingElementType);
        },

        handleDrop(event, zoneElement) {
            if (!this.wireCallback || !event?.dataTransfer || !zoneElement) {
                return;
            }

            const payload = this.__resolveDraggingPayload(event) ?? this.draggingPayload;

            if (!payload) {
                return;
            }

            const eventType = payload?.type;

            if (eventType === 'move-field' || eventType === 'move-repeater-field' || eventType === 'move-group') {
                if (payload.rowIndex === undefined) {
                    return;
                }

                if (payload.fieldPosition === undefined && (eventType === 'move-field' || eventType === 'move-repeater-field')) {
                    return;
                }

                if (payload.groupId === undefined && eventType === 'move-group') {
                    return;
                }

                if (eventType === 'move-repeater-field' && payload.currentRepeaterId === undefined) {
                    return;
                }

                this.__moveElement(
                    eventType === 'move-field' 
                        ? 'field' 
                        : eventType === 'move-repeater-field' 
                            ? 'repeater-field' 
                            : 'group', payload, zoneElement
                    );
            }

            else if (eventType === 'new-element') {
                if (!payload.elementType || !payload.elementHandle) {
                    return;
                }

                this.__insertElement(payload, zoneElement);
            }

            this.draggingElementType = null;
            this.draggingGroupId = null;
            this.draggingPayload = null;
        },

        __resolveDraggingElementType(event) {
            if (this.__hasTransferType(event, 'application/x-meros-group-move')) {
                return 'group';
            }

            if (this.__hasTransferType(event, 'application/x-meros-field-move')) {
                return 'field';
            }

            if (this.__hasTransferType(event, 'application/x-meros-new-element')) {
                const rawData = event?.dataTransfer?.getData('application/x-meros-new-element');

                if (!rawData) {
                    return null;
                }

                try {
                    return JSON.parse(rawData)?.elementType ?? null;
                } catch (_error) {
                    return null;
                }
            }

            return null;
        },

        __resolveDraggingPayload(event) {
            const rawData =
                event?.dataTransfer?.getData('application/x-meros-field-move') ||
                event?.dataTransfer?.getData('application/x-meros-group-move') ||
                event?.dataTransfer?.getData('application/x-meros-new-element') ||
                event?.dataTransfer?.getData('text/plain');

            if (!rawData) {
                return null;
            }

            try {
                return JSON.parse(rawData);
            } catch (_error) {
                return null;
            }
        },

        __canDropFieldIntoZone(zoneElement, payload = null, elementType = null) {
            if (!zoneElement || elementType === 'group') {
                return false;
            }

            const rowFieldCount = parseInt(zoneElement.dataset.rowFieldCount, 10);

            if (isNaN(rowFieldCount) || rowFieldCount < 3) {
                return true;
            }

            if (elementType !== 'field' || !payload) {
                return false;
            }

            const fromRowIndex = parseInt(payload.rowIndex, 10);
            const toRowIndex = parseInt(zoneElement.dataset.rowIndex, 10);

            if (isNaN(fromRowIndex) || isNaN(toRowIndex) || fromRowIndex !== toRowIndex) {
                return false;
            }

            const currentGroupId = payload.currentGroupId ?? null;
            const destinationGroupId = zoneElement.closest('.canvas-field-group')?.dataset.groupId ?? null;

            return currentGroupId === destinationGroupId;
        },

        __hasTransferType(event, type) {
            if (!event?.dataTransfer || !type) {
                return false;
            }

            const transferTypes = Array.from(event.dataTransfer.types ?? []);

            return transferTypes.includes(type);
        },

        __insertElement(payload, zoneElement) {
            const insertingIntoRepeater = zoneElement.closest('.canvas-repeater-field');

            if (insertingIntoRepeater) {
                this.__insertElementIntoRepeater(payload, zoneElement);
                return;
            }

            const insertingIntoNewRow =
                zoneElement.classList.contains('row-drop-zone') ||
                zoneElement.classList.contains('canvas-drop-zone') ||
                zoneElement.classList.contains('group-canvas-drop-zone');

            if (insertingIntoNewRow) {
                this.__insertElementIntoNewRow(payload, zoneElement);
            } else {
                this.__insertElementIntoExistingRow(payload, zoneElement);
            }
        },

        __insertElementIntoRepeater(payload, zoneElement) {
            if (payload.elementType !== 'field' || !payload.elementHandle) {
                return;
            }

            this.wireCallback('insert-field-into-repeater', {
                fieldHandle: payload.elementHandle,
                fieldPosition: parseInt(zoneElement.dataset.rowIndex, 10),
            });
        },

        __insertElementIntoNewRow(payload, zoneElement) {
            const rowIndex = parseInt(zoneElement.dataset.rowIndex, 10);
            const destinationGroupId = zoneElement.closest('.canvas-field-group')?.dataset.groupId ?? null;

            if (isNaN(rowIndex)) {
                return;
            }

            this.wireCallback('insert-element-into-new-row', {
                elementType: payload.elementType,
                elementHandle: payload.elementHandle,
                rowIndex: rowIndex,
                destinationGroupId: destinationGroupId
            });
        },

        __insertElementIntoExistingRow(payload, zoneElement) {
            if (payload.elementType === 'group') {
                return;
            }

            if (!this.__canDropFieldIntoZone(zoneElement, payload, payload.elementType)) {
                return;
            }

            const rowIndex = parseInt(zoneElement.dataset.rowIndex, 10);
            const fieldPosition = parseInt(zoneElement.dataset.fieldPosition, 10);
            const destinationGroupId = zoneElement.closest('.canvas-field-group')?.dataset.groupId ?? null;

            if (isNaN(rowIndex) || isNaN(fieldPosition)) {
                return;
            }

            this.wireCallback('insert-element-into-existing-row', {
                elementType: payload.elementType,
                elementHandle: payload.elementHandle,
                rowIndex: rowIndex,
                fieldPosition: fieldPosition,
                destinationGroupId: destinationGroupId
            });
        },

        __moveElement(elementType, payload, zoneElement) {
            if (elementType === 'field' || elementType === 'repeater-field') {
                this.__moveField(payload, zoneElement);
            } else if (elementType === 'group') {
                this.__moveGroup(payload, zoneElement);
            }
        },

        __moveGroup(payload, zoneElement) {
            if (zoneElement.classList.contains('field-drop-zone')) {
                return;
            }

            const fromRowIndex = parseInt(payload?.rowIndex, 10);
            const toRowIndex = parseInt(zoneElement.dataset.rowIndex, 10);

            if (isNaN(fromRowIndex) || isNaN(toRowIndex)) {
                return;
            }

            this.wireCallback('move-group-to-new-row', {
                groupId: payload.groupId,
                fromRowIndex: fromRowIndex,
                toRowIndex: toRowIndex
            });
        },

        __moveField(payload, zoneElement) {
            const isMovingRepeaterField = payload.type === 'move-repeater-field';

            if (isMovingRepeaterField) {
                this.__moveRepeaterField(payload, zoneElement);
                return;
            }

            const isCurrentlyInGroup    = !!payload?.currentGroupId;
            const isDroppingIntoGroup   = !!zoneElement.closest('.canvas-field-group');

            let currentGroupId = payload?.currentGroupId ?? null;
            let destinationGroupId = null;

            if (isDroppingIntoGroup) {
                destinationGroupId = zoneElement.closest('.canvas-field-group').dataset.groupId;
            }

            const dropZoneType        = zoneElement.classList.contains('field-drop-zone') ? 'field' : 'row';
            const fromRowIndex        = parseInt(payload?.rowIndex, 10);
            const toRowIndex          = parseInt(zoneElement.dataset.rowIndex, 10);
            const fromFieldPosition   = parseInt(payload?.fieldPosition, 10);
            const toFieldPosition     = parseInt(zoneElement.dataset.fieldPosition, 10);
            const isMovingInSameContainer = currentGroupId === destinationGroupId;
            const isMovingInSameRow   =
                isMovingInSameContainer &&
                dropZoneType === 'field' &&
                fromRowIndex === toRowIndex;

            if (dropZoneType === 'field' && !this.__canDropFieldIntoZone(zoneElement, payload, 'field')) {
                return;
            }


            // Dropping on the zone immediately to the left (same index) or immediately
            // to the right (index + 1) of the dragged field leaves its position unchanged.
            const isSamePosition = isMovingInSameRow && (
                toFieldPosition === fromFieldPosition ||
                toFieldPosition === fromFieldPosition + 1
            );

            if (isSamePosition) {
                return;
            }

            if (isMovingInSameRow) {
                if (isNaN(toFieldPosition)) {
                    return;
                }

                const adjustedToPosition = toFieldPosition > fromFieldPosition
                    ? toFieldPosition - 1
                    : toFieldPosition;

                this.__moveFieldInCurrentRow({
                    ...payload,
                    fromRowPosition: fromFieldPosition,
                    toRowPosition: adjustedToPosition,
                    currentGroupId: currentGroupId,
                    destinationGroupId: destinationGroupId
                });
                return;
            }

            const movingToExistingRow = payload?.fieldPosition !== undefined && dropZoneType === 'field';

            if (movingToExistingRow) {
                if (isNaN(toRowIndex) || isNaN(toFieldPosition)) {
                    return;
                }

                this.__moveFieldToExistingRow({
                    ...payload,
                    fromRowIndex: fromRowIndex,
                    fromRowPosition: fromFieldPosition,
                    toRowIndex: toRowIndex,
                    toRowPosition: toFieldPosition,
                    currentGroupId: currentGroupId,
                    destinationGroupId: destinationGroupId
                });
                return;
            }

            const movingToNewRow = !isNaN(toRowIndex) && dropZoneType === 'row';

            if (movingToNewRow) {
                this.__moveFieldToNewRow({
                    ...payload,
                    fromRowIndex: fromRowIndex,
                    toRowIndex: toRowIndex,
                    currentGroupId: currentGroupId,
                    destinationGroupId: destinationGroupId
                });
            }
        },

        __moveRepeaterField(payload, zoneElement) {
            // Counter-intuitive using rowIndex, but here it reflects the position of the field within the repeater.
            const newPosition = parseInt(zoneElement.dataset.rowIndex, 10);
            
            if (isNaN(newPosition)) return;

            this.wireCallback('move-repeater-field', {
                fieldId: payload.fieldId,
                toPosition: newPosition
            });
        },

        __moveFieldInCurrentRow(payload) {
            this.wireCallback('move-field-in-current-row', {
                fieldId: payload.fieldId,
                rowIndex: payload.rowIndex,
                fromRowPosition: payload.fromRowPosition,
                toRowPosition: payload.toRowPosition,
                currentGroupId: payload.currentGroupId,
                destinationGroupId: payload.destinationGroupId
            });
        },

        __moveFieldToExistingRow(payload) {
            const adjustedToRowPosition = this.__getAdjustedToRowPosition(
                payload.fromRowIndex,
                payload.fromRowPosition,
                payload.toRowIndex,
                payload.toRowPosition
            );

            this.wireCallback('move-field-to-existing-row', {
                fieldId: payload.fieldId,
                fromRowIndex: payload.fromRowIndex,
                fromRowPosition: payload.fromRowPosition,
                toRowIndex: payload.toRowIndex,
                toRowPosition: adjustedToRowPosition,
                currentGroupId: payload.currentGroupId,
                destinationGroupId: payload.destinationGroupId
            });
        },

        __moveFieldToNewRow(payload) {
            this.wireCallback('move-field-to-new-row', {
                fieldId: payload.fieldId,
                fromRowIndex: payload.fromRowIndex,
                toRowIndex: payload.toRowIndex,
                currentGroupId: payload.currentGroupId,
                destinationGroupId: payload.destinationGroupId
            });
        },

        __getAdjustedToRowPosition(fromRowIndex, fromFieldPosition, toRowIndex, toFieldPosition) {
            if (fromRowIndex === toRowIndex && toFieldPosition > fromFieldPosition) {
                return toFieldPosition - 1;
            }

            return toFieldPosition;
        }
    }));

    /**
     * Alpine component for the canvas sidebar, managing open categories and drag-and-drop of new elements from the sidebar onto the canvas.
     */
    Alpine.data('panelsidebar', (openCategory) => ({
        openCategory: openCategory,
        sidebarWidth: 320,
        isResizing: false,
        resizeStartX: 0,
        resizeStartWidth: 320,
        __onPanelResizeMouseMove: null,
        __onPanelResizeMouseUp: null,

        startResize(event) {
            startPanelResize(this, event, {
                widthKey: 'sidebarWidth',
                direction: 1,
            });
        },

        destroy() {
            stopPanelResize(this);
        },

        toggleCategory(category) {
            this.openCategory = this.openCategory === category ? null : category;
        },

        handleDragStart(event, itemKind, itemHandle) {
            if (!itemKind || !itemHandle) {
                return;
            }

            event.dataTransfer.effectAllowed = 'copy';

            const payload = {
                type: 'new-element',
                elementType: itemKind,
                elementHandle: itemHandle
            };

            this.$dispatch('mforms:canvas-drag-start', {
                elementType: itemKind,
                groupId: null,
                payload: payload,
            });

            try {
                event.dataTransfer.setData('application/x-meros-new-element', JSON.stringify(payload));
                event.dataTransfer.setData('text/plain', JSON.stringify(payload));
            } catch (error) {
                console.error('Failed to set drag data:', error);
            }
        }
    }));

    /**
     * Alpine component for the field settings panel, managing the active field being edited.
     */
    Alpine.data('panelfieldsettings', (
        setActiveFieldCallback, 
        updateActiveFieldCallback, 
        getActiveFieldCallback, 
        removeActiveFieldCallback
    ) => ({
        open: false,
        initialised: false,
        activeFieldId: null,
        activeFieldRowIndex: null,
        activeFieldGroupId: null,
        activeFieldProps: {},
        activeFieldSupports: {},

        setActiveFieldCallback: typeof setActiveFieldCallback === 'function' ? setActiveFieldCallback : null,
        updateActiveFieldCallback: typeof updateActiveFieldCallback === 'function' ? updateActiveFieldCallback : null,
        getActiveFieldCallback: typeof getActiveFieldCallback === 'function' ? getActiveFieldCallback : null,
        removeActiveFieldCallback: typeof removeActiveFieldCallback === 'function' ? removeActiveFieldCallback : null,
        
        onOpen: null,
        onSettingChange: null,
        onRefresh: null,
        onClose: null,
        onCloseRemoved: null,
        onHide: null,
        onUnHide: null,

        showRules: false,
        
        sidebarWidth: 320,
        isResizing: false,
        resizeStartX: 0,
        resizeStartWidth: 320,
        __onPanelResizeMouseMove: null,
        __onPanelResizeMouseUp: null,

        init() {
            this.onOpen = async (event) => {
                const { fieldId, rowIndex, groupId } = event.detail ?? {};

                if (fieldId) {
                    this.activeFieldId = fieldId;
                    this.activeFieldRowIndex = rowIndex;
                    this.activeFieldGroupId = groupId;
                    this.open = true;

                    const activeFieldProps = await this.getActiveField(true);

                    if (activeFieldProps) {
                        this.activeFieldProps = activeFieldProps;
                        this.activeFieldSupports = activeFieldProps.supports || {};

                        if (this.activeFieldProps?.hasRules || false) {
                            this.showRules = true;
                        }
                    }

                    this.initialised = true;
                    this.$dispatch('mforms:field-settings-opened');
                }
            };

            this.onSettingChange = (event) => {
                const { element, value } = event.detail ?? {};

                if (!element) return;
                const isDefaultValueControl = element.getAttribute('data-default-value-control') !== null;

                if (isDefaultValueControl) {
                    this.__onDefaultValueChange(element, value);
                    return;
                }
            }

            this.onRefresh = async () => {
                if (this.activeFieldId) {
                    const activeFieldProps = await this.getActiveField();

                    if (activeFieldProps) {
                        this.activeFieldProps = activeFieldProps;
                        this.activeFieldSupports = activeFieldProps.supports || {};
                    } else {
                        this.activeFieldProps = {};
                        this.activeFieldSupports = {};
                    }

                    this.$dispatch('mforms:field-settings-refreshed');
                }
            };

            this.onClose = () => {
                this.open = false;
                this.activeFieldId = null;
                this.activeFieldRowIndex = null;
                this.activeFieldGroupId = null;
                this.activeFieldProps = {};
                this.activeFieldSupports = {};

                if (this.removeActiveFieldCallback) {
                    this.removeActiveFieldCallback();
                }

                this.$dispatch('mforms:field-settings-closed');
            };

            this.onCloseRemoved = (event) => {
                if (this.activeFieldId === event.detail[0]) {
                    this.onClose();
                }
            };

            this.onHide = () => {
                this.open = false;
            };

            this.onUnHide = () => {
                if (this.activeFieldId) {
                    this.open = true;
                }
            };

            window.addEventListener('mforms:open-field-settings', this.onOpen);
            window.addEventListener('mforms:refresh-field-settings', this.onRefresh);
            window.addEventListener('mforms:close-field-settings', this.onClose);
            window.addEventListener('mforms:close-removed-field-settings', this.onCloseRemoved);
            window.addEventListener('mforms:hide-field-settings', this.onHide);
            window.addEventListener('mforms:unhide-field-settings', this.onUnHide);
            window.addEventListener('mforms:field-updated', this.onSettingChange);
        },

        updateActiveFieldProperty(property, value) {
            if (!this.activeFieldId || !this.updateActiveFieldCallback) {
                return;
            }

            if (this.updateActiveFieldCallback) {
                this.updateActiveFieldCallback(
                    property, 
                    value, 
                    this.activeFieldRowIndex, 
                    this.activeFieldGroupId
                );

                this.$dispatch('mforms:field-settings-property-updated', {
                    property: property,
                    value: value,
                });
            }
        },

        supportsProperty(property) {
            return Object.values(this.activeFieldSupports).includes(property);
        },

        async getActiveField(set = false) {
            if (!this.activeFieldId || !this.getActiveFieldCallback) {
                return null;
            }

            if (set && this.setActiveFieldCallback) {
                const activeField = await this.setActiveFieldCallback(
                    this.activeFieldId,
                    this.activeFieldRowIndex,
                    this.activeFieldGroupId
                );

                return activeField;
            }

            return await this.getActiveFieldCallback();
        },

        destroy() {
            stopPanelResize(this);

            window.removeEventListener('mforms:open-field-settings', this.onOpen);
            window.removeEventListener('mforms:refresh-field-settings', this.onRefresh);
            window.removeEventListener('mforms:close-field-settings', this.onClose);
            window.removeEventListener('mforms:close-removed-field-settings', this.onCloseRemoved);
            window.removeEventListener('mforms:hide-field-settings', this.onHide);
            window.removeEventListener('mforms:unhide-field-settings', this.onUnHide);
            window.removeEventListener('mforms:field-updated', this.onSettingChange);

            this.onOpen = null;
            this.onSettingChange = null;
            this.onRefresh = null;
            this.onClose = null;
            this.onCloseRemoved = null;
            this.onHide = null;
            this.onUnHide = null;
            this.initialised = false;
        },

        startResize(event) {
            startPanelResize(this, event, {
                widthKey: 'sidebarWidth',
                direction: -1,
            });
        },

        __onDefaultValueChange(element, value) {
            if (!element) return;
            const isDefaultValueControl = element.getAttribute('data-default-value-control') !== null;

            if (!isDefaultValueControl) return;
            if (value === undefined || value === this.activeFieldProps.default) {
                return;
            }

            this.updateActiveFieldProperty('default', value);
            
            const fieldId = element.getAttribute('data-field-id');

            if (fieldId) {
                setFieldValue(fieldId, value);
            }
        },
    }));

    Alpine.data('fieldConditions', (formFields) => ({
        formFields: formFields,

        showField: null,
        hideField: null,
        requireField: null,
        optionalField: null,
        disableField: null,
        enableField: null,

        showLogicField: null,
        hideLogicField: null,
        requireLogicField: null,
        optionalLogicField: null,
        disableLogicField: null,
        enableLogicField: null,

        onRuleChange: null,
        onRuleLogicChange: null,
        onRuleFieldChange: null,
        onRuleOperatorChange: null,

        init() {
            this.__initialiseFields();

            this.onRuleChange = (event) => {
                const { element, value, context } = event.detail ?? {};

                if (element && element.getAttribute('data-conditions-field-select') !== null) {
                    const formField = this.formFields[value] ?? null;
                    
                    if (formField && context) {
                        console.log('found field', formField);
                        this.__getRuleParams('show', context.repeater.row);
                    }
                }
            }

            window.addEventListener('mforms:field-updated', this.onRuleChange);
        },

        destroy() {
            window.removeEventListener('mforms:field-updated', this.onRuleChange);
            this.onRuleChange = null;
        },

        getConditions() {
            console.log({
                show: {
                    'logic': this.showLogicField ? mforms.getFieldValue(this.showLogicField) : null,
                    'rules': this.showField ? mforms.getFieldValue(this.showField) : null
                },
                hide: {
                    'logic': this.hideLogicField ? mforms.getFieldValue(this.hideLogicField) : null,
                    'rules': this.hideField ? mforms.getFieldValue(this.hideField) : null
                },
                require: {
                    'logic': this.requireLogicField ? mforms.getFieldValue(this.requireLogicField) : null,
                    'rules': this.requireField ? mforms.getFieldValue(this.requireField) : null
                },
                optional: {
                    'logic': this.optionalLogicField ? mforms.getFieldValue(this.optionalLogicField) : null,
                    'rules': this.optionalField ? mforms.getFieldValue(this.optionalField) : null
                },
                disable: {
                    'logic': this.disableLogicField ? mforms.getFieldValue(this.disableLogicField) : null,
                    'rules': this.disableField ? mforms.getFieldValue(this.disableField) : null
                },
                enable: {
                    'logic': this.enableLogicField ? mforms.getFieldValue(this.enableLogicField) : null,
                    'rules': this.enableField ? mforms.getFieldValue(this.enableField) : null
                }
            });
        },

        __initialiseFields() {
            this.showField     = mforms.getField('field-conditions-editor-show');
            console.log('showField', this.showField);
            this.hideField     = document.getElementById('field-conditions-editor-hide');
            this.requireField  = document.getElementById('field-conditions-editor-require');
            this.optionalField = document.getElementById('field-conditions-editor-optional');
            this.disableField  = document.getElementById('field-conditions-editor-disable');
            this.enableField   = document.getElementById('field-conditions-editor-enable');

            this.showLogicField     = document.getElementById('field-conditions-logic-show');
            this.hideLogicField     = document.getElementById('field-conditions-logic-hide');
            this.requireLogicField  = document.getElementById('field-conditions-logic-require');
            this.optionalLogicField = document.getElementById('field-conditions-logic-optional');
            this.disableLogicField  = document.getElementById('field-conditions-logic-disable');
            this.enableLogicField   = document.getElementById('field-conditions-logic-enable');
        },

        __getRuleParams(ruleType, index) {
            const ruleField = this[`${ruleType}Field`];
            console.log('ruleField', ruleField, index);

            if (ruleField) {
                const rule = ruleField.getRowValue(index);
                console.log(rule);
            }
        }
    }));

    /**
     * Alpine component for a canvas field, handling drag-and-drop of fields within the canvas.
     */
    Alpine.data('canvasfield', (fieldId) => ({
        fieldId: fieldId,
        moving: false,

        handleDragStart(event) {
            this.moving = true;
            event.dataTransfer.effectAllowed = 'move';

            const currentGroupId = event.currentTarget
                .closest('.canvas-field-group')
                ?.dataset.groupId ?? null;

            const currentRepeaterId = event.currentTarget
                .closest('.canvas-repeater-field')
                ?.dataset.repeaterId ?? null;

            const payload = {
                type: currentRepeaterId ? 'move-repeater-field' : 'move-field',
                fieldId: this.fieldId,
                rowIndex: event.currentTarget.closest('[data-row-index]')?.dataset.rowIndex,
                fieldPosition: event.currentTarget.dataset.fieldPosition,
                currentGroupId: currentGroupId,
                currentRepeaterId: currentRepeaterId,
            };

            this.$dispatch('mforms:canvas-drag-start', {
                elementType: 'field',
                groupId: currentGroupId,
                repeaterId: currentRepeaterId,
                payload: payload,
            });

            event.currentTarget.addEventListener('dragend', () => {
                this.$dispatch('mforms:canvas-drag-end');
            }, { once: true });

            try {
                event.dataTransfer.setData('application/x-meros-field-move', JSON.stringify(payload));
            } catch (error) {
                console.error('Failed to set drag data:', error);
            }
        }
    }));

    /**
     * Alpine component for a canvas group, handling drag-and-drop of groups within the canvas.
     */
    Alpine.data('canvasgroup', (groupId) => ({
        groupId: groupId,
        moving: false,

        handleDragStart(event) {
            this.moving = true;
            event.dataTransfer.effectAllowed = 'move';

            const payload = {
                type: 'move-group',
                groupId: this.groupId,
                rowIndex: event.currentTarget.closest('[data-row-index]')?.dataset.rowIndex,
            };

            this.$dispatch('mforms:canvas-drag-start', {
                elementType: 'group',
                groupId: this.groupId,
                payload: payload,
            });

            event.currentTarget.addEventListener('dragend', () => {
                this.$dispatch('mforms:canvas-drag-end');
            }, { once: true });

            try {
                event.dataTransfer.setData('application/x-meros-group-move', JSON.stringify(payload));
            } catch (error) {
                console.error('Failed to set drag data:', error);
            }
        }
    }));
});

// window.addEventListener('mforms:field-updated', ({ detail }) => {
//     console.log('Field updated:', detail);
// });
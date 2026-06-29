<div 
    class="flex h-full min-h-0 overflow-hidden" 
    x-data="canvas($wire.handleCanvasEvent)"
    @mforms:canvas-drag-start.window="isDragging = true; draggingElementType = $event.detail?.elementType ?? null; draggingGroupId = $event.detail?.groupId ?? null; draggingPayload = $event.detail?.payload ?? null"
    @mforms:canvas-drag-end.window="isDragging = false; draggingElementType = null; draggingGroupId = null; draggingPayload = null"
    @dragenter.prevent="handleCanvasDragOver($event)"
    @dragover.prevent="handleCanvasDragOver($event)"
    @dragend.prevent="isDragging = false; draggingPayload = null"
>
    @include('meros::toolbox.forms.builder.canvas.panel-sidebar')
    
    @if($screen === 'canvas-main')
        @include('meros::toolbox.forms.builder.canvas.main')
    @elseif($screen === 'canvas-rules-editor')
        @include('meros::toolbox.forms.builder.canvas.editor-rules')
    @elseif($screen === 'canvas-options-editor')
        @include('meros::toolbox.forms.builder.canvas.editor-options')
    @elseif($screen === 'canvas-conditions-editor')
        @include('meros::toolbox.forms.builder.canvas.editor-conditions')
    @elseif($screen === 'canvas-repeater-editor')
        @include('meros::toolbox.forms.builder.canvas.editor-repeater')
    @endif

    @include('meros::toolbox.forms.builder.canvas.panel-field-settings')
</div>
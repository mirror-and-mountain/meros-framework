<button
    type="button"
    class="mforms-field-drag-handle"
    draggable="true"
    aria-haspopup="dialog"
    aria-label="{{ $moveGroup ? 'Move group' : 'Move field' }}"
    title="Drag to reorder, or choose a new position"
    @click.stop.prevent="configureMove($el.closest('{{ $moveGroup ? '[data-form-group-component]' : '[data-form-field-component]' }}'))"
    @dragstart.stop="{{ $moveGroup ? 'startGroupDrag' : 'startFieldDrag' }}($event, $el.closest('{{ $moveGroup ? '.mforms-row' : '[data-form-field-component]' }}'))"
    @dragend="endDrag($el.closest('.meros-form-builder'))"
>
    <span aria-hidden="true">⠿</span>
    <span class="mforms-builder-move-label">Move</span>
</button>

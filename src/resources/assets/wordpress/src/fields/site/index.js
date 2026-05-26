import '../../../../forms/style.scss';

document.addEventListener('DOMContentLoaded', () => {
	initTomSelects();
	bindRepeaterCellChangeDelegation();
});

window.addEventListener('load', () => {
	Livewire.hook('morphed', () => {
		initTomSelects();
	});
});

document.addEventListener('alpine:init', ensureRepeaterHandlersOnFormDragStore);

// In some contexts Alpine may already be initialised before this bundle executes.
if (window.Alpine) {
    ensureRepeaterHandlersOnFormDragStore();
}
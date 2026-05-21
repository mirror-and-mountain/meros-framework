import { registerFormBuilderStore, registerRepeaterFieldStore } from '../../forms/alpine/stores';
import '../../wordpress/src/fields/site/forms.scss';
import './style.css';

// Initialise the alpine formBuilder store
document.addEventListener('alpine:init', registerFormBuilderStore);
document.addEventListener('alpine:init', registerRepeaterFieldStore);
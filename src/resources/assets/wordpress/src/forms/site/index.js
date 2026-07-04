import '../../../../forms/alpine/field-data';
import '../../../../forms/alpine/helpers';
import './style.scss';

window.addEventListener('mforms:field-updated', ({ detail }) => {
    console.log('Field updated:', detail);
});

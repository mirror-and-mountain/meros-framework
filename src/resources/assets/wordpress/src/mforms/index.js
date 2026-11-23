import mform from './form.js';
import mformSection from './section.js';
import mformsSelect from './fields/select.js';
import mformsRepeater from './fields/repeater.js';
import mformsFlowActionsRepeater from './fields/flow-actions-repeater.js';
import mformsRichText from './fields/richtext.js';
import mformsBuilder from './builder.js';
import mformsChoice from './fields/choice.js';
import { initializeFieldDynamicSuggestions } from './dynamic-suggestions.js';

import './style.scss';

document.addEventListener('alpine:init', () => {
    Alpine.directive('meros-dynamic-suggestions', initializeFieldDynamicSuggestions);
    Alpine.data('mform', mform);
    Alpine.data('mformSection', mformSection);
    Alpine.data('mformsSelect', mformsSelect);
    Alpine.data('mformsBuilder', mformsBuilder);
    Alpine.data('mformsChoice', mformsChoice);
    Alpine.data('mformsRepeater', mformsRepeater);
    Alpine.data('mformsFlowActionsRepeater', mformsFlowActionsRepeater);
    Alpine.data('mformsRichText', mformsRichText);
});

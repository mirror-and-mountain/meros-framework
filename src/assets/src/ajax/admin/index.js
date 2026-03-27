import handlePackageSwitches from './packages.js';
import handleActionButtonClick from './installers.js';

import './style.scss';

/* Handle AJAX calls for toggle switches */
document.addEventListener('click', handlePackageSwitches);

/* Handle AJAX calls for action buttons */
document.addEventListener('click', handleActionButtonClick);
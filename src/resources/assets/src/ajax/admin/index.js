import handleProviderInstaller from './installers.js';

import './style.scss';

/* Handle AJAX calls for action buttons */
document.addEventListener('DOMContentLoaded', function() {
    const installerButtons = document.querySelectorAll(
        ".meros-provider-action-button"
    );

    installerButtons.forEach(button => {
        button.addEventListener('click', handleProviderInstaller);
    });
});
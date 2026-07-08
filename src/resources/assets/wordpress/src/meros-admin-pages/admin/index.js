import handleProviderInstaller from './installers.js';

import './style.scss';

document.addEventListener('DOMContentLoaded', function() {
    /* Handle AJAX calls for action buttons */
    const installerButtons = document.querySelectorAll(
        ".meros-provider-action-button"
    );

    installerButtons.forEach(button => {
        button.addEventListener('click', handleProviderInstaller);
    });

    // Fix incorrect heading levels in settings sections
    const settingsSectionTitles = document.querySelectorAll('.meros-settings-section-title');
    settingsSectionTitles.forEach(title => {
        const wrongTitle = title.previousElementSibling;
        if (wrongTitle && wrongTitle.tagName === 'H2') {
            wrongTitle.remove();
        }
    });
});


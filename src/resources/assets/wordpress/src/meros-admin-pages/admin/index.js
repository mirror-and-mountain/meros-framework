import handleProviderInstaller from './installers.js';
import {handleOauthStart, handleIntegrationEnvironmentChange} from './integrations.js';
import './style.scss';

document.addEventListener('DOMContentLoaded', function() {
    console.log('Meros Admin Pages JS loaded');

    /* Handle AJAX calls for action buttons */
    const installerButtons = document.querySelectorAll(
        ".meros-provider-action-button"
    );

    const oauthButtons = document.querySelectorAll(
        ".button[data-meros-action='oauth-start']"
    );

    const integrationEnvironmentSelectors = document.querySelectorAll(
        "select[data-action='integration-select-environment']"
    );

    oauthButtons.forEach(button => {
        button.addEventListener('click', handleOauthStart);
    });

    installerButtons.forEach(button => {
        button.addEventListener('click', handleProviderInstaller);
    });

    integrationEnvironmentSelectors.forEach(selector => {
        selector.addEventListener('change', handleIntegrationEnvironmentChange);
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


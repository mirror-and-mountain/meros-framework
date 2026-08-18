import { merosHandleSingleTableOperation, merosHandleMultiTableOperation } from './tables.js';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    // Attach event listeners to all action buttons
    const singleTableActionButtons = document.querySelectorAll('.meros-table-card-action-button');
    singleTableActionButtons.forEach(button => {
        button.addEventListener('click', merosHandleSingleTableOperation);
    });

    const multiTableActionButtons = document.querySelectorAll('.meros-tables-action-button');
    multiTableActionButtons.forEach(button => {
        button.addEventListener('click', merosHandleMultiTableOperation);
    });
});
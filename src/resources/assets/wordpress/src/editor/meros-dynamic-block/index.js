import { __ } from '@wordpress/i18n';
import { registerBlockType} from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';

wp.domReady(() => {
    if (typeof window.meros_script_data !== 'object') return;

    const dynamicBlocks = window.meros_script_data.blocks || null;
    if (typeof dynamicBlocks !== 'object') return;

    Object.entries(dynamicBlocks).forEach(([name, block]) => {
        if (!block.attributes) return;
        if (!block.attributes.merosDynamicBlock) return;

        registerBlockType(name, {
            title: block.title || '',
            attributes: block.attributes,

            edit: Edit,
            save: () => null,
        });
    });
});
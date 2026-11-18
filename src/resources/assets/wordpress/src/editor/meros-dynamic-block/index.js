import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

wp.domReady(() => {
    const dynamicBlocks = window.meros_script_data?.blocks;
    if (!dynamicBlocks || typeof dynamicBlocks !== 'object') return;

    const blocks = Object.entries(dynamicBlocks).filter(([, block]) => block.attributes?.merosDynamicBlock);
    const childrenByParent = new Map();

    for (const [name, block] of blocks) {
        for (const parent of block.parent ?? []) {
            if (!childrenByParent.has(parent)) childrenByParent.set(parent, []);
            childrenByParent.get(parent).push(name);
        }
    }

    for (const [name, block] of blocks) {
        const allowedBlocks = block.allowedBlocks ?? childrenByParent.get(name);

        registerBlockType(name, {
            title: block.title || '',
            icon: block.icon || '',
            description: block.description || '',
            attributes: block.attributes,
            parent: block.parent ?? undefined,
            allowedBlocks: block.allowedBlocks ?? undefined,

            edit: allowedBlocks ? (props) => <Edit {...props} allowedBlocks={allowedBlocks} /> : Edit,
            save: () => <InnerBlocks.Content />,
        });
    }
});
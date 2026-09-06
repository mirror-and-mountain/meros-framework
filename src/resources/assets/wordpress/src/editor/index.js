import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';

import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    ToggleControl
} from '@wordpress/components';

const TestBlockControls = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const { name, attributes, setAttributes } = props;

        if (attributes.merosDynamicBlock && attributes.merosControls) {
            const controls = attributes.merosControls;
            return (
                <Fragment>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title={__('Test Panel', 'meros-theme')} initialOpen={true}>
                            {Object.entries(controls).map(([attribute, config]) => {
                                const type = config.type;
                                if (!type) return null;

                                if (type === 'toggle') {
                                    return (
                                        <ToggleControl
                                            key={attribute}
                                            label={config.label || __('Test Toggle Control', 'meros-theme')}
                                            checked={Boolean(attributes[attribute])}
                                            onChange={(value) => {
                                                setAttributes({
                                                    [attribute]: value
                                                });
                                            }}
                                        />
                                    );
                                }

                                return null;
                            })}
                        </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        }

        return <BlockEdit {...props} />
    };
}, 'merosTestControls');

wp.domReady(() => {
    addFilter('editor.BlockEdit', 'meros/test-block-controls', TestBlockControls);
});
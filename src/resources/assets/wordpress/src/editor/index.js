import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';

import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    ToggleControl,
    TextControl,
    DateTimePicker,
    DatePicker,
    TextareaControl,
    SelectControl,
    CheckboxControl
} from '@wordpress/components';

const createControl = (attribute, config, attributes, setAttributes, label) => {
    const value = attributes[attribute];
    const updateValue = (newValue) => setAttributes({
        [attribute]: newValue
    });

    switch (config.type) {
        case 'toggle':
            return (
                <ToggleControl
                    key={attribute}
                    label={label}
                    checked={Boolean(value)}
                    onChange={updateValue}
                />
            );
        case 'text':
            return (
                <TextControl
                    key={attribute}
                    label={label}
                    value={value || ''}
                    placeholder={config.placeholder || ''}
                    onChange={updateValue}
                />
            );
        case 'date':
            return (
                <div key={attribute} style={{ marginBottom: '16px' }}>
                    <DatePicker
                        currentDate={value || undefined}
                        onChange={updateValue}
                    />
                </div>
            );
        case 'datetime':
        case 'time':
            return (
                <div key={attribute} style={{ marginBottom: '16px' }}>
                    <DateTimePicker
                        currentDate={value || undefined}
                        is12Hour={true}
                        onChange={updateValue}
                    />
                </div>
            );
        case 'long-text':
            return (
                <TextareaControl
                    key={attribute}
                    label={label}
                    value={value || ''}
                    placeholder={config.placeholder || ''}
                    onChange={updateValue}
                />
            );
        case 'select':
            return config.options ? (
                <SelectControl
                    key={attribute}
                    label={label}
                    value={value || ''}
                    options={config.options}
                    onChange={updateValue}
                />
            ) : null;
        case 'multi-select':
            return config.options ? (
                <Fragment key={attribute}>
                    {config.options.map((option) => {
                        const optionValue = typeof option === 'string' ? option : option.value;
                        const optionLabel = typeof option === 'string' ? option : option.label;
                        const selectedValues = Array.isArray(value) ? value : [];

                        return (
                            <CheckboxControl
                                key={optionValue}
                                label={optionLabel}
                                checked={selectedValues.includes(optionValue)}
                                onChange={(checked) => updateValue(
                                    checked
                                        ? [...selectedValues, optionValue]
                                        : selectedValues.filter((item) => item !== optionValue)
                                )}
                            />
                        );
                    })}
                </Fragment>
            ) : null;
        default:
            return null;
    }
};

const MerosDynamicBlockControls = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const { name, attributes, setAttributes } = props;

        if (attributes.merosDynamicBlock && attributes.merosControls) {
            const controls = attributes.merosControls;
            return (
                <Fragment>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title={__('Settings', 'meros-theme')} initialOpen={true}>
                            {Object.entries(controls).map(([attribute, config]) => {
                                const type = config.type;
                                if (!type) return null;

                                const label = String(config.label || attribute)
                                    .replace(/([a-z])([A-Z])/g, '$1 $2')
                                    .replace(/[_-]+/g, ' ')
                                    .replace(/\s+/g, ' ')
                                    .trim()
                                    .replace(/^./, (character) => character.toUpperCase());

                                return createControl(attribute, config, attributes, setAttributes, label);
                            })}
                        </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        }

        return <BlockEdit {...props} />
    };
}, 'merosDynamicBlockControls');

wp.domReady(() => {
    addFilter('editor.BlockEdit', 'meros/dynamic-block-controls', MerosDynamicBlockControls);
});
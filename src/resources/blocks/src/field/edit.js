import { useBladeView } from '../../hooks/useBladeView';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { wrapper, component, attrs } = attributes;

    const data = {
        component,
        field: {
            ...attrs,
        },
    };
    
    const bladeViewContent = useBladeView(wrapper, data);

    return (
        <div>
            <div dangerouslySetInnerHTML={{ __html: bladeViewContent }} />
        </div>
    );
}
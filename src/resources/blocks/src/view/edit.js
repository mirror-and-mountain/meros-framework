import { useBladeView } from '../../hooks/useBladeView';

export default function Edit({ attributes, setAttributes }) {
    const { view, data } = attributes;
    const bladeViewContent = useBladeView(view, data);

    return (
        <div>
            <div dangerouslySetInnerHTML={{ __html: bladeViewContent }} />
        </div>
    );
}
import { SelectedSectionEditableFields, type SelectedSectionEditableFieldsProps } from '@/builder/inspector/SelectedSectionEditableFields';

export type CmsInspectorPanelProps = SelectedSectionEditableFieldsProps;

export function CmsInspectorPanel(props: CmsInspectorPanelProps) {
    return <SelectedSectionEditableFields {...props} />;
}

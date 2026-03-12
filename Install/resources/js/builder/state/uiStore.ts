import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useUiStore() {
    return useBuilderStore(useShallow((state) => ({
        leftPanelTab: state.leftPanelTab,
        rightPanelTab: state.rightPanelTab,
        assetsOpen: state.assetsOpen,
        aiPanelOpen: state.aiPanelOpen,
        collapsedLayerNodeIds: state.collapsedLayerNodeIds,
        zoom: state.zoom,
        devicePreset: state.devicePreset,
        guidesVisible: state.guidesVisible,
        viewportMode: state.viewportMode,
        setLeftPanelTab: state.setLeftPanelTab,
        setAssetsOpen: state.setAssetsOpen,
        setAiPanelOpen: state.setAiPanelOpen,
        toggleLayerCollapse: state.toggleLayerCollapse,
        setZoom: state.setZoom,
        setDevicePreset: state.setDevicePreset,
        setGuidesVisible: state.setGuidesVisible,
        setViewportMode: state.setViewportMode,
    })));
}

import { useMemo } from 'react';
import { Layers3, Library, Monitor, PanelRight, Save, Smartphone, Sparkles, Tablet, Undo2, Redo2, Images, UploadCloud } from 'lucide-react';
import { toast } from 'sonner';
import { useShallow } from 'zustand/shallow';
import { Button } from '@/components/ui/button';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { publishBuilderDocument } from '@/builder/api/builderApi';
import { useBuilderStore } from '@/builder/state/builderStore';
import { CanvasWorkspace } from '@/builder/canvas/CanvasWorkspace';
import { InspectorPanel } from '@/builder/inspector/InspectorPanel';
import { LayersPanel } from '@/builder/layers/LayersPanel';
import { ComponentsLibraryPanel } from '@/builder/library/ComponentsLibraryPanel';
import { AssetsPanel } from '@/builder/assets/AssetsPanel';
import { AIEditPanel } from '@/builder/ai/AIEditPanel';
import { resolveBuilderNodeLabel } from '@/builder/utils/document';

interface BuilderLayoutProps {
    project: {
        id: string;
        name: string;
        subdomain?: string | null;
        published_at?: string | null;
    };
    endpoints: BuilderApiEndpoints;
}

export function BuilderLayout({ project, endpoints }: BuilderLayoutProps) {
    const {
        builderDocument,
        publishedDocument,
        activePageId,
        selectedNodeId,
        leftPanelTab,
        dirty,
        lastSavedVersion,
        devicePreset,
        setLeftPanelTab,
        setAssetsOpen,
        setAiPanelOpen,
        setDevicePreset,
        undo,
        redo,
        undoStack,
        redoStack,
        setPublishedDocument,
    } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        publishedDocument: state.publishedDocument,
        activePageId: state.activePageId,
        selectedNodeId: state.selectedNodeId,
        leftPanelTab: state.leftPanelTab,
        dirty: state.dirty,
        lastSavedVersion: state.lastSavedVersion,
        devicePreset: state.devicePreset,
        setLeftPanelTab: state.setLeftPanelTab,
        setAssetsOpen: state.setAssetsOpen,
        setAiPanelOpen: state.setAiPanelOpen,
        setDevicePreset: state.setDevicePreset,
        undo: state.undo,
        redo: state.redo,
        undoStack: state.undoStack,
        redoStack: state.redoStack,
        setPublishedDocument: state.setPublishedDocument,
    })));

    const activePage = builderDocument.pages[activePageId] ?? builderDocument.pages[builderDocument.rootPageId];
    const selectedNode = selectedNodeId ? builderDocument.nodes[selectedNodeId] : null;
    const selectedLabel = resolveBuilderNodeLabel(selectedNode);
    const saveLabel = dirty ? 'Unsaved changes' : `Saved v${lastSavedVersion}`;
    const publishLabel = publishedDocument?.publishedAt
        ? `Published ${new Date(publishedDocument.publishedAt).toLocaleString()}`
        : 'Draft only';

    const leftPanel = useMemo(() => {
        switch (leftPanelTab) {
            case 'library':
                return <ComponentsLibraryPanel />;
            case 'assets':
                return <AssetsPanel endpoints={endpoints} />;
            case 'ai':
                return <AIEditPanel endpoints={endpoints} />;
            case 'layers':
            default:
                return <LayersPanel />;
        }
    }, [endpoints, leftPanelTab]);

    return (
        <div className="grid h-screen grid-rows-[auto_1fr_auto] overflow-hidden bg-slate-950 text-slate-900">
            <header className="border-b border-slate-800 bg-slate-950 px-5 py-4 text-slate-100">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.3em] text-slate-400">Webu Builder V2</div>
                        <div className="mt-1 flex items-center gap-3">
                            <h1 className="text-xl font-semibold">{project.name}</h1>
                            <span className="rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-300">
                                {activePage?.title ?? 'Untitled page'}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="outline" className="border-slate-700 bg-transparent text-slate-100 hover:bg-slate-900" onClick={undo} disabled={undoStack.length === 0}>
                            <Undo2 className="mr-2 h-4 w-4" />
                            Undo
                        </Button>
                        <Button type="button" variant="outline" className="border-slate-700 bg-transparent text-slate-100 hover:bg-slate-900" onClick={redo} disabled={redoStack.length === 0}>
                            <Redo2 className="mr-2 h-4 w-4" />
                            Redo
                        </Button>
                        <div className="mx-2 hidden h-8 w-px bg-slate-800 md:block" />
                        <div className="flex items-center rounded-full border border-slate-800 bg-slate-900 p-1">
                            {[
                                { key: 'desktop', icon: Monitor },
                                { key: 'tablet', icon: Tablet },
                                { key: 'mobile', icon: Smartphone },
                            ].map(({ key, icon: Icon }) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setDevicePreset(key as typeof devicePreset)}
                                    className={`rounded-full px-3 py-2 ${devicePreset === key ? 'bg-white text-slate-950' : 'text-slate-400'}`}
                                >
                                    <Icon className="h-4 w-4" />
                                </button>
                            ))}
                        </div>
                        <Button
                            type="button"
                            className="bg-emerald-500 text-slate-950 hover:bg-emerald-400"
                            onClick={async () => {
                                try {
                                    const published = await publishBuilderDocument(endpoints.publish);
                                    setPublishedDocument(published);
                                    toast.success('Draft published to the V2 snapshot');
                                } catch (error) {
                                    toast.error(error instanceof Error ? error.message : 'Publish failed');
                                }
                            }}
                        >
                            <UploadCloud className="mr-2 h-4 w-4" />
                            Publish
                        </Button>
                    </div>
                </div>
            </header>

            <div className="grid min-h-0 grid-cols-[320px_1fr_360px] overflow-hidden">
                <aside className="border-r border-slate-200 bg-slate-50">
                    <div className="grid grid-cols-4 gap-1 border-b border-slate-200 p-2">
                        <button
                            type="button"
                            onClick={() => setLeftPanelTab('layers')}
                            className={`flex items-center justify-center rounded-xl px-3 py-2 ${leftPanelTab === 'layers' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
                        >
                            <Layers3 className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => setLeftPanelTab('library')}
                            className={`flex items-center justify-center rounded-xl px-3 py-2 ${leftPanelTab === 'library' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
                        >
                            <Library className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setLeftPanelTab('assets');
                                setAssetsOpen(true);
                            }}
                            className={`flex items-center justify-center rounded-xl px-3 py-2 ${leftPanelTab === 'assets' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
                        >
                            <Images className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setLeftPanelTab('ai');
                                setAiPanelOpen(true);
                            }}
                            className={`flex items-center justify-center rounded-xl px-3 py-2 ${leftPanelTab === 'ai' ? 'bg-white shadow-sm' : 'text-slate-500'}`}
                        >
                            <Sparkles className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="h-[calc(100%-57px)]">
                        {leftPanel}
                    </div>
                </aside>

                <main className="min-h-0 overflow-hidden">
                    <CanvasWorkspace />
                </main>

                <aside className="border-l border-slate-200 bg-white">
                    <InspectorPanel endpoints={endpoints} />
                </aside>
            </div>

            <footer className="border-t border-slate-200 bg-white px-5 py-3">
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1">
                            <Save className="h-4 w-4" />
                            {saveLabel}
                        </span>
                        <span className="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1">
                            <PanelRight className="h-4 w-4" />
                            {selectedLabel}
                        </span>
                    </div>
                    <div className="rounded-full border border-slate-200 px-3 py-1">
                        {publishLabel}
                    </div>
                </div>
            </footer>
        </div>
    );
}

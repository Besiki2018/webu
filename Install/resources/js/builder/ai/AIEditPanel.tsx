import { useState } from 'react';
import { Loader2, Sparkles } from 'lucide-react';
import { useShallow } from 'zustand/shallow';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { requestAiBuilderSuggestions } from '@/builder/api/builderApi';
import { adaptAiSuggestions } from './aiMutationAdapter';
import { buildAiPromptContext } from './aiContextBuilder';
import { useBuilderStore } from '@/builder/state/builderStore';

interface AIEditPanelProps {
    endpoints: BuilderApiEndpoints;
}

export function AIEditPanel({ endpoints }: AIEditPanelProps) {
    const [prompt, setPrompt] = useState('');
    const {
        builderDocument,
        activePageId,
        selectedNodeId,
        applyStatus,
        setApplyStatus,
        setSuggestedMutations,
        suggestedMutations,
        applyMutations,
        appendConversation,
        setSelectedNodeContext,
    } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        selectedNodeId: state.selectedNodeId,
        applyStatus: state.applyStatus,
        setApplyStatus: state.setApplyStatus,
        setSuggestedMutations: state.setSuggestedMutations,
        suggestedMutations: state.suggestedMutations,
        applyMutations: state.applyMutations,
        appendConversation: state.appendConversation,
        setSelectedNodeContext: state.setSelectedNodeContext,
    })));

    const context = buildAiPromptContext(builderDocument, activePageId, selectedNodeId);

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-200 px-4 py-3">
                <div className="text-sm font-semibold text-slate-900">AI Assistant</div>
                <div className="text-xs text-slate-500">Structured mutations only. Review before applying.</div>
            </div>

            <div className="space-y-4 border-b border-slate-200 p-4">
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    <div className="font-medium text-slate-900">Active context</div>
                    <div className="mt-1">{context.pageSummary}</div>
                    {context.selectedNodeContext ? (
                        <div className="mt-1">Selected: {context.selectedNodeContext}</div>
                    ) : null}
                </div>
                <Textarea
                    rows={5}
                    value={prompt}
                    placeholder="Example: Add a stronger hero with a clearer headline and move the newsletter above the footer."
                    onChange={(event) => setPrompt(event.target.value)}
                />
                <Button
                    type="button"
                    className="w-full"
                    disabled={applyStatus === 'running' || prompt.trim() === ''}
                    onClick={async () => {
                        setApplyStatus('running');
                        setSelectedNodeContext(context.selectedNodeContext);
                        appendConversation({
                            id: `user-${Date.now()}`,
                            role: 'user',
                            content: prompt,
                        });

                        try {
                            const suggestions = adaptAiSuggestions(await requestAiBuilderSuggestions(
                                endpoints.aiSuggestions,
                                prompt,
                                builderDocument,
                                selectedNodeId,
                            ));
                            setSuggestedMutations(suggestions);
                            appendConversation({
                                id: `assistant-${Date.now()}`,
                                role: 'assistant',
                                content: suggestions.length > 0
                                    ? `Prepared ${suggestions.length} structured suggestion${suggestions.length > 1 ? 's' : ''}.`
                                    : 'No safe structured suggestions were returned.',
                            });
                            setApplyStatus('idle');
                        } catch (error) {
                            appendConversation({
                                id: `assistant-error-${Date.now()}`,
                                role: 'assistant',
                                content: error instanceof Error ? error.message : 'Failed to create suggestions.',
                            });
                            setSuggestedMutations([]);
                            setApplyStatus('error');
                        }
                    }}
                >
                    {applyStatus === 'running' ? (
                        <>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Generating suggestions
                        </>
                    ) : (
                        <>
                            <Sparkles className="mr-2 h-4 w-4" />
                            Suggest mutations
                        </>
                    )}
                </Button>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto p-4">
                {suggestedMutations.map((suggestion) => (
                    <div key={suggestion.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="text-sm font-semibold text-slate-900">{suggestion.title}</div>
                        <p className="mt-1 text-sm text-slate-600">{suggestion.summary}</p>
                        <div className="mt-3 space-y-2 rounded-2xl bg-slate-50 p-3">
                            {suggestion.mutations.map((mutation, index) => (
                                <div key={`${suggestion.id}-${index}`} className="text-xs text-slate-600">
                                    <span className="font-semibold text-slate-900">{mutation.type}</span>{' '}
                                    {JSON.stringify(mutation.payload)}
                                </div>
                            ))}
                        </div>
                        <Button
                            type="button"
                            className="mt-4 w-full"
                            onClick={() => {
                                applyMutations(suggestion.mutations);
                                appendConversation({
                                    id: `assistant-applied-${Date.now()}`,
                                    role: 'system',
                                    content: `Applied suggestion: ${suggestion.title}`,
                                });
                                setApplyStatus('success');
                            }}
                        >
                            Apply suggestion
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
}

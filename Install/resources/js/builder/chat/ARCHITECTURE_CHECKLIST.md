# Builder Architecture Reconciliation Checklist

## Legacy (old-Install) vs Current Patterns

### ✅ Good patterns from old-Install to preserve/restore
- [x] usePreviewInspector: clear lifecycle (postMessage, ready, mode, context menu)
- [x] useBuilderChat: transport/streaming separate from UI
- [x] InspectPreview: delegates to usePreviewInspector, thin orchestration
- [x] Chat.tsx: uses hooks, view modes, structure panel
- [x] BuilderService: AI config only, no UI logic

### Current Install improvements (in progress)
- [x] Chat.tsx: useGeneratedCodePreview hook integrated (~80 lines extracted)
- [x] InspectPreview: useInspectSelectionLifecycle hook extracted (~350 lines to hook)
- [ ] Cms.tsx (36k lines): extract into focused modules
- [x] Real behavior tests: builderSelectionToSidebarBehavior.test.ts added
- [x] Single canonical mutation model: applyBuilderChangeSetPipeline / updatePipeline (verified)

### Module boundaries (target)
1. **chat/build transport** - useBuilderChat (exists)
2. **inspect selection lifecycle** - extract from InspectPreview
3. **selected target resolution** - chatBuilderSelection (exists)
4. **sidebar editable field generation** - filterInspectorSchemaFields (exists)
5. **structure panel state** - extract from Chat
6. **preview iframe sync** - useChatEmbeddedBuilderBridge (exists)
7. **CMS embedded bridge** - useEmbeddedBuilderBridge (exists)
8. **draft persistence** - useDraftPersistSchedule (exists)
9. **AI site-editor** - useAiSiteEditor (exists)
10. **mutation ack/rollback** - chatBuilderMutationFlow (exists)

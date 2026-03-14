import type { BuilderPageModel } from '@/builder/model/pageModel';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import type {
    GeneratedPage,
    WorkspaceManifest,
} from '@/builder/codegen/types';

import {
    applyCmsBindingModelToBuilderPageModel,
    applyCmsBindingModelToContentJson,
    buildCmsBindingModelFromBuilderPageModel,
} from './cmsBindingModel';
import { routeBuilderOperationsToCmsEdit } from './editRouting';
import { buildCmsSyncPlan } from './cmsSyncPlan';
import type { CmsBindingModel, CmsBindingProvenanceEditor, CmsSyncPlan, RoutedCmsEdit } from './types';
import {
    applyCmsBindingModelToGeneratedPage,
    applyCmsBindingModelToWorkspaceManifest,
} from './workspaceCmsSync';

export interface PrepareVisualBuilderCmsEditExecutionInput {
    page: {
        id: string | number | null;
        slug: string | null;
        title: string | null;
        seoTitle?: string | null;
        seoDescription?: string | null;
    };
    model: BuilderPageModel;
    contentJson: Record<string, unknown>;
    operations?: BuilderUpdateOperation[];
    generatedPage?: GeneratedPage | null;
    manifest?: WorkspaceManifest | null;
    dirtyPaths?: string[];
    editor?: CmsBindingProvenanceEditor;
    createdBy?: CmsBindingProvenanceEditor;
    timestamp?: string | null;
}

export interface PreparedVisualBuilderCmsEditExecution {
    bindingModel: CmsBindingModel;
    routedEdit: RoutedCmsEdit;
    syncPlan: CmsSyncPlan;
    model: BuilderPageModel;
    contentJson: Record<string, unknown>;
    generatedPage: GeneratedPage | null;
    manifest: WorkspaceManifest | null;
}

export function prepareVisualBuilderCmsEditExecution(
    input: PrepareVisualBuilderCmsEditExecutionInput,
): PreparedVisualBuilderCmsEditExecution {
    const bindingModel = buildCmsBindingModelFromBuilderPageModel({
        page: input.page,
        model: input.model,
        editor: input.editor ?? 'visual_builder',
        createdBy: input.createdBy ?? input.editor ?? 'visual_builder',
        timestamp: input.timestamp ?? null,
    });
    const routedEdit = routeBuilderOperationsToCmsEdit(
        input.operations ?? [],
        input.model.sections.map((section) => ({
            localId: section.localId,
            type: section.type,
            props: section.props,
            propsText: JSON.stringify(section.props),
            propsError: null,
            bindingMeta: section.bindingMeta,
        } satisfies SectionDraft)),
    );
    const syncPlan = buildCmsSyncPlan(bindingModel, {
        route: routedEdit.route,
        filePaths: input.dirtyPaths,
    });

    return {
        bindingModel,
        routedEdit,
        syncPlan,
        model: applyCmsBindingModelToBuilderPageModel(input.model, bindingModel),
        contentJson: applyCmsBindingModelToContentJson(input.contentJson, bindingModel),
        generatedPage: input.generatedPage ? applyCmsBindingModelToGeneratedPage(input.generatedPage, bindingModel) : null,
        manifest: input.manifest ? applyCmsBindingModelToWorkspaceManifest(input.manifest, bindingModel) : null,
    };
}

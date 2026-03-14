import type { GeneratedProjectGraph } from '@/builder/codegen/types';
import type { GeneratedBuilderPageModelEntry } from '@/builder/codegen/projectGraphToBuilderModel';
import type { WorkspacePlan } from '@/builder/codegen/workspacePlan';
import type { AiWorkspaceOperation } from '@/builder/codegen/aiWorkspaceOps';
import type { ProjectType } from '@/builder/projectTypes';

export type ImageImportSourceKind = 'upload' | 'url' | 'screenshot' | 'reference';
export type ImageImportMode = 'reference' | 'recreate';

export type ImageImportVisualBlockKind =
    | 'navbar'
    | 'hero'
    | 'feature-list'
    | 'card-grid'
    | 'cta'
    | 'footer'
    | 'gallery'
    | 'form'
    | 'grid'
    | 'product-grid'
    | 'testimonial-strip'
    | 'content'
    | 'logo-strip'
    | 'pricing'
    | 'faq'
    | 'unknown';

export type ImageImportInteractiveModuleKind =
    | 'booking'
    | 'ecommerce'
    | 'newsletter'
    | 'contact_form'
    | 'blog';

export type ImageImportSpacingDensity = 'airy' | 'balanced' | 'compact';
export type ImageImportAlignment = 'left' | 'center' | 'split' | 'grid' | 'stack';
export type ImageImportAccentTone = 'modern' | 'minimal' | 'bold' | 'corporate' | 'editorial';

export interface ImageImportBounds {
    x: number;
    y: number;
    width: number;
    height: number;
    unit?: 'normalized' | 'pixel';
}

export interface ImageImportBlockContent {
    eyebrow: string | null;
    title: string | null;
    subtitle: string | null;
    body: string | null;
    ctaLabel: string | null;
    secondaryCtaLabel: string | null;
    items: string[];
    labels: string[];
    imageUrls: string[];
}

export interface ImageImportBlockLayoutIntent {
    columns: number | null;
    preserveHierarchy: boolean;
    preserveSpacing: boolean;
    hasMedia: boolean;
    hasButtons: boolean;
    density: ImageImportSpacingDensity;
    alignment: ImageImportAlignment;
}

export interface ImageImportVisualBlock {
    id: string;
    kind: ImageImportVisualBlockKind;
    order: number;
    level: number;
    parentId: string | null;
    confidence: number;
    bounds: ImageImportBounds | null;
    content: ImageImportBlockContent;
    layout: ImageImportBlockLayoutIntent;
    evidence: string[];
}

export interface ImageImportStyleDirection {
    primaryStyle: ImageImportAccentTone;
    isDark: boolean;
    spacing: ImageImportSpacingDensity;
    borderTreatment: 'soft' | 'sharp' | 'rounded';
    visualWeight: 'light' | 'balanced' | 'strong';
}

export interface ImageImportFunctionSignal {
    kind: ImageImportInteractiveModuleKind;
    confidence: number;
    evidence: string[];
    sourceBlockIds: string[];
}

export interface ImageImportDesignExtraction {
    schemaVersion: 1;
    sourceKind: ImageImportSourceKind;
    sourceLabel: string | null;
    projectType: ProjectType;
    mode: ImageImportMode;
    blocks: ImageImportVisualBlock[];
    functionSignals: ImageImportFunctionSignal[];
    styleDirection: ImageImportStyleDirection;
    warnings: string[];
    extractedAt: string | null;
    metadata: Record<string, unknown>;
}

export type ImageImportLayoutNodeKind =
    | 'header'
    | 'hero'
    | 'features'
    | 'gallery'
    | 'form'
    | 'grid'
    | 'product-grid'
    | 'testimonials'
    | 'cta'
    | 'footer'
    | 'faq'
    | 'content'
    | 'generated';

export interface ImageImportLayoutNode {
    id: string;
    kind: ImageImportLayoutNodeKind;
    sourceBlockIds: string[];
    order: number;
    sectionLabel: string;
    registryHint: string | null;
    mode: ImageImportMode;
    propsSeed: Record<string, unknown>;
    preserveHierarchy: boolean;
    preserveSpacing: boolean;
    repetition: number;
    interactiveModule: ImageImportInteractiveModuleKind | null;
    metadata: Record<string, unknown>;
}

export interface ImageImportLayoutInference {
    nodes: ImageImportLayoutNode[];
    functionModules: ImageImportFunctionSignal[];
    warnings: string[];
}

export interface ImageImportGeneratedComponentSpec {
    filePath: string;
    componentName: string;
    template: 'hero' | 'grid' | 'form' | 'content';
}

export interface ImageImportComponentMatch {
    nodeId: string;
    matchKind: 'registry' | 'generated';
    registryKey: string | null;
    displayName: string;
    componentName: string;
    sourceFilePath: string | null;
    ownerType: 'layout' | 'component' | 'section';
    score: number;
    rationale: string;
    props: Record<string, unknown>;
    generatedComponent: ImageImportGeneratedComponentSpec | null;
    metadata: Record<string, unknown>;
}

export type ImageImportRunPhase =
    | 'idle'
    | 'extracting_design'
    | 'inferring_layout'
    | 'matching_components'
    | 'building_graph'
    | 'planning_workspace'
    | 'building_preview'
    | 'ready'
    | 'failed';

export interface ImageImportProjectPlan {
    extraction: ImageImportDesignExtraction;
    layout: ImageImportLayoutInference;
    componentMatches: ImageImportComponentMatch[];
    projectGraph: GeneratedProjectGraph;
    workspacePlan: WorkspacePlan;
    builderModels: GeneratedBuilderPageModelEntry[];
    workspaceOperations: AiWorkspaceOperation[];
}

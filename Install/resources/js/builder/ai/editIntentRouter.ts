export type AiEditIntent =
    | 'prop_patch'
    | 'structure_change'
    | 'page_change'
    | 'file_change'
    | 'regeneration_request';

export type AiEditIntentConfidence = 'high' | 'medium' | 'low';

export interface RouteAiEditIntentInput {
    message: string;
    hasSelectedElement?: boolean;
    viewMode?: string | null;
    selectedFile?: string | null;
}

export interface RoutedAiEditIntent {
    intent: AiEditIntent;
    confidence: AiEditIntentConfidence;
    reason:
        | 'empty_message'
        | 'selected_element_scope'
        | 'regeneration_keywords'
        | 'selected_workspace_file'
        | 'explicit_file_keywords'
        | 'code_mode_default'
        | 'page_change_keywords'
        | 'structure_change_keywords'
        | 'default_builder_safe_path';
}

const REGENERATION_PATTERNS = [
    /\bregenerate\b/i,
    /\brebuild\b/i,
    /\bresync\b/i,
    /\bsync\b.+\b(site|workspace|code)\b/i,
    /\brefresh\b.+\bpreview\b/i,
    /regenerate.+\b(code|workspace|preview)\b/i,
    /rebuild.+\bpreview\b/i,
    /sync.+\bcode.+\bsite\b/i,
    /გადააგენერ/i,
    /ხელახლა.+აგებ/i,
    /ხელახლა.+preview/i,
    /სინქრონ/i,
];

const FILE_CHANGE_PATTERNS = [
    /\bfile\b/i,
    /\bfiles\b/i,
    /\bcode\b/i,
    /\bworkspace\b/i,
    /\brepository\b/i,
    /\brepo\b/i,
    /\broute file\b/i,
    /\bcomponent file\b/i,
    /\butility\b/i,
    /\bhook\b/i,
    /\bimport\b/i,
    /\bscaffold\b/i,
    /\bsrc\/[^\s]+/i,
    /\bpublic\/[^\s]+/i,
    /\.[jt]sx?\b/i,
    /\.css\b/i,
    /\.json\b/i,
    /კოდ/i,
    /ვორკსპეის/i,
    /რეპოზიტორი/i,
    /ფაილ/i,
    /რუტ/i,
];

const PAGE_CHANGE_PATTERNS = [
    /\bcreate\b.+\bpage\b/i,
    /\badd\b.+\bpage\b/i,
    /\bduplicate\b.+\bpage\b/i,
    /\bclone\b.+\bpage\b/i,
    /\babout page\b/i,
    /\bcampaign page\b/i,
    /\blanding page\b/i,
    /\bnew page\b/i,
    /\bpage for\b/i,
    /შექმენი.+გვერდ/i,
    /დაამატე.+გვერდ/i,
    /დააკოპირე.+გვერდ/i,
    /კამპანიის.+გვერდ/i,
    /about.+გვერდ/i,
];

const STRUCTURE_CHANGE_PATTERNS = [
    /\badd\b.+\bsection\b/i,
    /\bremove\b.+\bsection\b/i,
    /\breorder\b.+\bsection\b/i,
    /\bmove\b.+\bsection\b/i,
    /\bdelete\b.+\bsection\b/i,
    /\bpricing section\b/i,
    /\btestimonial(?:s)?\b.+\b(section|block)\b/i,
    /\bfaq\b.+\bsection\b/i,
    /\bhero\b.+\bsection\b/i,
    /\bcta\b.+\bsection\b/i,
    /\bnavbar\b.+\b(section|block)\b/i,
    /\bfooter\b.+\bsection\b/i,
    /დაამატე.+სექცი/i,
    /წაშალე.+სექცი/i,
    /გადაანაცვლე.+სექცი/i,
    /შეცვალე.+სექციების.+რიგ/i,
    /pricing.+სექცი/i,
    /testimonial.+ბლოკ/i,
];

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

function matchesAny(message: string, patterns: RegExp[]): boolean {
    return patterns.some((pattern) => pattern.test(message));
}

function isWorkspaceFilePath(path: string): boolean {
    return /^(src|public)\//i.test(path) || /\.[a-z0-9]+$/i.test(path);
}

export function routeAiEditIntent(input: RouteAiEditIntentInput): RoutedAiEditIntent {
    const message = normalizeText(input.message);
    const selectedFile = normalizeText(input.selectedFile);
    const viewMode = normalizeText(input.viewMode).toLowerCase();

    if (message === '') {
        return {
            intent: 'prop_patch',
            confidence: 'low',
            reason: 'empty_message',
        };
    }

    if (input.hasSelectedElement) {
        return {
            intent: 'prop_patch',
            confidence: 'high',
            reason: 'selected_element_scope',
        };
    }

    if (matchesAny(message, REGENERATION_PATTERNS)) {
        return {
            intent: 'regeneration_request',
            confidence: 'high',
            reason: 'regeneration_keywords',
        };
    }

    if (selectedFile !== '' && isWorkspaceFilePath(selectedFile)) {
        return {
            intent: 'file_change',
            confidence: viewMode === 'code' ? 'high' : 'medium',
            reason: 'selected_workspace_file',
        };
    }

    if (matchesAny(message, FILE_CHANGE_PATTERNS)) {
        return {
            intent: 'file_change',
            confidence: 'high',
            reason: 'explicit_file_keywords',
        };
    }

    if (matchesAny(message, PAGE_CHANGE_PATTERNS)) {
        return {
            intent: 'page_change',
            confidence: 'high',
            reason: 'page_change_keywords',
        };
    }

    if (matchesAny(message, STRUCTURE_CHANGE_PATTERNS)) {
        return {
            intent: 'structure_change',
            confidence: 'high',
            reason: 'structure_change_keywords',
        };
    }

    if (viewMode === 'code') {
        return {
            intent: 'file_change',
            confidence: 'medium',
            reason: 'code_mode_default',
        };
    }

    return {
        intent: 'prop_patch',
        confidence: 'medium',
        reason: 'default_builder_safe_path',
    };
}

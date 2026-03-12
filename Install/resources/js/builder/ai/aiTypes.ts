import type { BuilderMutation } from '@/builder/mutations/dispatchBuilderMutation';

export interface BuilderAiSuggestion {
    id: string;
    title: string;
    summary: string;
    mutations: BuilderMutation[];
}

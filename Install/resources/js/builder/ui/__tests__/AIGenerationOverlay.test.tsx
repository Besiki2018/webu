import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useBuilderStore } from '@/builder/store/builderStore';
import { AIGenerationOverlay } from '../AIGenerationOverlay';

vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: (key: string) => key,
        locale: 'en',
    }),
}));

describe('AIGenerationOverlay', () => {
    beforeEach(() => {
        useBuilderStore.getState().reset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the builder-canvas generation stages from store state', () => {
        act(() => {
            useBuilderStore.getState().setGenerationState({
                stage: 'planning_structure',
                progress: {
                    rawStatus: 'planning_structure',
                    headline: 'Planning the layout...',
                    detail: 'Planning the layout and blueprint.',
                    isActive: true,
                    isFailed: false,
                    readyForBuilder: false,
                    locked: true,
                    errorMessage: null,
                    recoveryMessage: null,
                    steps: [],
                },
                diagnostics: {
                    prompt: 'Create a veterinary clinic website',
                    generationMode: 'blueprint',
                    selectedProjectType: 'business',
                    selectedBusinessType: 'Vet Clinic',
                    selectedSectionTypes: ['header', 'hero', 'services', 'footer'],
                    selectedSections: ['header', 'hero', 'services', 'footer'],
                    selectedComponentKeys: ['webu_header_01', 'webu_general_hero_01', 'webu_services_01', 'webu_footer_01'],
                    validationPassed: true,
                    emergencyFallbackUsed: false,
                    fallbackUsed: false,
                    failedStep: null,
                    rootCause: null,
                    events: [],
                    stageTimingsMs: {
                        layoutPlanning: 4.5,
                        componentSelection: 8.25,
                        contentGeneration: 14,
                        treeAssembly: 5.75,
                        designOptimization: 6.5,
                        validation: 2.25,
                        previewRendering: null,
                    },
                    designQualityReport: {
                        overallScore: 84,
                        initialOverallScore: 78,
                        autoImproved: true,
                        categoryScores: {
                            spacing: 82,
                            typography: 83,
                            contrast: 84,
                            hierarchy: 85,
                            layoutBalance: 83,
                            ctaClarity: 87,
                        },
                        issues: [],
                        improvements: [],
                        improvementsApplied: [],
                    },
                } as never,
            });
        });

        render(<AIGenerationOverlay />);

        expect(screen.getByLabelText('Generating your website...')).toBeInTheDocument();
        expect(screen.getByText('Understanding project')).toBeInTheDocument();
        expect(screen.getByText('Planning layout')).toBeInTheDocument();
        expect(screen.getByText('Selecting components')).toBeInTheDocument();
        expect(screen.getByText('Generating content')).toBeInTheDocument();
        expect(screen.getByText('Assembling page')).toBeInTheDocument();
        expect(screen.getByText('Design optimization')).toBeInTheDocument();
        expect(screen.getByText('Finalizing preview')).toBeInTheDocument();
        expect(screen.getByText(/Design quality 84\/100/)).toBeInTheDocument();
    });

    it('fades out after generation unlocks the canvas', () => {
        vi.useFakeTimers();

        act(() => {
            useBuilderStore.getState().setGenerationState({
                stage: 'rendering_preview',
                progress: {
                    rawStatus: 'rendering_preview',
                    headline: 'Finalizing the preview...',
                    detail: 'Finalizing the preview and unlocking the canvas.',
                    isActive: true,
                    isFailed: false,
                    readyForBuilder: false,
                    locked: true,
                    errorMessage: null,
                    recoveryMessage: null,
                    steps: [],
                },
            });
        });

        render(<AIGenerationOverlay />);

        expect(screen.getByLabelText('Generating your website...')).toBeInTheDocument();

        act(() => {
            useBuilderStore.getState().setGenerationState({
                stage: 'completed',
                progress: {
                    rawStatus: 'ready',
                    headline: 'Website ready',
                    detail: 'Website ready.',
                    isActive: false,
                    isFailed: false,
                    readyForBuilder: true,
                    locked: false,
                    errorMessage: null,
                    recoveryMessage: null,
                    steps: [],
                },
            });
        });

        expect(screen.getByLabelText('Generating your website...')).toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(260);
        });

        expect(screen.queryByLabelText('Generating your website...')).not.toBeInTheDocument();
    });
});

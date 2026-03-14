import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

import { AIInspectorPanel } from '../AIInspectorPanel';

vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: (key: string, params?: Record<string, unknown>) => {
            if (key === ':count nodes selected for AI targeting') {
                return `${params?.count ?? 0} nodes selected for AI targeting`;
            }

            return key;
        },
        locale: 'en',
    }),
}));

describe('AIInspectorPanel', () => {
    it('renders debug info, grouped fields, and invokes targeting callbacks', () => {
        const onEditWithAi = vi.fn();
        const onEditManually = vi.fn();
        const onInsertNodeTag = vi.fn();
        const onClose = vi.fn();

        render(
            <AIInspectorPanel
                primaryMention={{
                    id: 'hero-1::title',
                    targetId: 'hero-1::title',
                    aiNodeId: 'hero-1.title',
                    tagName: 'h1',
                    selector: '[data-webu-field="title"]',
                    textPreview: 'Trusted veterinary care',
                    currentValue: 'Trusted veterinary care',
                    sectionKey: 'webu_general_hero_01',
                    sectionLocalId: 'hero-1',
                    propName: 'title',
                    parameterName: 'title',
                    componentKey: 'webu_general_hero_01',
                }}
                primaryTarget={{
                    targetId: 'hero-1::title',
                    sectionLocalId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    componentType: 'webu_general_hero_01',
                    componentName: 'Hero',
                    path: 'title',
                    elementId: 'Hero.title',
                    selector: '[data-webu-field="title"]',
                    textPreview: 'Trusted veterinary care',
                    props: { title: 'Trusted veterinary care' },
                    variants: {
                        layout: {
                            kind: 'layout',
                            path: 'layout_variant',
                            active: 'hero_01',
                            options: ['hero_01', 'hero_02'],
                        },
                    },
                    allowedUpdates: {
                        scope: 'element',
                        operationTypes: ['update_prop', 'update_style'],
                        fieldPaths: ['title', 'background.color'],
                        sectionOperationTypes: ['swap_component'],
                        sectionFieldPaths: ['layout_variant'],
                    },
                    responsiveContext: {
                        currentBreakpoint: 'desktop',
                        currentInteractionState: 'normal',
                        availableBreakpoints: ['desktop', 'tablet', 'mobile'],
                        availableInteractionStates: ['normal', 'hover'],
                        supportsVisibility: true,
                        supportsResponsiveOverrides: true,
                        visibleFieldPaths: ['title'],
                        responsiveFieldPaths: ['spacing.top'],
                        stateFieldPaths: ['button.hover.background'],
                    },
                }}
                selectedMentions={[{
                    id: 'hero-1::title',
                    targetId: 'hero-1::title',
                    aiNodeId: 'hero-1.title',
                    tagName: 'h1',
                    selector: '[data-webu-field="title"]',
                    textPreview: 'Trusted veterinary care',
                    currentValue: 'Trusted veterinary care',
                }]}
                textFields={[{
                    path: 'title',
                    label: 'Title',
                    value: 'Trusted veterinary care',
                }]}
                styleFields={[{
                    path: 'background.color',
                    label: 'Background',
                    value: '#F9FAFB',
                }]}
                settingsFields={[{
                    path: 'layout_variant',
                    label: 'Layout variant',
                    value: 'hero_01',
                }]}
                onEditWithAi={onEditWithAi}
                onEditManually={onEditManually}
                onInsertNodeTag={onInsertNodeTag}
                onClose={onClose}
            />
        );

        expect(screen.getByText('AI Inspect')).toBeInTheDocument();
        expect(screen.getByText('Hero')).toBeInTheDocument();
        expect(screen.getByText('nodeId')).toBeInTheDocument();
        expect(screen.getByText('hero-1.title')).toBeInTheDocument();
        expect(screen.getAllByText('Trusted veterinary care').length).toBeGreaterThan(0);
        expect(screen.getByText('Safe AI actions')).toBeInTheDocument();
        expect(screen.getByText('Variant switching')).toBeInTheDocument();
        expect(screen.getByText('Responsive overrides')).toBeInTheDocument();
        expect(screen.getByText('Variants')).toBeInTheDocument();
        expect(screen.getByText(/Active: hero_01/)).toBeInTheDocument();
        expect(screen.getByText('Styles')).toBeInTheDocument();
        expect(screen.getByText('Component settings')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Edit with AI' }));
        fireEvent.click(screen.getByRole('button', { name: 'Edit manually' }));
        fireEvent.click(screen.getByRole('button', { name: 'Close inspector' }));
        fireEvent.click(screen.getByRole('button', { name: '@node(hero-1.title)' }));

        expect(onEditWithAi).toHaveBeenCalledTimes(1);
        expect(onEditManually).toHaveBeenCalledTimes(1);
        expect(onClose).toHaveBeenCalledTimes(1);
        expect(onInsertNodeTag).toHaveBeenCalledWith('hero-1.title');
    });
});

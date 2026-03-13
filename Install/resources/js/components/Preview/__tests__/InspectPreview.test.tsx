import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { InspectPreview } from '../InspectPreview';
import { buildEditableTargetFromMention } from '@/builder/editingState';
import { getComponentSchema, resolveComponentProps } from '@/builder/componentRegistry';
import { collectBuilderSchemaPrimitiveFieldDescriptors } from '@/lib/schemaPrimitiveFields';
import { filterInspectorSchemaFields, type InspectorSchemaField } from '@/builder/inspector/filterInspectorSchemaFields';
import type { ElementMention, PendingEdit, InspectorElement } from '@/types/inspector';

vi.mock('canvas-confetti', () => ({
    default: {
        create: vi.fn(() => vi.fn()),
    },
}));

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    },
}));

vi.mock('@/hooks/usePreviewThemeSync', () => ({
    usePreviewThemeSync: vi.fn(() => ({
        sendTheme: vi.fn(),
    })),
}));

vi.mock('@/hooks/usePreviewThemeInjection', () => ({
    usePreviewThemeInjection: vi.fn(() => ({
        applyTheme: vi.fn(),
    })),
}));

vi.mock('@/hooks/useThumbnailCapture', () => ({
    useThumbnailCapture: vi.fn(() => ({
        capture: vi.fn(),
    })),
}));

const mockElement: InspectorElement = {
    id: 'el-123',
    tagName: 'button',
    elementId: 'submit-btn',
    classNames: ['btn', 'primary'],
    textPreview: 'Submit',
    xpath: '//*[@id="submit-btn"]',
    cssSelector: '#submit-btn',
    boundingRect: { top: 100, left: 200, width: 100, height: 40 },
    attributes: { title: 'Click to submit' },
    parentTagName: 'div',
};

const mockPendingEdit: PendingEdit = {
    id: 'edit-1',
    element: mockElement,
    field: 'text',
    originalValue: 'Submit',
    newValue: 'Save',
    timestamp: new Date(),
};

function attachIframeEnvironment(iframe: HTMLIFrameElement, iframeDoc: Document) {
    Object.defineProperty(iframe, 'contentDocument', {
        configurable: true,
        value: iframeDoc,
    });
    Object.defineProperty(iframe, 'contentWindow', {
        configurable: true,
        value: {
            document: iframeDoc,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            scrollX: 0,
            scrollY: 0,
            scrollTo: vi.fn(),
        },
    });
    iframe.getBoundingClientRect = () => ({
        left: 0,
        top: 0,
        width: 1280,
        height: 720,
        right: 1280,
        bottom: 720,
        x: 0,
        y: 0,
        toJSON: () => ({}),
    });
}

function createRepeatedCardsPreviewDocument() {
    const iframeDoc = document.implementation.createHTMLDocument('');
    const section = iframeDoc.createElement('section');
    section.setAttribute('data-webu-section', 'webu_general_cards_01');
    section.setAttribute('data-webu-section-local-id', 'cards-1');

    const blankChild = iframeDoc.createElement('div');
    blankChild.textContent = '';
    section.appendChild(blankChild);

    const card = iframeDoc.createElement('article');
    const cardBody = iframeDoc.createElement('div');
    card.appendChild(cardBody);

    const titleEl = iframeDoc.createElement('h3');
    titleEl.textContent = 'Starter';
    card.appendChild(titleEl);

    const descriptionEl = iframeDoc.createElement('p');
    descriptionEl.textContent = 'Short copy';
    card.appendChild(descriptionEl);

    const linkEl = iframeDoc.createElement('a');
    linkEl.textContent = 'Read more';
    linkEl.setAttribute('href', '/starter');
    card.appendChild(linkEl);

    section.appendChild(card);
    iframeDoc.body.appendChild(section);

    return {
        iframeDoc,
        section,
        blankChild,
        card,
        cardBody,
        titleEl,
        descriptionEl,
        linkEl,
    };
}

function createHeaderMenuPreviewDocument() {
    const iframeDoc = document.implementation.createHTMLDocument('');
    const section = iframeDoc.createElement('section');
    section.setAttribute('data-webu-section', 'webu_header_01');
    section.setAttribute('data-webu-section-local-id', 'header-1');

    const blankChild = iframeDoc.createElement('div');
    section.appendChild(blankChild);

    const nav = iframeDoc.createElement('nav');
    const menuItem = iframeDoc.createElement('a');
    menuItem.textContent = 'Shop';
    menuItem.setAttribute('href', '/shop');
    const itemBody = iframeDoc.createElement('span');
    itemBody.textContent = 'Shop';
    menuItem.appendChild(itemBody);
    nav.appendChild(menuItem);
    section.appendChild(nav);
    iframeDoc.body.appendChild(section);

    return {
        iframeDoc,
        section,
        blankChild,
        nav,
        menuItem,
        itemBody,
    };
}

function createHeroPreviewDocument() {
    const iframeDoc = document.implementation.createHTMLDocument('');
    const section = iframeDoc.createElement('section');
    section.setAttribute('data-webu-section', 'webu_general_hero_01');
    section.setAttribute('data-webu-section-local-id', 'hero-1');

    const titleEl = iframeDoc.createElement('h1');
    titleEl.textContent = 'Launch faster';

    const imageEl = iframeDoc.createElement('img');
    imageEl.setAttribute('src', '/hero.jpg');
    imageEl.setAttribute('alt', 'Hero');

    const buttonEl = iframeDoc.createElement('a');
    buttonEl.setAttribute('href', '/shop');
    const buttonLabel = iframeDoc.createElement('span');
    buttonLabel.textContent = 'Shop now';
    buttonEl.appendChild(buttonLabel);

    section.appendChild(titleEl);
    section.appendChild(imageEl);
    section.appendChild(buttonEl);
    iframeDoc.body.appendChild(section);

    return {
        iframeDoc,
        titleEl,
        imageEl,
        buttonLabel,
    };
}

function createEmptyPreviewDocument() {
    const iframeDoc = document.implementation.createHTMLDocument('');
    const main = iframeDoc.createElement('main');
    iframeDoc.body.appendChild(main);

    return {
        iframeDoc,
        main,
    };
}

function toInspectorFields(componentKey: string, props: Record<string, unknown>): InspectorSchemaField[] {
    const schema = getComponentSchema(componentKey);
    const resolvedProps = resolveComponentProps(componentKey, props);

    return collectBuilderSchemaPrimitiveFieldDescriptors(schema, { values: resolvedProps }).map((field) => {
        const group = typeof field.definition.control_group === 'string' ? field.definition.control_group : 'content';
        return {
            ...field,
            control_meta: {
                type: field.type,
                label: field.label,
                group,
                responsive: field.definition.responsive === true,
                stateful: group === 'states',
                dynamic_capable: field.definition.binding_compatible !== false,
            },
        };
    });
}

describe('InspectPreview', () => {
    const previewTitleMatcher = /Preview|პრევიუ/i;
    const buildingLabelMatcher = /Building your site\.\.\.|ვებსაიტი იქმნება\.\.\./i;
    const loadingLabelMatcher = /Loading preview\.\.\.|პრევიუ იტვირთება\.\.\./i;
    const freezeNoteMatcher = /Canvas stays in place while the updated result loads\.|კანვასი ადგილზე რჩება, სანამ განახლებული შედეგი ჩაიტვირთება\./i;

    const defaultProps = {
        previewUrl: 'http://localhost:3000/preview/123',
        refreshTrigger: 0,
        isBuilding: false,
        onElementSelect: vi.fn(),
        pendingEdits: [] as PendingEdit[],
        onSaveAllEdits: vi.fn().mockResolvedValue(undefined),
        onDiscardAllEdits: vi.fn(),
        onRemoveEdit: vi.fn(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders empty state when no preview URL', () => {
        render(<InspectPreview {...defaultProps} previewUrl={null} />);

        expect(screen.getByText('Nothing built yet')).toBeInTheDocument();
        expect(screen.getByText(/start a conversation with the ai/i)).toBeInTheDocument();
    });

    it('renders iframe with cache-busting refresh token when preview URL exists', () => {
        render(<InspectPreview {...defaultProps} refreshTrigger={42} />);

        const iframe = screen.getByTitle(previewTitleMatcher);
        expect(iframe).toBeInTheDocument();
        expect(iframe).toHaveAttribute('src', 'http://localhost:3000/preview/123?t=42');
    });

    it('shows building animation in both preview and empty states', () => {
        const { rerender } = render(<InspectPreview {...defaultProps} isBuilding={true} />);
        expect(screen.getByText(buildingLabelMatcher)).toBeInTheDocument();

        rerender(<InspectPreview {...defaultProps} previewUrl={null} isBuilding={true} />);
        expect(screen.getByText(buildingLabelMatcher)).toBeInTheDocument();
    });

    it('shows pending edits panel in inspect mode and executes save/discard actions', async () => {
        render(
            <InspectPreview
                {...defaultProps}
                pendingEdits={[mockPendingEdit]}
                mode="inspect"
            />
        );

        expect(screen.getByText(/1 pending change/i)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /save all/i }));
        await waitFor(() => {
            expect(defaultProps.onSaveAllEdits).toHaveBeenCalledTimes(1);
        });

        fireEvent.click(screen.getByRole('button', { name: /discard all/i }));
        expect(defaultProps.onDiscardAllEdits).toHaveBeenCalledTimes(1);
    });

    it('does not show pending edits panel outside inspect mode', () => {
        render(
            <InspectPreview
                {...defaultProps}
                pendingEdits={[mockPendingEdit]}
                mode="preview"
            />
        );

        expect(screen.queryByText(/pending changes/i)).not.toBeInTheDocument();
    });

    it('updates iframe src when refreshTrigger changes', () => {
        const { rerender } = render(<InspectPreview {...defaultProps} refreshTrigger={1} />);

        const srcBefore = screen.getByTitle(previewTitleMatcher).getAttribute('src');
        rerender(<InspectPreview {...defaultProps} refreshTrigger={2} />);
        const srcAfter = screen.getByTitle(previewTitleMatcher).getAttribute('src');

        expect(srcBefore).not.toBe(srcAfter);
    });

    it('covers the canvas with a blocking loader while the iframe reloads', () => {
        const { rerender } = render(<InspectPreview {...defaultProps} refreshTrigger={1} />);

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        fireEvent.load(iframe);

        rerender(<InspectPreview {...defaultProps} refreshTrigger={2} />);

        expect(screen.getByText(loadingLabelMatcher)).toBeInTheDocument();
        expect(screen.getByText(freezeNoteMatcher)).toBeInTheDocument();
    });

    it('refreshes the existing iframe in place when WebuCms runtime is available', async () => {
        const refresh = vi.fn().mockResolvedValue(undefined);
        const { rerender } = render(<InspectPreview {...defaultProps} refreshTrigger={1} />);

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const srcBefore = iframe.getAttribute('src');

        Object.defineProperty(iframe, 'contentWindow', {
            configurable: true,
            value: {
                WebuCms: {
                    refresh,
                },
                addEventListener: vi.fn(),
                removeEventListener: vi.fn(),
                scrollX: 0,
                scrollY: 0,
                scrollTo: vi.fn(),
            },
        });

        fireEvent.load(iframe);

        rerender(<InspectPreview {...defaultProps} refreshTrigger={2} />);

        await waitFor(() => {
            expect(refresh).toHaveBeenCalledTimes(1);
        });

        expect(screen.getByTitle(previewTitleMatcher)).toHaveAttribute('src', srcBefore);
    });

    it('uses stable device viewport dimensions for tablet and mobile presets', () => {
        const { rerender } = render(<InspectPreview {...defaultProps} viewport="tablet" />);

        expect(screen.getByTestId('preview-scale-shell')).toHaveStyle({ width: '834px', height: '1112px' });
        const wrapper = screen.getByTestId('preview-scale-wrapper');
        expect(wrapper).toHaveStyle({ width: '834px', height: '1112px' });

        rerender(<InspectPreview {...defaultProps} viewport="mobile" />);
        expect(screen.getByTestId('preview-scale-shell')).toHaveStyle({ width: '390px', height: '844px' });
        expect(screen.getByTestId('preview-scale-wrapper')).toHaveStyle({ width: '390px', height: '844px' });
    });

    it('injects stable iframe width constraints for the preview root in inspect mode', async () => {
        render(<InspectPreview {...defaultProps} mode="inspect" viewport="desktop" />);

        expect(screen.getByTestId('preview-scale-shell')).toHaveStyle({ width: '1440px', height: '960px' });

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc } = createHeroPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            const style = iframeDoc.getElementById('webu-chat-preview-highlight-style');
            expect(style).not.toBeNull();
            expect(style?.textContent).toContain('min-width: 1200px');
            expect(style?.textContent).toContain('width: 100%');
            expect(style?.textContent).toContain('[data-webu-section]');
        });
    });

    it('falls back to the section target when clicking a blank child inside a section', async () => {
        const onElementSelect = vi.fn();
        render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                onElementSelect={onElementSelect}
                liveStructureItems={[{
                    localId: 'cards-1',
                    sectionKey: 'webu_general_cards_01',
                    label: 'Cards',
                    previewText: 'Cards',
                    props: {
                        title: 'Cards',
                        backgroundColor: '#ffffff',
                        items: [{
                            title: 'Starter',
                            description: 'Short copy',
                            link: {
                                label: 'Read more',
                                url: '/starter',
                            },
                        }],
                    },
                }]}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc, section, blankChild } = createRepeatedCardsPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelector('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]')).toBeTruthy();
        });

        const hitLayer = iframe.parentElement?.querySelector<HTMLDivElement>('div[aria-hidden="true"]');
        expect(hitLayer).toBeTruthy();

        Object.defineProperty(iframeDoc, 'elementsFromPoint', {
            configurable: true,
            value: vi.fn(() => [blankChild, section]),
        });
        Object.defineProperty(iframeDoc, 'elementFromPoint', {
            configurable: true,
            value: vi.fn(() => blankChild),
        });

        fireEvent.click(hitLayer!, { clientX: 24, clientY: 24 });

        let mention: ElementMention | null = null;
        await waitFor(() => {
            expect(onElementSelect).toHaveBeenCalledTimes(1);
            mention = onElementSelect.mock.calls[0]?.[0] ?? null;
            expect(mention?.sectionLocalId).toBe('cards-1');
            expect(mention?.parameterName ?? null).toBeNull();
            expect(mention?.targetId).toBe('cards-1::section');
        });
    });

    it('clicks a repeated child area and resolves a stable repeater scope target', async () => {
        const onElementSelect = vi.fn();
        const props = {
            logoText: 'Webu',
            backgroundColor: '#ffffff',
            menu_items: [{
                label: 'Shop',
                url: '/shop',
            }],
        };

        render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                onElementSelect={onElementSelect}
                liveStructureItems={[{
                    localId: 'header-1',
                    sectionKey: 'webu_header_01',
                    label: 'Header',
                    previewText: 'Header',
                    props,
                }]}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc, itemBody } = createHeaderMenuPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelector('[data-webu-field-scope]')).toBeTruthy();
        });

        const hitLayer = iframe.parentElement?.querySelector<HTMLDivElement>('div[aria-hidden="true"]');
        const annotatedMenuTarget = iframeDoc.querySelector<HTMLElement>('[data-webu-field-scope], [data-webu-field], [data-webu-field-url]');
        expect(hitLayer).toBeTruthy();
        expect(annotatedMenuTarget).toBeTruthy();
        expect(itemBody.closest('section')).toBeTruthy();

        Object.defineProperty(iframeDoc, 'elementsFromPoint', {
            configurable: true,
            value: vi.fn(() => annotatedMenuTarget ? [annotatedMenuTarget] : []),
        });
        Object.defineProperty(iframeDoc, 'elementFromPoint', {
            configurable: true,
            value: vi.fn(() => annotatedMenuTarget ?? null),
        });

        fireEvent.click(hitLayer!, { clientX: 24, clientY: 24 });

        let mention: ElementMention | null = null;
        await waitFor(() => {
            expect(onElementSelect).toHaveBeenCalledTimes(1);
            mention = onElementSelect.mock.calls[0]?.[0] ?? null;
            expect(mention?.sectionLocalId).toBe('header-1');
            expect(mention?.sectionKey).toBe('webu_header_01');
            expect(mention?.parameterName).toBe('menu_items.0');
            expect(mention?.targetId).toBe('header-1::menu_items.0');
        });

        const target = buildEditableTargetFromMention(mention, props);
        expect(target?.sectionLocalId).toBe('header-1');
        expect(target?.path).toBe('menu_items.0');
        expect(target?.componentPath).toBe('menu_items.0');

        const filteredFields = filterInspectorSchemaFields(
            toInspectorFields('webu_header_01', props),
            {
                previewMode: 'desktop',
                interactionState: 'normal',
                targetPath: target?.path ?? null,
                targetComponentPath: target?.componentPath ?? null,
                targetEditableFields: target?.allowedUpdates?.fieldPaths ?? [],
                elementorLike: false,
            }
        ).map((field) => field.path.join('.'));

        expect(filteredFields).toEqual(expect.arrayContaining([
            'menu_items.0.label',
            'menu_items.0.url',
        ]));
    });

    it('keeps hero child clicks deterministic and field-aware', async () => {
        const onElementSelect = vi.fn();
        render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                onElementSelect={onElementSelect}
                liveStructureItems={[{
                    localId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Hero',
                    props: {
                        title: 'Launch faster',
                        image: '/hero.jpg',
                        buttonText: 'Shop now',
                        buttonLink: '/shop',
                    },
                }]}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc, titleEl, imageEl, buttonLabel } = createHeroPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelector('[data-webu-field], [data-webu-field-url]')).toBeTruthy();
        });

        const hitLayer = iframe.parentElement?.querySelector<HTMLDivElement>('div[aria-hidden="true"]');
        const annotatedTargets = Array.from(iframeDoc.querySelectorAll<HTMLElement>('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]'));
        expect(hitLayer).toBeTruthy();
        expect(annotatedTargets.length).toBeGreaterThanOrEqual(3);
        expect(titleEl.closest('section')).toBeTruthy();
        expect(imageEl.closest('section')).toBeTruthy();
        expect(buttonLabel.closest('section')).toBeTruthy();

        const resolveAnnotatedTargetForX = (x: number) => {
            if (x < 40) {
                return annotatedTargets[0] ?? null;
            }
            if (x < 55) {
                return annotatedTargets[1] ?? null;
            }
            return annotatedTargets[2] ?? null;
        };
        Object.defineProperty(iframeDoc, 'elementsFromPoint', {
            configurable: true,
            value: vi.fn((x: number) => {
                const current = resolveAnnotatedTargetForX(x);
                return current ? [current] : [];
            }),
        });
        Object.defineProperty(iframeDoc, 'elementFromPoint', {
            configurable: true,
            value: vi.fn((x: number) => resolveAnnotatedTargetForX(x)),
        });

        fireEvent.click(hitLayer!, { clientX: 36, clientY: 36 });
        fireEvent.click(hitLayer!, { clientX: 48, clientY: 48 });
        fireEvent.click(hitLayer!, { clientX: 60, clientY: 60 });

        await waitFor(() => {
            expect(onElementSelect).toHaveBeenCalledTimes(3);
        });

        const mentions = onElementSelect.mock.calls.map((call) => call[0] as ElementMention);
        expect(mentions[0]?.sectionLocalId).toBe('hero-1');
        expect(mentions[1]?.sectionLocalId).toBe('hero-1');
        expect(mentions[2]?.sectionLocalId).toBe('hero-1');
        expect(mentions[0]?.parameterName).toBeTruthy();
        expect(mentions[1]?.parameterName).toBeTruthy();
        expect(mentions[2]?.parameterName).toBeTruthy();
        expect(new Set(mentions.map((mention) => mention.targetId)).size).toBe(3);
    });

    it('reconciles optimistic placeholders instead of stacking duplicates on repeated unsaved syncs', async () => {
        const { rerender } = render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[{
                    localId: 'draft-hero-1',
                    sectionKey: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Draft hero',
                    props: {},
                }]}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc } = createEmptyPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelectorAll('[data-webu-chat-placeholder="true"]')).toHaveLength(1);
        });

        rerender(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[{
                    localId: 'draft-hero-1',
                    sectionKey: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Draft hero updated',
                    props: {},
                }]}
            />
        );

        await waitFor(() => {
            const placeholders = iframeDoc.querySelectorAll<HTMLElement>('[data-webu-chat-placeholder="true"]');
            expect(placeholders).toHaveLength(1);
            expect(placeholders[0]?.textContent).toContain('Draft hero updated');
        });
    });

    it('keeps placeholder text, link, and image metadata in sync for unsaved sections', async () => {
        const { rerender } = render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[{
                    localId: 'draft-hero-2',
                    sectionKey: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Draft hero',
                    props: {
                        title: 'Draft hero',
                        subtitle: 'Initial subtitle',
                        buttonText: 'Explore now',
                        buttonLink: '/shop',
                        image_url: '/hero-initial.jpg',
                    },
                }]}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc } = createEmptyPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            const placeholder = iframeDoc.querySelector<HTMLElement>('[data-webu-chat-placeholder="true"]');
            expect(placeholder).toBeTruthy();
            expect(placeholder?.textContent).toContain('Draft hero');
            expect(placeholder?.textContent).toContain('Initial subtitle');
            expect(placeholder?.textContent).toContain('Explore now -> /shop');
            expect(iframeDoc.querySelector<HTMLImageElement>('[data-webu-chat-placeholder-image="true"]')?.getAttribute('src')).toBe('/hero-initial.jpg');
        });

        rerender(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[{
                    localId: 'draft-hero-2',
                    sectionKey: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Draft hero updated',
                    props: {
                        title: 'Draft hero updated',
                        subtitle: 'Updated subtitle',
                        buttonText: 'Read more',
                        buttonLink: '/offers',
                        image_url: '/hero-updated.jpg',
                    },
                }]}
            />
        );

        await waitFor(() => {
            const placeholder = iframeDoc.querySelector<HTMLElement>('[data-webu-chat-placeholder="true"]');
            expect(placeholder).toBeTruthy();
            expect(placeholder?.textContent).toContain('Draft hero updated');
            expect(placeholder?.textContent).toContain('Updated subtitle');
            expect(placeholder?.textContent).toContain('Read more -> /offers');
            expect(iframeDoc.querySelector<HTMLImageElement>('[data-webu-chat-placeholder-image="true"]')?.getAttribute('src')).toBe('/hero-updated.jpg');
        });
    });

    it('removes a placeholder once a real preview section with the same local id exists', async () => {
        const liveStructureItems = [{
            localId: 'draft-hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Draft hero',
            props: {},
        }];
        const { rerender } = render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={liveStructureItems}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc, main } = createEmptyPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelectorAll('[data-webu-chat-placeholder="true"]')).toHaveLength(1);
        });

        const realSection = iframeDoc.createElement('section');
        realSection.setAttribute('data-webu-section', 'webu_general_hero_01');
        realSection.setAttribute('data-webu-section-local-id', 'draft-hero-1');
        const title = iframeDoc.createElement('h1');
        title.textContent = 'Saved hero';
        realSection.appendChild(title);
        main.appendChild(realSection);

        rerender(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[...liveStructureItems]}
            />
        );

        await waitFor(() => {
            expect(iframeDoc.querySelectorAll('[data-webu-chat-placeholder="true"]')).toHaveLength(0);
            expect(iframeDoc.querySelectorAll('[data-webu-section-local-id="draft-hero-1"]')).toHaveLength(1);
        });
    });

    it('finalizes a placeholder once real preview content is wrapped with the matching local id', async () => {
        const liveStructureItems = [{
            localId: 'draft-hero-3',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Draft hero',
            props: {
                title: 'Draft hero',
            },
        }];
        const { rerender } = render(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={liveStructureItems}
            />
        );

        const iframe = screen.getByTitle(previewTitleMatcher) as HTMLIFrameElement;
        const { iframeDoc, main } = createEmptyPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelectorAll('[data-webu-chat-placeholder="true"]')).toHaveLength(1);
        });

        const realContent = iframeDoc.createElement('div');
        realContent.textContent = 'Saved hero';
        main.appendChild(realContent);

        rerender(
            <InspectPreview
                {...defaultProps}
                mode="inspect"
                liveStructureItems={[...liveStructureItems]}
            />
        );

        await waitFor(() => {
            expect(iframeDoc.querySelectorAll('[data-webu-chat-placeholder="true"]')).toHaveLength(0);
            expect(iframeDoc.querySelector('[data-webu-section-local-id="draft-hero-3"]')).toBeTruthy();
            expect(main.querySelectorAll('[data-webu-section-local-id="draft-hero-3"]')).toHaveLength(1);
        });
    });
});

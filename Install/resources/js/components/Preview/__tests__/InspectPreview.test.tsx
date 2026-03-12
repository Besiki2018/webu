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

        const wrapper = screen.getByTestId('preview-scale-wrapper');
        expect(wrapper).toHaveStyle({ width: '834px', height: '1112px' });

        rerender(<InspectPreview {...defaultProps} viewport="mobile" />);
        expect(screen.getByTestId('preview-scale-wrapper')).toHaveStyle({ width: '390px', height: '844px' });
    });

    it('does not fall back to section selection when clicking a non-target child inside a section with component targets', async () => {
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
        const { iframeDoc, blankChild } = createRepeatedCardsPreviewDocument();
        attachIframeEnvironment(iframe, iframeDoc);

        fireEvent.load(iframe);

        await waitFor(() => {
            expect(iframeDoc.querySelector('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]')).toBeTruthy();
        });

        blankChild.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        await waitFor(() => {
            expect(onElementSelect).not.toHaveBeenCalled();
        });
    });

    it('clicks a repeated child field and keeps sidebar fields scoped to that item', async () => {
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

        itemBody.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        let mention: ElementMention | null = null;
        await waitFor(() => {
            expect(onElementSelect).toHaveBeenCalledTimes(1);
            mention = onElementSelect.mock.calls[0]?.[0] ?? null;
            expect(mention?.parameterName).toBe('menu_items.0.label');
            expect(mention?.componentPath).toBe('menu_items.0');
        });

        const target = buildEditableTargetFromMention(mention, props);
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
        expect(filteredFields).not.toContain('logoText');
        expect(filteredFields).not.toContain('backgroundColor');
    });

    it('selects exact hero title, image, and button targets instead of broad hero scope', async () => {
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

        titleEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        imageEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        buttonLabel.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        await waitFor(() => {
            expect(onElementSelect).toHaveBeenCalledTimes(3);
        });

        const mentions = onElementSelect.mock.calls.map((call) => call[0] as ElementMention);
        expect(mentions[0]?.parameterName).toBe('title');
        expect(mentions[0]?.componentPath).toBe('title');
        expect(mentions[1]?.parameterName).toBe('image');
        expect(mentions[1]?.componentPath).toBe('image');
        expect(mentions[2]?.parameterName).toBe('buttonText');
        expect(mentions[2]?.componentPath).toBe('buttonText');
    });
});

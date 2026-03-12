<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Interprets natural-language editing commands into ChangeSet JSON.
 * Uses the same prompt design as the frontend AI command library.
 */
class AiInterpretCommandService
{
    protected const ALLOWED_OPS = [
        'updateSection',
        'insertSection',
        'deleteSection',
        'reorderSection',
        'updateTheme',
        'updateGlobalComponent',
        'updateText',
        'replaceImage',
        'updateButton',
        'addProduct',
        'removeProduct',
        'translatePage',
        'generateContent',
    ];

    public function __construct(
        protected InternalAiService $internalAi
    ) {}

    /**
     * Interpret user command with page context. Returns ChangeSet or error.
     *
     * @param  array{page_slug?: string, page_id?: int|null, sections: array<int, array{id: string, type: string, label?: string, editable_fields?: array<int, string>, props?: array<string, mixed>}>, component_types?: string[], global_components?: array<int, array{id: string, label?: string, editable_fields?: array<int, string>}>, theme?: array<string, mixed>, selected_section_id?: string|null, selected_parameter_path?: string|null, selected_element_id?: string|null, selected_target?: array<string, mixed>|null, locale?: string}  $pageContext
     * @return array{success: true, change_set: array{operations: array<int, mixed>, summary: array<int, string>}}|array{success: false, error: string, raw_response?: string}
     */
    public function interpret(string $userPrompt, array $pageContext): array
    {
        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            return ['success' => false, 'error' => 'Command is required.'];
        }

        $resolvedCommandLocale = $this->resolveUserCommandLocale(
            $userPrompt,
            isset($pageContext['locale']) && is_string($pageContext['locale']) ? $pageContext['locale'] : null
        );
        $pageContext['locale'] = $resolvedCommandLocale;

        $deterministicChangeSet = $this->resolveDeterministicVariantSwitchChangeSet($userPrompt, $pageContext);
        if ($deterministicChangeSet !== null) {
            return [
                'success' => true,
                'change_set' => $deterministicChangeSet,
            ];
        }

        $deterministicTextChangeSet = $this->resolveDeterministicExactTextChangeSet($userPrompt, $pageContext);
        if ($deterministicTextChangeSet !== null) {
            Log::info('ai_interpret.deterministic_text_change_set', [
                'prompt' => $userPrompt,
                'summary' => $deterministicTextChangeSet['summary'] ?? [],
                'operations' => $this->summarizeDeterministicOperations($deterministicTextChangeSet['operations'] ?? []),
            ]);
            return [
                'success' => true,
                'change_set' => $deterministicTextChangeSet,
            ];
        }

        if (! $this->internalAi->isConfigured()) {
            return [
                'success' => false,
                'error' => 'AI provider is not configured. Configure in Admin Settings → Integrations.',
            ];
        }

        $contextSummary = $this->buildContextSummary($pageContext);
        $fullPrompt = $this->buildFullPrompt($userPrompt, $contextSummary, $resolvedCommandLocale);
        $maxRetries = 2;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->internalAi->complete($fullPrompt, 4000);
            if ($response === null || trim($response) === '') {
                return [
                    'success' => false,
                    'error' => 'AI did not return a response. Try again.',
                ];
            }

            $parsed = $this->parseChangeSetFromResponse($response);
            if ($parsed === null) {
                if ($attempt < $maxRetries) {
                    continue;
                }
                Log::warning('AI interpret command: failed to parse ChangeSet', [
                    'response_length' => strlen($response),
                ]);
                return [
                    'success' => false,
                    'error' => 'Could not parse AI response as ChangeSet JSON.',
                    'raw_response' => $response,
                ];
            }

            $validationError = $this->validateChangeSet($parsed);
            if ($validationError !== null) {
                if ($attempt < $maxRetries) {
                    continue;
                }
                return [
                    'success' => false,
                    'error' => $validationError,
                    'raw_response' => $response,
                ];
            }

            return [
                'success' => true,
                'change_set' => $parsed,
            ];
        }

        return [
            'success' => false,
            'error' => 'Could not produce a valid ChangeSet.',
        ];
    }

    /**
     * @param  array{page_slug?: string, page_id?: int|null, sections: array, component_types?: string[], global_components?: array<int, array{id: string, label?: string, editable_fields?: array<int, string>}>, theme?: array, selected_section_id?: string|null, selected_parameter_path?: string|null, selected_element_id?: string|null, selected_target?: array<string, mixed>|null, locale?: string, recent_edits?: string|null}  $ctx
     */
    private function buildContextSummary(array $ctx): string
    {
        $parts = ['Current page context:'];
        if (! empty($ctx['page_slug'])) {
            $parts[] = 'Page: '.$ctx['page_slug'];
        }
        if (! empty($ctx['sections']) && is_array($ctx['sections'])) {
            $sectionStrs = [];
            foreach ($ctx['sections'] as $s) {
                $id = $s['id'] ?? '';
                $type = $s['type'] ?? '';
                $label = $s['label'] ?? '';
                $sectionStrs[] = $id.' (type: '.$type.($label !== '' ? ', label: '.$label : '').')';
            }
            $parts[] = 'Sections (use these sectionId values): '.implode('; ', $sectionStrs);
            foreach ($ctx['sections'] as $s) {
                $props = $s['props'] ?? null;
                if (is_array($props) && $props !== []) {
                    $id = $s['id'] ?? '';
                    $parts[] = 'Section ['.$id.'] current props (for precise patches, change only what the user asked): '.json_encode($props, JSON_UNESCAPED_UNICODE);
                }
            }
        }
        if (! empty($ctx['component_types']) && is_array($ctx['component_types'])) {
            $parts[] = 'Available section types for insert: '.implode(', ', $ctx['component_types']);
        }
        if (! empty($ctx['global_components']) && is_array($ctx['global_components'])) {
            $globalComponentStrings = [];
            foreach ($ctx['global_components'] as $component) {
                if (! is_array($component)) {
                    continue;
                }
                $id = trim((string) ($component['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $label = trim((string) ($component['label'] ?? $id));
                $editableFields = is_array($component['editable_fields'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($field) => is_string($field) ? trim($field) : '', $component['editable_fields'])))
                    : [];
                $globalComponentStrings[] = $label.' (id: '.$id.($editableFields !== [] ? ', editable fields: '.implode(', ', $editableFields) : '').')';
            }
            if ($globalComponentStrings !== []) {
                $parts[] = 'Global components (site-wide): '.implode('; ', $globalComponentStrings);
            }
        }
        if (! empty($ctx['theme']) && is_array($ctx['theme'])) {
            $parts[] = 'Theme (for updateTheme): '.json_encode($ctx['theme'], JSON_UNESCAPED_UNICODE);
        }
        if (! empty($ctx['selected_section_id'])) {
            $parts[] = 'Selected section: '.$ctx['selected_section_id'];
        }
        if (! empty($ctx['selected_target']) && is_array($ctx['selected_target'])) {
            $selectedTarget = $ctx['selected_target'];
            $targetSummary = [];
            if (! empty($selectedTarget['component_name'])) {
                $targetSummary[] = 'component='.$selectedTarget['component_name'];
            }
            if (! empty($selectedTarget['component_type'])) {
                $targetSummary[] = 'type='.$selectedTarget['component_type'];
            }
            if (! empty($selectedTarget['section_id'])) {
                $targetSummary[] = 'sectionId='.$selectedTarget['section_id'];
            }
            if (! empty($selectedTarget['element_id'])) {
                $targetSummary[] = 'elementId='.$selectedTarget['element_id'];
            }
            if (! empty($selectedTarget['parameter_path'])) {
                $targetSummary[] = 'parameter='.$selectedTarget['parameter_path'];
            }
            if (! empty($selectedTarget['component_path'])) {
                $targetSummary[] = 'path='.$selectedTarget['component_path'];
            }
            if ($targetSummary !== []) {
                $parts[] = 'Selected target: '.implode(', ', $targetSummary);
            }
            $editableFields = is_array($selectedTarget['editable_fields'] ?? null) ? $selectedTarget['editable_fields'] : [];
            if ($editableFields !== []) {
                $parts[] = 'Selected target editable fields: '.implode(', ', $editableFields);
            }
            $allowedUpdates = is_array($selectedTarget['allowed_updates'] ?? null) ? $selectedTarget['allowed_updates'] : [];
            $operationTypes = is_array($allowedUpdates['operation_types'] ?? null) ? $allowedUpdates['operation_types'] : [];
            $fieldPaths = is_array($allowedUpdates['field_paths'] ?? null) ? $allowedUpdates['field_paths'] : [];
            $sectionOperationTypes = is_array($allowedUpdates['section_operation_types'] ?? null) ? $allowedUpdates['section_operation_types'] : [];
            $sectionFieldPaths = is_array($allowedUpdates['section_field_paths'] ?? null) ? $allowedUpdates['section_field_paths'] : [];
            if ($operationTypes !== []) {
                $parts[] = 'Selected target allowed operations (default exact target scope): '.implode(', ', $operationTypes);
            }
            if ($fieldPaths !== []) {
                $parts[] = 'Selected target allowed field paths (default exact target scope): '.implode(', ', $fieldPaths);
            }
            if ($sectionOperationTypes !== []) {
                $parts[] = 'If the user explicitly asks for a broader same-section/component change, allowed same-section operations: '.implode(', ', $sectionOperationTypes);
            }
            if ($sectionFieldPaths !== []) {
                $parts[] = 'If the user explicitly asks for a broader same-section/component change, allowed same-section field paths: '.implode(', ', $sectionFieldPaths);
            }
            $variants = is_array($selectedTarget['variants'] ?? null) ? $selectedTarget['variants'] : [];
            foreach (['layout', 'style'] as $variantKind) {
                if (! is_array($variants[$variantKind] ?? null)) {
                    continue;
                }
                $variant = $variants[$variantKind];
                $options = is_array($variant['options'] ?? null) ? array_values(array_filter(array_map('strval', $variant['options']))) : [];
                $parts[] = ucfirst($variantKind).' variant: '.($variant['active'] ?? 'default').($options !== [] ? ' (allowed: '.implode(', ', $options).')' : '');
            }
            if (! empty($selectedTarget['current_breakpoint'])) {
                $parts[] = 'Current responsive breakpoint: '.$selectedTarget['current_breakpoint'];
            }
            if (! empty($selectedTarget['current_interaction_state'])) {
                $parts[] = 'Current interaction state: '.$selectedTarget['current_interaction_state'];
            }
            $responsiveContext = is_array($selectedTarget['responsive_context'] ?? null) ? $selectedTarget['responsive_context'] : [];
            $availableBreakpoints = is_array($responsiveContext['availableBreakpoints'] ?? null) ? array_values(array_filter(array_map('strval', $responsiveContext['availableBreakpoints']))) : [];
            $availableInteractionStates = is_array($responsiveContext['availableInteractionStates'] ?? null) ? array_values(array_filter(array_map('strval', $responsiveContext['availableInteractionStates']))) : [];
            $responsiveFieldPaths = is_array($responsiveContext['responsiveFieldPaths'] ?? null) ? array_values(array_filter(array_map('strval', $responsiveContext['responsiveFieldPaths']))) : [];
            $stateFieldPaths = is_array($responsiveContext['stateFieldPaths'] ?? null) ? array_values(array_filter(array_map('strval', $responsiveContext['stateFieldPaths']))) : [];
            if ($availableBreakpoints !== []) {
                $parts[] = 'Available responsive breakpoints: '.implode(', ', $availableBreakpoints);
            }
            if ($availableInteractionStates !== []) {
                $parts[] = 'Available interaction states: '.implode(', ', $availableInteractionStates);
            }
            if ($responsiveFieldPaths !== []) {
                $parts[] = 'Responsive field paths for the current breakpoint: '.implode(', ', $responsiveFieldPaths);
            }
            if ($stateFieldPaths !== []) {
                $parts[] = 'State field paths for the current interaction state: '.implode(', ', $stateFieldPaths);
            }
        }
        if (! empty($ctx['selected_parameter_path'])) {
            $parts[] = 'Selected parameter (update this field): '.$ctx['selected_parameter_path'];
        }
        if (! empty($ctx['selected_element_id'])) {
            $parts[] = 'Selected element id: '.$ctx['selected_element_id'];
        }
        if (! empty($ctx['locale'])) {
            $parts[] = 'Locale: '.$ctx['locale'];
        }
        if (! empty($ctx['recent_edits']) && is_string($ctx['recent_edits'])) {
            $parts[] = 'Recent edits in this session (use to resolve "it", "the title", "make it shorter", etc.): '.$ctx['recent_edits'];
        }

        return implode("\n", $parts);
    }

    /**
     * Resolve simple "change header/footer design" requests deterministically by switching to the next allowed variant.
     *
     * @param  array{theme?: array<string, mixed>, locale?: string|null}  $pageContext
     * @return array{operations: array<int, array<string, mixed>>, summary: array<int, string>}|null
     */
    private function resolveDeterministicVariantSwitchChangeSet(string $userPrompt, array $pageContext): ?array
    {
        if (! $this->looksLikePureGlobalDesignSwitchRequest($userPrompt)) {
            return null;
        }

        $component = $this->resolveGlobalDesignTarget($userPrompt);
        if ($component === null) {
            return null;
        }

        $nextVariant = $this->resolveNextGlobalComponentVariant($component, $pageContext);
        if ($nextVariant === null) {
            return null;
        }

        return [
            'operations' => [[
                'op' => 'updateGlobalComponent',
                'component' => $component,
                'patch' => [
                    'layout_variant' => $nextVariant,
                ],
            ]],
            'summary' => [$this->designSwitchSummary($component, $userPrompt, $pageContext)],
        ];
    }

    private function looksLikePureGlobalDesignSwitchRequest(string $userPrompt): bool
    {
        $needle = Str::lower(trim($userPrompt));
        if ($needle === '') {
            return false;
        }

        $mentionsChange = Str::contains($needle, [
            'change',
            'switch',
            'replace',
            'different',
            'new',
            'update',
            'redesign',
            'შეცვალ',
            'შემიცვალ',
            'შევცვალ',
            'გადართ',
            'სხვა',
            'ახალი',
            'განაახლ',
        ]);
        $mentionsDesign = Str::contains($needle, [
            'design',
            'layout',
            'style',
            'variant',
            'look',
            'დიზაინ',
            'დიზიან',
            'სტილ',
            'ვარიანტ',
            'განლაგ',
            'ვიზუალ',
        ]);
        if (! $mentionsChange || ! $mentionsDesign) {
            return false;
        }

        $mentionsSpecificContent = Str::contains($needle, [
            'logo',
            'menu',
            'text',
            'title',
            'email',
            'phone',
            'cart',
            'wishlist',
            'search',
            'cta',
            'link',
            'url',
            'color',
            'ფერი',
            'ლოგო',
            'მენიუ',
            'ტექსტ',
            'იმეილ',
            'ტელეფონ',
            'კალათ',
            'ძებნ',
            'ღილაკ',
        ]);

        return ! $mentionsSpecificContent;
    }

    private function resolveGlobalDesignTarget(string $userPrompt): ?string
    {
        $needle = Str::lower(trim($userPrompt));
        if ($needle === '') {
            return null;
        }

        $mentionsHeader = Str::contains($needle, ['header', 'navbar', 'nav bar', 'top bar', 'ჰედერ', 'ნავიგ']);
        $mentionsFooter = Str::contains($needle, ['footer', 'ფუტერ']);

        if ($mentionsHeader && ! $mentionsFooter) {
            return 'header';
        }

        if ($mentionsFooter && ! $mentionsHeader) {
            return 'footer';
        }

        return null;
    }

    /**
     * @param  array{theme?: array<string, mixed>}  $pageContext
     */
    private function resolveNextGlobalComponentVariant(string $component, array $pageContext): ?string
    {
        $theme = is_array($pageContext['theme'] ?? null) ? $pageContext['theme'] : [];
        $layout = is_array($theme['layout'] ?? null) ? $theme['layout'] : [];
        $sectionKey = trim((string) ($layout[$component.'_section_key'] ?? ($component === 'header' ? 'webu_header_01' : 'webu_footer_01')));
        $props = is_array($layout[$component.'_props'] ?? null) ? $layout[$component.'_props'] : [];
        $variantConfig = config('component-variants', []);
        $entry = $variantConfig[$sectionKey] ?? $variantConfig[$component] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $allowedVariants = array_values(array_filter(
            $entry['layout_variants'] ?? [],
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        if (count($allowedVariants) < 2) {
            return null;
        }

        $normalizedAllowedVariants = array_map(
            static fn (string $value): string => Str::lower(trim($value)),
            $allowedVariants
        );
        $defaultVariant = is_string($entry['default_layout'] ?? null) && trim((string) $entry['default_layout']) !== ''
            ? Str::lower(trim((string) $entry['default_layout']))
            : $normalizedAllowedVariants[0];
        $currentVariant = Str::lower(trim((string) ($props['layout_variant'] ?? $props['variant'] ?? $defaultVariant)));
        $currentIndex = array_search($currentVariant, $normalizedAllowedVariants, true);
        if ($currentIndex === false) {
            return $normalizedAllowedVariants[0] ?? null;
        }

        return $normalizedAllowedVariants[($currentIndex + 1) % count($normalizedAllowedVariants)] ?? null;
    }

    /**
     * @param  array{locale?: string|null}  $pageContext
     */
    private function designSwitchSummary(string $component, string $userPrompt, array $pageContext): string
    {
        $locale = Str::lower(trim((string) ($pageContext['locale'] ?? '')));
        $isGeorgian = $locale === 'ka' || preg_match('/\p{Georgian}/u', $userPrompt) === 1;

        if ($isGeorgian) {
            return $component === 'header'
                ? 'ჰედერის დიზაინი შევცვალე'
                : 'ფუტერის დიზაინი შევცვალე';
        }

        return $component === 'header'
            ? 'Changed header design'
            : 'Changed footer design';
    }

    /**
     * Resolve explicit text translation / replacement / duplicate cleanup requests without relying on the general AI planner.
     *
     * @param  array<string, mixed>  $pageContext
     * @return array{operations: array<int, array<string, mixed>>, summary: array<int, string>}|null
     */
    private function resolveDeterministicExactTextChangeSet(string $userPrompt, array $pageContext): ?array
    {
        foreach ([
            fn (): ?array => $this->resolveDeterministicDuplicateCleanupChangeSet($userPrompt, $pageContext),
            fn (): ?array => $this->resolveDeterministicSimpleReplacementChangeSet($userPrompt, $pageContext),
            fn (): ?array => $this->resolveDeterministicTranslationChangeSet($userPrompt, $pageContext),
        ] as $resolver) {
            $changeSet = $resolver();
            if ($changeSet !== null) {
                return $changeSet;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array{operations: array<int, array<string, mixed>>, summary: array<int, string>}|null
     */
    private function resolveDeterministicTranslationChangeSet(string $userPrompt, array $pageContext): ?array
    {
        if (! $this->looksLikePureTranslationRequest($userPrompt)) {
            return null;
        }

        $targetLocale = $this->detectRequestedTargetLocale($userPrompt);
        if ($targetLocale === null) {
            return null;
        }

        $targets = $this->resolveSelectedTextTarget($pageContext);
        if ($targets === []) {
            $sourceBlock = $this->extractTranslationSourceBlock($userPrompt, $pageContext);
            if ($sourceBlock === null) {
                return null;
            }
            $targets = $this->matchTargetsForSourceText($sourceBlock, $pageContext);
        }
        if ($targets === []) {
            Log::warning('ai_interpret.translation_no_match', [
                'prompt' => $userPrompt,
                'target_locale' => $targetLocale,
            ]);
            return null;
        }

        $uniqueValues = array_values(array_unique(array_map(static fn (array $target): string => $target['value'], $targets)));
        $translations = $this->translateStringsDeterministically($uniqueValues, $targetLocale);
        if ($translations === null) {
            return null;
        }

        $translationMap = [];
        foreach ($uniqueValues as $index => $value) {
            $translated = $translations[$index] ?? null;
            if (! is_string($translated) || trim($translated) === '') {
                continue;
            }
            $translationMap[$value] = trim($translated);
        }
        if ($translationMap === []) {
            return null;
        }

        $operations = $this->buildTargetOperations($targets, static fn (array $target) => $translationMap[$target['value']] ?? null);
        if ($operations === []) {
            return null;
        }

        return [
            'operations' => $operations,
            'summary' => [$this->deterministicTextSummary('translate', $userPrompt, $targetLocale)],
        ];
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array{operations: array<int, array<string, mixed>>, summary: array<int, string>}|null
     */
    private function resolveDeterministicDuplicateCleanupChangeSet(string $userPrompt, array $pageContext): ?array
    {
        $targetText = $this->extractDuplicateCleanupTarget($userPrompt);
        if ($targetText === null) {
            return null;
        }

        $matches = array_values(array_filter(
            $this->collectTextTargets($pageContext),
            fn (array $target): bool => $this->normalizeComparableText($target['value']) === $this->normalizeComparableText($targetText)
        ));
        if (count($matches) < 2) {
            return null;
        }

        $operations = $this->buildTargetOperations(
            array_slice($matches, 1),
            static fn (): string => ''
        );
        if ($operations === []) {
            return null;
        }

        return [
            'operations' => $operations,
            'summary' => [$this->deterministicTextSummary('dedupe', $userPrompt)],
        ];
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array{operations: array<int, array<string, mixed>>, summary: array<int, string>}|null
     */
    private function resolveDeterministicSimpleReplacementChangeSet(string $userPrompt, array $pageContext): ?array
    {
        $replacement = $this->parsePureReplacementInstruction($userPrompt);
        if ($replacement === null) {
            return null;
        }

        $oldText = $replacement['old'];
        $newText = $replacement['new'];
        $matches = array_values(array_filter(
            $this->collectTextTargets($pageContext),
            fn (array $target): bool => $this->normalizeComparableText($target['value']) === $this->normalizeComparableText($oldText)
        ));
        if ($matches === []) {
            $matches = $this->matchTargetsForSourceText($oldText, $pageContext);
        }
        if ($matches === []) {
            Log::warning('ai_interpret.replace_no_match', [
                'prompt' => $userPrompt,
                'old_text' => $oldText,
                'new_text' => $newText,
            ]);
            return null;
        }

        $operations = $this->buildTargetOperations(
            $matches,
            static fn () => $newText
        );

        if (($replacement['redirect_to_shop'] ?? false) === true) {
            foreach ($matches as $match) {
                $linkPath = $this->resolveSiblingLinkPath($match, $pageContext);
                if ($linkPath === null) {
                    continue;
                }

                if (($match['scope'] ?? 'section') === 'section') {
                    $operations[] = [
                        'op' => 'updateText',
                        'sectionId' => $match['section_id'],
                        'path' => $linkPath,
                        'value' => '/shop',
                    ];
                    continue;
                }

                $operations[] = [
                    'op' => 'updateGlobalComponent',
                    'component' => $match['component'],
                    'patch' => $this->buildPatchForPath($linkPath, '/shop'),
                ];
            }
        }

        if ($operations === []) {
            return null;
        }

        return [
            'operations' => $operations,
            'summary' => [$this->deterministicTextSummary('replace', $userPrompt)],
        ];
    }

    private function looksLikePureTranslationRequest(string $userPrompt): bool
    {
        $needle = Str::lower(trim($userPrompt));
        if ($needle === '') {
            return false;
        }

        if (! Str::contains($needle, ['translate', 'translated', 'თარგმნ', 'გადმოთარგმნ'])) {
            return false;
        }

        return ! Str::contains($needle, ['design', 'layout', 'style', 'color', 'დიზაინ', 'განლაგ', 'ფერი', 'წაშალე', 'delete', 'remove']);
    }

    private function detectRequestedTargetLocale(string $userPrompt): ?string
    {
        $needle = Str::lower(trim($userPrompt));

        if (Str::contains($needle, ['ქართულ', 'ქარტულ', 'qartul', 'kartul', 'georgian'])) {
            return 'ka';
        }

        if (Str::contains($needle, ['ინგლისურ', 'english'])) {
            return 'en';
        }

        if (Str::contains($needle, ['რუსულ', 'russian'])) {
            return 'ru';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pageContext
     */
    private function extractTranslationSourceBlock(string $userPrompt, array $pageContext): ?string
    {
        $lines = preg_split('/\R/u', trim($userPrompt)) ?: [];
        $sourceLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($this->looksLikeTranslationInstructionLine($trimmed)) {
                continue;
            }
            $sourceLines[] = $trimmed;
        }

        if ($sourceLines !== []) {
            return implode("\n", $sourceLines);
        }

        if (preg_match('/^(?<source>.+?)\s+(?:ეს\s+)?(?:თარგმნე|გადმოთარგმნე|translate)\b/iu', trim($userPrompt), $matches)) {
            $source = trim((string) ($matches['source'] ?? ''));
            if ($source !== '') {
                return $source;
            }
        }

        $selectedTarget = $this->resolveSelectedTextTarget($pageContext);

        return $selectedTarget[0]['value'] ?? null;
    }

    private function looksLikeTranslationInstructionLine(string $line): bool
    {
        $needle = Str::lower(trim($line));
        if ($needle === '') {
            return false;
        }

        return Str::contains($needle, ['translate', 'თარგმნ', 'გადმოთარგმნ', 'ქართულ', 'ქარტულ', 'english', 'ინგლისურ']);
    }

    private function extractDuplicateCleanupTarget(string $userPrompt): ?string
    {
        $prompt = trim($userPrompt);

        if (! preg_match('/(only\s+one|leave\s+one|ერთი\s+დატოვე|ერტი\s+დატოვე)/iu', $prompt)) {
            return null;
        }

        if (preg_match('/^(?<target>.+?)\s+(?:ეს\s+)?(?:წაშალე|მოაშორე|delete|remove)\b.*$/iu', $prompt, $matches)) {
            $target = trim((string) ($matches['target'] ?? ''));
            return $target !== '' ? $target : null;
        }

        return null;
    }

    /**
     * @return array{old: string, new: string, redirect_to_shop: bool}|null
     */
    private function parsePureReplacementInstruction(string $userPrompt): ?array
    {
        $prompt = trim($userPrompt);
        if ($prompt === '') {
            return null;
        }

        $patterns = [
            '/^(?<old>.+?)\s+(?:ის\s+)?ნაცვლად\s+(?<new>.+?)(?:\s+დაწერე|\s+ჩაწერე|\s+დააწერე|\s+დამიწერე|\s+ჩამიწერე)(?<rest>.*)$/iu',
            '/^(?<old>.+?)\s+(?:ეს\s+)?(?:თარგმნე|გადმოთარგმნე|translate)(?:\s+და)?\s+(?:დაწერე|ჩაწერე|დააწერე|დამიწერე|ჩამიწერე|write|put)\s+(?<new>.+?)(?<rest>.*)$/iu',
            '/^(?<old>.+?)\s+(?:აქ(?:ვე)?\s+)?(?:დაწერე|ჩაწერე|დააწერე|დამიწერე|ჩამიწერე|write|put)\s+(?<new>.+?)(?<rest>.*)$/iu',
            '/^(?:replace\s+)?(?<old>.+?)\s+(?:with|instead of)\s+(?<new>.+?)(?<rest>.*)$/iu',
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (! preg_match($pattern, $prompt, $matches)) {
                continue;
            }

            $old = $this->normalizeReplacementText((string) ($matches['old'] ?? ''));
            $new = $this->normalizeReplacementText((string) ($matches['new'] ?? ''));
            $rest = trim((string) ($matches['rest'] ?? ''));

            if ($rest !== '') {
                $allowedRest = preg_match('/^(?:და\s+)?(?:shop|products|მაღაზი).*/iu', $rest) === 1;
                if (! $allowedRest) {
                    $new = $this->normalizeReplacementText(((string) ($matches['new'] ?? '')).((string) ($matches['rest'] ?? '')));
                    $rest = '';
                }
            }

            if ($old === '' || $new === '') {
                continue;
            }

            if (preg_match('/\b(დაწერე|translate|თარგმნ|წაშალე|remove|delete)\b/iu', $old) === 1) {
                continue;
            }

            $redirectToShop = false;
            if ($rest !== '') {
                $redirectToShop = preg_match('/(shop|მაღაზი|products)/iu', $rest) === 1;
                $allowedRest = preg_match('/^(?:და\s+)?(?:shop|products|მაღაზი).*/iu', $rest) === 1;
                if (! $allowedRest) {
                    continue;
                }
            }

            return [
                'old' => $old,
                'new' => $new,
                'redirect_to_shop' => $redirectToShop,
            ];
        }

        $explicitWriteInstruction = $this->parseExplicitWriteInstruction($prompt);
        if ($explicitWriteInstruction !== null) {
            return $explicitWriteInstruction;
        }

        return null;
    }

    /**
     * @return array{old: string, new: string, redirect_to_shop: bool}|null
     */
    private function parseExplicitWriteInstruction(string $prompt): ?array
    {
        if (! preg_match('/(?<verb>დაწერე|დაწერ|ჩაწერე|ჩაწერ|დააწერე|დააწერ|დამიწერე|დამიწერ|ჩამიწერე|ჩამიწერ|write|put)(?=\s|$)/iu', $prompt, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $verb = (string) ($matches['verb'][0] ?? '');
        $verbOffset = (int) ($matches['verb'][1] ?? 0);
        if ($verb === '' || $verbOffset <= 0) {
            return null;
        }

        $old = trim((string) substr($prompt, 0, $verbOffset));
        $new = trim((string) substr($prompt, $verbOffset + strlen($verb)));
        $old = preg_replace('/\s+(?:აქ(?:ვე)?|here)\s*$/iu', '', $old) ?? $old;
        $old = preg_replace('/\s+(?:ეს\s+)?(?:თარგმნე|გადმოთარგმნე|translate)\s*$/iu', '', $old) ?? $old;
        $old = preg_replace('/\s+(?:ის\s+)?ნაცვლად\s*$/iu', '', $old) ?? $old;
        $old = $this->normalizeReplacementText($old);
        $new = $this->normalizeReplacementText($new);

        if ($old === '' || $new === '') {
            return null;
        }

        $redirectToShop = preg_match('/(shop|მაღაზი|products)/iu', $new) === 1
            && preg_match('/^(?:to\s+|ზე\s+)?(?:shop|products|მაღაზი)/iu', $new) === 1;

        return [
            'old' => $old,
            'new' => $new,
            'redirect_to_shop' => $redirectToShop,
        ];
    }

    private function normalizeReplacementText(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/^[“"\'„`\s]+|[”"\'`\s]+$/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array<int, array{scope: string, section_id?: string, component?: string, path: string, value: string, editable_fields?: array<int, string>}>
     */
    private function resolveSelectedTextTarget(array $pageContext): array
    {
        $selectedTarget = is_array($pageContext['selected_target'] ?? null) ? $pageContext['selected_target'] : [];
        $path = trim((string) ($selectedTarget['parameter_path'] ?? $selectedTarget['component_path'] ?? ''));
        if ($path === '') {
            $path = trim((string) ($pageContext['selected_parameter_path'] ?? ''));
        }
        if ($path === '') {
            return [];
        }

        $sectionId = trim((string) ($selectedTarget['section_id'] ?? $pageContext['selected_section_id'] ?? ''));
        if ($sectionId !== '') {
            foreach ($pageContext['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                if (trim((string) ($section['id'] ?? '')) !== $sectionId) {
                    continue;
                }

                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $value = Arr::get($props, $path);
                $editableFields = $this->normalizeEditableFields($section['editable_fields'] ?? null);
                if (is_string($value) && $this->looksLikeHumanTextValue($path, $value)) {
                    return [[
                        'scope' => 'section',
                        'section_id' => $sectionId,
                        'path' => $path,
                        'value' => $value,
                        'editable_fields' => $editableFields,
                    ]];
                }
            }
        }

        $globalTarget = Str::lower(trim((string) ($selectedTarget['section_key'] ?? $selectedTarget['component_type'] ?? '')));
        $component = Str::contains($globalTarget, 'footer') ? 'footer' : (Str::contains($globalTarget, 'header') ? 'header' : null);
        if ($component !== null) {
            $theme = is_array($pageContext['theme'] ?? null) ? $pageContext['theme'] : [];
            $layout = is_array($theme['layout'] ?? null) ? $theme['layout'] : [];
            $props = is_array($layout[$component.'_props'] ?? null) ? $layout[$component.'_props'] : [];
            $editableFields = $this->resolveGlobalEditableFields($component, $pageContext);
            $value = Arr::get($props, $path);
            if (is_string($value) && $this->looksLikeHumanTextValue($path, $value)) {
                return [[
                    'scope' => 'global',
                    'component' => $component,
                    'path' => $path,
                    'value' => $value,
                    'editable_fields' => $editableFields,
                ]];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array<int, array{scope: string, section_id?: string, component?: string, path: string, value: string, editable_fields?: array<int, string>}>
     */
    private function matchTargetsForSourceText(string $sourceText, array $pageContext): array
    {
        $targets = [];
        foreach ($this->collectTextTargets($pageContext) as $target) {
            if ($this->textMatchesSource($target['value'], $sourceText)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array<int, array{scope: string, section_id?: string, component?: string, path: string, value: string, editable_fields?: array<int, string>}>
     */
    private function collectTextTargets(array $pageContext): array
    {
        $targets = [];

        foreach ($pageContext['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionId = trim((string) ($section['id'] ?? ''));
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            if ($sectionId === '' || $props === []) {
                continue;
            }

            $editableFields = $this->normalizeEditableFields($section['editable_fields'] ?? null);
            foreach ($this->flattenTextTargetsFromProps($props) as $target) {
                $target['scope'] = 'section';
                $target['section_id'] = $sectionId;
                $target['editable_fields'] = $editableFields;
                $targets[] = $target;
            }
        }

        $theme = is_array($pageContext['theme'] ?? null) ? $pageContext['theme'] : [];
        $layout = is_array($theme['layout'] ?? null) ? $theme['layout'] : [];
        foreach (['header', 'footer'] as $component) {
            $props = is_array($layout[$component.'_props'] ?? null) ? $layout[$component.'_props'] : [];
            foreach ($this->flattenTextTargetsFromProps($props) as $target) {
                $target['scope'] = 'global';
                $target['component'] = $component;
                $target['editable_fields'] = $this->resolveGlobalEditableFields($component, $pageContext);
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, array{path: string, value: string}>
     */
    private function flattenTextTargetsFromProps(array $props, string $prefix = ''): array
    {
        $targets = [];

        foreach ($props as $key => $value) {
            $segment = trim((string) $key);
            if ($segment === '') {
                continue;
            }

            $path = $prefix !== '' ? $prefix.'.'.$segment : $segment;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $index => $listValue) {
                        if (is_array($listValue)) {
                            $targets = [...$targets, ...$this->flattenTextTargetsFromProps($listValue, $path.'.'.$index)];
                            continue;
                        }
                        if (is_string($listValue) && $this->looksLikeHumanTextValue($path.'.'.$index, $listValue)) {
                            $targets[] = ['path' => $path.'.'.$index, 'value' => $listValue];
                        }
                    }
                    continue;
                }

                $targets = [...$targets, ...$this->flattenTextTargetsFromProps($value, $path)];
                continue;
            }

            if (is_string($value) && $this->looksLikeHumanTextValue($path, $value)) {
                $targets[] = ['path' => $path, 'value' => $value];
            }
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeEditableFields(mixed $fields): array
    {
        return array_values(array_filter(array_map(
            static fn ($field): string => is_string($field) ? trim($field) : '',
            is_array($fields) ? $fields : []
        )));
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function resolveTargetOperationPath(array $target): string
    {
        $path = trim((string) ($target['path'] ?? 'headline'));
        if ($path === '') {
            $path = 'headline';
        }

        $editableFields = $this->normalizeEditableFields($target['editable_fields'] ?? null);
        if ($editableFields === [] || $this->pathWithinEditableFields($path, $editableFields)) {
            return $path;
        }

        $fallback = $this->resolveFallbackEditableTextPath($path, $editableFields);

        return $fallback ?? $path;
    }

    /**
     * @param  array<int, string>  $editableFields
     */
    private function pathWithinEditableFields(string $path, array $editableFields): bool
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || $editableFields === []) {
            return $editableFields === [];
        }

        foreach ($editableFields as $editableField) {
            $normalizedField = trim($editableField);
            if ($normalizedField === '') {
                continue;
            }
            if ($normalizedPath === $normalizedField || Str::startsWith($normalizedPath, $normalizedField.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $editableFields
     */
    private function resolveFallbackEditableTextPath(string $path, array $editableFields): ?string
    {
        $textFields = array_values(array_filter($editableFields, fn (string $field): bool => $this->looksLikeTextFieldPath($field)));
        if ($textFields === []) {
            return null;
        }

        $preferredByAlias = [
            'title' => ['headline', 'title', 'heading', 'eyebrow', 'subtitle', 'description', 'body'],
            'headline' => ['headline', 'title', 'heading'],
            'heading' => ['headline', 'title', 'heading'],
            'eyebrow' => ['eyebrow', 'title', 'headline'],
            'subtitle' => ['subtitle', 'description', 'body'],
            'description' => ['description', 'subtitle', 'body'],
            'body' => ['body', 'description', 'subtitle'],
            'text' => ['body', 'description', 'subtitle', 'headline', 'title'],
        ];

        foreach ($preferredByAlias[$path] ?? [$path] as $candidate) {
            if ($this->pathWithinEditableFields($candidate, $textFields)) {
                return $candidate;
            }
        }

        if (count($textFields) === 1) {
            return $textFields[0];
        }

        foreach (['headline', 'title', 'eyebrow', 'subtitle', 'description', 'body'] as $candidate) {
            if ($this->pathWithinEditableFields($candidate, $textFields)) {
                return $candidate;
            }
        }

        return $textFields[0] ?? null;
    }

    private function looksLikeTextFieldPath(string $path): bool
    {
        $normalizedPath = Str::lower(trim($path));
        if ($normalizedPath === '') {
            return false;
        }

        return preg_match('/(^|\.|_)(title|headline|heading|eyebrow|subtitle|description|body|text|label|caption|content|copy)$/', $normalizedPath) === 1;
    }

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array<int, string>
     */
    private function resolveGlobalEditableFields(string $component, array $pageContext): array
    {
        foreach ($pageContext['global_components'] ?? [] as $globalComponent) {
            if (! is_array($globalComponent)) {
                continue;
            }
            if (trim((string) ($globalComponent['id'] ?? '')) !== $component) {
                continue;
            }

            return $this->normalizeEditableFields($globalComponent['editable_fields'] ?? null);
        }

        return [];
    }

    private function looksLikeHumanTextValue(string $path, string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        $normalizedPath = Str::lower($path);
        if (preg_match('/(^|\.|_)(url|href|link|email|phone|icon|variant|layout|class|slug|id|image|src)$/', $normalizedPath) === 1) {
            return false;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return false;
        }

        return preg_match('/[\p{L}]/u', $trimmed) === 1;
    }

    private function textMatchesSource(string $candidate, string $sourceText): bool
    {
        $candidateNormalized = $this->normalizeComparableText($candidate);
        if ($candidateNormalized === '') {
            return false;
        }

        $sourceNormalized = $this->normalizeComparableText($sourceText);
        if ($sourceNormalized === '') {
            return false;
        }

        if ($candidateNormalized === $sourceNormalized) {
            return true;
        }

        if (Str::length($candidateNormalized) >= 4 && Str::contains($sourceNormalized, $candidateNormalized)) {
            return true;
        }

        $fragments = preg_split('/[\r\n]+|(?<=[.!?])\s+/u', $sourceText) ?: [];
        foreach ($fragments as $fragment) {
            $fragmentNormalized = $this->normalizeComparableText($fragment);
            if ($fragmentNormalized === '' || Str::length($fragmentNormalized) < 4) {
                continue;
            }

            if ($candidateNormalized === $fragmentNormalized || Str::contains($candidateNormalized, $fragmentNormalized) || Str::contains($fragmentNormalized, $candidateNormalized)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparableText(string $value): string
    {
        $normalized = Str::lower(trim($value));
        $normalized = preg_replace('/["“”„\'`]+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    /**
     * @param  array<int, string>  $strings
     * @return array<int, string>|null
     */
    private function translateStringsDeterministically(array $strings, string $targetLocale): ?array
    {
        if ($strings === []) {
            return [];
        }

        if (! $this->internalAi->isConfigured()) {
            return null;
        }

        $languageNames = [
            'ka' => 'Georgian',
            'en' => 'English',
            'ru' => 'Russian',
        ];
        $targetLanguage = $languageNames[$targetLocale] ?? $targetLocale;
        $payload = json_encode(array_values($strings), JSON_UNESCAPED_UNICODE);
        if (! is_string($payload)) {
            return null;
        }

        $prompt = <<<PROMPT
Translate each string in this JSON array to {$targetLanguage}. Preserve intent and marketing tone. Return JSON array only, in the same order, with translated strings only.

{$payload}
PROMPT;

        $response = $this->internalAi->complete($prompt, 1200);
        if ($response === null || trim($response) === '') {
            return null;
        }

        $decoded = $this->parseJsonStringArray($response);
        if ($decoded === null || count($decoded) !== count($strings)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array<int, string>|null
     */
    private function parseJsonStringArray(string $response): ?array
    {
        $text = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $text = trim((string) ($matches[1] ?? ''));
        }
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $text = $matches[0];
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            return null;
        }

        $strings = [];
        foreach ($decoded as $value) {
            if (! is_string($value)) {
                return null;
            }
            $strings[] = trim($value);
        }

        return $strings;
    }

    /**
     * @param  array<int, array{scope: string, section_id?: string, component?: string, path: string, value: string}>  $targets
     * @param  callable(array{scope: string, section_id?: string, component?: string, path: string, value: string, editable_fields?: array<int, string>}): string|null  $valueResolver
     * @return array<int, array<string, mixed>>
     */
    private function buildTargetOperations(array $targets, callable $valueResolver): array
    {
        $operations = [];
        $globalPatches = [];

        foreach ($targets as $target) {
            $nextValue = $valueResolver($target);
            if ($nextValue === null) {
                continue;
            }

            if (($target['scope'] ?? 'section') === 'section') {
                $resolvedPath = $this->resolveTargetOperationPath($target);
                $operations[] = [
                    'op' => 'updateText',
                    'sectionId' => $target['section_id'],
                    'path' => $resolvedPath,
                    'value' => $nextValue,
                ];
                continue;
            }

            $component = trim((string) ($target['component'] ?? ''));
            if ($component === '') {
                continue;
            }

            $resolvedPath = $this->resolveTargetOperationPath($target);

            $globalPatches[$component] = array_replace_recursive(
                is_array($globalPatches[$component] ?? null) ? $globalPatches[$component] : [],
                $this->buildPatchForPath($resolvedPath, $nextValue)
            );
        }

        foreach ($globalPatches as $component => $patch) {
            if (! is_array($patch) || $patch === []) {
                continue;
            }

            $operations[] = [
                'op' => 'updateGlobalComponent',
                'component' => $component,
                'patch' => $patch,
            ];
        }

        return $operations;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPatchForPath(string $path, mixed $value): array
    {
        $patch = [];
        Arr::set($patch, $path, $value);

        return $patch;
    }

    /**
     * @param  array{scope: string, section_id?: string, component?: string, path: string, value: string}  $target
     * @param  array<string, mixed>  $pageContext
     */
    private function resolveSiblingLinkPath(array $target, array $pageContext): ?string
    {
        $path = trim((string) ($target['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $candidates = [];
        if (preg_match('/\.label$/', $path) === 1) {
            $base = substr($path, 0, -strlen('.label'));
            $candidates = [$base.'.url', $base.'.href', $base.'.link'];
        } elseif (preg_match('/_label$/', $path) === 1) {
            $base = substr($path, 0, -strlen('_label'));
            $candidates = [$base.'_url', $base.'_href', $base.'_link'];
        } elseif ($path === 'buttonText' || $path === 'buttonLabel') {
            $candidates = ['buttonLink', 'buttonUrl', 'buttonHref'];
        }

        if ($candidates === []) {
            return null;
        }

        $props = [];
        if (($target['scope'] ?? 'section') === 'section') {
            foreach ($pageContext['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                if (trim((string) ($section['id'] ?? '')) !== trim((string) ($target['section_id'] ?? ''))) {
                    continue;
                }
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                break;
            }
        } else {
            $theme = is_array($pageContext['theme'] ?? null) ? $pageContext['theme'] : [];
            $layout = is_array($theme['layout'] ?? null) ? $theme['layout'] : [];
            $component = trim((string) ($target['component'] ?? ''));
            $props = is_array($layout[$component.'_props'] ?? null) ? $layout[$component.'_props'] : [];
        }

        foreach ($candidates as $candidate) {
            if ($props !== [] && Arr::has($props, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    private function deterministicTextSummary(string $kind, string $userPrompt, ?string $targetLocale = null): string
    {
        $isGeorgian = $this->resolveUserCommandLocale($userPrompt, null) === 'ka';

        if ($kind === 'translate') {
            if ($isGeorgian) {
                return match ($targetLocale) {
                    'ka' => 'ტექსტი ქართულად ვთარგმნე',
                    'en' => 'ტექსტი ინგლისურად ვთარგმნე',
                    default => 'ტექსტი ვთარგმნე',
                };
            }

            return match ($targetLocale) {
                'ka' => 'Translated text to Georgian',
                'en' => 'Translated text to English',
                default => 'Translated text',
            };
        }

        if ($kind === 'dedupe') {
            return $isGeorgian ? 'დუბლირებული ტექსტი მოვაშორე' : 'Removed duplicate text';
        }

        return $isGeorgian ? 'ტექსტი განვაახლე' : 'Updated text';
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     * @return array<int, string>
     */
    private function summarizeDeterministicOperations(array $operations): array
    {
        $summary = [];

        foreach ($operations as $operation) {
            $opType = trim((string) ($operation['op'] ?? ''));
            if ($opType === '') {
                continue;
            }

            $target = trim((string) ($operation['sectionId'] ?? $operation['component'] ?? ''));
            $path = trim((string) ($operation['path'] ?? ''));
            $summary[] = implode(' ', array_filter([
                $opType,
                $target !== '' ? '-> '.$target : null,
                $path !== '' ? '['.$path.']' : null,
            ]));
        }

        return $summary;
    }

    private function buildFullPrompt(string $userPrompt, string $contextSummary, ?string $userLanguageHint = null): string
    {
        $resolvedLanguageHint = $this->normalizeUserLanguageHint($userLanguageHint);
        $languageRule = match ($resolvedLanguageHint) {
            'ka' => "\n- The user's primary working language is Georgian. Write the summary array in Georgian.\n- Treat minor Georgian spelling mistakes, colloquial phrasing, and romanized Georgian hints as valid Georgian commands.\n- When the user provides visible Georgian copy, preserve it exactly as requested.",
            'en' => "\n- Write the summary array in the same language as the user's command. If the user wrote in English, keep the summary in English.",
            default => "\n- Write the summary array in the same language as the user's command. If the language is ambiguous, prefer Georgian.",
        };

        $system = <<<SYSTEM
You are an AI website editing assistant. Work at Codex-level precision: minimal, exact patches only.

Convert user commands into structured ChangeSet operations.

Rules:
- Output JSON only. No markdown, no code fence, no explanation.
- Follow the ChangeSet schema exactly.
- Use only the allowed operations: updateSection, insertSection, deleteSection, reorderSection, updateTheme, updateGlobalComponent (component: "header"|"footer", patch: {}), updateText, replaceImage, updateButton, addProduct, removeProduct, translatePage, generateContent.
- Keep summary array short (1-3 brief phrases).
- Do not generate HTML or raw CSS.
- Codex-level precision: when "Section [id] current props" is provided, output updateSection with a patch containing ONLY the keys that actually change. Do not repeat unchanged fields. Same for updateTheme: patch only the theme keys the user asked to change (e.g. primary, background, borderRadius, spacing, shadows). Use exact token/key names from the context.
- Use sectionId from the provided page context when the user refers to "this section", "hero", "testimonials", etc.
- For insertSection use one of the exact sectionType values listed in the context under "Available section types for insert". Prefer canonical registry ids such as webu_general_hero_01, webu_general_features_01, webu_general_cta_01, webu_general_grid_01, webu_general_testimonials_01.
- For updateTheme use patch with theme token keys from the provided Theme (e.g. colors.primary, layout.borderRadius). Output only the keys that change.
- If the user asks to change the site-wide header or footer, prefer only updateGlobalComponent for that target. Do not delete or modify page sections unless the user explicitly asks for a page section change too.
- When global component editable fields are provided, use those exact field names. Avoid generic patch keys like "text" if a more specific field exists.
- For translatePage use targetLocale (e.g. "ka", "en", "es").{$languageRule}
- For simple text changes (headline, title, subtitle, button label, CTA text) use updateText with sectionId (from page context), path (e.g. headline, title, subtitle, buttonText), and value (the new text). Example: {"op":"updateText","sectionId":"hero-1","path":"headline","value":"Autumn Collection. A New Season."}
- If selected target context is provided, default to modifying only that selected target.
- When selected target context is provided, stay inside its allowed operations and allowed field paths unless the user explicitly asks for a broader same-section/component change ("this section", "this component", "this header", "this footer") or a broader page/global change.
- When the user explicitly asks for a broader same-section/component change, stay within the same section/component and use only the allowed same-section field paths from context.
- If the selected target request cannot be mapped safely to allowed operations/paths, return {"operations":[],"summary":["No safe target-mapped change"]}.

Example output:
{"operations":[{"op":"updateSection","sectionId":"hero-1","patch":{"headline":"New headline"}},{"op":"insertSection","sectionType":"webu_general_features_01","afterSectionId":"hero-1"}],"summary":["Updated hero headline","Added features section"]}
SYSTEM;

        $user = "User command: {$userPrompt}\n\n{$contextSummary}\n\nRespond with a single JSON object: { \"operations\": [...], \"summary\": [...] }";

        return $system."\n\n".$user;
    }

    private function resolveUserCommandLocale(string $userPrompt, ?string $userLanguageHint = null): string
    {
        if ($this->looksLikeGeorgianCommand($userPrompt)) {
            return 'ka';
        }

        if ($this->looksLikeEnglishCommand($userPrompt)) {
            return 'en';
        }

        return $this->normalizeUserLanguageHint($userLanguageHint) ?? 'ka';
    }

    private function normalizeUserLanguageHint(?string $userLanguageHint): ?string
    {
        $hint = strtolower(trim((string) $userLanguageHint));
        if ($hint === '') {
            return null;
        }

        if (Str::startsWith($hint, 'ka')) {
            return 'ka';
        }

        if (Str::startsWith($hint, 'en')) {
            return 'en';
        }

        return null;
    }

    private function looksLikeGeorgianCommand(string $userPrompt): bool
    {
        if (preg_match('/\p{Georgian}/u', $userPrompt) === 1) {
            return true;
        }

        $normalized = Str::lower(trim($userPrompt));
        if ($normalized === '') {
            return false;
        }

        return Str::contains($normalized, [
            'qartul',
            'qartulad',
            'qartuli',
            'kartul',
            'kartulad',
            'kartuli',
        ]);
    }

    private function looksLikeEnglishCommand(string $userPrompt): bool
    {
        if ($this->looksLikeGeorgianCommand($userPrompt)) {
            return false;
        }

        return preg_match('/[a-z]{3,}/i', $userPrompt) === 1;
    }

    /**
     * @return array{operations: array, summary: array<int, string>}|null
     */
    private function parseChangeSetFromResponse(string $response): ?array
    {
        $text = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            return null;
        }
        if (! isset($decoded['operations']) || ! is_array($decoded['operations'])) {
            return null;
        }
        if (! isset($decoded['summary']) || ! is_array($decoded['summary'])) {
            return null;
        }
        $decoded['summary'] = array_values(array_filter(array_map('strval', $decoded['summary'])));

        return $decoded;
    }

    /**
     * @param  array{operations: array, summary: array}  $changeSet
     */
    private function validateChangeSet(array $changeSet): ?string
    {
        foreach ($changeSet['operations'] as $i => $op) {
            if (! is_array($op) || empty($op['op']) || ! is_string($op['op'])) {
                return "Operation {$i}: missing or invalid 'op'.";
            }
            if (! in_array($op['op'], self::ALLOWED_OPS, true)) {
                return "Operation {$i}: unknown op '".$op['op']."'.";
            }
            $err = $this->validateOperation($op, $i);
            if ($err !== null) {
                return $err;
            }
        }
        return null;
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function validateOperation(array $op, int $index): ?string
    {
        $opType = $op['op'];
        switch ($opType) {
            case 'updateSection':
                if (empty($op['sectionId']) || ! is_string($op['sectionId'])) {
                    return "Operation {$index}: updateSection requires sectionId.";
                }
                if (! isset($op['patch']) || ! is_array($op['patch'])) {
                    return "Operation {$index}: updateSection requires patch object.";
                }
                break;
            case 'insertSection':
                if (empty($op['sectionType']) || ! is_string($op['sectionType'])) {
                    return "Operation {$index}: insertSection requires sectionType.";
                }
                break;
            case 'deleteSection':
            case 'reorderSection':
                if (empty($op['sectionId']) || ! is_string($op['sectionId'])) {
                    return "Operation {$index}: {$opType} requires sectionId.";
                }
                if ($opType === 'reorderSection') {
                    if (! array_key_exists('toIndex', $op) || ! is_numeric($op['toIndex'])) {
                        return "Operation {$index}: reorderSection requires toIndex (integer).";
                    }
                }
                break;
            case 'updateTheme':
                if (! isset($op['patch']) || ! is_array($op['patch'])) {
                    return "Operation {$index}: updateTheme requires patch object.";
                }
                break;
            case 'updateGlobalComponent':
                if (empty($op['component']) || ! is_string($op['component'])) {
                    return "Operation {$index}: updateGlobalComponent requires component (header|footer).";
                }
                if (! in_array(strtolower((string) $op['component']), ['header', 'footer'], true)) {
                    return "Operation {$index}: updateGlobalComponent component must be header or footer.";
                }
                if (! isset($op['patch']) || ! is_array($op['patch'])) {
                    return "Operation {$index}: updateGlobalComponent requires patch object.";
                }
                break;
            case 'updateText':
                if (empty($op['sectionId']) && empty($op['section_id'])) {
                    return "Operation {$index}: updateText requires sectionId.";
                }
                if (! isset($op['value']) || ! is_string($op['value'])) {
                    return "Operation {$index}: updateText requires value string.";
                }
                break;
            case 'replaceImage':
                if (empty($op['sectionId']) || ! is_string($op['sectionId'])) {
                    return "Operation {$index}: replaceImage requires sectionId.";
                }
                break;
            case 'updateButton':
                if (empty($op['sectionId']) || ! is_string($op['sectionId'])) {
                    return "Operation {$index}: updateButton requires sectionId.";
                }
                break;
            case 'translatePage':
                if (empty($op['targetLocale']) || ! is_string($op['targetLocale'])) {
                    return "Operation {$index}: translatePage requires targetLocale.";
                }
                break;
            case 'generateContent':
                if (empty($op['instruction']) || ! is_string($op['instruction'])) {
                    return "Operation {$index}: generateContent requires instruction.";
                }
                break;
        }
        return null;
    }
}

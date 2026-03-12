<?php

namespace App\Services;

use App\Models\CmsLearnedRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CmsAiLearnedRuleApplicationService
{
    public const ENGINE_VERSION = 1;

    public const GENERATION_VERSION = 'p6-g3-01.v1';

    public const RULE_VERSIONING_VERSION = 'p6-g3-02.v1';

    public const FINGERPRINT_ALGORITHM = 'sha256';

    public const CANONICAL_JSON_VERSION = 1;

    /**
     * Fetch and apply active learned rules to generated pages in deterministic order.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $aiInput
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function applyToGeneratedPages(array $pages, array $aiInput, array $context = []): array
    {
        $diagnostics = [
            'engine_version' => self::ENGINE_VERSION,
            'generation_version' => self::GENERATION_VERSION,
            'enabled' => true,
            'site_id' => $this->safeString(data_get($aiInput, 'platform_context.site.id'), 64) ?: null,
            'project_id' => $this->safeString(data_get($aiInput, 'platform_context.project.id'), 64) ?: null,
            'site_family' => $this->safeString($context['site_family'] ?? null, 50) ?: null,
            'eligible_rules' => 0,
            'matched_rules' => 0,
            'applied_rule_count' => 0,
            'applied_rules' => [],
            'skipped_rules' => [],
            'conflicts_skipped' => 0,
            'nodes_touched' => 0,
            'rule_fetch' => null,
            'versioning' => $this->initializeVersioningDiagnostics(),
            'privacy_enforcement' => $this->normalizePrivacyEnforcementDiagnostics($context['privacy_policy'] ?? null),
        ];
        $warnings = [];
        $privacyPolicy = $this->normalizePrivacyPolicy($context['privacy_policy'] ?? null);

        $siteId = is_string($diagnostics['site_id']) ? $diagnostics['site_id'] : null;
        if ($siteId === null || $siteId === '') {
            $diagnostics['enabled'] = false;
            $diagnostics['rule_fetch'] = ['status' => 'skipped', 'reason' => 'site_id_missing'];

            return [
                'pages' => $pages,
                'diagnostics' => $diagnostics,
                'warnings' => [],
            ];
        }

        if (! (bool) ($privacyPolicy['apply_learned_rules'] ?? true)) {
            $reason = (bool) ($privacyPolicy['tenant_opt_out'] ?? false)
                ? 'tenant_opt_out'
                : 'system_learning_generation_disabled';
            $diagnostics['enabled'] = false;
            $diagnostics['rule_fetch'] = ['status' => 'skipped', 'reason' => $reason];
            $warnings[] = $reason === 'tenant_opt_out'
                ? 'Learned rule application skipped because tenant opted out of learning-enhanced generation.'
                : 'Learned rule application skipped because system learning generation is disabled by privacy policy.';

            return [
                'pages' => $pages,
                'diagnostics' => $diagnostics,
                'warnings' => $warnings,
            ];
        }

        try {
            if (! Schema::hasTable('cms_learned_rules')) {
                $diagnostics['enabled'] = false;
                $diagnostics['rule_fetch'] = ['status' => 'skipped', 'reason' => 'cms_learned_rules_table_missing'];
                $warnings[] = 'Learned rule application skipped because cms_learned_rules table is not available.';

                return [
                    'pages' => $pages,
                    'diagnostics' => $diagnostics,
                    'warnings' => $warnings,
                ];
            }

            $allowGlobalRules = (bool) ($privacyPolicy['allow_global_learned_rules'] ?? true);
            $rules = CmsLearnedRule::query()
                ->where(function ($query) use ($siteId, $allowGlobalRules): void {
                    $query->where(function ($tenantScoped) use ($siteId): void {
                        $tenantScoped->where('scope', 'tenant')->where('site_id', $siteId);
                    });

                    if ($allowGlobalRules) {
                        $query->orWhere(function ($globalScoped): void {
                            $globalScoped->where('scope', 'global')->whereNull('site_id');
                        });
                    }
                })
                ->where('status', 'active')
                ->where('active', true)
                ->orderByDesc('confidence')
                ->orderByDesc('sample_size')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $exception) {
            $diagnostics['enabled'] = false;
            $diagnostics['rule_fetch'] = ['status' => 'failed', 'reason' => 'query_error'];
            $warnings[] = 'Learned rule application skipped because rule fetch failed.';
            Log::warning('cms.ai.learned_rules.fetch_failed', [
                'site_id' => $siteId,
                'project_id' => $diagnostics['project_id'],
                'error' => $exception->getMessage(),
            ]);

            return [
                'pages' => $pages,
                'diagnostics' => $diagnostics,
                'warnings' => $warnings,
            ];
        }

        $diagnostics['eligible_rules'] = $rules->count();
        $diagnostics['rule_fetch'] = ['status' => 'ok', 'count' => $rules->count()];
        $eligibleRuleSnapshots = [];
        foreach ($rules as $eligibleRule) {
            $eligibleRuleSnapshots[] = $this->ruleVersionSnapshot($eligibleRule);
        }
        $diagnostics['versioning'] = $this->buildRuleVersioningDiagnostics($eligibleRuleSnapshots, [], []);

        if ($rules->isEmpty()) {
            return [
                'pages' => $pages,
                'diagnostics' => $diagnostics,
                'warnings' => [],
            ];
        }

        $siteFamily = $this->safeString($context['site_family'] ?? null, 50);
        $prompt = strtolower($this->safeString(data_get($aiInput, 'request.prompt'), 5000));
        $promptTokens = $this->extractPromptTokens($prompt);
        $claimedTargets = [];
        $matchedRules = 0;
        $appliedRuleCount = 0;
        $conflictsSkipped = 0;
        $nodesTouched = 0;
        $matchedRuleSnapshots = [];
        $appliedRuleSnapshots = [];

        foreach ($rules as $rule) {
            $conditions = is_array($rule->conditions_json) ? $rule->conditions_json : [];
            $patch = is_array($rule->patch_json) ? $rule->patch_json : [];
            $matchDecision = $this->ruleMatchesGenerationContext($conditions, $siteFamily, $promptTokens);
            if (($matchDecision['matched'] ?? false) !== true) {
                $diagnostics['skipped_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_key' => $rule->rule_key,
                    'reason' => $matchDecision['reason'] ?? 'conditions_not_matched',
                ];
                continue;
            }

            $normalizedPatch = $this->normalizePatchTemplate($patch, $conditions);
            if (! is_array($normalizedPatch)) {
                $diagnostics['skipped_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_key' => $rule->rule_key,
                    'reason' => 'unsupported_patch_template',
                ];
                continue;
            }

            $matchedRules++;
            $matchedRuleSnapshots[] = $this->ruleVersionSnapshot($rule, $normalizedPatch);
            $affectedPages = [];
            $affectedNodes = 0;
            $ruleConflicts = 0;
            $ruleNoops = 0;

            foreach ($pages as $pageIndex => $page) {
                if (! is_array($page)) {
                    continue;
                }

                $pageTemplateKey = $this->safeString($page['template_key'] ?? null, 80);
                $pageSlug = $this->safeString($page['slug'] ?? null, 120) ?: 'unknown';
                $requiredTemplateKey = $this->safeString(data_get($conditions, 'page_template_key'), 80);
                if ($requiredTemplateKey !== '' && ! $this->pageTemplateMatchesCondition($requiredTemplateKey, $pageTemplateKey, $pageSlug)) {
                    continue;
                }

                $nodes = is_array($page['builder_nodes'] ?? null) ? $page['builder_nodes'] : null;
                if (! is_array($nodes)) {
                    continue;
                }

                $pageChanged = false;
                $updatedNodes = $this->applyRuleToNodesRecursive(
                    $nodes,
                    $normalizedPatch,
                    $claimedTargets,
                    $pageSlug,
                    '/builder_nodes',
                    $affectedNodes,
                    $ruleConflicts,
                    $ruleNoops,
                    $pageChanged
                );

                if ($pageChanged) {
                    $pages[$pageIndex]['builder_nodes'] = $updatedNodes;
                    $affectedPages[] = $pageSlug;
                }
            }

            if ($affectedNodes > 0) {
                $appliedRuleCount++;
                $nodesTouched += $affectedNodes;
                $appliedRuleSnapshots[] = array_merge(
                    $this->ruleVersionSnapshot($rule, $normalizedPatch),
                    [
                        'pages' => array_values(array_unique($affectedPages)),
                        'affected_nodes' => $affectedNodes,
                    ]
                );
                $diagnostics['applied_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_key' => $rule->rule_key,
                    'priority' => [
                        'confidence' => $rule->confidence !== null ? (float) $rule->confidence : null,
                        'sample_size' => (int) $rule->sample_size,
                        'id' => (int) $rule->id,
                    ],
                    'component_type' => $normalizedPatch['component_type'],
                    'patch' => [
                        'op' => $normalizedPatch['op'],
                        'path_suffix' => $normalizedPatch['path_suffix'],
                        'value' => $normalizedPatch['value'],
                    ],
                    'pages' => array_values(array_unique($affectedPages)),
                    'affected_nodes' => $affectedNodes,
                    'conflicts_skipped' => $ruleConflicts,
                    'noops' => $ruleNoops,
                ];
            } else {
                $diagnostics['skipped_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_key' => $rule->rule_key,
                    'reason' => $ruleConflicts > 0 ? 'conflict_with_higher_priority_rule' : 'no_matching_nodes_or_noop',
                    'conflicts_skipped' => $ruleConflicts,
                    'noops' => $ruleNoops,
                ];
            }

            $conflictsSkipped += $ruleConflicts;
        }

        $diagnostics['matched_rules'] = $matchedRules;
        $diagnostics['applied_rule_count'] = $appliedRuleCount;
        $diagnostics['conflicts_skipped'] = $conflictsSkipped;
        $diagnostics['nodes_touched'] = $nodesTouched;
        $diagnostics['versioning'] = $this->buildRuleVersioningDiagnostics(
            $eligibleRuleSnapshots,
            $matchedRuleSnapshots,
            $appliedRuleSnapshots
        );

        return [
            'pages' => $pages,
            'diagnostics' => $diagnostics,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $promptTokens
     * @return array{matched:bool,reason:string}
     */
    private function ruleMatchesGenerationContext(array $conditions, string $siteFamily, array $promptTokens): array
    {
        $storeType = strtolower($this->safeString($conditions['store_type'] ?? null, 50));
        if ($storeType !== '' && $storeType !== 'unknown' && $siteFamily !== '' && $storeType !== strtolower($siteFamily)) {
            return ['matched' => false, 'reason' => 'store_type_mismatch'];
        }

        $requiredTags = [];
        if (is_array($conditions['prompt_intent_tags'] ?? null)) {
            foreach ($conditions['prompt_intent_tags'] as $tag) {
                $safe = strtolower($this->safeString($tag, 40));
                if ($safe !== '') {
                    $requiredTags[] = $safe;
                }
            }
        }
        $requiredTags = array_values(array_unique($requiredTags));

        foreach ($requiredTags as $tag) {
            if (! in_array($tag, $promptTokens, true)) {
                return ['matched' => false, 'reason' => 'prompt_intent_tags_mismatch'];
            }
        }

        return ['matched' => true, 'reason' => 'matched'];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $conditions
     * @return array<string, mixed>|null
     */
    private function normalizePatchTemplate(array $patch, array $conditions): ?array
    {
        $format = $this->safeString($patch['format'] ?? null, 64);
        if ($format !== 'json_patch_template') {
            return null;
        }

        $strategy = $this->safeString($patch['strategy'] ?? null, 80) ?: 'component_type_path_suffix';
        if ($strategy !== 'component_type_path_suffix') {
            return null;
        }

        $op = $this->safeString($patch['op'] ?? null, 16);
        if (! in_array($op, ['add', 'replace', 'remove'], true)) {
            return null;
        }

        $componentType = $this->safeString($patch['component_type'] ?? ($conditions['component_type'] ?? null), 120);
        if ($componentType === '') {
            return null;
        }

        $pathSuffix = $this->safeString($patch['path_suffix'] ?? null, 500);
        if ($pathSuffix === '' || ! str_starts_with($pathSuffix, '/props/')) {
            return null;
        }

        if ($op !== 'remove' && ! array_key_exists('value', $patch)) {
            return null;
        }

        return [
            'op' => $op,
            'component_type' => $componentType,
            'component_aliases' => $this->componentAliases($componentType),
            'path_suffix' => $pathSuffix,
            'value' => $op === 'remove' ? null : ($patch['value'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $patch
     * @param  array<string, int>  $claimedTargets
     * @return array<int, array<string, mixed>>
     */
    private function applyRuleToNodesRecursive(
        array $nodes,
        array $patch,
        array &$claimedTargets,
        string $pageSlug,
        string $basePath,
        int &$affectedNodes,
        int &$ruleConflicts,
        int &$ruleNoops,
        bool &$pageChanged,
    ): array {
        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                continue;
            }

            $nodePath = $basePath.'/'.$index;
            $nodeType = $this->safeString($node['type'] ?? null, 120);
            if ($nodeType !== '' && $this->componentMatches($nodeType, (array) ($patch['component_aliases'] ?? []))) {
                $claimKey = $pageSlug.'|'.$nodePath.'|'.(string) $patch['path_suffix'];
                if (array_key_exists($claimKey, $claimedTargets)) {
                    $ruleConflicts++;
                } else {
                    [$updatedNode, $changed] = $this->applyPatchToNode($node, $patch);
                    if ($changed) {
                        $nodes[$index] = $updatedNode;
                        $claimedTargets[$claimKey] = 1;
                        $affectedNodes++;
                        $pageChanged = true;
                    } else {
                        $ruleNoops++;
                    }
                }
            }

            $children = $node['children'] ?? null;
            if (is_array($children)) {
                $nodes[$index]['children'] = $this->applyRuleToNodesRecursive(
                    $children,
                    $patch,
                    $claimedTargets,
                    $pageSlug,
                    $nodePath.'/children',
                    $affectedNodes,
                    $ruleConflicts,
                    $ruleNoops,
                    $pageChanged
                );
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $patch
     * @return array{0:array<string, mixed>,1:bool}
     */
    private function applyPatchToNode(array $node, array $patch): array
    {
        $pathTokens = $this->decodeJsonPointer((string) $patch['path_suffix']);
        if ($pathTokens === []) {
            return [$node, false];
        }

        $before = $node;
        $changed = $this->applyPointerPatch(
            $node,
            $pathTokens,
            (string) $patch['op'],
            $patch['value'] ?? null
        );

        if (! $changed || $before == $node) {
            return [$before, false];
        }

        return [$node, true];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function applyPointerPatch(array &$root, array $tokens, string $op, mixed $value): bool
    {
        $lastToken = array_pop($tokens);
        if ($lastToken === null) {
            return false;
        }

        $current =& $root;
        foreach ($tokens as $token) {
            $key = $this->normalizeArrayKey($token, $current);
            if (! is_array($current)) {
                return false;
            }

            if (! array_key_exists($key, $current)) {
                if ($op === 'add' || $op === 'replace') {
                    $current[$key] = [];
                } else {
                    return false;
                }
            }

            if (! is_array($current[$key])) {
                if ($op === 'add' || $op === 'replace') {
                    $current[$key] = [];
                } else {
                    return false;
                }
            }

            $current =& $current[$key];
        }

        $leafKey = $this->normalizeArrayKey($lastToken, $current);
        if ($op === 'remove') {
            if (! is_array($current) || ! array_key_exists($leafKey, $current)) {
                return false;
            }
            unset($current[$leafKey]);

            return true;
        }

        if (! is_array($current)) {
            return false;
        }

        $current[$leafKey] = $this->deepCopy($value);

        return true;
    }

    /**
     * @return list<string>
     */
    private function decodeJsonPointer(string $pointer): array
    {
        if ($pointer === '' || ! str_starts_with($pointer, '/')) {
            return [];
        }

        $parts = explode('/', ltrim($pointer, '/'));
        $tokens = [];
        foreach ($parts as $part) {
            $tokens[] = str_replace(['~1', '~0'], ['/', '~'], $part);
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function componentMatches(string $nodeType, array $aliases): bool
    {
        $normalizedNodeType = strtolower($nodeType);
        foreach ($aliases as $alias) {
            if ($normalizedNodeType === strtolower($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function componentAliases(string $componentType): array
    {
        $normalized = strtolower(trim($componentType));
        $aliases = [$componentType];

        $heuristics = [
            'product_grid' => 'products-grid',
            'products_grid' => 'products-grid',
            'product-detail' => 'product-detail',
            'product_detail' => 'product-detail',
            'order_detail' => 'order-detail',
            'orders_list' => 'orders-list',
            'checkout_form' => 'checkout-form',
            'order_summary' => 'order-summary',
            'account_dashboard' => 'account-dashboard',
            'cart_summary' => 'cart-summary',
            'posts_list' => 'posts-list',
            'post_detail' => 'post-detail',
        ];

        foreach ($heuristics as $needle => $alias) {
            if (str_contains($normalized, $needle)) {
                $aliases[] = $alias;
            }
        }

        if (preg_match('/(?:^|[_-])(button|heading|text|section|auth|form)(?:$|[_-])/', $normalized, $matches) === 1) {
            $aliases[] = strtolower((string) $matches[1]);
        }

        $aliases[] = str_replace('_', '-', $normalized);
        $aliases[] = str_replace('-', '_', $normalized);

        $aliases = array_values(array_unique(array_filter(array_map('trim', $aliases), fn ($value): bool => is_string($value) && $value !== '')));
        sort($aliases);

        return $aliases;
    }

    private function pageTemplateMatchesCondition(string $requiredTemplateKey, string $pageTemplateKey, string $pageSlug): bool
    {
        $normalizedRequired = strtolower(trim($requiredTemplateKey));
        $normalizedTemplate = strtolower(trim($pageTemplateKey));
        $normalizedSlug = strtolower(trim($pageSlug));

        $candidates = array_values(array_unique(array_filter([
            $normalizedTemplate !== '' ? $normalizedTemplate : null,
            $normalizedSlug !== '' ? $normalizedSlug : null,
        ])));

        $aliases = [
            'shop' => ['product-listing'],
            'product-listing' => ['shop'],
            'product' => ['product-detail'],
            'product-detail' => ['product'],
            'login' => ['login-register'],
            'login-register' => ['login'],
            'orders' => ['orders-list'],
            'orders-list' => ['orders'],
            'order' => ['order-detail'],
            'order-detail' => ['order'],
        ];

        foreach (array_values($candidates) as $candidate) {
            foreach (($aliases[$candidate] ?? []) as $alias) {
                $candidates[] = strtolower($alias);
            }
        }

        $candidates = array_values(array_unique($candidates));

        return in_array($normalizedRequired, $candidates, true);
    }

    /**
     * @return list<string>
     */
    private function extractPromptTokens(string $prompt): array
    {
        if ($prompt === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9._:-]+/i', $prompt) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtolower(trim((string) $part));
            if ($token === '') {
                continue;
            }
            $tokens[] = $token;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    private function normalizeArrayKey(string $token, array $container): int|string
    {
        return ctype_digit($token) && array_is_list($container)
            ? (int) $token
            : $token;
    }

    private function deepCopy(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->deepCopy($item);
        }

        return $result;
    }

    private function safeString(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, max(1, $max));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePrivacyPolicy(mixed $value): array
    {
        if (! is_array($value)) {
            return [
                'tenant_opt_out' => false,
                'apply_learned_rules' => true,
                'allow_global_learned_rules' => true,
                'diagnostics' => [
                    'status' => 'implicit_default_allow',
                    'reasons' => [],
                ],
            ];
        }

        return [
            'tenant_opt_out' => (bool) data_get($value, 'effective.tenant_opt_out', false),
            'apply_learned_rules' => (bool) data_get($value, 'effective.apply_learned_rules', true),
            'allow_global_learned_rules' => (bool) data_get($value, 'effective.allow_global_learned_rules', true),
            'diagnostics' => is_array($value['diagnostics'] ?? null) ? $value['diagnostics'] : [
                'status' => 'provided',
                'reasons' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePrivacyEnforcementDiagnostics(mixed $policy): array
    {
        $normalized = $this->normalizePrivacyPolicy($policy);

        return [
            'tenant_opt_out' => (bool) ($normalized['tenant_opt_out'] ?? false),
            'apply_learned_rules' => (bool) ($normalized['apply_learned_rules'] ?? true),
            'allow_global_learned_rules' => (bool) ($normalized['allow_global_learned_rules'] ?? true),
            'policy' => is_array($normalized['diagnostics'] ?? null) ? $normalized['diagnostics'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeVersioningDiagnostics(): array
    {
        return [
            'schema_version' => 1,
            'versioning_version' => self::RULE_VERSIONING_VERSION,
            'fingerprint_algorithm' => self::FINGERPRINT_ALGORITHM,
            'canonical_json_version' => self::CANONICAL_JSON_VERSION,
            'selection_order_version' => 'confidence_desc_sample_size_desc_id_asc.v1',
            'eligible_rule_set_version' => null,
            'matched_rule_set_version' => null,
            'applied_rule_set_version' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $eligibleSnapshots
     * @param  array<int, array<string, mixed>>  $matchedSnapshots
     * @param  array<int, array<string, mixed>>  $appliedSnapshots
     * @return array<string, mixed>
     */
    private function buildRuleVersioningDiagnostics(array $eligibleSnapshots, array $matchedSnapshots, array $appliedSnapshots): array
    {
        return array_merge($this->initializeVersioningDiagnostics(), [
            'eligible_rule_set_version' => $this->stableFingerprint($eligibleSnapshots),
            'matched_rule_set_version' => $this->stableFingerprint($matchedSnapshots),
            'applied_rule_set_version' => $this->stableFingerprint($appliedSnapshots),
            'eligible_rule_count' => count($eligibleSnapshots),
            'matched_rule_count' => count($matchedSnapshots),
            'applied_rule_count' => count($appliedSnapshots),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $normalizedPatch
     * @return array<string, mixed>
     */
    private function ruleVersionSnapshot(CmsLearnedRule $rule, ?array $normalizedPatch = null): array
    {
        return [
            'id' => (int) $rule->id,
            'scope' => $this->safeString($rule->scope, 20),
            'site_id' => $this->safeString($rule->site_id, 64) ?: null,
            'project_id' => $this->safeString($rule->project_id, 64) ?: null,
            'rule_key' => $this->safeString($rule->rule_key, 120),
            'status' => $this->safeString($rule->status, 20),
            'active' => (bool) $rule->active,
            'source' => $this->safeString($rule->source, 40),
            'confidence' => $rule->confidence !== null ? (float) $rule->confidence : null,
            'sample_size' => (int) $rule->sample_size,
            'delta_count' => (int) $rule->delta_count,
            'promoted_at' => $rule->promoted_at?->toIso8601String(),
            'conditions_json' => is_array($rule->conditions_json) ? $rule->conditions_json : [],
            'patch_json' => $normalizedPatch ?? (is_array($rule->patch_json) ? $rule->patch_json : []),
        ];
    }

    private function stableFingerprint(mixed $value): string
    {
        $canonical = $this->canonicalizeForHash($value);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $json = 'null';
        }

        return self::FINGERPRINT_ALGORITHM.':'.hash(self::FINGERPRINT_ALGORITHM, $json);
    }

    private function canonicalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->canonicalizeForHash($item);
            }

            return $result;
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeForHash($item);
        }

        return $value;
    }
}

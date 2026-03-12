<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * AI-driven requirement collection: conversation → structured config object.
 * Returns either the next question or the completed config for the site generator.
 */
class RequirementCollectionService
{
    public const STATE_COLLECTING = 'collecting';

    public const STATE_COMPLETE = 'complete';

    /**
     * Default config when AI is not configured or after fallback questions.
     */
    public static function defaultConfig(): array
    {
        return [
            'siteType' => 'ecommerce',
            'businessType' => 'general',
            'designStyle' => 'luxury_minimal',
            'payments' => ['card', 'bank_transfer'],
            'shipping' => ['courier'],
            'modules' => ['products', 'orders', 'checkout', 'blog'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'testimonials'],
        ];
    }

    public function __construct(
        protected InternalAiService $internalAi
    ) {}

    /**
     * Process user message: return next question or completed config.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     * @return array{type: 'question', text: string}|array{type: 'config', config: array<string, mixed>}
     */
    public function processMessage(Project $project, array $conversationHistory, string $userMessage): array
    {
        $existingConfig = is_array($project->requirement_config) ? $project->requirement_config : [];

        if (! $this->internalAi->isConfigured()) {
            return $this->fallbackFlow($conversationHistory, $userMessage, $existingConfig);
        }

        $prompt = $this->buildRequirementPrompt($conversationHistory, $userMessage);

        $response = $this->internalAi->complete($prompt, 1500);

        if ($response === null || trim($response) === '') {
            return $this->fallbackFlow($conversationHistory, $userMessage, $existingConfig);
        }

        $parsed = $this->parseAiResponse($response);

        if ($parsed !== null) {
            return $parsed;
        }

        return $this->fallbackFlow($conversationHistory, $userMessage, $existingConfig);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{type: 'question', text: string}|array{type: 'config', config: array<string, mixed>}
     */
    private function fallbackFlow(array $history, string $userMessage, array $existingConfig): array
    {
        $turnCount = count(array_filter($history, fn ($m) => ($m['role'] ?? '') === 'user'));
        $turnCount += 1;

        if ($turnCount === 1) {
            return [
                'type' => 'question',
                'text' => 'What type of products will you sell? (e.g. fashion, electronics, cosmetics)',
            ];
        }
        if ($turnCount === 2) {
            return [
                'type' => 'question',
                'text' => 'Which design style do you prefer? Options: clean minimal, luxury brand, colorful startup, dark modern, soft pastel.',
            ];
        }
        if ($turnCount === 3) {
            return [
                'type' => 'question',
                'text' => 'Do you need online payments? If yes, which: card, bank transfer, cash on delivery?',
            ];
        }

        $config = array_merge(self::defaultConfig(), $existingConfig);
        $this->inferConfigFromLastMessage($userMessage, $config);

        return [
            'type' => 'config',
            'config' => $this->normalizeConfig($config),
        ];
    }

    private function buildRequirementPrompt(array $conversationHistory, string $userMessage): string
    {
        $historyText = '';
        foreach (array_slice($conversationHistory, -12) as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            $historyText .= ($role === 'assistant' ? 'Assistant: ' : 'User: ').trim($content)."\n";
        }
        $historyText .= 'User: '.trim($userMessage)."\n";

        return <<<PROMPT
You are collecting requirements for an AI website builder. The user wants to create a website (e.g. online store). Ask short, one-at-a-time questions to fill:

- Product/business type (fashion, electronics, cosmetics, etc.)
- Brand name (optional)
- Design style: one of clean_minimal, luxury_minimal, luxury_brand, colorful_startup, dark_modern, soft_pastel
- Payments: array of card, bank_transfer, cash_on_delivery
- Shipping: array of courier, pickup, international
- Modules: products, orders, checkout, blog (for ecommerce)
- Homepage sections: hero, categories, featured_products, testimonials, promo_banner, newsletter

After 2-4 exchanges, when you have enough to decide, respond with ONLY a JSON object, no markdown:
{"type":"config","config":{"siteType":"ecommerce","businessType":"fashion","designStyle":"luxury_minimal","payments":["card"],"shipping":["courier"],"modules":["products","orders","checkout"],"homepageSections":["hero","categories","featured_products"]}}

Otherwise respond with ONLY:
{"type":"question","text":"Your next question here?"}

Conversation so far:
{$historyText}
Assistant:
PROMPT;
    }

    private function parseAiResponse(string $response): ?array
    {
        $trimmed = trim($response);
        $trimmed = preg_replace('/^```\w*\s*|\s*```$/m', '', $trimmed);

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $trimmed, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $type = $decoded['type'] ?? null;
        if ($type === 'question' && ! empty($decoded['text'])) {
            return [
                'type' => 'question',
                'text' => trim((string) $decoded['text']),
            ];
        }

        if ($type === 'config' && isset($decoded['config']) && is_array($decoded['config'])) {
            return [
                'type' => 'config',
                'config' => $this->normalizeConfig($decoded['config']),
            ];
        }

        return null;
    }

    private function inferConfigFromLastMessage(string $message, array &$config): void
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'luxury') || str_contains($lower, 'minimal')) {
            $config['designStyle'] = 'luxury_minimal';
        } elseif (str_contains($lower, 'dark')) {
            $config['designStyle'] = 'dark_modern';
        } elseif (str_contains($lower, 'pastel') || str_contains($lower, 'soft')) {
            $config['designStyle'] = 'soft_pastel';
        } elseif (str_contains($lower, 'colorful') || str_contains($lower, 'startup')) {
            $config['designStyle'] = 'bold_startup';
        } elseif (str_contains($lower, 'corporate') || str_contains($lower, 'clean')) {
            $config['designStyle'] = 'corporate_clean';
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $designStyles = [
            'clean_minimal', 'luxury_minimal', 'luxury_brand', 'colorful_startup',
            'dark_modern', 'soft_pastel', 'corporate_clean', 'bold_startup', 'creative_portfolio',
        ];
        $default = self::defaultConfig();

        $out = [
            'siteType' => is_string($config['siteType'] ?? null) ? trim($config['siteType']) : $default['siteType'],
            'businessType' => is_string($config['businessType'] ?? null) ? trim($config['businessType']) : $default['businessType'],
            'designStyle' => is_string($config['designStyle'] ?? null) ? trim($config['designStyle']) : $default['designStyle'],
            'payments' => is_array($config['payments'] ?? null) ? array_values(array_map('strval', $config['payments'])) : $default['payments'],
            'shipping' => is_array($config['shipping'] ?? null) ? array_values(array_map('strval', $config['shipping'])) : $default['shipping'],
            'modules' => is_array($config['modules'] ?? null) ? array_values(array_map('strval', $config['modules'])) : $default['modules'],
            'homepageSections' => is_array($config['homepageSections'] ?? null) ? array_values(array_map('strval', $config['homepageSections'])) : $default['homepageSections'],
        ];

        if (! in_array($out['designStyle'], $designStyles, true)) {
            $out['designStyle'] = 'luxury_minimal';
        }

        return $out;
    }
}

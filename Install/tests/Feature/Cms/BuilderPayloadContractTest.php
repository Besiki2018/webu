<?php

namespace Tests\Feature\Cms;

use App\Models\Builder;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Services\BuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuilderPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_session_payload_contains_template_locale_and_module_flags_contracts(): void
    {
        $builder = Builder::factory()->create([
            'url' => 'https://builder-contract.test',
            'port' => 8080,
            'server_key' => 'builder-contract-key',
        ]);

        $plan = Plan::factory()->create();
        $user = User::factory()->withPlan($plan)->create(['locale' => 'en']);
        $template = Template::factory()->create([
            'slug' => 'restaurant-contract',
            'name' => 'Restaurant Contract',
            'category' => 'restaurant',
            'version' => '2.1.0',
            'metadata' => [
                'module_flags' => [
                    'ecommerce' => true,
                    'booking' => true,
                    'payments' => true,
                    'shipping' => true,
                ],
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero', 'services']],
                    ['slug' => 'menu', 'title' => 'Menu', 'sections' => ['services']],
                ],
                'default_sections' => [
                    'home' => [['key' => 'hero', 'enabled' => true]],
                    'menu' => [['key' => 'services', 'enabled' => true]],
                ],
                'typography_tokens' => [
                    'heading' => 'heading',
                    'body' => 'body',
                    'button' => 'body',
                ],
            ],
        ]);

        $project = Project::factory()->for($user)->create([
            'template_id' => $template->id,
        ]);

        $site = $this->ensureSite($project);
        $site->update(['locale' => 'en']);

        Http::fake([
            "{$builder->full_url}/api/run" => Http::response([
                'session_id' => 'session-e3',
                'status' => 'started',
            ], 200),
        ]);

        $result = app(BuilderService::class)->startSession(
            $builder,
            $project,
            'Build me a restaurant website with online ordering',
            [],
            null,
            (string) $template->id,
            ['agent' => ['api_key' => 'test-key']],
            ['history' => [], 'is_compacted' => false],
            [
                'source' => 'internal_catalog',
                'fallback_to_generic' => false,
            ]
        );

        $this->assertSame('session-e3', $result['session_id']);

        Http::assertSent(function (Request $request) use ($builder, $template, $project): bool {
            if ($request->url() !== "{$builder->full_url}/api/run") {
                return false;
            }

            $payload = $request->data();

            return ($payload['locale'] ?? null) === 'en'
                && ($payload['template']['template_id'] ?? null) === (string) $template->id
                && ($payload['template']['template_slug'] ?? null) === 'restaurant-contract'
                && (($payload['template']['module_flags']['booking'] ?? null) === true)
                && (($payload['template']['typography_tokens']['button'] ?? null) === 'body')
                && (($payload['template_contract']['locale'] ?? null) === 'en')
                && (($payload['template_contract']['module_flags']['payments'] ?? null) === true)
                && (($payload['template_contract']['typography_tokens']['heading'] ?? null) === 'heading')
                && (($payload['module_flags']['shipping'] ?? null) === true)
                && (($payload['project_capabilities']['localization']['default_locale'] ?? null) === 'en')
                && (($payload['project_capabilities']['template']['selected']['slug'] ?? null) === 'restaurant-contract')
                && (($payload['project_capabilities']['template']['selected']['typography_tokens']['body'] ?? null) === 'body')
                && (($payload['project_capabilities']['modules']['requested']['ecommerce'] ?? null) === true)
                && (($payload['project_capabilities']['cms']['site_id'] ?? null) === $project->site?->id)
                && (($payload['retrieval_context']['source'] ?? null) === 'internal_catalog');
        });
    }

    public function test_start_session_without_template_uses_site_locale_and_empty_module_flags(): void
    {
        $builder = Builder::factory()->create([
            'url' => 'https://builder-contract.test',
            'port' => 8080,
            'server_key' => 'builder-contract-key-2',
        ]);

        $plan = Plan::factory()->create();
        $user = User::factory()->withPlan($plan)->create(['locale' => 'en']);
        $project = Project::factory()->for($user)->create(['template_id' => null]);

        $site = $this->ensureSite($project);
        $site->update(['locale' => 'ka']);

        Http::fake([
            "{$builder->full_url}/api/run" => Http::response([
                'session_id' => 'session-e3-no-template',
                'status' => 'started',
            ], 200),
        ]);

        app(BuilderService::class)->startSession(
            $builder,
            $project,
            'ბიზნეს საიტი',
            [],
            null,
            null,
            ['agent' => ['api_key' => 'test-key']],
            ['history' => [], 'is_compacted' => false]
        );

        Http::assertSent(function (Request $request) use ($builder): bool {
            if ($request->url() !== "{$builder->full_url}/api/run") {
                return false;
            }

            $payload = $request->data();

            return ($payload['locale'] ?? null) === 'ka'
                && ($payload['module_flags'] ?? null) === []
                && ! array_key_exists('template_contract', $payload)
                && ! array_key_exists('retrieval_context', $payload)
                && (($payload['project_capabilities']['modules']['requested'] ?? null) === [])
                && (($payload['project_capabilities']['localization']['default_locale'] ?? null) === 'ka');
        });
    }

    private function ensureSite(Project $project): Site
    {
        $site = $project->site()->first();
        if ($site instanceof Site) {
            return $site;
        }

        /** @var Site $created */
        $created = $project->site()->create([
            'name' => $project->name,
            'primary_domain' => null,
            'subdomain' => null,
            'status' => 'draft',
            'locale' => 'ka',
            'theme_settings' => [],
        ]);

        return $created;
    }
}

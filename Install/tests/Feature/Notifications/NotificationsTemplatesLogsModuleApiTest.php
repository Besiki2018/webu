<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class NotificationsTemplatesLogsModuleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_notification_templates_and_logs_endpoints_work_with_site_scoping(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.notification-templates.store', ['site' => $site->id]), [
                'key' => 'forms_lead_default',
                'name' => 'Forms Lead Default',
                'channel' => 'email',
                'event_key' => 'forms.lead_submitted',
                'locale' => 'en',
                'status' => 'active',
                'subject_template' => 'New lead from {{fields.full_name}}',
                'body_template' => "Form: {{form.name}}\nEmail: {{fields.email}}\nMessage: {{fields.message}}",
                'variables_json' => [
                    ['key' => 'form.name', 'required' => true],
                    ['key' => 'fields.full_name', 'required' => true],
                    ['key' => 'fields.email', 'required' => true],
                ],
                'meta_json' => [
                    'scope' => 'forms',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('template.key', 'forms_lead_default')
            ->assertJsonPath('template.channel', 'email')
            ->assertJsonPath('template.builder_contract.component_type', 'notification_template');

        $templateId = (int) $createResponse->json('template.id');

        $this->assertSame(
            route('panel.sites.notification-logs.preview-dispatch', ['site' => $site->id]),
            $createResponse->json('template.builder_contract.preview_dispatch_endpoint')
        );

        $this->actingAs($owner)
            ->getJson(route('panel.sites.notification-templates.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('templates.0.key', 'forms_lead_default')
            ->assertJsonPath('templates.0.event_key', 'forms.lead_submitted');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.notification-templates.show', ['site' => $site->id, 'template' => $templateId]))
            ->assertOk()
            ->assertJsonPath('template.id', $templateId)
            ->assertJsonPath('template.variables_json.0.key', 'form.name');

        $dispatchResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.notification-logs.preview-dispatch', ['site' => $site->id]), [
                'template_key' => 'forms_lead_default',
                'recipient' => 'owner@example.test',
                'status' => 'preview',
                'payload_json' => [
                    'form' => ['name' => 'Contact Form'],
                    'fields' => [
                        'full_name' => 'Besik Example',
                        'email' => 'besik@example.test',
                        'message' => 'Need a quote.',
                    ],
                ],
                'meta_json' => [
                    'source' => 'test-suite',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('rendered.channel', 'email')
            ->assertJsonPath('rendered.subject', 'New lead from Besik Example')
            ->assertJsonPath('log.status', 'preview')
            ->assertJsonPath('log.template_id', $templateId)
            ->assertJsonPath('log.template_key', 'forms_lead_default')
            ->assertJsonPath('log.payload_json.fields.email', 'besik@example.test');

        $this->assertStringContainsString('Form: Contact Form', (string) $dispatchResponse->json('rendered.body'));
        $this->assertSame([], $dispatchResponse->json('rendered.missing_variables'));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.notification-logs.index', ['site' => $site->id, 'template_id' => $templateId]))
            ->assertOk()
            ->assertJsonPath('logs.0.template_key', 'forms_lead_default')
            ->assertJsonPath('logs.0.recipient', 'owner@example.test')
            ->assertJsonPath('logs.0.channel', 'email');

        $modulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk();

        $notificationsModule = collect($modulesResponse->json('modules'))
            ->firstWhere('key', CmsModuleRegistryService::MODULE_NOTIFICATIONS);
        $this->assertNotNull($notificationsModule);
        $this->assertTrue((bool) ($notificationsModule['implemented'] ?? false));
        $this->assertTrue((bool) ($notificationsModule['requested'] ?? false));
        $this->assertTrue((bool) ($notificationsModule['available'] ?? false));

        $otherOwner = User::factory()->create();
        [, $otherSite] = $this->createPublishedProjectWithSite($otherOwner);

        $this->actingAs($otherOwner)
            ->getJson(route('panel.sites.notification-templates.show', ['site' => $otherSite->id, 'template' => $templateId]))
            ->assertNotFound()
            ->assertJsonPath('code', 'tenant_scope_route_binding_mismatch');
    }

    public function test_notification_template_validation_and_disabled_template_dispatch_are_enforced(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.notification-templates.store', ['site' => $site->id]), [
                'key' => 'invalid email template',
                'name' => 'Invalid Email Template',
                'channel' => 'email',
                'event_key' => 'forms.lead_submitted',
                'body_template' => 'Body only',
            ])
            ->assertStatus(422)
            ->assertJsonPath('field', 'subject_template');

        $template = $this->actingAs($owner)
            ->postJson(route('panel.sites.notification-templates.store', ['site' => $site->id]), [
                'key' => 'sms_disabled',
                'name' => 'Disabled SMS Template',
                'channel' => 'sms',
                'event_key' => 'forms.lead_submitted',
                'status' => 'disabled',
                'body_template' => 'Lead from {{fields.full_name}}',
            ])
            ->assertCreated()
            ->json('template');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.notification-logs.preview-dispatch', ['site' => $site->id]), [
                'template_id' => (int) $template['id'],
                'recipient' => '+995555123456',
                'payload_json' => ['fields' => ['full_name' => 'Besik']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Notification template is disabled.');
    }

    public function test_architecture_doc_locks_p5_f2_03_notifications_module_contract(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_NOTIFICATIONS_MODULE_P5_F2_03.md');
        $this->assertFileExists($path);

        $doc = File::get($path);
        $routes = File::get(base_path('routes/web.php'));

        $this->assertStringContainsString('P5-F2-03', $doc);
        $this->assertStringContainsString('CmsNotificationsModuleService', $doc);
        $this->assertStringContainsString('App\\Http\\Controllers\\Cms\\PanelNotificationController', $doc);
        $this->assertStringContainsString('site_notification_templates', $doc);
        $this->assertStringContainsString('site_notification_logs', $doc);
        $this->assertStringContainsString('builder_contract', $doc);
        $this->assertStringContainsString('panel.sites.notification-logs.preview-dispatch', $doc);
        $this->assertStringContainsString('P5-F2-04', $doc);

        $this->assertStringContainsString("Route::get('/notification-templates'", $routes);
        $this->assertStringContainsString("Route::post('/notification-logs/preview-dispatch'", $routes);
    }

    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}

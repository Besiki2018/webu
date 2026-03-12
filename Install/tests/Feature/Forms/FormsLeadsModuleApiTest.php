<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class FormsLeadsModuleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_forms_crud_public_submit_and_leads_endpoints_work_with_site_scoping(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.forms.store', ['site' => $site->id]), [
                'key' => 'contact_main',
                'name' => 'Contact Form',
                'status' => 'active',
                'schema_json' => [
                    'fields' => [
                        ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                        ['name' => 'service', 'label' => 'Service', 'type' => 'select', 'required' => true, 'options' => [
                            ['label' => 'Design', 'value' => 'design'],
                            ['label' => 'Development', 'value' => 'development'],
                        ]],
                        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false, 'max_length' => 1000],
                    ],
                ],
                'settings_json' => [
                    'success_message' => 'Thanks for contacting us.',
                    'store_context' => true,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('form.key', 'contact_main')
            ->assertJsonPath('form.status', 'active')
            ->assertJsonPath('form.builder_contract.component_type', 'form');

        $formId = (int) $createResponse->json('form.id');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.forms.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('forms.0.key', 'contact_main')
            ->assertJsonPath('forms.0.schema_json.fields.1.type', 'email');

        $showResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.forms.show', ['site' => $site->id, 'form' => $formId]))
            ->assertOk()
            ->assertJsonPath('form.field_count', 4)
            ->assertJsonPath('form.schema_json.fields.1.type', 'email');

        $this->assertSame(
            route('public.sites.forms.submit', ['site' => $site->id, 'key' => 'contact_main']),
            $showResponse->json('form.builder_contract.submit_endpoint')
        );

        $modulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk();

        $formsModule = collect($modulesResponse->json('modules'))
            ->firstWhere('key', CmsModuleRegistryService::MODULE_FORMS);
        $this->assertNotNull($formsModule);
        $this->assertTrue((bool) ($formsModule['implemented'] ?? false));
        $this->assertTrue((bool) ($formsModule['requested'] ?? false));
        $this->assertTrue((bool) ($formsModule['available'] ?? false));

        $this->postJson(route('public.sites.forms.submit', ['site' => $site->id, 'key' => 'contact_main']), [
            'fields' => [
                'full_name' => 'Besik Example',
                'email' => 'besik@example.test',
                'service' => 'design',
                'message' => 'Need a storefront redesign.',
            ],
            'context' => [
                'page_slug' => 'landing',
                'component_id' => 'contact-form-1',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('lead.form_id', $formId)
            ->assertJsonPath('lead.form_key', 'contact_main')
            ->assertJsonPath('lead.status', 'new')
            ->assertJsonPath('meta.accepted_fields.1', 'email');

        $leadsResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.form-leads.index', ['site' => $site->id, 'form_id' => $formId]))
            ->assertOk()
            ->assertJsonPath('leads.0.form_key', 'contact_main')
            ->assertJsonPath('leads.0.fields_json.full_name', 'Besik Example')
            ->assertJsonPath('leads.0.fields_json.email', 'besik@example.test');

        $leadId = (int) $leadsResponse->json('leads.0.id');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.form-leads.status.update', ['site' => $site->id, 'lead' => $leadId]), [
                'status' => 'reviewed',
            ])
            ->assertOk()
            ->assertJsonPath('lead.status', 'reviewed');

        $otherOwner = User::factory()->create();
        [, $otherSite] = $this->createPublishedProjectWithSite($otherOwner);

        $this->actingAs($otherOwner)
            ->getJson(route('panel.sites.forms.show', ['site' => $otherSite->id, 'form' => $formId]))
            ->assertNotFound()
            ->assertJsonPath('code', 'tenant_scope_route_binding_mismatch');
    }

    public function test_public_form_submit_validates_required_fields_and_inactive_forms(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $form = $this->actingAs($owner)
            ->postJson(route('panel.sites.forms.store', ['site' => $site->id]), [
                'key' => 'newsletter_signup',
                'name' => 'Newsletter Signup',
                'status' => 'active',
                'schema_json' => [
                    'fields' => [
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                    ],
                ],
            ])
            ->assertCreated()
            ->json('form');

        $this->postJson(route('public.sites.forms.submit', ['site' => $site->id, 'key' => 'newsletter_signup']), [
            'fields' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath('field', 'fields')
            ->assertJsonPath('missing.0', 'email');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.forms.update', ['site' => $site->id, 'form' => (int) $form['id']]), [
                'key' => 'newsletter_signup',
                'name' => 'Newsletter Signup',
                'status' => 'disabled',
                'schema_json' => [
                    'fields' => [
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('form.status', 'disabled');

        $this->postJson(route('public.sites.forms.submit', ['site' => $site->id, 'key' => 'newsletter_signup']), [
            'fields' => ['email' => 'hi@example.test'],
        ])
            ->assertNotFound()
            ->assertJsonPath('error', 'Form not found.');
    }

    public function test_public_form_submit_supports_radio_fields_and_typed_validation_rules(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.forms.store', ['site' => $site->id]), [
                'key' => 'lead_rules',
                'name' => 'Lead Rules',
                'status' => 'active',
                'schema_json' => [
                    'fields' => [
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                        ['name' => 'website', 'type' => 'url', 'required' => false],
                        ['name' => 'budget', 'type' => 'number', 'required' => false],
                        ['name' => 'service_tier', 'type' => 'radio', 'required' => true, 'options' => [
                            ['label' => 'Starter', 'value' => 'starter'],
                            ['label' => 'Pro', 'value' => 'pro'],
                        ]],
                        ['name' => 'topic', 'type' => 'select', 'required' => false, 'options' => [
                            ['label' => 'Sales', 'value' => 'sales'],
                            ['label' => 'Support', 'value' => 'support'],
                        ]],
                        ['name' => 'notes', 'type' => 'text', 'required' => false, 'max_length' => 5],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('form.schema_json.fields.3.type', 'radio');

        $formId = (int) $createResponse->json('form.id');

        $submitRoute = route('public.sites.forms.submit', ['site' => $site->id, 'key' => 'lead_rules']);

        $validPayload = [
            'fields' => [
                'email' => 'lead@example.test',
                'website' => 'https://example.test',
                'budget' => '1200',
                'service_tier' => 'pro',
                'topic' => 'sales',
                'notes' => 'hello',
            ],
        ];

        $this->postJson($submitRoute, $validPayload)
            ->assertCreated()
            ->assertJsonPath('meta.accepted_fields.3', 'service_tier')
            ->assertJsonPath('meta.accepted_fields.4', 'topic');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.form-leads.index', ['site' => $site->id, 'form_id' => $formId]))
            ->assertOk()
            ->assertJsonPath('leads.0.fields_json.service_tier', 'pro')
            ->assertJsonPath('leads.0.fields_json.topic', 'sales')
            ->assertJsonPath('leads.0.fields_json.budget', 1200);

        $invalidRadioPayload = $validPayload;
        $invalidRadioPayload['fields']['service_tier'] = 'enterprise';

        $this->postJson($submitRoute, $invalidRadioPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid select field option.')
            ->assertJsonPath('field', 'fields.service_tier');

        $invalidEmailPayload = $validPayload;
        $invalidEmailPayload['fields']['email'] = 'not-an-email';

        $this->postJson($submitRoute, $invalidEmailPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid email field value.')
            ->assertJsonPath('field', 'fields.email');

        $invalidUrlPayload = $validPayload;
        $invalidUrlPayload['fields']['website'] = 'not-a-url';

        $this->postJson($submitRoute, $invalidUrlPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid URL field value.')
            ->assertJsonPath('field', 'fields.website');

        $invalidNumberPayload = $validPayload;
        $invalidNumberPayload['fields']['budget'] = 'abc';

        $this->postJson($submitRoute, $invalidNumberPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid number field value.')
            ->assertJsonPath('field', 'fields.budget');

        $invalidSelectPayload = $validPayload;
        $invalidSelectPayload['fields']['topic'] = 'unknown';

        $this->postJson($submitRoute, $invalidSelectPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid select field option.')
            ->assertJsonPath('field', 'fields.topic');

        $tooLongPayload = $validPayload;
        $tooLongPayload['fields']['notes'] = 'toolong';

        $this->postJson($submitRoute, $tooLongPayload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Field value exceeds maximum length.')
            ->assertJsonPath('field', 'fields.notes')
            ->assertJsonPath('max_length', 5);
    }

    public function test_architecture_doc_locks_p5_f2_02_forms_leads_module_contract(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_FORMS_LEADS_MODULE_P5_F2_02.md');
        $this->assertFileExists($path);

        $doc = File::get($path);
        $routes = File::get(base_path('routes/web.php'));

        $this->assertStringContainsString('P5-F2-02', $doc);
        $this->assertStringContainsString('CmsFormsLeadsService', $doc);
        $this->assertStringContainsString('App\\Http\\Controllers\\Cms\\PanelFormController', $doc);
        $this->assertStringContainsString('App\\Http\\Controllers\\Cms\\PublicFormController', $doc);
        $this->assertStringContainsString('site_forms', $doc);
        $this->assertStringContainsString('site_form_leads', $doc);
        $this->assertStringContainsString('builder_contract', $doc);
        $this->assertStringContainsString('public.sites.forms.submit', $doc);
        $this->assertStringContainsString('P5-F2-03', $doc);

        $this->assertStringContainsString("Route::post('/{site}/forms/{key}/submit'", $routes);
        $this->assertStringContainsString("Route::get('/form-leads'", $routes);
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

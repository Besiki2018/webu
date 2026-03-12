<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Services\SiteProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UniversalDemoProjectTypesSeeder extends Seeder
{
    /**
     * Seed demo project types required by the universal-platform source spec deliverable.
     *
     * Creates/updates one project per demo vertical:
     * - ecommerce storefront
     * - clinic booking
     * - portfolio showcase
     *
     * Idempotent:
     * - identifies records by deterministic subdomain per project
     * - updates template/type/defaults on rerun
     */
    public function run(): void
    {
        $owner = $this->resolveOwnerUser();
        if (! $owner) {
            $this->command?->warn('UniversalDemoProjectTypesSeeder: skipped (no user found)');

            return;
        }

        $provisioner = app(SiteProvisioningService::class);

        foreach ($this->demoProjectDefinitions() as $definition) {
            $template = $this->resolveTemplateForDefinition($definition);
            if (! $template) {
                $this->command?->warn(sprintf(
                    'UniversalDemoProjectTypesSeeder: skipped [%s] (no matching template)',
                    $definition['project_type']
                ));
                continue;
            }

            $project = Project::query()->firstOrNew([
                'subdomain' => $definition['subdomain'],
            ]);

            $project->fill([
                'user_id' => $owner->id,
                'tenant_id' => $project->tenant_id ?: null,
                'template_id' => $template->id,
                'name' => $definition['name'],
                'type' => $definition['project_type'],
                'default_currency' => $definition['default_currency'],
                'default_locale' => $definition['default_locale'],
                'timezone' => $definition['timezone'],
                'description' => $definition['description'],
                'initial_prompt' => $definition['initial_prompt'],
                'is_public' => true,
                'published_visibility' => 'public',
                'subdomain' => $definition['subdomain'],
                'published_at' => $project->published_at ?? now(),
            ]);

            if (! $project->exists) {
                $project->last_viewed_at = now();
            }

            $project->save();

            // Force one explicit provisioning pass on reruns to sync template/type/theme changes.
            $site = $provisioner->provisionForProject($project->fresh(['template', 'user']));

            // Stamp site theme_settings project_type for future-safe fallback consumers.
            $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
            $themeProjectType = (string) Arr::get($themeSettings, 'project_type', '');
            if ($themeProjectType !== $definition['project_type']) {
                $themeSettings['project_type'] = $definition['project_type'];
                $site->forceFill(['theme_settings' => $themeSettings])->save();
            }

            $this->command?->info(sprintf(
                'UniversalDemoProjectTypesSeeder: ready [%s] project=%s template=%s subdomain=%s',
                $definition['project_type'],
                (string) $project->id,
                $template->slug,
                $project->subdomain
            ));
        }
    }

    private function resolveOwnerUser(): ?User
    {
        return User::query()
            ->where('email', 'admin@webby.com')
            ->orWhere('role', 'admin')
            ->orderBy('created_at')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoProjectDefinitions(): array
    {
        return [
            [
                'project_type' => 'ecommerce',
                'name' => 'Demo Ecommerce Store',
                'subdomain' => 'demo-ecommerce',
                'default_currency' => 'GEL',
                'default_locale' => 'ka',
                'timezone' => 'Asia/Tbilisi',
                'description' => 'Universal demo project for ecommerce storefront builder and runtime flows.',
                'initial_prompt' => 'Build a modern online store with product listing, product pages, cart, checkout, and customer account.',
                'template_slugs' => ['ecommerce'],
                'template_categories' => ['ecommerce'],
                'template_keywords' => ['shop', 'store', 'ecommerce'],
            ],
            [
                'project_type' => 'booking',
                'name' => 'Demo Clinic Booking',
                'subdomain' => 'demo-clinic-booking',
                'default_currency' => 'GEL',
                'default_locale' => 'ka',
                'timezone' => 'Asia/Tbilisi',
                'description' => 'Universal demo project for clinic/service booking flows with staff, slots, and bookings.',
                'initial_prompt' => 'Build a clinic website with services, doctors, appointment slots, and online booking.',
                'template_slugs' => ['booking-starter', 'medical', 'vet', 'grooming'],
                'template_categories' => ['booking', 'medical', 'vet', 'grooming'],
                'template_keywords' => ['clinic', 'medical', 'appointment', 'booking'],
            ],
            [
                'project_type' => 'portfolio',
                'name' => 'Demo Portfolio Showcase',
                'subdomain' => 'demo-portfolio',
                'default_currency' => 'GEL',
                'default_locale' => 'ka',
                'timezone' => 'Asia/Tbilisi',
                'description' => 'Universal demo project for portfolio/case-study/gallery content sites.',
                'initial_prompt' => 'Build a portfolio website with project grid, case study pages, and contact form.',
                'template_slugs' => ['portfolio'],
                'template_categories' => ['portfolio'],
                'template_keywords' => ['portfolio', 'showcase', 'gallery'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveTemplateForDefinition(array $definition): ?Template
    {
        $slugCandidates = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($definition['template_slugs'] ?? null) ? $definition['template_slugs'] : []
        )));

        if ($slugCandidates !== []) {
            $templates = Template::query()
                ->whereIn('slug', $slugCandidates)
                ->get()
                ->sortBy(static function (Template $template) use ($slugCandidates): int {
                    $index = array_search($template->slug, $slugCandidates, true);

                    return $index === false ? PHP_INT_MAX : $index;
                })
                ->values();
            $template = $templates->first();
            if ($template) {
                return $template;
            }
        }

        $categoryCandidates = array_values(array_filter(array_map(
            static fn ($value): string => trim(Str::lower((string) $value)),
            is_array($definition['template_categories'] ?? null) ? $definition['template_categories'] : []
        )));

        if ($categoryCandidates !== []) {
            $template = Template::query()
                ->whereIn('category', $categoryCandidates)
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->first();
            if ($template) {
                return $template;
            }
        }

        $keywordCandidates = array_values(array_filter(array_map(
            static fn ($value): string => trim(Str::lower((string) $value)),
            is_array($definition['template_keywords'] ?? null) ? $definition['template_keywords'] : []
        )));

        if ($keywordCandidates !== []) {
            $templates = Template::query()->orderByDesc('is_system')->orderBy('id')->get();
            foreach ($templates as $template) {
                $keywords = array_map(
                    static fn ($value): string => Str::lower(trim((string) $value)),
                    is_array($template->keywords) ? $template->keywords : []
                );
                if (array_intersect($keywordCandidates, $keywords) !== []) {
                    return $template;
                }
            }
        }

        return null;
    }
}

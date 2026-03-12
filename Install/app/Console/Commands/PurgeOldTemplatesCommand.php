<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Template;
use App\Support\OwnedTemplateCatalog;
use Illuminate\Console\Command;

/**
 * Remove old (legacy) templates from the database and keep only the new Webu-component templates.
 * Reassigns projects that used a removed template to the main ecommerce template.
 */
class PurgeOldTemplatesCommand extends Command
{
    protected $signature = 'templates:purge-old
        {--dry-run : List templates that would be removed without deleting}
        {--force : Skip confirmation}';

    protected $description = 'Delete templates not in the owned catalog (ecommerce, default) and reassign projects to ecommerce.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $keepSlugs = OwnedTemplateCatalog::slugs();
        $toRemove = Template::query()
            ->whereNotIn('slug', $keepSlugs)
            ->get();

        if ($toRemove->isEmpty()) {
            $this->info('No old templates to remove. All templates are in the catalog: '.implode(', ', $keepSlugs));

            return self::SUCCESS;
        }

        $fallbackTemplate = Template::query()->whereIn('slug', $keepSlugs)->orderByRaw("slug = 'ecommerce' DESC")->first();
        if (! $fallbackTemplate && ! $dryRun) {
            $this->error('No template found with slug in ['.implode(', ', $keepSlugs).']. Create at least one (e.g. run templates seed) before purging.');

            return self::FAILURE;
        }

        $this->table(
            ['Slug', 'Name', 'ID'],
            $toRemove->map(fn (Template $t) => [$t->slug, $t->name, $t->id])->toArray()
        );

        if ($dryRun) {
            $this->info('Dry run: '.$toRemove->count().' template(s) would be removed. Run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Remove '.$toRemove->count().' template(s) and reassign their projects to '.$fallbackTemplate?->slug.'?')) {
            return self::SUCCESS;
        }

        $idsToRemove = $toRemove->pluck('id')->all();
        $reassigned = 0;
        if ($fallbackTemplate) {
            $reassigned = Project::query()
                ->whereIn('template_id', $idsToRemove)
                ->update(['template_id' => $fallbackTemplate->id]);
        }

        $this->info("Reassigned {$reassigned} project(s) to template: {$fallbackTemplate->slug}.");

        $deleted = 0;
        foreach ($toRemove as $template) {
            Template::withoutEvents(function () use ($template, &$deleted): void {
                $template->delete();
                $deleted++;
            });
        }

        $this->info("Removed {$deleted} old template(s). Only catalog templates remain: ".implode(', ', $keepSlugs));

        return self::SUCCESS;
    }
}

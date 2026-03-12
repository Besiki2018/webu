<?php

use App\Models\Site;
use App\Services\CmsTypographyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        $typography = app(CmsTypographyService::class);

        Site::query()
            ->select(['id', 'theme_settings'])
            ->cursor()
            ->each(function (Site $site) use ($typography): void {
                $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
                $normalized = $typography->normalizeThemeSettings($themeSettings);

                if ($normalized === $themeSettings) {
                    return;
                }

                $site->forceFill([
                    'theme_settings' => $normalized,
                ])->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructive rollback; typography normalization is forward-only.
    }
};

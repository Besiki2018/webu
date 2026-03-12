<?php

namespace Tests\Feature\Security;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_media_upload_rejects_blocked_executable_extension(): void
    {
        [$user, $site] = $this->createSiteWithFileStorage();

        $file = UploadedFile::fake()->create('shell.php', 4, 'application/x-httpd-php');

        $this->actingAs($user)
            ->post(route('panel.sites.media.upload', ['site' => $site->id]), [
                'file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Blocked file extension [.php]');
    }

    public function test_media_upload_rejects_script_signature_even_with_safe_extension(): void
    {
        [$user, $site] = $this->createSiteWithFileStorage();

        $file = UploadedFile::fake()->createWithContent('avatar.jpg', "<?php echo 'owned';");

        $response = $this->actingAs($user)
            ->post(route('panel.sites.media.upload', ['site' => $site->id]), [
                'file' => $file,
            ])
            ->assertStatus(422);

        $error = (string) $response->json('error');
        $this->assertTrue(
            str_contains($error, 'Executable/script signature')
            || str_contains($error, 'Blocked file MIME type'),
            "Unexpected upload hardening error: {$error}"
        );
    }

    public function test_media_upload_allows_valid_image_file(): void
    {
        [$user, $site] = $this->createSiteWithFileStorage();

        $file = UploadedFile::fake()->image('hero.jpg', 1200, 800);

        $this->actingAs($user)
            ->post(route('panel.sites.media.upload', ['site' => $site->id]), [
                'file' => $file,
            ])
            ->assertCreated()
            ->assertJsonPath('media.site_id', $site->id);
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function createSiteWithFileStorage(): array
    {
        $plan = Plan::factory()->withFileStorage(512)->create();
        $user = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($user)->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$user, $site];
    }
}

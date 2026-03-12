<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserAiSettings;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * Create default AI settings with sound preferences for new users.
     */
    public function created(User $user): void
    {
        // Create default AI settings with sound enabled
        UserAiSettings::create([
            'user_id' => $user->id,
            'sounds_enabled' => true,
            'sound_style' => 'playful',
            'sound_volume' => 100,
        ]);
    }

    /**
     * Handle the User "updated" event.
     *
     * When a user's plan changes, automatically refill their build credits.
     */
    public function updated(User $user): void
    {
        // Only trigger on plan_id change, not on build_credits change (to avoid infinite loop)
        if ($user->wasChanged('plan_id') && ! $user->wasChanged('build_credits') && $user->plan_id !== null) {
            $oldPlanId = $user->getOriginal('plan_id');
            $newPlanId = $user->plan_id;

            Log::info('User plan changed, refilling build credits', [
                'user_id' => $user->id,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlanId,
            ]);

            $user->refillBuildCredits();
        }
    }
}

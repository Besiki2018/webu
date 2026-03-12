<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\AdminUserRegisteredNotification;
use App\Rules\RecaptchaToken;
use App\Services\AdminNotificationService;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $minLength = SystemSetting::get('password_min_length', 8);
        $defaultPlanId = Plan::resolveDefaultPlan()?->id;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Password::min($minLength)],
        ];

        // Add reCAPTCHA validation if enabled
        if (SystemSetting::get('recaptcha_enabled', false)) {
            $rules['recaptcha_token'] = ['required', 'string', new RecaptchaToken];
        }

        $request->validate($rules);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'locale' => SystemSetting::get('default_locale', config('app.locale', 'ka')),
            'plan_id' => $defaultPlanId,
        ]);

        event(new Registered($user));

        // Process referral tracking if referral cookie exists
        $referralCode = $request->cookie('referral_code');
        if ($referralCode) {
            $referralService = app(ReferralService::class);
            if ($referralService->isEnabled()) {
                $code = $referralService->resolveCode($referralCode);
                if ($code) {
                    $referralService->createReferral(
                        $user,
                        $code,
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }
        }

        // Send admin notification if enabled
        AdminNotificationService::notify(
            'user_registered',
            new AdminUserRegisteredNotification($user)
        );

        Auth::login($user);

        return redirect(route('create', absolute: false));
    }
}

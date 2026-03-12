<?php

namespace App\Http\Requests\Auth;

use App\Models\SystemSetting;
use App\Rules\RecaptchaToken;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];

        // Add reCAPTCHA only if DB is available and it is enabled (avoid DB access failure on login)
        try {
            if (SystemSetting::get('recaptcha_enabled', false)) {
                $rules['recaptcha_token'] = ['required', 'string', new RecaptchaToken];
            }
        } catch (\Throwable $e) {
            // DB not available – skip recaptcha requirement so user can at least see connection error on attempt
        }

        return $rules;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        try {
            $this->ensureIsNotRateLimited();
        } catch (\Throwable $e) {
            // Rate limiter (e.g. cache/database) failed – continue so we can attempt auth and show real error
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'email' => trans('auth.database_connection_failed'),
            ]);
        }

        try {
            if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => trans('auth.failed'),
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $message = $this->isDatabaseConnectionError($e)
                ? trans('auth.database_connection_failed')
                : trans('auth.failed');

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        try {
            RateLimiter::clear($this->throttleKey());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function isDatabaseConnectionError(\Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
            return true;
        }
        $previous = $e->getPrevious();
        if ($previous instanceof \Throwable && $this->isDatabaseConnectionError($previous)) {
            return true;
        }
        $msg = strtolower($e->getMessage());
        $connectionKeywords = [
            'connection', 'sqlstate', 'could not find driver', 'connection refused',
            'unknown database', 'access denied', 'no such file', 'server has gone away',
            'lost connection', 'can\'t connect', 'failed to connect', 'network',
        ];
        foreach ($connectionKeywords as $keyword) {
            if (str_contains($msg, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

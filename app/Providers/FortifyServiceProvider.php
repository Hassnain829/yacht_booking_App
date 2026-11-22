<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Http\Responses\Auth\RegisterResponse;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Fortify (PLAN §8.1): the auth screens are our own Blade views (built in Phase 1.5).
 * Features switched on are listed in config/fortify.php.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /** bcrypt hash of a random string — compared when the email is unknown, to even out timing. */
    private const TIMING_HASH = '$2y$12$5SXMNMaAylVjcW4ze0wBy.Q2cVcsikxFeuXGgP4tBD/gWqIUluHye';

    public function register(): void
    {
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        // After login (and after the 2FA code): the page they wanted, else their role's home (PLAN §4.2).
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
        // Same answer whether the email exists or not (no account enumeration).
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(fn (Request $request) => $this->authenticate($request));

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'token' => (string) $request->route('token'),
            'email' => is_string($request->query('email')) ? $request->query('email') : null,
        ]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        $this->configureRateLimiting();
    }

    /**
     * Email + password check for every login.
     * - A wrong password and an unknown email both return null → the same generic error (no enumeration).
     *   An unknown email still runs one hash check, so both take about the same time.
     * - A suspended or banned account is refused (only after the correct password, so nothing leaks).
     */
    private function authenticate(Request $request): ?User
    {
        $email = Str::lower((string) $request->input(Fortify::username()));
        $password = (string) $request->input('password');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            Hash::check($password, self::TIMING_HASH);

            return null;
        }
        if (! Hash::check($password, $user->password)) {
            return null;
        }
        if (! $user->isActive()) {
            throw ValidationException::withMessages([Fortify::username() => EnsureAccountIsActive::MESSAGE]);
        }
        if ($user->isAdmin()) {
            $request->merge(['remember' => false]); // no "remember me" for the admin (PLAN §8.1)
        }

        return $user;
    }

    private function configureRateLimiting(): void
    {
        // Login: 5 tries a minute per email + IP (PLAN §8.1). A blocked try is a "lockout" in the security log
        // (the route throttle doesn't fire Laravel's Lockout event itself); the visitor still gets our 429 page.
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers) {
                event(new Lockout($request));

                throw new ThrottleRequestsException('Too Many Attempts.', null, $headers);
            });
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        // Every auth route (pages + register / reset / verify posts): a ceiling per IP.
        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

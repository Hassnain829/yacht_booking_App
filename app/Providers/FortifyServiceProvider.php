<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Http\Responses\Auth\RegisterResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
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
            throw ValidationException::withMessages([
                Fortify::username() => 'This account is not active. Please contact us if you think this is a mistake.',
            ]);
        }

        return $user;
    }

    private function configureRateLimiting(): void
    {
        // Login: 5 tries a minute per email + IP (PLAN §8.1).
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        // Every auth route (pages + register / reset / verify posts): a ceiling per IP.
        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

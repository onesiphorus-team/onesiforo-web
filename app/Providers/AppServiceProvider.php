<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Roles;
use App\Listeners\NotifyAdminsOfNewRegistration;
use App\Listeners\UpdateLastLogin;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;
use App\Policies\ApplianceScreenshotPolicy;
use App\Policies\OnesiBoxPolicy;
use App\Policies\UserPolicy;
use App\Services\OnesiBoxCommandService;
use App\Services\OnesiBoxCommandServiceInterface;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OnesiBoxCommandServiceInterface::class,
            OnesiBoxCommandService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant "Super Admin" role all permissions
        // ---> Before to decomment this, add the right condition
        // ---> otherwise all policies will be ignored and all user
        // ---> will not be able to perform any action
        // Gate::before(fn (User $user, string $ability): bool => false); // TODO implement logic here

        // Register UserPolicy explicitly (also auto-discovered by convention)
        Gate::policy(User::class, UserPolicy::class);

        // Register OnesiBoxPolicy for caregiver dashboard authorization
        Gate::policy(OnesiBox::class, OnesiBoxPolicy::class);

        // Register ApplianceScreenshotPolicy for diagnostic screenshot access
        Gate::policy(ApplianceScreenshot::class, ApplianceScreenshotPolicy::class);

        // Register Login event listener to update last_login_at
        Event::listen(Login::class, UpdateLastLogin::class);

        // Notify admins when a new user registers
        Event::listen(Registered::class, NotifyAdminsOfNewRegistration::class);

        // limit pulse dashboard access to admin and super-admin users
        Gate::define('viewPulse', fn (User $user): bool => $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin));

        // limit api docs access to admin and super-admin users
        Gate::define('viewApiDocs', fn (User $user): bool => $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin));

        // limit telescope access to admin users
        // Gate::define('viewTelescope', fn (User $user): bool => false); // TODO implement logic here

        $this->configureCommands();
        $this->configureModels();
        $this->configureVite();
        $this->configureDates();
        $this->configureUrls();
        $this->configureScramble();
        $this->configureRateLimiting();

        if (App::isProduction()) {
            // Define password validation rules
            Password::defaults(fn () => Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised());
        }

    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->isProduction()
        );
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict();
        Model::unguard();
    }

    private function configureVite(): void
    {
        Vite::usePrefetchStrategy('aggressive');
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureUrls(): void
    {
        if (App::isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configureScramble(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    /**
     * Configure rate limiting for API endpoints.
     *
     * Rate limits are based on the OnesiBox appliance token to prevent abuse
     * while allowing legitimate device communication.
     */
    private function configureRateLimiting(): void
    {
        // Heartbeat: 4 requests per minute per appliance (normal interval is 30s)
        RateLimiter::for('heartbeat', function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(4)->by('heartbeat:'.$key);
        });

        // Commands polling: 20 requests per minute per appliance (normal interval is 5s)
        RateLimiter::for('commands', function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(20)->by('commands:'.$key);
        });

        // Playback events: 30 requests per minute per appliance
        RateLimiter::for('playback', function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(30)->by('playback:'.$key);
        });

        // Command acknowledgment: 60 requests per minute per appliance
        RateLimiter::for('command-ack', function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(60)->by('command-ack:'.$key);
        });

        // Screenshot upload: 12 requests per minute per appliance (minimum 10s interval floor)
        RateLimiter::for('screenshot-upload', function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(12)->by('screenshot-upload:'.$key);
        });
    }
}

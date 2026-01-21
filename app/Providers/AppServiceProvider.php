<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\UpdateLastLogin;
use App\Models\User;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        //
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

        // Register Login event listener to update last_login_at
        Event::listen(Login::class, UpdateLastLogin::class);

        // limit pulse dashboard access to admin and super-admin users
        Gate::define('viewPulse', fn (User $user): bool => $user->hasAnyRoles('super-admin', 'admin'));

        // limit api docs access to admin users
        // Gate::define('viewApiDocs', fn (User $user): bool => false);   // TODO implement logic here

        // limit telescope access to admin users
        // Gate::define('viewTelescope', fn (User $user): bool => false); // TODO implement logic here

        $this->configureCommands();
        $this->configureModels();
        $this->configureVite();
        $this->configureDates();
        $this->configureUrls();
        $this->configureScramble();

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
}

<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />
        @if(config('app.env') === 'local')
        <div class="w-full flex flex-col items-center justify-center">
            <div class="text-xl my-8 font-medium font-mono">AMBIENTE LOCALE</div>

            @if(App\Models\User::count() > 0)
                <div class="text-base my-4 font-medium font-mono">Disponibile login automatico:</div>
                @foreach(\App\Enums\Roles::cases() as $role)
                    @if(!Oltrematica\RoleLite\Models\Role::where('name', $role->value)->first()?->users->first())
                        @continue
                    @endif
                    <x-login-link
                        :email="Oltrematica\RoleLite\Models\Role::where('name', $role->value)->first()->users->first()->email"
                        :redirect-url="route('filament.admin.pages.dashboard')"
                        class="cursor-pointer hover:font-bold"
                        label="{{ strtoupper( $role->value ) . ': Click to login' }}"/>

                @endforeach
            @else
                <div class="text-lg font-medium my-4">NON SONO PRESENTI UTENTI</div>
            @endif
        </div>
        @endif


        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>

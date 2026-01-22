<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if(config('app.env') === 'local' && App\Models\User::count() > 0)
            <div class="flex flex-col gap-2 p-4 rounded-lg bg-stone-100 dark:bg-stone-800">
                <span class="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-wide">Dev Login</span>
                @foreach(\App\Enums\Roles::cases() as $role)
                    @if($user = Oltrematica\RoleLite\Models\Role::where('name', $role->value)->first()?->users->first())
                        <x-login-link
                            :email="$user->email"
                            :redirect-url="route('filament.admin.pages.dashboard')"
                            class="text-sm text-stone-700 dark:text-stone-300 hover:text-stone-900 dark:hover:text-white cursor-pointer"
                            label="{{ ucfirst($role->value) }}"/>
                    @endif
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
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
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-stone-600 dark:text-stone-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>

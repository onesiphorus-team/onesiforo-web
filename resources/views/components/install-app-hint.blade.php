{{--
    Invites family members using a phone browser to add Onesiforo to their home screen.
    - Android/Chrome: a button that opens the browser's own install prompt.
    - iPhone/iPad: step-by-step instructions (Safari has no install prompt).
    - Other mobile browsers: where to find "Add to home screen" in the menu.
    Hidden when the app is already installed, on desktop, or after "Non ora".
--}}
<div x-data="{
        visible: false,
        platform: 'other',
        installPrompt: null,
        init() {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const isPhone = window.matchMedia('(max-width: 767px)').matches && /android|iphone|ipad|ipod|mobile/i.test(navigator.userAgent);
            let dismissed = false;
            try { dismissed = localStorage.getItem('onesiforo:install-hint-dismissed') === '1'; } catch (e) {}

            if (isStandalone || ! isPhone || dismissed) {
                return;
            }

            this.platform = /iphone|ipad|ipod/i.test(navigator.userAgent) ? 'ios' : 'other';
            this.visible = true;

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.installPrompt = event;
                this.platform = 'prompt';
            });
            window.addEventListener('appinstalled', () => this.visible = false);
        },
        async install() {
            if (! this.installPrompt) {
                return;
            }
            this.installPrompt.prompt();
            const choice = await this.installPrompt.userChoice;
            this.installPrompt = null;
            if (choice.outcome === 'accepted') {
                this.visible = false;
            }
        },
        dismiss() {
            this.visible = false;
            try { localStorage.setItem('onesiforo:install-hint-dismissed', '1'); } catch (e) {}
        },
     }"
     x-show="visible"
     x-cloak
     data-slot="install-app-hint"
     {{ $attributes->class('rounded-2xl border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-500/30 dark:bg-zinc-800') }}>
    <div class="flex items-start gap-3">
        <img src="/icons/icon-192.png" alt="" class="size-12 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-zinc-900 dark:text-white">Tieni Onesiforo sulla schermata Home</p>

            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300" x-show="platform === 'prompt'">
                Così lo apri con un tocco, come le altre app, senza cercare l'indirizzo.
            </p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300" x-show="platform === 'ios'">
                In basso tocca
                <flux:icon name="arrow-up-on-square" variant="micro" class="inline size-4 align-text-bottom text-indigo-600 dark:text-indigo-300" />
                <strong>Condividi</strong>, poi scegli <strong>«Aggiungi alla schermata Home»</strong>.
            </p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300" x-show="platform === 'other'">
                Apri il menu del browser
                <flux:icon name="ellipsis-vertical" variant="micro" class="inline size-4 align-text-bottom text-indigo-600 dark:text-indigo-300" />
                in alto a destra e scegli <strong>«Aggiungi a schermata Home»</strong> o <strong>«Installa app»</strong>.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button x-show="platform === 'prompt'" x-on:click="install()" variant="primary" size="sm" icon="plus">
                    Aggiungi alla schermata Home
                </flux:button>
                <flux:button x-on:click="dismiss()" variant="subtle" size="sm">Non ora</flux:button>
            </div>
        </div>
    </div>
</div>

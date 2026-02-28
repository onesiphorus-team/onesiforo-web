import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

const isDocker = process.env.DOCKER === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        ...(isDocker && {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5174',
            hmr: {
                host: 'localhost',
                port: 5174,
            },
        }),
        watch: {
            ignored: ['**/storage/framework/views/**'],
            ...(isDocker && { usePolling: true }),
        },
    },
});

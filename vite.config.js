import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    // server: {
    //     watch: {
    //         ignored: ['**/storage/framework/views/**'],
    //     },
    // },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true, // Unlocks cross-origin requests for Codespaces
        hmr: {
            host: process.env.CODESPACE_NAME ? `${process.env.CODESPACE_NAME}-5173.app.github.dev` : 'localhost',
            clientPort: 443, // Forces standard HTTPS, dropping the :5173 tail
            protocol: 'wss',
        }
    }
});

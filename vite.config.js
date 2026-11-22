import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fonts are downloaded from Google Fonts at BUILD time (same files as the mockup) and self-hosted from public/build
            // (no font CDN at runtime → a strict Content-Security-Policy stays possible).
            // Rendered in the layout with the @fonts Blade directive.
            fonts: [
                // Fallback stacks live in resources/css/app.css (@theme --font-sans / --font-display).
                google('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [{ weight: 500 }, { weight: 700 }],
                }),
                google('Playfair Display', {
                    weights: [500, 600, 700],
                    styles: ['normal', 'italic'],
                    preload: [{ weight: 600 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // An IPv4 address on purpose: on Windows, Vite's default "localhost" can become http://[::1]:5173, which a
        // Content-Security-Policy cannot name — the dev CSS and fonts were blocked (app/Http/Middleware/SecurityHeaders.php).
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

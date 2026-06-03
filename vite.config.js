import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/react-admin/src/main.tsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/react-admin/src'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Split rarely-changing vendor libs and the (large) translation
                // bundles into their own cacheable chunks so the main entry
                // stays under the 500 kB warning threshold and browser caching
                // is effective across deploys.
                manualChunks(id) {
                    // ~190 kB of admin translations, imported eagerly by the
                    // LocaleProvider — keep them out of the main entry chunk.
                    if (id.includes('/locales/')) return 'i18n-data';

                    if (id.includes('node_modules')) {
                        if (
                            id.includes('node_modules/react/') ||
                            id.includes('react-dom') ||
                            id.includes('react-router')
                        ) {
                            return 'react-vendor';
                        }
                        if (id.includes('@tanstack') || id.includes('axios')) {
                            return 'data-vendor';
                        }
                        // Matches i18next, react-i18next and the language detector.
                        if (id.includes('i18next')) return 'i18n-vendor';
                    }
                },
            },
        },
    },
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

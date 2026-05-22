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

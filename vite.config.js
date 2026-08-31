import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            // Mirrors tsconfig.json's "@/*" path so the alias resolves both
            // for type-checking and for the actual bundle.
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        // Low-end Android is the target device: keep chunks small and legible.
        target: 'es2020',
    },
});

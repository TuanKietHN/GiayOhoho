import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    resolve: {
        dedupe: ['react', 'react-dom']
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/spa/main.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
});

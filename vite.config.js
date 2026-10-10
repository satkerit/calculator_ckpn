import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        cssCodeSplit: true,
        cssTarget: 'chrome120',
        minify: 'terser',
        chunkSizeWarningLimit: 1000,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    optimizeDeps: {
        include: ['tailwindcss', '@tailwindcss/forms'],
    },
});

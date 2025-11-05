import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
             input: ['resources/css/slot.css', 'resources/js/slot.js'],
            refresh: true,
        }),
    ],
});

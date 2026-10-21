import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/site.scss', 'resources/js/site.js', 'resources/scss/dashboard.scss', 'resources/js/dashboard.js'],
            refresh: true,
            fonts: [bunny('Manrope', { weights: [400, 500, 600, 700, 800] }), bunny('IBM Plex Mono', { weights: [500] })],
        }),
    ],
    css: { preprocessorOptions: { scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'] } } },
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});

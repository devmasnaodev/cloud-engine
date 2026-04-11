import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const ddevPrimaryUrl = process.env.DDEV_PRIMARY_URL;
const ddevHostname = ddevPrimaryUrl ? new URL(ddevPrimaryUrl).hostname : null;
const ddevUsesHttps = ddevPrimaryUrl ? ddevPrimaryUrl.startsWith('https://') : false;

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: ddevHostname
            ? {
                  host: ddevHostname,
                  protocol: ddevUsesHttps ? 'wss' : 'ws',
              }
            : undefined,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});

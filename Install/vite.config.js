import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.esm.js'),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.tsx',
                'resources/css/app.css',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        // Increase chunk size warning limit (default is 500 kB)
        chunkSizeWarningLimit: 700,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('@monaco-editor/react') || id.includes('monaco-editor')) {
                            return 'monaco-editor';
                        }

                        if (id.includes('@fullcalendar/')) {
                            return 'fullcalendar';
                        }

                        if (id.includes('@tiptap/')) {
                            return 'tiptap';
                        }

                        if (id.includes('@dnd-kit/')) {
                            return 'dnd-kit';
                        }

                        if (id.includes('@tanstack/react-table') || id.includes('@tanstack/react-virtual')) {
                            return 'tanstack';
                        }

                        if (id.includes('lucide-react')) {
                            return 'lucide-react';
                        }

                        if (id.includes('@lexical/') || id.includes('lexical')) {
                            return 'lexical';
                        }
                    }

                    return undefined;
                },
            },
        },
    },
});

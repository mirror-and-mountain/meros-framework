import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: '/assets/vite/build/',

    plugins: [
        tailwindcss(),
    ],
    
    build: {
        outDir: 'src/resources/assets/vite/build',
        emptyOutDir: true,
        manifest: true, 
        rollupOptions: {
            input: {
                app: 'src/resources/assets/vite/src/index.js',
            },
        },
    },
});
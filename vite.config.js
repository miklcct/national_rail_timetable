// vite.config.js
import {defineConfig} from 'vite';

export default defineConfig({
    build: {
        // generate manifest.json in outDir
        manifest : true,
        rollupOptions : {
            // overwrite default .html entry
            input: ['/service.ts', '/schedule.ts']
        },
        target : 'esnext',
    },
    server : {
        origin : 'http://gbtt.localhost'
    },
    root : 'public_html'
});
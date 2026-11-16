import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const LIVE_MANIFEST = 'manifest.json';
const NEXT_MANIFEST = 'manifest.next.json';

/**
 * Builds alongside the live build instead of replacing it.
 *
 * On build start, deletes assets the live manifest no longer references (the build before last).
 * The new build writes its manifest to `manifest.next.json`, which is renamed over `manifest.json`
 * after every file in the bundle has been written, so the live manifest always points at files that exist.
 */
function stableBuild() {
    let outDir;

    const livePaths = () => {
        const manifestPath = path.join(outDir, LIVE_MANIFEST);

        if (!fs.existsSync(manifestPath)) {
            return null;
        }

        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

        return new Set(
            Object.values(manifest).flatMap((chunk) => [chunk.file, ...(chunk.css ?? []), ...(chunk.assets ?? [])]),
        );
    };

    return {
        name: 'regrowth:stable-build',
        apply: 'build',
        configResolved(config) {
            outDir = path.resolve(config.root, config.build.outDir);
        },
        buildStart() {
            fs.rmSync(path.join(outDir, NEXT_MANIFEST), { force: true });

            const keep = livePaths();
            const assetsDir = path.join(outDir, 'assets');

            if (keep === null || !fs.existsSync(assetsDir)) {
                return;
            }

            for (const file of fs.readdirSync(assetsDir)) {
                if (!keep.has(`assets/${file}`)) {
                    fs.rmSync(path.join(assetsDir, file), { force: true });
                }
            }
        },
        writeBundle() {
            const nextManifest = path.join(outDir, NEXT_MANIFEST);

            if (fs.existsSync(nextManifest)) {
                fs.renameSync(nextManifest, path.join(outDir, LIVE_MANIFEST));
            }
        },
    };
}

export default defineConfig({
    build: {
        emptyOutDir: false,
        manifest: NEXT_MANIFEST,
    },
    plugins: [
        laravel({
            input: ['resources/js/app.jsx', 'resources/css/app.css'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        stableBuild(),
    ],
});

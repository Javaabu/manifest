import path from "node:path";
/**
 * A Vite config that builds a Laravel package's assets into a dist folder
 * that javaabu/manifest serves to apps that don't build them: hashed file
 * names and a Vite manifest keyed by the registered entries.
 *
 *     // vite.config.js in the package repo
 *     import { defineConfig } from "vite"
 *     import { packageBuild } from "./vendor/javaabu/manifest/dist/package.js"
 *
 *     export default defineConfig(packageBuild({ input: ["css/filters.css", "js/filter-bar.ts"] }))
 */
export function packageBuild(options) {
    const root = path.resolve(options.root ?? process.cwd());
    const resources = path.join(root, options.resources ?? "resources");
    return {
        root: resources,
        base: "./",
        publicDir: false,
        build: {
            outDir: path.resolve(root, options.outDir ?? "dist"),
            emptyOutDir: true,
            manifest: true,
            rollupOptions: {
                input: options.input.map((entry) => path.join(resources, entry)),
                external: options.external ?? [],
            },
        },
    };
}

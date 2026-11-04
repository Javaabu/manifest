import type { UserConfig } from "vite";
export interface PackageBuildOptions {
    /**
     * The entries to build, relative to `resources` (the same paths you
     * register with ->style() and ->script()).
     */
    input: string[];
    /**
     * The package's resources folder, relative to the package root.
     * @default "resources"
     */
    resources?: string;
    /**
     * Where to write the build, relative to the package root. Register the same
     * folder with ->dist() (relative to resources: "../dist").
     * @default "dist"
     */
    outDir?: string;
    /**
     * Modules to leave out of the bundle. Apps without Vite load the dist in the
     * browser as it is, so bundle everything unless the page provides the module
     * (with an import map, say).
     * @default []
     */
    external?: (string | RegExp)[];
    /** The package root. @default process.cwd() */
    root?: string;
}
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
export declare function packageBuild(options: PackageBuildOptions): UserConfig;

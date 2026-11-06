import { type Plugin, type PluginOption } from "vite";
import { type Discovery, type ResolvedPackage } from "./discovery.js";
import { type LoadedPackagePlugin, type PackagePluginSettings } from "./plugins.js";
export interface ManifestPluginOptions {
    /**
     * The file `php artisan manifest:discover` writes, relative to the Vite root.
     * @default "bootstrap/cache/manifest.json"
     */
    discovery?: string;
    /**
     * Run discovery when Vite starts: `true` runs `php artisan manifest:discover`,
     * a string runs that command instead (in the Vite root), and `false` only
     * reads the existing file. When the command fails (no PHP, say), the plugin
     * falls back to the existing file with a warning.
     * @default true
     */
    discover?: boolean | string;
    /**
     * Add every registered asset to the build's inputs. Turn it off to list
     * entries yourself with `inputs()`.
     * @default true
     */
    input?: boolean;
    /**
     * The Vite plugins packages register (`->vitePlugin()`), by package name:
     * `false` doesn't run that package's plugins, and an object is merged over
     * the options the package registered.
     * @default {}
     */
    plugins?: PackagePluginSettings;
}
export interface ManifestPlugin extends Plugin {
    /** The discovered packages, once Vite has resolved its config. */
    packages(): ResolvedPackage[];
    /** Every registered asset's entry (overrides applied), relative to the Vite root. */
    inputs(): string[];
    /** Imports and runs the packages' Vite plugins (once). */
    packagePlugins(): Promise<LoadedPackagePlugin[]>;
}
/**
 * What `manifest()` returns: manifest's own plugin, then the packages' Vite
 * plugins. Vite flattens it; `packages()` and `inputs()` work on it too.
 */
export interface ManifestPlugins extends Array<PluginOption> {
    /** manifest's own plugin. */
    plugin: ManifestPlugin;
    packages(): ResolvedPackage[];
    inputs(): string[];
}
export declare const INERTIA_MODULE_ID = "@javaabu/manifest/inertia";
export declare function computeInputs(root: string, discovery: Discovery, packages: ResolvedPackage[]): string[];
interface ManifestChunk {
    src?: string;
    imports?: string[];
    dynamicImports?: string[];
    [key: string]: unknown;
}
type RollupInput = string | string[] | Record<string, string> | undefined;
export declare function mergeInputs(existing: RollupInput, inputs: string[]): RollupInput;
/**
 * Rewrites Vite manifest keys of packages symlinked from a Composer path
 * repository (`../filters/resources/...`) to the vendor/ paths Laravel renders.
 */
export declare function rewriteManifestKeys(root: string, manifest: Record<string, ManifestChunk>, packages: ResolvedPackage[]): boolean;
/**
 * Builds the assets Laravel packages register with javaabu/manifest, and runs
 * the Vite plugins they register. Use it next to laravel-vite-plugin:
 *
 *     plugins: [laravel({ input: ["resources/js/app.ts"] }), manifest()]
 */
export default function manifest(options?: ManifestPluginOptions): ManifestPlugins;
export { manifest };
export type { Discovery, DiscoveredAsset, DiscoveredPackage, DiscoveredVitePlugin, PackageVitePlugin, ResolvedPackage, } from "./discovery.js";
export type { LoadedPackagePlugin, PackagePluginSettings } from "./plugins.js";

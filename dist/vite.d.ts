import { type Plugin } from "vite";
import { type Discovery, type ResolvedPackage } from "./discovery.js";
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
}
export interface ManifestPlugin extends Plugin {
    /** The discovered packages, once Vite has resolved its config. */
    packages(): ResolvedPackage[];
    /** Every registered asset's entry (overrides applied), relative to the Vite root. */
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
 * Builds the assets Laravel packages register with javaabu/manifest. Use it
 * next to laravel-vite-plugin:
 *
 *     plugins: [laravel({ input: ["resources/js/app.ts"] }), manifest()]
 */
export default function manifest(options?: ManifestPluginOptions): ManifestPlugin;
export { manifest };
export type { Discovery, DiscoveredAsset, DiscoveredPackage, ResolvedPackage } from "./discovery.js";

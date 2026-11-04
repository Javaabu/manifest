/** A package as `php artisan manifest:discover` writes it. Paths are relative to the app root. */
export interface DiscoveredPackage {
    path: string;
    alias: string | null;
    js: string | null;
    pages: string | null;
    dist: string | null;
    overrides: string;
    dependencies: Record<string, string>;
    peerDependencies: Record<string, string>;
}
export interface DiscoveredAsset {
    id: string;
    package: string;
    name: string;
    type: "css" | "sass" | "js" | "ts" | "react" | "vue";
    entry: string;
    input: string;
    dependencies: string[];
}
export interface Discovery {
    version: number;
    packages: Record<string, DiscoveredPackage>;
    assets: DiscoveredAsset[];
}
/** A discovered package with absolute paths. */
export interface ResolvedPackage {
    name: string;
    /** The resources folder, through vendor/ (a symlink for path repositories). */
    dir: string;
    /** The same folder with symlinks resolved, as Vite sees module ids. */
    realDir: string;
    jsDir: string | null;
    alias: string | null;
    pagesDir: string | null;
    overridesDir: string;
    info: DiscoveredPackage;
}
export declare const DEFAULT_DISCOVERY_PATH = "bootstrap/cache/manifest.json";
export declare const DEFAULT_DISCOVER_COMMAND = "php artisan manifest:discover";
/**
 * Runs `php artisan manifest:discover` in the app root. Returns the error
 * when it fails, so the caller can fall back to an existing file.
 */
export declare function runDiscovery(root: string, command: string): Error | null;
export declare function readDiscovery(file: string): Discovery;
export declare function resolvePackages(root: string, discovery: Discovery): ResolvedPackage[];
export declare function realpath(file: string): string;
export declare function toPosix(file: string): string;

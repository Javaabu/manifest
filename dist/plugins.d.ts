import type { Plugin, PluginOption } from "vite";
import { type PackageVitePlugin, type ResolvedPackage } from "./discovery.js";
/** Per package: `false` skips its Vite plugins, an object is merged over the options it registered. */
export type PackagePluginSettings = Record<string, boolean | Record<string, unknown>>;
export interface LoadedPackagePlugin {
    plugin: PackageVitePlugin;
    /** What the factory returned, flattened. */
    plugins: Plugin[];
}
/** The registered package plugins that run, with the app's options merged over the package's. */
export declare function selectPackagePlugins(packages: ResolvedPackage[], settings?: PackagePluginSettings): PackageVitePlugin[];
/** Keys of the app's `plugins` option that name no package with a Vite plugin. */
export declare function unknownPluginSettings(packages: ResolvedPackage[], settings?: PackagePluginSettings): string[];
export declare function flattenPlugins(option: PluginOption): Promise<Plugin[]>;
export declare function loadPackagePlugin(plugin: PackageVitePlugin, root: string): Promise<LoadedPackagePlugin>;
/**
 * A promise-like that only starts when Vite awaits it, while it flattens the
 * config's plugins, so nothing is discovered or imported before Vite runs.
 */
export declare function lazy<T>(start: () => Promise<T>): Promise<T>;

import { type ResolvedPackage } from "./discovery.js";
export declare const PAGES_MODULE_ID = "virtual:javaabu-manifest/pages";
export declare const RESOLVED_PAGES_MODULE_ID = "\0virtual:javaabu-manifest/pages";
export declare const PAGE_EXTENSIONS: string[];
/**
 * The virtual module behind `@javaabu/manifest/inertia`: lazy globs over each
 * package's pages folder and the app's overrides of it, with paths from the
 * app root, the way Vite's import.meta.glob expects them.
 */
export declare function pagesModule(root: string, packages: ResolvedPackage[]): string;

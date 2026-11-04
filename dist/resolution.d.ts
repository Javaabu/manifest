import type { ResolvedPackage } from "./discovery.js";
export interface Owner {
    pkg: ResolvedPackage;
    /** The path relative to the package's resources folder. */
    relative: string;
    /** Whether the file is the app's override rather than the package's own. */
    override: boolean;
}
export declare function splitQuery(id: string): [string, string];
/** The package a file belongs to: its resources folder (real or through vendor/) or its overrides folder. */
export declare function ownerOf(packages: ResolvedPackage[], id: string): Owner | null;
/** The app's override of a package file, when there is one; the file itself otherwise. */
export declare function applyOverride(packages: ResolvedPackage[], id: string): string;
/** Matches `@javaabu/filters` and `@javaabu/filters/sub/path` to the package that owns the alias. */
export declare function matchAlias(packages: ResolvedPackage[], source: string): {
    pkg: ResolvedPackage;
    subpath: string;
} | null;
export declare function isBareImport(source: string): boolean;
export declare function isRelativeImport(source: string): boolean;

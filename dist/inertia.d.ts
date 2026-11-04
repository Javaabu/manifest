/**
 * Every package page, by name. `php artisan manifest:types` fills it in for
 * the app, so `PackagePageName` is a union like "filters::Index" and
 * `PackagePageModule<"filters::Index">` is that page's module.
 */
export interface PackagePages {
}
export type PackagePageName = keyof PackagePages extends never ? string : Extract<keyof PackagePages, string>;
export type PackagePageModule<K extends PackagePageName> = K extends keyof PackagePages ? PackagePages[K] : unknown;
export type PageLoader<T> = () => Promise<T>;
/** What `import.meta.glob()` returns, lazy or eager. */
export type PageGlob<T> = Record<string, PageLoader<T> | T>;
/** A resolver for the app's own pages, such as laravel-vite-plugin's resolvePageComponent. */
export type PageResolver<T> = (name: string) => Promise<T> | T;
export interface ResolvePageOptions {
    /** The folder the app's glob keys start with, like "./pages/". Found from the keys when left out. */
    base?: string;
    /** Extensions to try, in order. */
    extensions?: string[];
}
export declare const PACKAGE_SEPARATOR = "::";
/** Whether an Inertia page name belongs to a package (`filters::Index`). */
export declare function isPackagePage(name: string): boolean;
export declare function parsePageName(name: string): {
    package: string;
    page: string;
} | null;
/**
 * Resolves an Inertia page: `package::Page` from the package's pages folder
 * (the app's override first), anything else from the app's own pages.
 *
 *     createInertiaApp({
 *       resolve: (name) => resolvePage(name, import.meta.glob("./pages/**\/*.tsx")),
 *     })
 */
export declare function resolvePage<T = unknown>(name: string, pages?: PageGlob<T> | PageResolver<T>, options?: ResolvePageOptions): Promise<T>;
/** A package page name, checked against the generated PackagePages. */
export declare function packagePage<K extends PackagePageName>(name: K): K;
export declare function resolvePackagePage<T = unknown>(packageName: string, page: string, options?: Pick<ResolvePageOptions, "extensions">): Promise<T>;
/** The names of every package page, like `filters::Reports/Show`. */
export declare function packagePageNames(): string[];

import { packages } from "virtual:javaabu-manifest/pages"

/**
 * Every package page, by name. `php artisan manifest:types` fills it in for
 * the app, so `PackagePageName` is a union like "filters::Index" and
 * `PackagePageModule<"filters::Index">` is that page's module.
 */
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface PackagePages {}

export type PackagePageName = keyof PackagePages extends never ? string : Extract<keyof PackagePages, string>

export type PackagePageModule<K extends PackagePageName> = K extends keyof PackagePages ? PackagePages[K] : unknown

export type PageLoader<T> = () => Promise<T>

/** What `import.meta.glob()` returns, lazy or eager. */
export type PageGlob<T> = Record<string, PageLoader<T> | T>

/** A resolver for the app's own pages, such as laravel-vite-plugin's resolvePageComponent. */
export type PageResolver<T> = (name: string) => Promise<T> | T

export interface ResolvePageOptions {
    /** The folder the app's glob keys start with, like "./pages/". Found from the keys when left out. */
    base?: string
    /** Extensions to try, in order. */
    extensions?: string[]
}

const DEFAULT_EXTENSIONS = [".tsx", ".jsx", ".vue", ".ts", ".js", ".svelte"]

export const PACKAGE_SEPARATOR = "::"

/** Whether an Inertia page name belongs to a package (`filters::Index`). */
export function isPackagePage(name: string): boolean {
    return name.includes(PACKAGE_SEPARATOR)
}

export function parsePageName(name: string): { package: string; page: string } | null {
    const index = name.indexOf(PACKAGE_SEPARATOR)

    if (index <= 0) {
        return null
    }

    return { package: name.slice(0, index), page: name.slice(index + PACKAGE_SEPARATOR.length) }
}

function stripExtension(key: string): string {
    return key.replace(/\.[^./]+$/, "")
}

async function load<T>(value: PageLoader<T> | T): Promise<T> {
    return typeof value === "function" ? await (value as PageLoader<T>)() : value
}

function findInGlob<T>(
    glob: PageGlob<T>,
    base: string,
    page: string,
    extensions: string[],
): PageLoader<T> | T | undefined {
    for (const extension of extensions) {
        const key = `${base}${page}${extension}`

        if (key in glob) {
            return glob[key]
        }
    }

    return undefined
}

function findAppPage<T>(glob: PageGlob<T>, name: string, options: ResolvePageOptions): PageLoader<T> | T | undefined {
    const extensions = options.extensions ?? DEFAULT_EXTENSIONS

    if (options.base !== undefined) {
        return findInGlob(glob, options.base, name, extensions)
    }

    // Without a base, the shortest key that ends in /{name} wins: "Dashboard" is
    // ./pages/Dashboard.tsx rather than ./pages/admin/Dashboard.tsx.
    let match: string | undefined

    for (const key of Object.keys(glob)) {
        const stripped = stripExtension(key)

        if (!extensions.some((extension) => key.endsWith(extension))) {
            continue
        }

        if (stripped !== name && !stripped.endsWith(`/${name}`)) {
            continue
        }

        if (match === undefined || key.length < match.length) {
            match = key
        }
    }

    return match === undefined ? undefined : glob[match]
}

/**
 * Resolves an Inertia page: `package::Page` from the package's pages folder
 * (the app's override first), anything else from the app's own pages.
 *
 *     createInertiaApp({
 *       resolve: (name) => resolvePage(name, import.meta.glob("./pages/**\/*.tsx")),
 *     })
 */
export async function resolvePage<T = unknown>(
    name: string,
    pages?: PageGlob<T> | PageResolver<T>,
    options: ResolvePageOptions = {},
): Promise<T> {
    const parsed = parsePageName(name)

    if (parsed) {
        return resolvePackagePage<T>(parsed.package, parsed.page, options)
    }

    if (typeof pages === "function") {
        return await pages(name)
    }

    const found = pages ? findAppPage(pages, name, options) : undefined

    if (found === undefined) {
        throw new Error(`Page not found: ${name}`)
    }

    return load(found)
}

/** A package page name, checked against the generated PackagePages. */
export function packagePage<K extends PackagePageName>(name: K): K {
    return name
}

export async function resolvePackagePage<T = unknown>(
    packageName: string,
    page: string,
    options: Pick<ResolvePageOptions, "extensions"> = {},
): Promise<T> {
    const pkg = packages[packageName]
    const extensions = options.extensions ?? DEFAULT_EXTENSIONS

    if (!pkg) {
        throw new Error(
            `Page not found: ${packageName}${PACKAGE_SEPARATOR}${page}. No package named "${packageName}" registers Inertia pages with javaabu/manifest (->pages()).`,
        )
    }

    const found =
        findInGlob(pkg.overrides as PageGlob<T>, pkg.overrideBase, page, extensions) ??
        findInGlob(pkg.pages as PageGlob<T>, pkg.base, page, extensions)

    if (found === undefined) {
        throw new Error(
            `Page not found: ${packageName}${PACKAGE_SEPARATOR}${page}. Looked in ${pkg.overrideBase} and ${pkg.base}.`,
        )
    }

    return load(found)
}

/** The names of every package page, like `filters::Reports/Show`. */
export function packagePageNames(): string[] {
    const names = new Set<string>()

    for (const [name, pkg] of Object.entries(packages)) {
        for (const [glob, base] of [
            [pkg.pages, pkg.base],
            [pkg.overrides, pkg.overrideBase],
        ] as const) {
            for (const key of Object.keys(glob)) {
                names.add(`${name}${PACKAGE_SEPARATOR}${stripExtension(key.slice(base.length))}`)
            }
        }
    }

    return [...names].sort()
}

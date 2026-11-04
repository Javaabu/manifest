import { packages } from "virtual:javaabu-manifest/pages";
const DEFAULT_EXTENSIONS = [".tsx", ".jsx", ".vue", ".ts", ".js", ".svelte"];
export const PACKAGE_SEPARATOR = "::";
/** Whether an Inertia page name belongs to a package (`filters::Index`). */
export function isPackagePage(name) {
    return name.includes(PACKAGE_SEPARATOR);
}
export function parsePageName(name) {
    const index = name.indexOf(PACKAGE_SEPARATOR);
    if (index <= 0) {
        return null;
    }
    return { package: name.slice(0, index), page: name.slice(index + PACKAGE_SEPARATOR.length) };
}
function stripExtension(key) {
    return key.replace(/\.[^./]+$/, "");
}
async function load(value) {
    return typeof value === "function" ? await value() : value;
}
function findInGlob(glob, base, page, extensions) {
    for (const extension of extensions) {
        const key = `${base}${page}${extension}`;
        if (key in glob) {
            return glob[key];
        }
    }
    return undefined;
}
function findAppPage(glob, name, options) {
    const extensions = options.extensions ?? DEFAULT_EXTENSIONS;
    if (options.base !== undefined) {
        return findInGlob(glob, options.base, name, extensions);
    }
    // Without a base, the shortest key that ends in /{name} wins: "Dashboard" is
    // ./pages/Dashboard.tsx rather than ./pages/admin/Dashboard.tsx.
    let match;
    for (const key of Object.keys(glob)) {
        const stripped = stripExtension(key);
        if (!extensions.some((extension) => key.endsWith(extension))) {
            continue;
        }
        if (stripped !== name && !stripped.endsWith(`/${name}`)) {
            continue;
        }
        if (match === undefined || key.length < match.length) {
            match = key;
        }
    }
    return match === undefined ? undefined : glob[match];
}
/**
 * Resolves an Inertia page: `package::Page` from the package's pages folder
 * (the app's override first), anything else from the app's own pages.
 *
 *     createInertiaApp({
 *       resolve: (name) => resolvePage(name, import.meta.glob("./pages/**\/*.tsx")),
 *     })
 */
export async function resolvePage(name, pages, options = {}) {
    const parsed = parsePageName(name);
    if (parsed) {
        return resolvePackagePage(parsed.package, parsed.page, options);
    }
    if (typeof pages === "function") {
        return await pages(name);
    }
    const found = pages ? findAppPage(pages, name, options) : undefined;
    if (found === undefined) {
        throw new Error(`Page not found: ${name}`);
    }
    return load(found);
}
export async function resolvePackagePage(packageName, page, options = {}) {
    const pkg = packages[packageName];
    const extensions = options.extensions ?? DEFAULT_EXTENSIONS;
    if (!pkg) {
        throw new Error(`Page not found: ${packageName}${PACKAGE_SEPARATOR}${page}. No package named "${packageName}" registers Inertia pages with javaabu/manifest (->pages()).`);
    }
    const found = findInGlob(pkg.overrides, pkg.overrideBase, page, extensions) ??
        findInGlob(pkg.pages, pkg.base, page, extensions);
    if (found === undefined) {
        throw new Error(`Page not found: ${packageName}${PACKAGE_SEPARATOR}${page}. Looked in ${pkg.overrideBase} and ${pkg.base}.`);
    }
    return load(found);
}
/** The names of every package page, like `filters::Reports/Show`. */
export function packagePageNames() {
    const names = new Set();
    for (const [name, pkg] of Object.entries(packages)) {
        for (const [glob, base] of [
            [pkg.pages, pkg.base],
            [pkg.overrides, pkg.overrideBase],
        ]) {
            for (const key of Object.keys(glob)) {
                names.add(`${name}${PACKAGE_SEPARATOR}${stripExtension(key.slice(base.length))}`);
            }
        }
    }
    return [...names].sort();
}

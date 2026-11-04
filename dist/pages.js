import path from "node:path";
import { toPosix } from "./discovery.js";
export const PAGES_MODULE_ID = "virtual:javaabu-manifest/pages";
export const RESOLVED_PAGES_MODULE_ID = `\0${PAGES_MODULE_ID}`;
export const PAGE_EXTENSIONS = ["tsx", "jsx", "vue", "ts", "js", "svelte"];
/**
 * The virtual module behind `@javaabu/manifest/inertia`: lazy globs over each
 * package's pages folder and the app's overrides of it, with paths from the
 * app root, the way Vite's import.meta.glob expects them.
 */
export function pagesModule(root, packages) {
    const extensions = `{${PAGE_EXTENSIONS.join(",")}}`;
    const entries = packages
        .filter((pkg) => pkg.pagesDir && pkg.info.pages)
        .map((pkg) => {
        const base = `/${toPosix(path.relative(root, pkg.pagesDir))}/`;
        const overrideBase = `/${toPosix(path.relative(root, path.join(pkg.overridesDir, pkg.info.pages)))}/`;
        return [
            `  ${JSON.stringify(pkg.name)}: {`,
            `    base: ${JSON.stringify(base)},`,
            `    overrideBase: ${JSON.stringify(overrideBase)},`,
            `    pages: import.meta.glob(${JSON.stringify(`${base}**/*.${extensions}`)}),`,
            `    overrides: import.meta.glob(${JSON.stringify(`${overrideBase}**/*.${extensions}`)}),`,
            `  },`,
        ].join("\n");
    });
    return `export const packages = {\n${entries.join("\n")}\n}\n`;
}

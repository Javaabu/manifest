import fs from "node:fs";
import path from "node:path";
function within(dir, file) {
    const relative = path.relative(dir, file);
    if (relative === "" || relative.startsWith("..") || path.isAbsolute(relative)) {
        return null;
    }
    return relative;
}
export function splitQuery(id) {
    const index = id.indexOf("?");
    return index === -1 ? [id, ""] : [id.slice(0, index), id.slice(index)];
}
/** The package a file belongs to: its resources folder (real or through vendor/) or its overrides folder. */
export function ownerOf(packages, id) {
    const [file] = splitQuery(id);
    for (const pkg of packages) {
        const override = within(pkg.overridesDir, file);
        if (override !== null) {
            return { pkg, relative: override, override: true };
        }
        const relative = within(pkg.realDir, file) ?? within(pkg.dir, file);
        if (relative !== null) {
            return { pkg, relative, override: false };
        }
    }
    return null;
}
/** The app's override of a package file, when there is one; the file itself otherwise. */
export function applyOverride(packages, id) {
    const [file, query] = splitQuery(id);
    const owner = ownerOf(packages, file);
    if (!owner || owner.override) {
        return id;
    }
    const candidate = path.join(owner.pkg.overridesDir, owner.relative);
    return fs.existsSync(candidate) ? candidate + query : id;
}
/** Matches `@javaabu/filters` and `@javaabu/filters/sub/path` to the package that owns the alias. */
export function matchAlias(packages, source) {
    let match = null;
    for (const pkg of packages) {
        if (!pkg.alias || !pkg.jsDir) {
            continue;
        }
        if (source !== pkg.alias && !source.startsWith(`${pkg.alias}/`)) {
            continue;
        }
        if (!match || pkg.alias.length > (match.pkg.alias?.length ?? 0)) {
            match = { pkg, subpath: source.slice(pkg.alias.length + 1) };
        }
    }
    return match;
}
export function isBareImport(source) {
    return !(source.startsWith(".") ||
        source.startsWith("/") ||
        source.startsWith("\0") ||
        /^[a-zA-Z]:[\\/]/.test(source) ||
        /^[a-z][a-z0-9+.-]*:/i.test(source));
}
export function isRelativeImport(source) {
    return source === "." || source === ".." || source.startsWith("./") || source.startsWith("../");
}

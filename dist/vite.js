import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { searchForWorkspaceRoot } from "vite";
import { DEFAULT_DISCOVER_COMMAND, DEFAULT_DISCOVERY_PATH, readDiscovery, resolvePackages, runDiscovery, toPosix, } from "./discovery.js";
import { PAGES_MODULE_ID, RESOLVED_PAGES_MODULE_ID, pagesModule } from "./pages.js";
import { lazy, loadPackagePlugin, selectPackagePlugins, unknownPluginSettings, } from "./plugins.js";
import { applyOverride, isBareImport, isRelativeImport, matchAlias, ownerOf } from "./resolution.js";
export const INERTIA_MODULE_ID = "@javaabu/manifest/inertia";
const here = path.dirname(fileURLToPath(import.meta.url));
function inertiaModulePath() {
    const built = path.join(here, "inertia.js");
    return fs.existsSync(built) ? built : path.join(here, "inertia.ts");
}
function loadDiscovery(root, options, warn) {
    const file = path.resolve(root, options.discovery ?? DEFAULT_DISCOVERY_PATH);
    const discover = options.discover ?? true;
    if (discover !== false) {
        const command = typeof discover === "string" ? discover : DEFAULT_DISCOVER_COMMAND;
        const error = runDiscovery(root, command);
        if (error && !fs.existsSync(file)) {
            throw new Error(`[javaabu/manifest] "${command}" failed, and there's no ${file} to fall back to.\n${error.message}`);
        }
        if (error) {
            warn(`[javaabu/manifest] "${command}" failed, so the existing ${path.relative(root, file)} is used.`);
        }
    }
    return readDiscovery(file);
}
export function computeInputs(root, discovery, packages) {
    const byName = new Map(packages.map((pkg) => [pkg.name, pkg]));
    const inputs = [];
    for (const asset of discovery.assets) {
        const pkg = byName.get(asset.package);
        if (!pkg) {
            continue;
        }
        const override = path.join(pkg.overridesDir, asset.entry);
        const file = fs.existsSync(override) ? override : path.join(pkg.dir, asset.entry);
        const input = toPosix(path.relative(root, file));
        if (!inputs.includes(input)) {
            inputs.push(input);
        }
    }
    return inputs;
}
export function mergeInputs(existing, inputs) {
    if (!inputs.length) {
        return existing;
    }
    if (existing === undefined) {
        return inputs;
    }
    if (typeof existing === "string") {
        return [existing, ...inputs.filter((input) => input !== existing)];
    }
    if (Array.isArray(existing)) {
        return [...existing, ...inputs.filter((input) => !existing.includes(input))];
    }
    const merged = { ...existing };
    const values = Object.values(existing);
    for (const input of inputs) {
        if (!values.includes(input)) {
            merged[input] = input;
        }
    }
    return merged;
}
/**
 * Rewrites Vite manifest keys of packages symlinked from a Composer path
 * repository (`../filters/resources/...`) to the vendor/ paths Laravel renders.
 */
export function rewriteManifestKeys(root, manifest, packages) {
    const mappings = packages
        .filter((pkg) => pkg.realDir !== pkg.dir)
        .map((pkg) => [toPosix(path.relative(root, pkg.realDir)) + "/", toPosix(path.relative(root, pkg.dir)) + "/"]);
    if (!mappings.length) {
        return false;
    }
    const map = (key) => {
        for (const [from, to] of mappings) {
            if (key.startsWith(from)) {
                return to + key.slice(from.length);
            }
        }
        return key;
    };
    let changed = false;
    for (const key of Object.keys(manifest)) {
        const chunk = manifest[key];
        const mapped = map(key);
        if (chunk.src) {
            chunk.src = map(chunk.src);
        }
        for (const list of ["imports", "dynamicImports"]) {
            const keys = chunk[list];
            if (Array.isArray(keys)) {
                chunk[list] = keys.map(map);
            }
        }
        if (mapped !== key) {
            manifest[mapped] = chunk;
            delete manifest[key];
            changed = true;
        }
    }
    return changed;
}
/**
 * Builds the assets Laravel packages register with javaabu/manifest, and runs
 * the Vite plugins they register. Use it next to laravel-vite-plugin:
 *
 *     plugins: [laravel({ input: ["resources/js/app.ts"] }), manifest()]
 */
export default function manifest(options = {}) {
    const plugin = manifestPlugin(options);
    const packagePlugins = lazy(async () => (await plugin.packagePlugins()).flatMap((loaded) => loaded.plugins));
    return Object.assign([plugin, packagePlugins], {
        plugin,
        packages: () => plugin.packages(),
        inputs: () => plugin.inputs(),
    });
}
function manifestPlugin(options) {
    let root = process.cwd();
    let discovery = { version: 1, packages: {}, assets: [] };
    let packages = [];
    let config;
    let loaded = false;
    let loadedPlugins;
    const pending = [];
    const load = (from) => {
        root = from;
        discovery = loadDiscovery(root, options, (message) => pending.push(message));
        packages = resolvePackages(root, discovery);
        loaded = true;
    };
    return {
        name: "javaabu-manifest",
        enforce: "pre",
        packages: () => packages,
        packagePlugins() {
            // Vite awaits this while it flattens the config's plugins, before any
            // hook runs: discover now, from where Vite runs.
            if (!loaded) {
                load(process.cwd());
            }
            loadedPlugins ??= Promise.all(selectPackagePlugins(packages, options.plugins).map((registered) => loadPackagePlugin(registered, root)));
            return loadedPlugins;
        },
        inputs() {
            // Called from vite.config.js before Vite runs the plugin: discover now.
            if (!loaded) {
                load(process.cwd());
            }
            return computeInputs(root, discovery, packages);
        },
        config(userConfig) {
            const from = path.resolve(userConfig.root ?? process.cwd());
            if (!loaded || from !== root) {
                load(from);
            }
            const allow = new Set([searchForWorkspaceRoot(root)]);
            for (const pkg of packages) {
                allow.add(pkg.realDir);
                allow.add(pkg.overridesDir);
            }
            return {
                server: { fs: { allow: [...(userConfig.server?.fs?.allow ?? []), ...allow] } },
                optimizeDeps: { exclude: [INERTIA_MODULE_ID] },
            };
        },
        async configResolved(resolved) {
            config = resolved;
            for (const message of pending.splice(0)) {
                resolved.logger.warn(message);
            }
            for (const name of unknownPluginSettings(packages, options.plugins)) {
                resolved.logger.warn(`[javaabu/manifest] The "plugins" option names "${name}", but no package by that name registers a Vite plugin.`);
            }
            for (const { plugin: registered, plugins } of loadedPlugins ? await loadedPlugins : []) {
                for (const name of new Set(plugins.map((added) => added.name))) {
                    if ((resolved.plugins ?? []).filter((other) => other.name === name).length > 1) {
                        resolved.logger.warn(`[javaabu/manifest] The ${registered.package} package's Vite plugin "${name}" runs twice: manifest() adds it, and so does vite.config.js. Remove it from vite.config.js, or pass manifest({ plugins: { "${registered.package}": false } }).`);
                    }
                }
            }
            for (const pkg of packages) {
                if (pkg.alias && fs.existsSync(path.join(root, "node_modules", pkg.alias, "package.json"))) {
                    resolved.logger.warn(`[javaabu/manifest] "${pkg.alias}" is the import alias of the ${pkg.name} package and also an installed npm package. Imports of "${pkg.alias}" resolve to the Laravel package.`);
                }
            }
        },
        options(rollupOptions) {
            if (config?.command !== "build" || config.build.ssr || options.input === false) {
                return null;
            }
            return {
                ...rollupOptions,
                input: mergeInputs(rollupOptions.input, computeInputs(root, discovery, packages)),
            };
        },
        async resolveId(source, importer, resolveOptions) {
            if (source === INERTIA_MODULE_ID) {
                return inertiaModulePath();
            }
            if (source === PAGES_MODULE_ID) {
                return RESOLVED_PAGES_MODULE_ID;
            }
            const aliased = matchAlias(packages, source);
            if (aliased) {
                const target = aliased.subpath
                    ? path.join(aliased.pkg.jsDir, aliased.subpath)
                    : aliased.pkg.jsDir;
                const resolved = await this.resolve(target, importer, { ...resolveOptions, skipSelf: true });
                if (!resolved) {
                    return null;
                }
                return { ...resolved, id: applyOverride(packages, resolved.id) };
            }
            if (!importer) {
                return null;
            }
            const owner = ownerOf(packages, importer);
            if (!owner) {
                return null;
            }
            if (isRelativeImport(source)) {
                // An override resolves its relative imports as if it were the package's file,
                // so overriding one file doesn't mean copying its neighbours.
                const from = owner.override ? path.join(owner.pkg.realDir, owner.relative) : importer;
                const resolved = (await this.resolve(source, from, { ...resolveOptions, skipSelf: true })) ??
                    (owner.override ? await this.resolve(source, importer, { ...resolveOptions, skipSelf: true }) : null);
                if (!resolved) {
                    return null;
                }
                return { ...resolved, id: applyOverride(packages, resolved.id) };
            }
            if (isBareImport(source)) {
                // Bare imports in package files resolve from the app, so there's one copy of
                // React or Vue, even for packages symlinked from outside the app.
                return await this.resolve(source, path.join(root, "package.json"), {
                    ...resolveOptions,
                    skipSelf: true,
                });
            }
            return null;
        },
        load(id) {
            if (id === RESOLVED_PAGES_MODULE_ID) {
                return pagesModule(root, packages);
            }
            return null;
        },
        writeBundle(outputOptions) {
            if (!config?.build.manifest || !outputOptions.dir) {
                return;
            }
            const name = typeof config.build.manifest === "string" ? config.build.manifest : ".vite/manifest.json";
            const file = path.join(outputOptions.dir, name);
            if (!fs.existsSync(file)) {
                return;
            }
            const data = JSON.parse(fs.readFileSync(file, "utf8"));
            if (rewriteManifestKeys(root, data, packages)) {
                fs.writeFileSync(file, JSON.stringify(data, null, 2));
            }
        },
    };
}
export { manifest };

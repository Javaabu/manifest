import { execSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
export const DEFAULT_DISCOVERY_PATH = "bootstrap/cache/manifest.json";
export const DEFAULT_DISCOVER_COMMAND = "php artisan manifest:discover";
/**
 * Runs `php artisan manifest:discover` in the app root. Returns the error
 * when it fails, so the caller can fall back to an existing file.
 */
export function runDiscovery(root, command) {
    try {
        execSync(command, { cwd: root, stdio: "pipe" });
        return null;
    }
    catch (error) {
        return error instanceof Error ? error : new Error(String(error));
    }
}
export function readDiscovery(file) {
    if (!fs.existsSync(file)) {
        throw new Error(`[javaabu/manifest] ${file} doesn't exist. Run "php artisan manifest:discover", or let the plugin run it (the "discover" option).`);
    }
    const data = JSON.parse(fs.readFileSync(file, "utf8"));
    if (data.version !== 1 || typeof data.packages !== "object" || !Array.isArray(data.assets)) {
        throw new Error(`[javaabu/manifest] ${file} isn't a manifest discovery file this plugin understands. Update javaabu/manifest and run "php artisan manifest:discover".`);
    }
    return data;
}
export function resolvePackages(root, discovery) {
    return Object.entries(discovery.packages).map(([name, info]) => {
        const dir = path.resolve(root, info.path);
        const realDir = realpath(dir);
        const overridesDir = path.resolve(root, info.overrides);
        return {
            name,
            dir,
            realDir,
            jsDir: info.js ? path.join(realDir, info.js) : null,
            alias: info.alias,
            pagesDir: info.pages ? path.join(dir, info.pages) : null,
            overridesDir,
            vitePlugins: (info.vitePlugins ?? []).map((plugin) => ({
                package: name,
                module: plugin.module,
                file: path.join(dir, plugin.module),
                options: plugin.options ?? {},
            })),
            info,
        };
    });
}
export function realpath(file) {
    try {
        return fs.realpathSync(file);
    }
    catch {
        return file;
    }
}
export function toPosix(file) {
    return file.split(path.sep).join("/");
}

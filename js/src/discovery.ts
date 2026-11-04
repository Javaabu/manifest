import { execSync } from "node:child_process"
import fs from "node:fs"
import path from "node:path"

/** A package as `php artisan manifest:discover` writes it. Paths are relative to the app root. */
export interface DiscoveredPackage {
    path: string
    alias: string | null
    js: string | null
    pages: string | null
    dist: string | null
    overrides: string
    dependencies: Record<string, string>
    peerDependencies: Record<string, string>
}

export interface DiscoveredAsset {
    id: string
    package: string
    name: string
    type: "css" | "sass" | "js" | "ts" | "react" | "vue"
    entry: string
    input: string
    dependencies: string[]
}

export interface Discovery {
    version: number
    packages: Record<string, DiscoveredPackage>
    assets: DiscoveredAsset[]
}

/** A discovered package with absolute paths. */
export interface ResolvedPackage {
    name: string
    /** The resources folder, through vendor/ (a symlink for path repositories). */
    dir: string
    /** The same folder with symlinks resolved, as Vite sees module ids. */
    realDir: string
    jsDir: string | null
    alias: string | null
    pagesDir: string | null
    overridesDir: string
    info: DiscoveredPackage
}

export const DEFAULT_DISCOVERY_PATH = "bootstrap/cache/manifest.json"

export const DEFAULT_DISCOVER_COMMAND = "php artisan manifest:discover"

/**
 * Runs `php artisan manifest:discover` in the app root. Returns the error
 * when it fails, so the caller can fall back to an existing file.
 */
export function runDiscovery(root: string, command: string): Error | null {
    try {
        execSync(command, { cwd: root, stdio: "pipe" })

        return null
    } catch (error) {
        return error instanceof Error ? error : new Error(String(error))
    }
}

export function readDiscovery(file: string): Discovery {
    if (!fs.existsSync(file)) {
        throw new Error(
            `[javaabu/manifest] ${file} doesn't exist. Run "php artisan manifest:discover", or let the plugin run it (the "discover" option).`,
        )
    }

    const data = JSON.parse(fs.readFileSync(file, "utf8")) as Partial<Discovery>

    if (data.version !== 1 || typeof data.packages !== "object" || !Array.isArray(data.assets)) {
        throw new Error(
            `[javaabu/manifest] ${file} isn't a manifest discovery file this plugin understands. Update javaabu/manifest and run "php artisan manifest:discover".`,
        )
    }

    return data as Discovery
}

export function resolvePackages(root: string, discovery: Discovery): ResolvedPackage[] {
    return Object.entries(discovery.packages).map(([name, info]) => {
        const dir = path.resolve(root, info.path)
        const realDir = realpath(dir)
        const overridesDir = path.resolve(root, info.overrides)

        return {
            name,
            dir,
            realDir,
            jsDir: info.js ? path.join(realDir, info.js) : null,
            alias: info.alias,
            pagesDir: info.pages ? path.join(dir, info.pages) : null,
            overridesDir,
            info,
        }
    })
}

export function realpath(file: string): string {
    try {
        return fs.realpathSync(file)
    } catch {
        return file
    }
}

export function toPosix(file: string): string {
    return file.split(path.sep).join("/")
}

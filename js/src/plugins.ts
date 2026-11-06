import fs from "node:fs"
import path from "node:path"
import { pathToFileURL } from "node:url"
import type { Plugin, PluginOption } from "vite"
import { toPosix, type PackageVitePlugin, type ResolvedPackage } from "./discovery.js"

/** Per package: `false` skips its Vite plugins, an object is merged over the options it registered. */
export type PackagePluginSettings = Record<string, boolean | Record<string, unknown>>

export interface LoadedPackagePlugin {
  plugin: PackageVitePlugin
  /** What the factory returned, flattened. */
  plugins: Plugin[]
}

/** The registered package plugins that run, with the app's options merged over the package's. */
export function selectPackagePlugins(
  packages: ResolvedPackage[],
  settings: PackagePluginSettings = {},
): PackageVitePlugin[] {
  const selected: PackageVitePlugin[] = []

  for (const pkg of packages) {
    const setting = settings[pkg.name]

    if (setting === false) {
      continue
    }

    for (const plugin of pkg.vitePlugins) {
      selected.push(typeof setting === "object" ? { ...plugin, options: { ...plugin.options, ...setting } } : plugin)
    }
  }

  return selected
}

/** Keys of the app's `plugins` option that name no package with a Vite plugin. */
export function unknownPluginSettings(packages: ResolvedPackage[], settings: PackagePluginSettings = {}): string[] {
  const names = new Set(packages.filter((pkg) => pkg.vitePlugins.length).map((pkg) => pkg.name))

  return Object.keys(settings).filter((name) => !names.has(name))
}

export async function flattenPlugins(option: PluginOption): Promise<Plugin[]> {
  const value = await option

  if (!value) {
    return []
  }

  if (Array.isArray(value)) {
    return (await Promise.all(value.map(flattenPlugins))).flat()
  }

  return [value]
}

export async function loadPackagePlugin(plugin: PackageVitePlugin, root: string): Promise<LoadedPackagePlugin> {
  const label = `the ${plugin.package} package's Vite plugin (${toPosix(path.relative(root, plugin.file))})`

  if (!fs.existsSync(plugin.file)) {
    throw new Error(
      `[javaabu/manifest] Can't find ${label}. Reinstall the package, or turn its plugin off with manifest({ plugins: { "${plugin.package}": false } }).`,
    )
  }

  const module = (await import(pathToFileURL(plugin.file).href)) as { default?: unknown }

  if (typeof module.default !== "function") {
    throw new Error(`[javaabu/manifest] The default export of ${label} isn't a function that returns a Vite plugin.`)
  }

  const factory = module.default as (options: Record<string, unknown>) => PluginOption

  return { plugin, plugins: await flattenPlugins(factory(plugin.options)) }
}

/**
 * A promise-like that only starts when Vite awaits it, while it flattens the
 * config's plugins, so nothing is discovered or imported before Vite runs.
 */
export function lazy<T>(start: () => Promise<T>): Promise<T> {
  let promise: Promise<T> | undefined

  const run = () => (promise ??= start())

  return {
    then: (resolve, reject) => run().then(resolve, reject),
    catch: (reject) => run().catch(reject),
    finally: (callback) => run().finally(callback),
    [Symbol.toStringTag]: "Promise",
  } as Promise<T>
}

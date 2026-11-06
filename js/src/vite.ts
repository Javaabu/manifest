import fs from "node:fs"
import path from "node:path"
import { fileURLToPath } from "node:url"
import { searchForWorkspaceRoot, type Plugin, type PluginOption, type ResolvedConfig } from "vite"
import {
  DEFAULT_DISCOVER_COMMAND,
  DEFAULT_DISCOVERY_PATH,
  readDiscovery,
  resolvePackages,
  runDiscovery,
  toPosix,
  type Discovery,
  type ResolvedPackage,
} from "./discovery.js"
import { PAGES_MODULE_ID, RESOLVED_PAGES_MODULE_ID, pagesModule } from "./pages.js"
import {
  lazy,
  loadPackagePlugin,
  selectPackagePlugins,
  unknownPluginSettings,
  type LoadedPackagePlugin,
  type PackagePluginSettings,
} from "./plugins.js"
import { applyOverride, isBareImport, isRelativeImport, matchAlias, ownerOf } from "./resolution.js"

export interface ManifestPluginOptions {
  /**
   * The file `php artisan manifest:discover` writes, relative to the Vite root.
   * @default "bootstrap/cache/manifest.json"
   */
  discovery?: string

  /**
   * Run discovery when Vite starts: `true` runs `php artisan manifest:discover`,
   * a string runs that command instead (in the Vite root), and `false` only
   * reads the existing file. When the command fails (no PHP, say), the plugin
   * falls back to the existing file with a warning.
   * @default true
   */
  discover?: boolean | string

  /**
   * Add every registered asset to the build's inputs. Turn it off to list
   * entries yourself with `inputs()`.
   * @default true
   */
  input?: boolean

  /**
   * The Vite plugins packages register (`->vitePlugin()`), by package name:
   * `false` doesn't run that package's plugins, and an object is merged over
   * the options the package registered.
   * @default {}
   */
  plugins?: PackagePluginSettings
}

export interface ManifestPlugin extends Plugin {
  /** The discovered packages, once Vite has resolved its config. */
  packages(): ResolvedPackage[]
  /** Every registered asset's entry (overrides applied), relative to the Vite root. */
  inputs(): string[]
  /** Imports and runs the packages' Vite plugins (once). */
  packagePlugins(): Promise<LoadedPackagePlugin[]>
}

/**
 * What `manifest()` returns: manifest's own plugin, then the packages' Vite
 * plugins. Vite flattens it; `packages()` and `inputs()` work on it too.
 */
export interface ManifestPlugins extends Array<PluginOption> {
  /** manifest's own plugin. */
  plugin: ManifestPlugin
  packages(): ResolvedPackage[]
  inputs(): string[]
}

export const INERTIA_MODULE_ID = "@javaabu/manifest/inertia"

const here = path.dirname(fileURLToPath(import.meta.url))

function inertiaModulePath(): string {
  const built = path.join(here, "inertia.js")

  return fs.existsSync(built) ? built : path.join(here, "inertia.ts")
}

function loadDiscovery(root: string, options: ManifestPluginOptions, warn: (message: string) => void): Discovery {
  const file = path.resolve(root, options.discovery ?? DEFAULT_DISCOVERY_PATH)
  const discover = options.discover ?? true

  if (discover !== false) {
    const command = typeof discover === "string" ? discover : DEFAULT_DISCOVER_COMMAND
    const error = runDiscovery(root, command)

    if (error && !fs.existsSync(file)) {
      throw new Error(
        `[javaabu/manifest] "${command}" failed, and there's no ${file} to fall back to.\n${error.message}`,
      )
    }

    if (error) {
      warn(`[javaabu/manifest] "${command}" failed, so the existing ${path.relative(root, file)} is used.`)
    }
  }

  return readDiscovery(file)
}

export function computeInputs(root: string, discovery: Discovery, packages: ResolvedPackage[]): string[] {
  const byName = new Map(packages.map((pkg) => [pkg.name, pkg]))
  const inputs: string[] = []

  for (const asset of discovery.assets) {
    const pkg = byName.get(asset.package)

    if (!pkg) {
      continue
    }

    const override = path.join(pkg.overridesDir, asset.entry)
    const file = fs.existsSync(override) ? override : path.join(pkg.dir, asset.entry)
    const input = toPosix(path.relative(root, file))

    if (!inputs.includes(input)) {
      inputs.push(input)
    }
  }

  return inputs
}

interface ManifestChunk {
  src?: string
  imports?: string[]
  dynamicImports?: string[]
  [key: string]: unknown
}

type RollupInput = string | string[] | Record<string, string> | undefined

export function mergeInputs(existing: RollupInput, inputs: string[]): RollupInput {
  if (!inputs.length) {
    return existing
  }

  if (existing === undefined) {
    return inputs
  }

  if (typeof existing === "string") {
    return [existing, ...inputs.filter((input) => input !== existing)]
  }

  if (Array.isArray(existing)) {
    return [...existing, ...inputs.filter((input) => !existing.includes(input))]
  }

  const merged = { ...existing }
  const values = Object.values(existing)

  for (const input of inputs) {
    if (!values.includes(input)) {
      merged[input] = input
    }
  }

  return merged
}

/**
 * Rewrites Vite manifest keys of packages symlinked from a Composer path
 * repository (`../filters/resources/...`) to the vendor/ paths Laravel renders.
 */
export function rewriteManifestKeys(
  root: string,
  manifest: Record<string, ManifestChunk>,
  packages: ResolvedPackage[],
): boolean {
  const mappings = packages
    .filter((pkg) => pkg.realDir !== pkg.dir)
    .map(
      (pkg) => [toPosix(path.relative(root, pkg.realDir)) + "/", toPosix(path.relative(root, pkg.dir)) + "/"] as const,
    )

  if (!mappings.length) {
    return false
  }

  const map = (key: string) => {
    for (const [from, to] of mappings) {
      if (key.startsWith(from)) {
        return to + key.slice(from.length)
      }
    }

    return key
  }

  let changed = false

  for (const key of Object.keys(manifest)) {
    const chunk = manifest[key] as ManifestChunk
    const mapped = map(key)

    if (chunk.src) {
      chunk.src = map(chunk.src)
    }

    for (const list of ["imports", "dynamicImports"] as const) {
      const keys = chunk[list]

      if (Array.isArray(keys)) {
        chunk[list] = keys.map(map)
      }
    }

    if (mapped !== key) {
      manifest[mapped] = chunk
      delete manifest[key]
      changed = true
    }
  }

  return changed
}

/**
 * Builds the assets Laravel packages register with javaabu/manifest, and runs
 * the Vite plugins they register. Use it next to laravel-vite-plugin:
 *
 *     plugins: [laravel({ input: ["resources/js/app.ts"] }), manifest()]
 */
export default function manifest(options: ManifestPluginOptions = {}): ManifestPlugins {
  const plugin = manifestPlugin(options)
  const packagePlugins = lazy(async () => (await plugin.packagePlugins()).flatMap((loaded) => loaded.plugins))

  return Object.assign([plugin, packagePlugins] as PluginOption[], {
    plugin,
    packages: () => plugin.packages(),
    inputs: () => plugin.inputs(),
  })
}

function manifestPlugin(options: ManifestPluginOptions): ManifestPlugin {
  let root = process.cwd()
  let discovery: Discovery = { version: 1, packages: {}, assets: [] }
  let packages: ResolvedPackage[] = []
  let config: ResolvedConfig | undefined
  let loaded = false
  let loadedPlugins: Promise<LoadedPackagePlugin[]> | undefined
  const pending: string[] = []

  const load = (from: string) => {
    root = from
    discovery = loadDiscovery(root, options, (message) => pending.push(message))
    packages = resolvePackages(root, discovery)
    loaded = true
  }

  return {
    name: "javaabu-manifest",
    enforce: "pre",

    packages: () => packages,

    packagePlugins() {
      // Vite awaits this while it flattens the config's plugins, before any
      // hook runs: discover now, from where Vite runs.
      if (!loaded) {
        load(process.cwd())
      }

      loadedPlugins ??= Promise.all(
        selectPackagePlugins(packages, options.plugins).map((registered) => loadPackagePlugin(registered, root)),
      )

      return loadedPlugins
    },

    inputs() {
      // Called from vite.config.js before Vite runs the plugin: discover now.
      if (!loaded) {
        load(process.cwd())
      }

      return computeInputs(root, discovery, packages)
    },

    config(userConfig) {
      const from = path.resolve(userConfig.root ?? process.cwd())

      if (!loaded || from !== root) {
        load(from)
      }

      const allow = new Set<string>([searchForWorkspaceRoot(root)])

      for (const pkg of packages) {
        allow.add(pkg.realDir)
        allow.add(pkg.overridesDir)
      }

      return {
        server: { fs: { allow: [...(userConfig.server?.fs?.allow ?? []), ...allow] } },
        optimizeDeps: { exclude: [INERTIA_MODULE_ID] },
      }
    },

    async configResolved(resolved) {
      config = resolved

      for (const message of pending.splice(0)) {
        resolved.logger.warn(message)
      }

      for (const name of unknownPluginSettings(packages, options.plugins)) {
        resolved.logger.warn(
          `[javaabu/manifest] The "plugins" option names "${name}", but no package by that name registers a Vite plugin.`,
        )
      }

      for (const { plugin: registered, plugins } of loadedPlugins ? await loadedPlugins : []) {
        for (const name of new Set(plugins.map((added) => added.name))) {
          if ((resolved.plugins ?? []).filter((other) => other.name === name).length > 1) {
            resolved.logger.warn(
              `[javaabu/manifest] The ${registered.package} package's Vite plugin "${name}" runs twice: manifest() adds it, and so does vite.config.js. Remove it from vite.config.js, or pass manifest({ plugins: { "${registered.package}": false } }).`,
            )
          }
        }
      }

      for (const pkg of packages) {
        if (pkg.alias && fs.existsSync(path.join(root, "node_modules", pkg.alias, "package.json"))) {
          resolved.logger.warn(
            `[javaabu/manifest] "${pkg.alias}" is the import alias of the ${pkg.name} package and also an installed npm package. Imports of "${pkg.alias}" resolve to the Laravel package.`,
          )
        }
      }
    },

    options(rollupOptions) {
      if (config?.command !== "build" || config.build.ssr || options.input === false) {
        return null
      }

      return {
        ...rollupOptions,
        input: mergeInputs(rollupOptions.input as RollupInput, computeInputs(root, discovery, packages)),
      }
    },

    async resolveId(source, importer, resolveOptions) {
      if (source === INERTIA_MODULE_ID) {
        return inertiaModulePath()
      }

      if (source === PAGES_MODULE_ID) {
        return RESOLVED_PAGES_MODULE_ID
      }

      const aliased = matchAlias(packages, source)

      if (aliased) {
        const target = aliased.subpath
          ? path.join(aliased.pkg.jsDir as string, aliased.subpath)
          : (aliased.pkg.jsDir as string)
        const resolved = await this.resolve(target, importer, { ...resolveOptions, skipSelf: true })

        if (!resolved) {
          return null
        }

        return { ...resolved, id: applyOverride(packages, resolved.id) }
      }

      if (!importer) {
        return null
      }

      const owner = ownerOf(packages, importer)

      if (!owner) {
        return null
      }

      if (isRelativeImport(source)) {
        // An override resolves its relative imports as if it were the package's file,
        // so overriding one file doesn't mean copying its neighbours.
        const from = owner.override ? path.join(owner.pkg.realDir, owner.relative) : importer
        const resolved =
          (await this.resolve(source, from, { ...resolveOptions, skipSelf: true })) ??
          (owner.override ? await this.resolve(source, importer, { ...resolveOptions, skipSelf: true }) : null)

        if (!resolved) {
          return null
        }

        return { ...resolved, id: applyOverride(packages, resolved.id) }
      }

      if (isBareImport(source)) {
        // Bare imports in package files resolve from the app, so there's one copy of
        // React or Vue, even for packages symlinked from outside the app.
        return await this.resolve(source, path.join(root, "package.json"), {
          ...resolveOptions,
          skipSelf: true,
        })
      }

      return null
    },

    load(id) {
      if (id === RESOLVED_PAGES_MODULE_ID) {
        return pagesModule(root, packages)
      }

      return null
    },

    writeBundle(outputOptions) {
      if (!config?.build.manifest || !outputOptions.dir) {
        return
      }

      const name = typeof config.build.manifest === "string" ? config.build.manifest : ".vite/manifest.json"
      const file = path.join(outputOptions.dir, name)

      if (!fs.existsSync(file)) {
        return
      }

      const data = JSON.parse(fs.readFileSync(file, "utf8"))

      if (rewriteManifestKeys(root, data, packages)) {
        fs.writeFileSync(file, JSON.stringify(data, null, 2))
      }
    },
  }
}

export { manifest }
export type {
  Discovery,
  DiscoveredAsset,
  DiscoveredPackage,
  DiscoveredVitePlugin,
  PackageVitePlugin,
  ResolvedPackage,
} from "./discovery.js"
export type { LoadedPackagePlugin, PackagePluginSettings } from "./plugins.js"

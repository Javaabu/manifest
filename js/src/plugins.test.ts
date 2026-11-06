import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import type { Plugin } from "vite"
import { realpath, resolvePackages, type Discovery } from "./discovery.js"
import { flattenPlugins, lazy, loadPackagePlugin, selectPackagePlugins, unknownPluginSettings } from "./plugins.js"
import manifest from "./vite.js"

let root: string

function write(file: string, contents = "") {
  fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true })
  fs.writeFileSync(path.join(root, file), contents)
}

const factory = `export default (options = {}) => [{ name: "acme-" + (options.name ?? "plugin"), options }]\n`

function discovery(): Discovery {
  const pkg = (name: string, vitePlugins: Discovery["packages"][string]["vitePlugins"]) => ({
    path: `vendor/acme/${name}/resources`,
    alias: null,
    js: null,
    pages: null,
    dist: null,
    overrides: `resources/vendor/${name}`,
    dependencies: {},
    peerDependencies: {},
    vitePlugins,
  })

  return {
    version: 1,
    packages: {
      localization: pkg("localization", [{ module: "../dist/vite.mjs", options: { name: "lang", watch: ["lang"] } }]),
      widgets: pkg("widgets", []),
      // Written by an older manifest:discover, without vitePlugins
      old: { ...pkg("old", []), vitePlugins: undefined },
    },
    assets: [],
  }
}

beforeEach(() => {
  root = realpath(fs.mkdtempSync(path.join(os.tmpdir(), "manifest-plugins-")))
})

afterEach(() => {
  fs.rmSync(root, { recursive: true, force: true })
})

describe("selectPackagePlugins", () => {
  it("resolves each package's plugins from its resources folder", () => {
    const [plugin, ...rest] = selectPackagePlugins(resolvePackages(root, discovery()))

    expect(rest).toEqual([])
    expect(plugin).toEqual({
      package: "localization",
      module: "../dist/vite.mjs",
      file: path.join(root, "vendor/acme/localization/dist/vite.mjs"),
      options: { name: "lang", watch: ["lang"] },
    })
  })

  it("merges the app's options over the package's, or skips the package", () => {
    const packages = resolvePackages(root, discovery())

    expect(selectPackagePlugins(packages, { localization: { watch: [] } })[0]?.options).toEqual({
      name: "lang",
      watch: [],
    })
    expect(selectPackagePlugins(packages, { localization: true })[0]?.options).toEqual({
      name: "lang",
      watch: ["lang"],
    })
    expect(selectPackagePlugins(packages, { localization: false })).toEqual([])
  })

  it("lists settings that name no package with a plugin", () => {
    const packages = resolvePackages(root, discovery())

    expect(unknownPluginSettings(packages, { localization: false, widgets: false, missing: {} })).toEqual([
      "widgets",
      "missing",
    ])
  })
})

describe("loadPackagePlugin", () => {
  it("imports the module and calls its default export with the options", async () => {
    write("vendor/acme/localization/dist/vite.mjs", factory)

    const [registered] = selectPackagePlugins(resolvePackages(root, discovery()))
    const loaded = await loadPackagePlugin(registered!, root)

    expect(loaded.plugins).toEqual([{ name: "acme-lang", options: { name: "lang", watch: ["lang"] } }])
  })

  it("explains a missing module or one that doesn't export a factory", async () => {
    const [registered] = selectPackagePlugins(resolvePackages(root, discovery()))

    await expect(loadPackagePlugin(registered!, root)).rejects.toThrow(
      `Can't find the localization package's Vite plugin (vendor/acme/localization/dist/vite.mjs)`,
    )

    write("vendor/acme/localization/dist/vite.mjs", "export const plugin = {}\n")

    await expect(loadPackagePlugin(registered!, root)).rejects.toThrow("isn't a function that returns a Vite plugin")
  })
})

describe("flattenPlugins", () => {
  it("flattens nested arrays and promises and drops falsy values", async () => {
    const a = { name: "a" }
    const b = { name: "b" }

    expect(await flattenPlugins([a, null, false, Promise.resolve([b, [undefined]])])).toEqual([a, b])
  })
})

describe("lazy", () => {
  it("only starts once awaited, and only once", async () => {
    const start = vi.fn(async () => 42)
    const value = lazy(start)

    expect(start).not.toHaveBeenCalled()
    expect(await value).toBe(42)
    expect(await value.catch(() => 0)).toBe(42)
    expect(await value.finally(() => undefined)).toBe(42)
    expect(start).toHaveBeenCalledTimes(1)
  })
})

describe("manifest()", () => {
  it("returns its own plugin and the packages' plugins, for Vite to flatten", async () => {
    write("bootstrap/cache/manifest.json", JSON.stringify(discovery()))
    write("vendor/acme/localization/dist/vite.mjs", factory)

    const cwd = process.cwd()

    process.chdir(root)

    try {
      const plugins = manifest({ discover: false })

      expect(plugins.plugin.name).toBe("javaabu-manifest")
      expect(plugins[0]).toBe(plugins.plugin)
      expect(plugins.packages()).toEqual([])

      const flat = await flattenPlugins(plugins)

      expect(flat.map((plugin) => plugin.name)).toEqual(["javaabu-manifest", "acme-lang"])
      expect(plugins.packages().map((pkg) => pkg.name)).toEqual(["localization", "widgets", "old"])
      expect(plugins.inputs()).toEqual([])
    } finally {
      process.chdir(cwd)
    }
  })

  it("warns about plugins added twice and settings for unknown packages", async () => {
    write("bootstrap/cache/manifest.json", JSON.stringify(discovery()))
    write("vendor/acme/localization/dist/vite.mjs", factory)

    const cwd = process.cwd()

    process.chdir(root)

    try {
      const plugins = manifest({ discover: false, plugins: { missing: false } })
      const flat = await flattenPlugins(plugins)
      const warn = vi.fn()
      const configResolved = plugins.plugin.configResolved as (config: object) => Promise<void>

      // The app also added the package's plugin to vite.config.js
      await configResolved({ logger: { warn }, plugins: [...flat, { name: "acme-lang" } as Plugin] })

      expect(warn).toHaveBeenCalledWith(expect.stringContaining('The "plugins" option names "missing"'))
      expect(warn).toHaveBeenCalledWith(
        expect.stringContaining(`The localization package's Vite plugin "acme-lang" runs twice`),
      )
    } finally {
      process.chdir(cwd)
    }
  })
})

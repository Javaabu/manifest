import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { readDiscovery, realpath, resolvePackages, runDiscovery, type Discovery } from "./discovery.js"
import { pagesModule } from "./pages.js"
import manifest, { computeInputs, mergeInputs, rewriteManifestKeys } from "./vite.js"

let root: string

function pkg(name: string, extra: Partial<Discovery["packages"][string]> = {}): Discovery["packages"][string] {
  return {
    path: `vendor/acme/${name}/resources`,
    alias: `@javaabu/${name}`,
    js: "js",
    pages: null,
    dist: null,
    overrides: `resources/vendor/${name}`,
    dependencies: {},
    peerDependencies: {},
    ...extra,
  }
}

function write(file: string, contents = "") {
  fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true })
  fs.writeFileSync(path.join(root, file), contents)
}

const discovery: Discovery = {
  version: 1,
  packages: { filters: pkg("filters", { pages: "js/Pages" }) },
  assets: [
    { id: "filters::a", package: "filters", name: "a", type: "ts", entry: "js/a.ts", input: "", dependencies: [] },
    { id: "filters::b", package: "filters", name: "b", type: "ts", entry: "js/b.ts", input: "", dependencies: [] },
    { id: "filters::c", package: "filters", name: "c", type: "ts", entry: "js/a.ts", input: "", dependencies: [] },
    { id: "gone::x", package: "gone", name: "x", type: "js", entry: "x.js", input: "", dependencies: [] },
  ],
}

beforeEach(() => {
  root = realpath(fs.mkdtempSync(path.join(os.tmpdir(), "manifest-vite-")))
})

afterEach(() => {
  fs.rmSync(root, { recursive: true, force: true })
})

describe("computeInputs", () => {
  it("lists each entry once, with overrides applied", () => {
    write("resources/vendor/filters/js/b.ts")

    const packages = resolvePackages(root, discovery)

    expect(computeInputs(root, discovery, packages)).toEqual([
      "vendor/acme/filters/resources/js/a.ts",
      "resources/vendor/filters/js/b.ts",
    ])
  })
})

describe("mergeInputs", () => {
  it("merges into every shape Rollup accepts", () => {
    expect(mergeInputs(undefined, ["a.ts"])).toEqual(["a.ts"])
    expect(mergeInputs("app.ts", [])).toBe("app.ts")
    expect(mergeInputs("app.ts", ["app.ts", "a.ts"])).toEqual(["app.ts", "a.ts"])
    expect(mergeInputs(["app.ts"], ["a.ts", "app.ts"])).toEqual(["app.ts", "a.ts"])
    expect(mergeInputs({ app: "app.ts" }, ["app.ts", "a.ts"])).toEqual({ app: "app.ts", "a.ts": "a.ts" })
  })
})

describe("rewriteManifestKeys", () => {
  it("maps symlinked package paths back to vendor/", () => {
    const outside = realpath(fs.mkdtempSync(path.join(os.tmpdir(), "manifest-linked-")))

    fs.mkdirSync(path.join(root, "vendor/acme"), { recursive: true })
    fs.symlinkSync(outside, path.join(root, "vendor/acme/linked"))
    fs.mkdirSync(path.join(outside, "resources/js"), { recursive: true })

    const packages = resolvePackages(root, { version: 1, packages: { linked: pkg("linked") }, assets: [] })
    const real = path.relative(root, path.join(outside, "resources")).split(path.sep).join("/")

    const data = {
      [`${real}/js/app.ts`]: {
        file: "assets/app.js",
        src: `${real}/js/app.ts`,
        imports: [`${real}/js/shared.ts`],
      },
      [`${real}/js/shared.ts`]: { file: "assets/shared.js", dynamicImports: [`${real}/js/lazy.ts`] },
      "resources/js/app.ts": { file: "assets/main.js", src: "resources/js/app.ts" },
    }

    expect(rewriteManifestKeys(root, data, packages)).toBe(true)

    expect(Object.keys(data).sort()).toEqual([
      "resources/js/app.ts",
      "vendor/acme/linked/resources/js/app.ts",
      "vendor/acme/linked/resources/js/shared.ts",
    ])
    expect(data["vendor/acme/linked/resources/js/app.ts" as keyof typeof data]).toMatchObject({
      src: "vendor/acme/linked/resources/js/app.ts",
      imports: ["vendor/acme/linked/resources/js/shared.ts"],
    })
    expect(data["vendor/acme/linked/resources/js/shared.ts" as keyof typeof data]).toMatchObject({
      dynamicImports: ["vendor/acme/linked/resources/js/lazy.ts"],
    })

    fs.rmSync(outside, { recursive: true, force: true })
  })

  it("leaves manifests alone without symlinked packages", () => {
    const packages = resolvePackages(root, discovery)

    expect(rewriteManifestKeys(root, { "a.ts": { file: "a.js" } }, packages)).toBe(false)
  })
})

describe("pagesModule", () => {
  it("globs each package's pages and overrides from the app root", () => {
    const code = pagesModule(root, resolvePackages(root, discovery))

    expect(code).toContain('"filters": {')
    expect(code).toContain('base: "/vendor/acme/filters/resources/js/Pages/"')
    expect(code).toContain('overrideBase: "/resources/vendor/filters/js/Pages/"')
    expect(code).toContain(
      'import.meta.glob("/vendor/acme/filters/resources/js/Pages/**/*.{tsx,jsx,vue,ts,js,svelte}")',
    )
  })
})

describe("discovery", () => {
  it("explains a missing or foreign discovery file", () => {
    expect(() => readDiscovery(path.join(root, "missing.json"))).toThrow('Run "php artisan manifest:discover"')

    write("other.json", JSON.stringify({ version: 2 }))

    expect(() => readDiscovery(path.join(root, "other.json"))).toThrow("isn't a manifest discovery file")
  })

  it("reports a failed discovery command", () => {
    expect(runDiscovery(root, "true")).toBeNull()
    expect(runDiscovery(root, "exit 3")).toBeInstanceOf(Error)
  })

  it("falls back to the existing file when discovery fails, with a warning", () => {
    write("bootstrap/cache/manifest.json", JSON.stringify(discovery))

    const plugin = manifest({ discover: "exit 1" })
    const warn = vi.fn()

    ;(plugin.config as (config: object) => unknown)({ root })
    ;(plugin.configResolved as (config: object) => void)({ logger: { warn } })

    expect(warn).toHaveBeenCalledWith(expect.stringContaining('"exit 1" failed'))
    expect(plugin.packages().map((pkg) => pkg.name)).toEqual(["filters"])
    expect(plugin.inputs()).toEqual(["vendor/acme/filters/resources/js/a.ts", "vendor/acme/filters/resources/js/b.ts"])
  })

  it("fails when discovery fails and there's nothing to fall back to", () => {
    const plugin = manifest({ discover: "exit 1" })

    expect(() => (plugin.config as (config: object) => unknown)({ root })).toThrow("there's no")
  })

  it("only reads the file when discovery is off", () => {
    write("custom.json", JSON.stringify(discovery))

    const plugin = manifest({ discover: false, discovery: "custom.json" })

    ;(plugin.config as (config: object) => unknown)({ root })

    expect(plugin.packages()).toHaveLength(1)
  })

  it("lists inputs before Vite has run the plugin", () => {
    write("custom.json", JSON.stringify(discovery))

    const cwd = process.cwd()

    process.chdir(root)

    try {
      const plugin = manifest({ discover: false, discovery: "custom.json", input: false })

      expect(plugin.inputs()).toEqual([
        "vendor/acme/filters/resources/js/a.ts",
        "vendor/acme/filters/resources/js/b.ts",
      ])

      ;(plugin.config as (config: object) => unknown)({})

      expect(plugin.packages()).toHaveLength(1)
    } finally {
      process.chdir(cwd)
    }
  })

  it("warns when an alias shadows an installed npm package", () => {
    write("custom.json", JSON.stringify(discovery))
    write("node_modules/@javaabu/filters/package.json", "{}")

    const plugin = manifest({ discover: false, discovery: "custom.json" })
    const warn = vi.fn()

    ;(plugin.config as (config: object) => unknown)({ root })
    ;(plugin.configResolved as (config: object) => void)({ logger: { warn } })

    expect(warn).toHaveBeenCalledWith(
      expect.stringContaining('"@javaabu/filters" is the import alias of the filters package'),
    )
  })
})

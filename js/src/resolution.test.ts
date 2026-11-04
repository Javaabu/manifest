import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { afterAll, beforeAll, describe, expect, it } from "vitest"
import { resolvePackages, type Discovery, type ResolvedPackage } from "./discovery.js"
import { applyOverride, isBareImport, isRelativeImport, matchAlias, ownerOf, splitQuery } from "./resolution.js"

let root: string
let packages: ResolvedPackage[]

const discovery = (overrides: Partial<Discovery["packages"][string]> = {}): Discovery => ({
  version: 1,
  packages: {
    filters: {
      path: "vendor/acme/filters/resources",
      alias: "@javaabu/filters",
      js: "js",
      pages: "js/Pages",
      dist: null,
      overrides: "resources/vendor/filters",
      dependencies: {},
      peerDependencies: {},
      ...overrides,
    },
    "filters-pro": {
      path: "vendor/acme/filters-pro/resources",
      alias: "@javaabu/filters-pro",
      js: "js",
      pages: null,
      dist: null,
      overrides: "resources/vendor/filters-pro",
      dependencies: {},
      peerDependencies: {},
    },
    plain: {
      path: "vendor/acme/plain/resources",
      alias: null,
      js: null,
      pages: null,
      dist: null,
      overrides: "resources/vendor/plain",
      dependencies: {},
      peerDependencies: {},
    },
  },
  assets: [],
})

beforeAll(() => {
  root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), "manifest-resolution-")))

  fs.mkdirSync(path.join(root, "vendor/acme/filters/resources/js"), { recursive: true })
  fs.writeFileSync(path.join(root, "vendor/acme/filters/resources/js/a.ts"), "")
  fs.writeFileSync(path.join(root, "vendor/acme/filters/resources/js/b.ts"), "")
  fs.mkdirSync(path.join(root, "resources/vendor/filters/js"), { recursive: true })
  fs.writeFileSync(path.join(root, "resources/vendor/filters/js/b.ts"), "")

  packages = resolvePackages(root, discovery())
})

afterAll(() => fs.rmSync(root, { recursive: true, force: true }))

describe("ownerOf", () => {
  it("finds the package of a package file or an override", () => {
    expect(ownerOf(packages, path.join(root, "vendor/acme/filters/resources/js/a.ts?v=1"))).toMatchObject({
      pkg: { name: "filters" },
      relative: path.join("js", "a.ts"),
      override: false,
    })

    expect(ownerOf(packages, path.join(root, "resources/vendor/filters/js/b.ts"))).toMatchObject({
      pkg: { name: "filters" },
      override: true,
    })

    expect(ownerOf(packages, path.join(root, "resources/js/app.ts"))).toBeNull()
    expect(ownerOf(packages, path.join(root, "vendor/acme/filters/resources"))).toBeNull()
  })
})

describe("applyOverride", () => {
  it("uses the app's copy of a file when there is one", () => {
    const b = path.join(root, "vendor/acme/filters/resources/js/b.ts")
    const a = path.join(root, "vendor/acme/filters/resources/js/a.ts")

    expect(applyOverride(packages, `${b}?import`)).toBe(path.join(root, "resources/vendor/filters/js/b.ts") + "?import")
    expect(applyOverride(packages, a)).toBe(a)
    expect(applyOverride(packages, "/elsewhere/app.ts")).toBe("/elsewhere/app.ts")
  })
})

describe("matchAlias", () => {
  it("matches the alias and its subpaths, longest alias first", () => {
    expect(matchAlias(packages, "@javaabu/filters")).toMatchObject({ pkg: { name: "filters" }, subpath: "" })
    expect(matchAlias(packages, "@javaabu/filters/components/FilterBar")).toMatchObject({
      pkg: { name: "filters" },
      subpath: "components/FilterBar",
    })
    expect(matchAlias(packages, "@javaabu/filters-pro")).toMatchObject({ pkg: { name: "filters-pro" } })
    expect(matchAlias(packages, "@javaabu/filtersx")).toBeNull()
    expect(matchAlias(packages, "@javaabu/plain")).toBeNull()
  })
})

describe("import kinds", () => {
  it("tells bare imports from paths and virtual modules", () => {
    expect(isBareImport("react")).toBe(true)
    expect(isBareImport("@javaabu/onu/button")).toBe(true)
    expect(isBareImport("./a")).toBe(false)
    expect(isBareImport("/vendor/a.ts")).toBe(false)
    expect(isBareImport("\0virtual")).toBe(false)
    expect(isBareImport("virtual:pages")).toBe(false)
    expect(isBareImport("C:\\app\\a.ts")).toBe(false)

    expect(isRelativeImport("./a")).toBe(true)
    expect(isRelativeImport("..")).toBe(true)
    expect(isRelativeImport(".hidden")).toBe(false)
  })

  it("splits queries", () => {
    expect(splitQuery("/a.ts?raw")).toEqual(["/a.ts", "?raw"])
    expect(splitQuery("/a.ts")).toEqual(["/a.ts", ""])
  })
})

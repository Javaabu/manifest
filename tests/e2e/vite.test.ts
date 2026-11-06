import fs from "node:fs"
import path from "node:path"
import { afterAll, beforeAll, describe, expect, it } from "vitest"
import { build, createServer, type ViteDevServer } from "vite"
import { app, fixtureConfig } from "./fixture.js"

const configFile = path.join(app, "vite.config.js")
const overrides = path.join(app, "resources/vendor")

// laravel-vite-plugin resolves public/ from the working directory, as in an app.
process.chdir(app)

type ViteManifest = Record<
  string,
  { file: string; src?: string; isEntry?: boolean; isDynamicEntry?: boolean; css?: string[] }
>

function readManifest(dir: string): ViteManifest {
  return JSON.parse(fs.readFileSync(path.join(app, "public", dir, "manifest.json"), "utf8"))
}

function readBuilt(dir: string, manifest: ViteManifest): string {
  return Object.values(manifest)
    .map((chunk) => fs.readFileSync(path.join(app, "public", dir, chunk.file), "utf8"))
    .join("\n")
}

function writeOverride(file: string, contents: string) {
  fs.mkdirSync(path.dirname(path.join(overrides, file)), { recursive: true })
  fs.writeFileSync(path.join(overrides, file), contents)
}

const assetInputs = [
  "vendor/acme/widgets/resources/css/theme.scss",
  "vendor/acme/widgets/resources/js/core.js",
  "vendor/acme/widgets/resources/js/chart.ts",
  "vendor/acme/filters/resources/css/filter-bar.css",
  "vendor/acme/filters/resources/js/filter-bar.ts",
  "vendor/acme/filters/resources/js/date-range.tsx",
]

describe("vite build", () => {
  beforeAll(async () => {
    fs.rmSync(overrides, { recursive: true, force: true })

    await build(fixtureConfig())
  })

  it("builds the app's inputs and every registered asset", () => {
    const manifest = readManifest("e2e-build")

    expect(manifest["resources/js/app.tsx"]?.isEntry).toBe(true)

    for (const input of assetInputs) {
      expect(manifest[input], input).toMatchObject({ src: input, isEntry: true })
    }
  })

  it("compiles Sass and keeps logical CSS properties", () => {
    const manifest = readManifest("e2e-build")
    const theme = manifest["vendor/acme/widgets/resources/css/theme.scss"]

    const css = fs.readFileSync(path.join(app, "public/e2e-build", theme!.file), "utf8")

    expect(css).toContain("border-inline-start")
    expect(css).toContain("#0b7285")
  })

  it("resolves package aliases and package pages", () => {
    const manifest = readManifest("e2e-build")
    const code = readBuilt("e2e-build", manifest)

    expect(code).toContain("ފިލްޓަރު")
    expect(manifest["vendor/acme/filters/resources/js/Pages/Index.tsx"]?.isDynamicEntry).toBe(true)
    expect(manifest["vendor/acme/filters/resources/js/Pages/Reports/Show.tsx"]?.isDynamicEntry).toBe(true)
    expect(manifest["resources/js/pages/Dashboard.tsx"]?.isDynamicEntry).toBe(true)
    expect(code).toContain("/vendor/acme/filters/resources/js/Pages/Reports/Show.tsx")
  })

  it("bundles one copy of React", () => {
    const code = readBuilt("e2e-build", readManifest("e2e-build"))

    expect(
      code.match(/react\.production|__SECRET_INTERNALS|react\.transitional\.element/g)?.length ?? 0,
    ).toBeGreaterThan(0)
  })
})

describe("package Vite plugins", () => {
  const registered = { label: "ވިޖެޓު", theme: "light" }

  function readPluginFile(dir: string): unknown {
    const file = path.join(app, "public", dir, "acme-widgets.json")

    return fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, "utf8")) : null
  }

  afterAll(() => {
    for (const dir of ["e2e-build-plugin-options", "e2e-build-plugin-off"]) {
      fs.rmSync(path.join(app, "public", dir), { recursive: true, force: true })
    }
  })

  it("runs the plugins packages register, with their options", () => {
    // Built by the "vite build" suite above.
    expect(readPluginFile("e2e-build")).toEqual(registered)
  })

  it("merges the app's options over the package's", async () => {
    await build(
      fixtureConfig(
        { build: { outDir: path.join(app, "public/e2e-build-plugin-options") } },
        { plugins: { widgets: { theme: "dark" } } },
      ),
    )

    expect(readPluginFile("e2e-build-plugin-options")).toEqual({ ...registered, theme: "dark" })
  })

  it("doesn't run a package's plugins the app turns off", async () => {
    await build(
      fixtureConfig(
        { build: { outDir: path.join(app, "public/e2e-build-plugin-off") } },
        { plugins: { widgets: false } },
      ),
    )

    expect(readManifest("e2e-build-plugin-off")["resources/js/app.tsx"]?.isEntry).toBe(true)
    expect(readPluginFile("e2e-build-plugin-off")).toBeNull()
  })
})

describe("vite build with overrides", () => {
  beforeAll(async () => {
    fs.rmSync(overrides, { recursive: true, force: true })

    writeOverride("filters/js/components/label.ts", "export function label(): string {\n  return 'އޯވަރައިޑް'\n}\n")
    writeOverride(
      "filters/js/filter-bar.ts",
      "import { label } from './components/label'\n\nconsole.log('override entry', label())\n",
    )
    writeOverride(
      "filters/js/Pages/Index.tsx",
      "import { FilterBar } from '../components/FilterBar'\n\nexport default function Index() {\n  return <FilterBar>override page</FilterBar>\n}\n",
    )

    await build(fixtureConfig({ build: { outDir: path.join(app, "public/e2e-build-overrides") } }))
  })

  afterAll(() => {
    fs.rmSync(overrides, { recursive: true, force: true })
    fs.rmSync(path.join(app, "public/e2e-build-overrides"), { recursive: true, force: true })
  })

  it("builds the override of an entry instead of the package's", () => {
    const manifest = readManifest("e2e-build-overrides")

    expect(manifest["resources/vendor/filters/js/filter-bar.ts"]?.isEntry).toBe(true)
    expect(manifest["vendor/acme/filters/resources/js/filter-bar.ts"]).toBeUndefined()
  })

  it("uses an overridden file everywhere the package imports it", () => {
    const code = readBuilt("e2e-build-overrides", readManifest("e2e-build-overrides"))

    expect(code).toContain("އޯވަރައިޑް")
    expect(code).not.toContain("ފިލްޓަރު")
    expect(code).toContain("override entry")
  })

  it("resolves an overridden page and its relative imports from the package", () => {
    const manifest = readManifest("e2e-build-overrides")

    expect(manifest["resources/vendor/filters/js/Pages/Index.tsx"]?.isDynamicEntry).toBe(true)
    expect(readBuilt("e2e-build-overrides", manifest)).toContain("override page")
  })
})

describe("vite dev server", () => {
  let server: ViteDevServer
  let base: string

  beforeAll(async () => {
    fs.rmSync(overrides, { recursive: true, force: true })

    // laravel-vite-plugin skips its dev server hooks (the hot file) under Vitest.
    delete process.env.VITEST
    // ...and refuses to start a dev server in CI.
    process.env.LARAVEL_BYPASS_ENV_CHECK = "1"

    server = await createServer(fixtureConfig({ server: { port: 0, host: "127.0.0.1" } }))
    await server.listen()

    const address = server.httpServer?.address()

    base = typeof address === "object" && address ? `http://127.0.0.1:${address.port}` : ""
  })

  afterAll(async () => {
    await server?.close()
  })

  it("writes the hot file Laravel reads", () => {
    expect(fs.readFileSync(path.join(app, "public/e2e-hot"), "utf8")).toMatch(/^http:\/\//)
  })

  it("serves every registered asset at the URL Laravel renders", async () => {
    for (const input of assetInputs) {
      const response = await fetch(`${base}/${input}`)

      expect(response.status, input).toBe(200)
    }
  })

  it("rewrites package aliases to the package's files", async () => {
    const code = await (await fetch(`${base}/vendor/acme/filters/resources/js/filter-bar.ts`)).text()

    expect(code).toContain("/vendor/acme/widgets/resources/js/index.ts")
    expect(code).not.toContain('from "@javaabu/widgets"')
  })

  it("runs the packages' Vite plugins", async () => {
    const response = await fetch(`${base}/__acme-widgets`)

    expect(response.status).toBe(200)
    expect(await response.json()).toEqual({ label: "ވިޖެޓު", theme: "light" })
  })

  it("serves the Inertia helper and the pages module", async () => {
    const code = await (await fetch(`${base}/resources/js/app.tsx`)).text()

    expect(code).not.toContain('from "@javaabu/manifest/inertia"')

    const pages = await (await fetch(`${base}/@id/__x00__virtual:javaabu-manifest/pages`)).text()

    expect(pages).toContain("/vendor/acme/filters/resources/js/Pages/Index.tsx")
    expect(pages).toContain("/vendor/acme/filters/resources/js/Pages/Reports/Show.tsx")
  })
})

describe("the shipped dist", () => {
  const outDir = path.join(app, "public/e2e-build-dist")

  afterAll(() => fs.rmSync(outDir, { recursive: true, force: true }))

  it("builds the fixture app with its own vite.config.js", async () => {
    fs.rmSync(overrides, { recursive: true, force: true })

    await build({ root: app, configFile, logLevel: "warn", build: { outDir } })

    const manifest = JSON.parse(fs.readFileSync(path.join(outDir, "manifest.json"), "utf8"))

    for (const input of assetInputs) {
      expect(manifest[input], input).toBeDefined()
    }

    expect(JSON.parse(fs.readFileSync(path.join(outDir, "acme-widgets.json"), "utf8"))).toEqual({
      label: "ވިޖެޓު",
      theme: "light",
    })
  })
})

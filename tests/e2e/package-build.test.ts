import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { afterAll, describe, expect, it } from "vitest"
import { build } from "vite"
import { app } from "./fixture.js"

const outDir = fs.mkdtempSync(path.join(os.tmpdir(), "manifest-package-build-"))

afterAll(() => fs.rmSync(outDir, { recursive: true, force: true }))

describe("packageBuild", () => {
  it("builds a package's dist the way the PHP side reads it", async () => {
    // The package's own vite.config.js, with the shipped dist/package.js.
    await build({
      configFile: path.join(app, "vendor/acme/widgets/vite.config.js"),
      logLevel: "warn",
      build: { outDir },
    })

    const manifest = JSON.parse(fs.readFileSync(path.join(outDir, ".vite/manifest.json"), "utf8"))

    expect(
      Object.keys(manifest)
        .filter((key) => manifest[key].isEntry)
        .sort(),
    ).toEqual(["css/theme.scss", "js/chart.ts", "js/core.js"])

    for (const chunk of Object.values(manifest) as { file: string }[]) {
      expect(fs.existsSync(path.join(outDir, chunk.file)), chunk.file).toBe(true)
      expect(chunk.file).toMatch(/^assets\/.+-[\w-]{8}\.(js|css)$/)
    }

    const chart = fs.readFileSync(path.join(outDir, manifest["js/chart.ts"].file), "utf8")

    expect(chart).not.toMatch(/from\s*["']vue["']/)
  })
})

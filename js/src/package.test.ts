import path from "node:path"
import { describe, expect, it } from "vitest"
import { packageBuild } from "./package.js"

describe("packageBuild", () => {
  it("builds from resources into dist with a manifest", () => {
    const config = packageBuild({ root: "/pkg", input: ["js/a.ts", "css/a.css"] })

    expect(config.root).toBe(path.join("/pkg", "resources"))
    expect(config.build?.outDir).toBe(path.join("/pkg", "dist"))
    expect(config.build?.manifest).toBe(true)
    expect(config.build?.rollupOptions?.input).toEqual([
      path.join("/pkg", "resources", "js/a.ts"),
      path.join("/pkg", "resources", "css/a.css"),
    ])
    expect(config.build?.rollupOptions?.external).toEqual([])
  })

  it("takes other folders and externals", () => {
    const config = packageBuild({
      root: "/pkg",
      resources: "assets",
      outDir: "public/build",
      input: [],
      external: ["vue"],
    })

    expect(config.root).toBe(path.join("/pkg", "assets"))
    expect(config.build?.outDir).toBe(path.join("/pkg", "public/build"))
    expect(config.build?.rollupOptions?.external).toEqual(["vue"])
  })
})

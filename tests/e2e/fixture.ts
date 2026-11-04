import path from "node:path"
import { fileURLToPath } from "node:url"
import react from "@vitejs/plugin-react"
import vue from "@vitejs/plugin-vue"
import laravel from "laravel-vite-plugin"
import { mergeConfig, type InlineConfig } from "vite"
import manifest, { type ManifestPluginOptions } from "../../js/src/vite.js"

export const repo = fileURLToPath(new URL("../..", import.meta.url))

export const app = path.join(repo, "tests/Fixtures/app")

export const discoverCommand = `TESTBENCH_WORKING_PATH="${repo}" php "${path.join(repo, "vendor/bin/testbench")}" manifest:discover`

/**
 * The fixture app's vite.config.js, with the plugin loaded from source so the
 * e2e tests count towards coverage.
 */
export function fixtureConfig(overrides: InlineConfig = {}, options: ManifestPluginOptions = {}): InlineConfig {
  return mergeConfig(
    {
      root: app,
      configFile: false,
      logLevel: "warn",
      plugins: [
        laravel({
          input: ["resources/js/app.tsx"],
          buildDirectory: "e2e-build",
          hotFile: "public/e2e-hot",
          refresh: false,
        }),
        manifest({ discover: discoverCommand, ...options }),
        react(),
        vue(),
      ],
    },
    overrides,
  )
}

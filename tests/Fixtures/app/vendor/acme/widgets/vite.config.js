// How a package builds its prebuilt dist with javaabu/manifest's preset.
// In a real package: import { packageBuild } from "./vendor/javaabu/manifest/dist/package.js"
import vue from "@vitejs/plugin-vue"
import { defineConfig } from "vite"
import { packageBuild } from "../../../../../../dist/package.js"

export default defineConfig({
  ...packageBuild({
    root: import.meta.dirname,
    input: ["css/theme.scss", "js/core.js", "js/chart.ts"],
  }),
  plugins: [vue()],
})

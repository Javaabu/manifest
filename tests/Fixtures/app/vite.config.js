// The fixture app's Vite config, as an app would write it. The only
// difference: discovery runs through Testbench, since the fixture app
// has no artisan file of its own.
import path from "node:path"
import { fileURLToPath } from "node:url"
import react from "@vitejs/plugin-react"
import vue from "@vitejs/plugin-vue"
import laravel from "laravel-vite-plugin"
import { defineConfig } from "vite"
import manifest from "../../../dist/vite.js"

const repo = fileURLToPath(new URL("../../..", import.meta.url))

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/app.tsx"],
            buildDirectory: "e2e-build",
            hotFile: "public/e2e-hot",
            refresh: false,
        }),
        manifest({
            discover: `TESTBENCH_WORKING_PATH="${repo}" php "${path.join(repo, "vendor/bin/testbench")}" manifest:discover`,
        }),
        react(),
        vue(),
    ],
})

import { defineConfig } from "vitest/config"

export default defineConfig({
    test: {
        projects: [
            {
                test: {
                    name: "unit",
                    include: ["js/src/**/*.test.ts"],
                },
            },
            {
                // Real Vite builds and a dev server against tests/Fixtures/app. Needs PHP
                // and Composer: discovery runs through vendor/bin/testbench.
                test: {
                    name: "e2e",
                    include: ["tests/e2e/**/*.test.ts"],
                    testTimeout: 120_000,
                    hookTimeout: 120_000,
                    fileParallelism: false,
                    pool: "forks",
                },
            },
        ],
        coverage: {
            provider: "v8",
            include: ["js/src/**/*.ts"],
            exclude: ["js/src/**/*.test.ts", "js/src/**/*.d.ts"],
            reporter: ["text", "lcov", "json-summary"],
            thresholds: { lines: 90, statements: 90, functions: 90, branches: 85 },
        },
    },
})

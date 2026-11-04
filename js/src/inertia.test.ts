import { describe, expect, it, vi } from "vitest"

vi.mock("virtual:javaabu-manifest/pages", () => ({
    packages: {
        filters: {
            base: "/vendor/acme/filters/resources/js/Pages/",
            overrideBase: "/resources/vendor/filters/js/Pages/",
            pages: {
                "/vendor/acme/filters/resources/js/Pages/Index.tsx": async () => ({ default: "package Index" }),
                "/vendor/acme/filters/resources/js/Pages/Reports/Show.tsx": async () => ({ default: "package Show" }),
            },
            overrides: {
                "/resources/vendor/filters/js/Pages/Reports/Show.tsx": async () => ({ default: "override Show" }),
            },
        },
    },
}))

const { isPackagePage, packagePageNames, parsePageName, resolvePage } = await import("./inertia.js")

const appPages = {
    "./pages/Dashboard.tsx": async () => ({ default: "Dashboard" }),
    "./pages/admin/Dashboard.tsx": async () => ({ default: "admin Dashboard" }),
    "./pages/ތާނަ.tsx": async () => ({ default: "ތާނަ" }),
}

describe("resolvePage", () => {
    it("resolves a package page", async () => {
        await expect(resolvePage("filters::Index", appPages)).resolves.toEqual({ default: "package Index" })
    })

    it("prefers the app's override of a package page", async () => {
        await expect(resolvePage("filters::Reports/Show", appPages)).resolves.toEqual({ default: "override Show" })
    })

    it("resolves the app's own pages, shortest match first", async () => {
        await expect(resolvePage("Dashboard", appPages)).resolves.toEqual({ default: "Dashboard" })
        await expect(resolvePage("admin/Dashboard", appPages)).resolves.toEqual({ default: "admin Dashboard" })
        await expect(resolvePage("ތާނަ", appPages)).resolves.toEqual({ default: "ތާނަ" })
    })

    it("uses an explicit base", async () => {
        await expect(resolvePage("Dashboard", appPages, { base: "./pages/admin/" })).resolves.toEqual({
            default: "admin Dashboard",
        })
    })

    it("accepts eager globs", async () => {
        await expect(resolvePage("Home", { "./Pages/Home.vue": { default: "Home" } })).resolves.toEqual({
            default: "Home",
        })
    })

    it("hands app pages to a resolver function", async () => {
        const resolver = vi.fn(async (name: string) => ({ default: name }))

        await expect(resolvePage("Settings", resolver)).resolves.toEqual({ default: "Settings" })
        await expect(resolvePage("filters::Index", resolver)).resolves.toEqual({ default: "package Index" })
        expect(resolver).toHaveBeenCalledTimes(1)
    })

    it("throws for missing pages and packages", async () => {
        await expect(resolvePage("Missing", appPages)).rejects.toThrow("Page not found: Missing")
        await expect(resolvePage("Missing")).rejects.toThrow("Page not found: Missing")
        await expect(resolvePage("filters::Missing")).rejects.toThrow("Page not found: filters::Missing. Looked in")
        await expect(resolvePage("forms::Index")).rejects.toThrow('No package named "forms"')
    })
})

describe("page names", () => {
    it("knows package pages", () => {
        expect(isPackagePage("filters::Index")).toBe(true)
        expect(isPackagePage("Dashboard")).toBe(false)
        expect(parsePageName("filters::Reports/Show")).toEqual({ package: "filters", page: "Reports/Show" })
        expect(parsePageName("::Index")).toBeNull()
        expect(parsePageName("Index")).toBeNull()
    })

    it("lists every package page", () => {
        expect(packagePageNames()).toEqual(["filters::Index", "filters::Reports/Show"])
    })
})

declare module "virtual:javaabu-manifest/pages" {
    export interface PackagePages {
        base: string
        overrideBase: string
        pages: Record<string, () => Promise<unknown>>
        overrides: Record<string, () => Promise<unknown>>
    }

    export const packages: Record<string, PackagePages>
}

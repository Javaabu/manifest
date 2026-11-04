import {
    packagePage,
    packagePageNames,
    resolvePage,
    type PackagePageModule,
    type PackagePageName,
} from "@javaabu/manifest/inertia"
import { FilterBar, label } from "@javaabu/filters"
import { widget } from "@javaabu/widgets"

export function resolve(name: string) {
    return resolvePage(name, import.meta.glob("./pages/**/*.tsx"))
}

// The page types from `php artisan manifest:types` (resources/js/types/manifest.d.ts).
export const index: PackagePageName = packagePage("filters::Reports/Show")

// @ts-expect-error: not a page any package ships
packagePage("filters::Missing")

export type IndexProps = Parameters<PackagePageModule<"filters::Index">["default"]>[0]

export const props: IndexProps = { title: "ފިލްޓަރުތައް" }

Object.assign(window, { manifestFixture: { FilterBar, label, widget, resolve, pages: packagePageNames() } })

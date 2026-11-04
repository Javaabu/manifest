import { packagePageNames, resolvePage } from "@javaabu/manifest/inertia"
import { FilterBar, label } from "@javaabu/filters"
import { widget } from "@javaabu/widgets"

export function resolve(name: string) {
    return resolvePage(name, import.meta.glob("./pages/**/*.tsx"))
}

Object.assign(window, { manifestFixture: { FilterBar, label, widget, resolve, pages: packagePageNames() } })

import { label } from './label'

export function FilterBar({ children }: { children?: React.ReactNode }) {
    return (
        <div className="filter-bar">
            <span className="filter-bar__label">{label()}</span>
            {children}
        </div>
    )
}

import { FilterBar } from '../components/FilterBar'

export default function Index({ title }: { title: string }) {
    return (
        <main>
            <h1>{title}</h1>
            <FilterBar />
        </main>
    )
}

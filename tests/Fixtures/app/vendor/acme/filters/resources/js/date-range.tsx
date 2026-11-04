import { createRoot } from 'react-dom/client'
import { FilterBar } from './components/FilterBar'

document.querySelectorAll<HTMLElement>('[data-date-range]').forEach((element) => {
    createRoot(element).render(<FilterBar />)
})

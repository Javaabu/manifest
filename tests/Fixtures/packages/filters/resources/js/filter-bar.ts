import { widget } from '@javaabu/widgets'
import { label } from './components/label'

document.querySelectorAll<HTMLElement>('[data-filter-bar]').forEach((element) => {
    widget(element)
    element.dataset.label = label()
})

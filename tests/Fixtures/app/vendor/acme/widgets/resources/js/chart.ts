import { createApp, h } from 'vue'
import Chart from './Chart.vue'

document.querySelectorAll<HTMLElement>('[data-widget-chart]').forEach((element) => {
    createApp({ render: () => h(Chart, { label: element.dataset.label ?? '' }) }).mount(element)
})

export function widget(element) {
    element.classList.add('widget')

    return element
}

document.querySelectorAll('[data-widget]').forEach(widget)

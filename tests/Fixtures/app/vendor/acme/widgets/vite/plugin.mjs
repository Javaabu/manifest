// The widgets package's Vite plugin, which manifest() runs (registered with
// ->vitePlugin() in WidgetsServiceProvider). Like js-localization's, it's a
// factory whose options come from the service provider, merged with the app's.
const BUILD_FILE = "acme-widgets.json"
const DEV_URL = "/__acme-widgets"

export default function widgets(options = {}) {
  const body = JSON.stringify(options)

  return {
    name: "acme-widgets",

    configureServer(server) {
      server.middlewares.use(DEV_URL, (request, response) => {
        response.setHeader("Content-Type", "application/json")
        response.end(body)
      })
    },

    generateBundle() {
      this.emitFile({ type: "asset", fileName: BUILD_FILE, source: body })
    },
  }
}

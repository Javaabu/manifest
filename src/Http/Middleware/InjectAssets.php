<?php

namespace Javaabu\Manifest\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Javaabu\Manifest\AssetRequirements;
use Javaabu\Manifest\Rendering\Injector;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Pushed onto the global middleware stack by the service provider, so
 * rendering errors (like a missing Vite manifest) show as normal error pages.
 */
class InjectAssets
{
    public function __construct(
        protected Injector $injector,
        protected AssetRequirements $requirements,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        try {
            if ($this->shouldInject($request, $response)) {
                $response->setContent($this->injector->inject(
                    $response->getContent(),
                    auto: (bool) config('manifest.inject'),
                ));

                $response->headers->remove('Content-Length');
            }
        } finally {
            $this->requirements->flush();
        }

        return $response;
    }

    protected function shouldInject(Request $request, SymfonyResponse $response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        if ($response->headers->has('X-Inertia') || $request->headers->has('X-Inertia')) {
            return false;
        }

        $content_type = $response->headers->get('Content-Type');

        if ($content_type && ! str_contains(strtolower($content_type), 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return false;
        }

        return $this->injector->hasWork($content);
    }
}

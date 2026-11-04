<?php

namespace Javaabu\Manifest\Http\Controllers;

use Javaabu\Manifest\Manifest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Serves a package's prebuilt files when they haven't been published to
 * public/. Published files have the same URL, so the web server serves them
 * without reaching Laravel.
 */
class DistAssetsController
{
    /** @var array<string, string> */
    protected const array CONTENT_TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
    ];

    public function __invoke(Manifest $manifest, string $package, string $path): BinaryFileResponse
    {
        $dist = $manifest->distPath($package);

        if (! $dist) {
            throw new NotFoundHttpException;
        }

        $file = realpath("{$dist}/{$path}");

        if (! $file || ! is_file($file) || ! str_starts_with($file, $dist . DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $content_type = static::CONTENT_TYPES[$extension]
            ?? MimeTypes::getDefault()->getMimeTypes($extension)[0]
            ?? 'application/octet-stream';

        return response()->file($file, [
            'Content-Type' => $content_type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}

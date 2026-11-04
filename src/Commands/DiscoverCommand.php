<?php

namespace Javaabu\Manifest\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Javaabu\Manifest\Manifest;

class DiscoverCommand extends Command
{
    protected $signature = 'manifest:discover';

    protected $description = 'Write the registered packages and assets to the JSON file the Vite plugin reads';

    public function handle(Manifest $manifest, Filesystem $files): int
    {
        $path = static::path();

        $files->ensureDirectoryExists(dirname($path));

        $files->put($path, json_encode(
            $manifest->toDiscoveryArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n");

        $count = count($manifest->packages());

        $this->components->info(trans_choice('manifest::messages.discover.written', $count, [
            'count' => $count,
            'path' => $manifest->relativeToBase($path),
        ]));

        return self::SUCCESS;
    }

    public static function path(): string
    {
        return config('manifest.discovery_path') ?: app()->bootstrapPath('cache/manifest.json');
    }
}

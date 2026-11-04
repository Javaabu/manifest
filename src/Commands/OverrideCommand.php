<?php

namespace Javaabu\Manifest\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Javaabu\Manifest\Exceptions\UnknownAssetException;
use Javaabu\Manifest\Manifest;

class OverrideCommand extends Command
{
    protected $signature = 'manifest:override
                            {target : An asset (filters::filter-bar), or a package name with a path}
                            {path? : A file in the package\'s resources folder, like js/components/FilterBar.tsx}
                            {--force : Overwrite an existing override}';

    protected $description = 'Copy one file of a package into the app, where it overrides the package\'s copy';

    public function handle(Manifest $manifest, Filesystem $files): int
    {
        $target = (string) $this->argument('target');

        try {
            [$package, $relative] = str_contains($target, '::')
                ? [$manifest->find($target)->package, $manifest->find($target)->entry]
                : [$manifest->package($target)->name(), (string) $this->argument('path')];
        } catch (UnknownAssetException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($relative === '') {
            $this->components->error(__('manifest::messages.override.path_required'));

            return self::FAILURE;
        }

        $source = $manifest->package($package)->path($relative);
        $destination = $manifest->overridePath($package, $relative);

        if (! is_file($source)) {
            $this->components->error(__('manifest::messages.override.missing', ['path' => $manifest->relativeToBase($source)]));

            return self::FAILURE;
        }

        if (is_file($destination) && ! $this->option('force')) {
            $this->components->warn(__('manifest::messages.override.exists', ['path' => $manifest->relativeToBase($destination)]));

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($destination));
        $files->copy($source, $destination);

        $this->components->info(__('manifest::messages.override.copied', ['path' => $manifest->relativeToBase($destination)]));

        return self::SUCCESS;
    }
}

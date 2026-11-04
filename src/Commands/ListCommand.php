<?php

namespace Javaabu\Manifest\Commands;

use Illuminate\Console\Command;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest;

class ListCommand extends Command
{
    protected $signature = 'manifest:list
                            {package? : Only list this package\'s assets and their dependencies}
                            {--json : Output JSON}';

    protected $description = 'List the assets packages have registered, in dependency order';

    public function handle(Manifest $manifest): int
    {
        $package = $this->argument('package');

        $assets = $package
            ? $manifest->resolve([$package])
            : $manifest->all();

        if ($this->option('json')) {
            $this->line(json_encode(
                array_map(fn (Asset $asset) => $this->row($manifest, $asset), $assets),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        if (! $assets) {
            $this->components->info(__('manifest::messages.list.empty'));

            return self::SUCCESS;
        }

        $this->table(
            [
                __('manifest::messages.list.asset'),
                __('manifest::messages.list.type'),
                __('manifest::messages.list.entry'),
                __('manifest::messages.list.depends_on'),
            ],
            array_map(function (Asset $asset) use ($manifest) {
                $row = $this->row($manifest, $asset);

                $entry = $row['overridden']
                    ? $row['path'] . ' (' . __('manifest::messages.list.override') . ')'
                    : $row['path'];

                return [
                    $row['id'],
                    $row['type'],
                    $entry,
                    implode(', ', $row['dependencies']),
                ];
            }, $assets),
        );

        $this->components->info(trans_choice('manifest::messages.list.summary', count($assets), ['count' => count($assets)]));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Manifest $manifest, Asset $asset): array
    {
        return [
            ...$asset->toArray(),
            'path' => $manifest->relativeToBase($manifest->entryPath($asset)),
            'overridden' => $manifest->isOverridden($asset),
        ];
    }
}

<?php

namespace Javaabu\Manifest\Commands;

use Illuminate\Console\Command;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Support\NpmVersion;

class CheckCommand extends Command
{
    protected $signature = 'manifest:check
                            {--json : Output JSON}';

    protected $description = 'Check that the app has installed the npm packages its Laravel packages need';

    public function handle(Manifest $manifest): int
    {
        $declared = $this->declaredDependencies();
        $rows = [];

        foreach ($this->requirements($manifest) as $name => $requirement) {
            $installed = $this->installedVersion($name);

            $status = match (true) {
                ! isset($declared[$name]) => 'missing',
                $installed === null => 'not_installed',
                default => $this->satisfiesAll($installed, $requirement['constraints']),
            };

            $rows[] = [
                'name' => $name,
                'constraints' => $requirement['constraints'],
                'needed_by' => array_values(array_unique($requirement['needed_by'])),
                'declared' => $declared[$name] ?? null,
                'installed' => $installed,
                'status' => $status,
            ];
        }

        $problems = array_filter($rows, fn (array $row) => in_array($row['status'], ['missing', 'not_installed', 'mismatch'], true));

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $problems ? self::FAILURE : self::SUCCESS;
        }

        if (! $rows) {
            $this->components->info(__('manifest::messages.check.nothing'));

            return self::SUCCESS;
        }

        $this->table(
            [
                __('manifest::messages.check.package'),
                __('manifest::messages.check.needs'),
                __('manifest::messages.check.needed_by'),
                __('manifest::messages.check.installed'),
                __('manifest::messages.check.status'),
            ],
            array_map(fn (array $row) => [
                $row['name'],
                implode(' ', array_unique($row['constraints'])),
                implode(', ', $row['needed_by']),
                $row['installed'] ?? '-',
                __("manifest::messages.check.statuses.{$row['status']}"),
            ], $rows),
        );

        if (! $problems) {
            $this->components->info(__('manifest::messages.check.ok'));

            return self::SUCCESS;
        }

        $this->components->error(trans_choice('manifest::messages.check.problems', count($problems), ['count' => count($problems)]));

        $this->line('  ' . $this->installCommand($problems));

        return self::FAILURE;
    }

    /**
     * @return array<string, array{constraints: array<int, string>, needed_by: array<int, string>}>
     */
    protected function requirements(Manifest $manifest): array
    {
        $requirements = [];

        $add = function (string $name, string $constraint, string $needed_by) use (&$requirements) {
            $requirements[$name] ??= ['constraints' => [], 'needed_by' => []];

            if ($constraint !== '*') {
                $requirements[$name]['constraints'][] = $constraint;
            }

            $requirements[$name]['needed_by'][] = $needed_by;
        };

        foreach ($manifest->packages() as $package_name => $package) {
            $dependencies = [...$package->npmDependencies(), ...$package->npmPeerDependencies()];

            foreach ($dependencies as $name => $constraint) {
                $add($name, $constraint, $package_name);
            }

            foreach ($package->assets() as $asset) {
                foreach ($asset->type->buildRequirements() as $name) {
                    $add($name, '*', $package_name);
                }
            }

            if ($package->assets() || $package->pagesPath()) {
                $add('vite', '*', $package_name);
                $add('laravel-vite-plugin', '*', $package_name);
            }
        }

        ksort($requirements);

        return $requirements;
    }

    /**
     * @param  array<int, string>  $constraints
     */
    protected function satisfiesAll(string $installed, array $constraints): string
    {
        $status = 'ok';

        foreach ($constraints as $constraint) {
            $result = NpmVersion::satisfies($installed, $constraint);

            if ($result === false) {
                return 'mismatch';
            }

            if ($result === null) {
                $status = 'unchecked';
            }
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function declaredDependencies(): array
    {
        $package_json = $this->readJson(base_path('package.json'));

        return [
            ...($package_json['dependencies'] ?? []),
            ...($package_json['devDependencies'] ?? []),
            ...($package_json['optionalDependencies'] ?? []),
        ];
    }

    protected function installedVersion(string $name): ?string
    {
        $package_json = $this->readJson(base_path("node_modules/{$name}/package.json"));

        return $package_json['version'] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $problems
     */
    protected function installCommand(array $problems): string
    {
        $packages = array_map(function (array $row) {
            $constraints = array_unique($row['constraints']);

            if (count($constraints) !== 1) {
                return $row['name'];
            }

            $constraint = $constraints[0];

            return preg_match('/^(github:|git[+:]|https?:|file:|link:)/', $constraint)
                ? $constraint
                : "{$row['name']}@\"{$constraint}\"";
        }, array_values($problems));

        $packages = implode(' ', $packages);

        return match (true) {
            is_file(base_path('pnpm-lock.yaml')) => "pnpm add -D {$packages}",
            is_file(base_path('yarn.lock')) => "yarn add -D {$packages}",
            is_file(base_path('bun.lock')), is_file(base_path('bun.lockb')) => "bun add -d {$packages}",
            default => "npm install -D {$packages}",
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}

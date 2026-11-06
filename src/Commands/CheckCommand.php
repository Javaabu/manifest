<?php

namespace Javaabu\Manifest\Commands;

use Illuminate\Console\Command;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Support\NpmVersion;

class CheckCommand extends Command
{
    protected $signature = 'manifest:check
                            {--scope=* : What to check: assets, pages, or both (default: assets, and pages if the app uses Inertia)}
                            {--json : Output JSON}';

    protected $description = 'Check that the app has installed the npm packages its Laravel packages need';

    public function handle(Manifest $manifest): int
    {
        $declared = $this->declaredDependencies();
        $scopes = $this->scopes($declared);

        if ($scopes === null) {
            return self::FAILURE;
        }

        $rows = [];

        foreach ($this->requirements($manifest, $scopes) as $name => $requirement) {
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
                'scopes' => array_values(array_unique($requirement['scopes'])),
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

        if ($this->skipsPages($manifest, $scopes)) {
            $this->components->info(__('manifest::messages.check.pages_skipped'));
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
     * The scopes to check: the --scope option, or the assets, and the pages
     * when the app installs an Inertia adapter. Null when an option is invalid.
     *
     * @param  array<string, string>  $declared
     * @return array<int, NpmScopes>|null
     */
    protected function scopes(array $declared): ?array
    {
        $options = (array) $this->option('scope');

        if (! $options) {
            $uses_inertia = (bool) array_filter(array_keys($declared), fn (string $name) => str_starts_with($name, '@inertiajs/'));

            return $uses_inertia ? [NpmScopes::ASSETS, NpmScopes::PAGES] : [NpmScopes::ASSETS];
        }

        $scopes = [];

        foreach ($options as $option) {
            $scope = NpmScopes::tryFrom($option);

            if (! $scope) {
                $this->components->error(__('manifest::messages.check.invalid_scope', [
                    'scope' => $option,
                    'scopes' => implode(', ', NpmScopes::values()),
                ]));

                return null;
            }

            $scopes[$scope->value] = $scope;
        }

        return array_values($scopes);
    }

    /**
     * Whether pages aren't checked, though a package has some.
     *
     * @param  array<int, NpmScopes>  $scopes
     */
    protected function skipsPages(Manifest $manifest, array $scopes): bool
    {
        if (in_array(NpmScopes::PAGES, $scopes, true)) {
            return false;
        }

        foreach ($manifest->packages() as $package) {
            if ($package->npmDependencies(NpmScopes::PAGES) || $package->npmPeerDependencies(NpmScopes::PAGES)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, NpmScopes>  $scopes
     * @return array<string, array{constraints: array<int, string>, needed_by: array<int, string>, scopes: array<int, string>}>
     */
    protected function requirements(Manifest $manifest, array $scopes): array
    {
        $requirements = [];

        $add = function (string $name, string $constraint, string $needed_by, NpmScopes $scope) use (&$requirements) {
            $requirements[$name] ??= ['constraints' => [], 'needed_by' => [], 'scopes' => []];

            if ($constraint !== '*') {
                $requirements[$name]['constraints'][] = $constraint;
            }

            $requirements[$name]['needed_by'][] = $needed_by;
            $requirements[$name]['scopes'][] = $scope->value;
        };

        foreach ($manifest->packages() as $package_name => $package) {
            foreach ($scopes as $scope) {
                $dependencies = [...$package->npmDependencies($scope), ...$package->npmPeerDependencies($scope)];

                foreach ($dependencies as $name => $constraint) {
                    $add($name, $constraint, $package_name, $scope);
                }
            }

            $builds_assets = in_array(NpmScopes::ASSETS, $scopes, true) && $package->assets();
            $builds_pages = in_array(NpmScopes::PAGES, $scopes, true) && ($package->pagesPath() || $package->jsPath());

            if ($builds_assets) {
                foreach ($package->assets() as $asset) {
                    foreach ($asset->type->buildRequirements() as $name) {
                        $add($name, '*', $package_name, NpmScopes::ASSETS);
                    }
                }
            }

            if ($builds_assets || $builds_pages) {
                $scope = $builds_assets ? NpmScopes::ASSETS : NpmScopes::PAGES;

                $add('vite', '*', $package_name, $scope);
                $add('laravel-vite-plugin', '*', $package_name, $scope);
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

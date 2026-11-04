<?php

return [
    'list' => [
        'asset' => 'Asset',
        'type' => 'Type',
        'entry' => 'Entry',
        'depends_on' => 'Depends on',
        'override' => 'override',
        'empty' => 'No packages have registered assets.',
        'summary' => '{1} :count asset, in dependency order.|[2,*] :count assets, in dependency order.',
    ],
    'discover' => [
        'written' => '{0} Wrote :path (no packages have registered assets).|{1} Wrote :count package to :path.|[2,*] Wrote :count packages to :path.',
    ],
    'override' => [
        'path_required' => 'Give the path of the file to override, relative to the package\'s resources folder.',
        'missing' => 'There is no :path to override.',
        'exists' => ':path already exists. Use --force to overwrite it.',
        'copied' => 'Copied to :path. Edit it there; it is used instead of the package\'s copy.',
    ],
    'types' => [
        'written' => 'Wrote the package page types to :path.',
        'tsconfig_updated' => 'Added the package import paths to tsconfig.json.',
        'no_tsconfig' => 'There is no tsconfig.json. If you use TypeScript, add these paths to it:',
        'cannot_update' => 'tsconfig.json has comments or isn\'t plain JSON, so it was left alone. Add these paths to it:',
    ],
    'check' => [
        'package' => 'npm package',
        'needs' => 'Needs',
        'needed_by' => 'Needed by',
        'installed' => 'Installed',
        'status' => 'Status',
        'nothing' => 'No package needs anything from npm.',
        'ok' => 'Everything the packages need is installed.',
        'problems' => '{1} :count npm package is missing or the wrong version. Install it with:|[2,*] :count npm packages are missing or the wrong version. Install them with:',
        'statuses' => [
            'ok' => 'OK',
            'unchecked' => 'Installed (version not checked)',
            'missing' => 'Missing from package.json',
            'not_installed' => 'Not installed (run npm install)',
            'mismatch' => 'Wrong version',
        ],
    ],
];

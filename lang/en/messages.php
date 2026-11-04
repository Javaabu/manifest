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
];

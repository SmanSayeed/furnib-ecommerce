<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('has no destructive shell or filesystem-wipe capability in app code', function () {
    $forbidden = [
        'shell_exec',
        'proc_open',
        'passthru(',
        'pcntl_exec',
        'popen(',
        'deleteDirectory(',
    ];

    $hits = [];

    $files = Finder::create()->files()->in(dirname(__DIR__, 2).'/app')->name('*.php');

    foreach ($files as $file) {
        $contents = $file->getContents();

        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $hits[] = $file->getFilename().' contains '.$needle;
            }
        }
    }

    expect($hits)->toBe([]);
});

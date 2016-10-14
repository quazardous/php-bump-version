<?php
/**
 * Bump version and other git helpers.
 * (c) Quazaradous <berliozdavid@gmail.com>
 * 
 * Provides commands to help use GIT in the way describes at:
 * http://nvie.com/posts/a-successful-git-branching-model/
 * 
 */
foreach ([
    __DIR__ . '/../../../../vendor/autoload.php', // general case
    __DIR__ . '/../vendor/autoload.php', // for this project
    ] as $file)
{
    if (is_file($file)) {
        $autoload = $file;
        break;
    }
}
if (!$autoload) {
    throw new \RuntimeException('Something is wrong...');
}
require_once $autoload;

use Quazardous\BumpVersion;

$bv = new BumpVersion();

set_exception_handler(function ($e) use ($bv) {
    $bv::error($e->getMessage(), $e->getCode());
});

$bv->run();


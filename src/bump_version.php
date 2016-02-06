<?php
/**
 * Bump version and other git helpers.
 * (c) Quazaradous <berliozdavid@gmail.com>
 * 
 * Provides commands to help use GIT in the way describes at:
 * http://nvie.com/posts/a-successful-git-branching-model/
 * 
 */
require_once 'vendor/autoload.php';

use Quazardous\BumpVersion;

$bv = new BumpVersion();

set_exception_handler(function ($e) use ($bv) {
    $bv::error($e->getMessage(), $e->getCode());
});

$bv->run();


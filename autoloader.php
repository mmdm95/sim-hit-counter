<?php

/**
 * Thanks to Aura libraries
 */
spl_autoload_register(function ($class) {
    // the package namespace
    $namespace = 'Sim\HitCounter';

    $prefixes = [
        "{$namespace}\\Config\\" => [
            __DIR__ . '/src/_Config',
        ],
        "{$namespace}\\" => [
            __DIR__ . '/src',
        ],
        "Jaybizzle\\CrawlerDetect\\" => [
            __DIR__ . '/vendor/jaybizzle/crawler-detect/src',
        ],
        "Jenssegers\\Agent\\" => [
            __DIR__ . '/vendor/jenssegers/agent/src',
        ],
        "Detection\\" => [
            __DIR__ . '/vendor/mobiledetect/mobiledetectlib/namespaced/Detection',
        ],
    ];

    foreach ($prefixes as $prefix => $dirs) {
        // does the requested class match the namespace prefix
        $prefix_len = strlen($prefix);
        if (substr($class, 0, $prefix_len) !== $prefix) {
            continue;
        }

        // strip the prefix off the class
        $class = substr($class, $prefix_len);

        // a partial filename
        $part = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $class) . '.php';

        // go through the directories to find classes
        foreach ($dirs as $dir) {
            $dir = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $dir);
            $file = $dir . DIRECTORY_SEPARATOR . $part;
            if (is_readable($file)) {
                require $file . '';
                return;
            }
        }
    }
});

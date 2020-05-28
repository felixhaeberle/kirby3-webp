<?php

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/src/webp.php';

Kirby::plugin('felixhaeberle/kirby3-webp', [
    'hooks' => [
        'file.create:after' => function ($file) {
            if ($this->option('kirby3-webp', false)) {
                (new WebP\Convert)->generateWebP($file);
            }
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            if ($this->option('kirby3-webp', false)) {
                (new WebP\Convert)->generateWebP($newFile);
            }
        },
    ],
]);

<?php

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/src/webp.php';

function shouldGenerateWebP($file) {
    return $file->kirby()->option('kirby3-webp', false);
}

function generateWebP($file) {
    (new WebP\Convert)->generateWebP($file);
}

function deleteWebPFiles($file) {
    $webpFile = dirname($file->root()) . '/' . $file->name() . '.webp';
    $webpTxtFile = dirname($file->root()) . '/' . $file->name() . '.webp.txt';
    deleteIfExist($webpFile);
    deleteIfExist($webpTxtFile);
}

function deleteIfExist($file) {
    if (F::exists($file)) {
        F::remove($file);
    }
}

Kirby::plugin('felixhaeberle/kirby3-webp', [
    'hooks' => [
        'file.create:after' => function ($file) {
            if (shouldGenerateWebP($file)) {
                generateWebP($file);
            }
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            if (shouldGenerateWebP($newFile)) {
                generateWebP($newFile);
            }
        },
        'file.delete:after' => function ($file) {
            deleteWebPFiles($file);
        },
    ],
]);

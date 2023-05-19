<?php

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/src/webp.php';

Kirby::plugin('felixhaeberle/kirby3-webp', [
    'hooks' => [
        'file.create:after' => function ($file) {
            if ($this->option('kirby3-webp', false) && $file->extension() !== 'svg') {
                (new WebP\Convert)->generateWebP($file);
            }
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            if ($this->option('kirby3-webp', false) && $newFile->extension() !== 'svg') {
                (new WebP\Convert)->generateWebP($newFile);
            }
        },
        'file.delete:after' => function ($file) {
            $webpFile = dirname($file->root()) . '/' . $file->name() . '.webp';
            $webpTxtFile = dirname($file->root()) . '/' . $file->name() . '.webp.txt';
            if (F::exists($webpFile)) {
                F::remove($webpFile);
            }
            if (F::exists($webpTxtFile)) {
                F::remove($webpTxtFile);
            }
        },
    ],
]);

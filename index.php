<?php

use FelixHaeberle\Webp\Hooks;
use FelixHaeberle\Webp\Picture;
use FelixHaeberle\Webp\Webp;
use Kirby\Cms\App as Kirby;

// self-contained autoloading — works for composer, submodule and manual installs
spl_autoload_register(function (string $class): void {
	if (str_starts_with($class, 'FelixHaeberle\\Webp\\') === true) {
		$file = __DIR__ . '/src/' . basename(str_replace('\\', '/', $class)) . '.php';

		if (is_file($file) === true) {
			require $file;
		}
	}
});

// v1 compatibility: WebP\Convert keeps working
class_alias(FelixHaeberle\Webp\Convert::class, 'WebP\\Convert');

Kirby::plugin('felixhaeberle/kirby3-webp', [
	// all defaults are null so the legacy flat config keys
	// (kirby3-webp, kirby3-webp.quality, …) stay effective;
	// real defaults live in Webp::DEFAULTS
	'options' => [
		'companion'   => null,
		'formats'     => null,
		'quality'     => null,
		'sources'     => null,
		'scheme'      => null,
		'skipLarger'  => null,
		'minSize'     => null,
		'engine'      => null,
		'srcset'      => null,
		'sizes'       => null,
		'webpConvert' => null,
	],

	// hook closures bind adaptively: Kirby 3/4 pass $newFile/$oldFile,
	// newer versions pass $file — unmatched names resolve to null
	'hooks' => [
		'file.create:after' => function ($file) {
			Hooks::created($file);
		},
		'file.replace:after' => function ($newFile = null, $file = null) {
			Hooks::replaced($newFile ?? $file);
		},
		'file.changeName:before' => function ($file) {
			Hooks::renameBefore($file);
		},
		'file.changeName:after' => function ($newFile = null, $file = null) {
			Hooks::renamed($newFile ?? $file);
		},
		'file.delete:before' => function ($file) {
			Hooks::deleted($file);
		},
	],

	'fileMethods' => [
		'webp' => function (array $thumb = []) {
			return Picture::version($this, 'webp', $thumb);
		},
		'avif' => function (array $thumb = []) {
			return Picture::version($this, 'avif', $thumb);
		},
		'picture' => function (array $options = []) {
			return Picture::html($this, $options);
		},
		'companion' => function (string $format = 'webp') {
			return Webp::companionFile($this, $format);
		},
		'hasCompanion' => function (string $format = 'webp') {
			return Webp::companionExists($this, $format);
		},
	],

	'filesMethods' => [
		'withoutCompanions' => function () {
			return Webp::filterCompanions($this);
		},
	],

	'snippets' => [
		'webp/picture' => __DIR__ . '/snippets/picture.php',
	],

	// Kirby CLI (getkirby/cli) commands
	'commands' => [
		'webp:generate' => [
			'description' => 'Generates WebP/AVIF companions for all images',
			'args' => [
				'force' => [
					'prefix'      => 'f',
					'longPrefix'  => 'force',
					'description' => 'Regenerate companions even if they are up to date',
					'noValue'     => true,
				],
			],
			'command' => function ($cli): void {
				$report = Webp::generateAll($cli->kirby(), $cli->arg('force') === true);

				foreach ($report as $key => $value) {
					$cli->out(str_pad($key, 16) . $value);
				}

				$cli->success('Done: ' . $report['created'] . ' companions generated for ' . $report['files'] . ' images');
			},
		],
		'webp:clean' => [
			'description' => 'Removes all generated WebP/AVIF companions',
			'args'        => [],
			'command'     => function ($cli): void {
				$removed = Webp::cleanAll($cli->kirby());
				$cli->success('Done: ' . $removed . ' companion files removed');
			},
		],
	],
]);

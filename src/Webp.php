<?php

namespace FelixHaeberle\Webp;

use Generator;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Files;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Throwable;

/**
 * Core service: generates and maintains WebP/AVIF companion files
 * next to original images, and powers the file/picture helpers.
 */
class Webp
{
	/**
	 * Formats this plugin can produce
	 */
	public const FORMATS = ['webp', 'avif'];

	/**
	 * Source extensions that can be converted
	 */
	public const SOURCES = ['jpg', 'jpeg', 'png'];

	public const DEFAULTS = [
		'companion'  => false,
		'formats'    => ['webp'],
		'quality'    => ['webp' => 80, 'avif' => 60],
		'sources'    => ['image/jpeg', 'image/png'],
		'scheme'     => 'name',   // 'name' => photo.webp | 'filename' => photo.jpg.webp
		'skipLarger' => true,
		'minSize'    => 0,
		'engine'     => 'auto',   // auto | kirby | webp-convert
		'srcset'     => [400, 800, 1200, 1600, 2000],
		'sizes'      => '100vw',
	];

	/**
	 * v1 config keys (flat, un-namespaced) that keep working
	 */
	protected const LEGACY = [
		'companion'      => 'kirby3-webp',
		'quality'        => 'kirby3-webp.quality',
		'maxQuality'     => 'kirby3-webp.maxQuality',
		'defaultQuality' => 'kirby3-webp.defaultQuality',
		'metadata'       => 'kirby3-webp.metadata',
		'encoding'       => 'kirby3-webp.encoding',
		'skip'           => 'kirby3-webp.skip',
	];

	/**
	 * Cached capability checks per format
	 */
	protected static array $support = [];

	/**
	 * Reads a plugin option with fallback chain:
	 * felixhaeberle.kirby3-webp.* -> legacy kirby3-webp.* -> defaults
	 */
	public static function option(string $key, App|null $kirby = null): mixed
	{
		$kirby ??= App::instance();
		$value   = $kirby->option('felixhaeberle.kirby3-webp.' . $key);

		if ($value === null && isset(static::LEGACY[$key]) === true) {
			$value = $kirby->option(static::LEGACY[$key]);
		}

		return $value ?? static::DEFAULTS[$key] ?? null;
	}

	/**
	 * Whether companion generation on upload is enabled
	 */
	public static function enabled(App|null $kirby = null): bool
	{
		return static::option('companion', $kirby) === true;
	}

	/**
	 * Normalized list of target formats
	 */
	public static function formats(App|null $kirby = null): array
	{
		$formats = static::option('formats', $kirby);
		$formats = array_map('strtolower', (array)$formats);

		return array_values(array_intersect($formats, static::FORMATS));
	}

	/**
	 * Quality for a given target format
	 */
	public static function qualityFor(string $format, App|null $kirby = null): int
	{
		$quality = static::option('quality', $kirby);

		if (is_array($quality) === true) {
			$quality = $quality[$format]
				?? $quality['default']
				?? static::DEFAULTS['quality'][$format]
				?? 80;
		}

		if (is_numeric($quality) === false) {
			// e.g. legacy 'auto' (only meaningful to the webp-convert engine)
			$quality = static::DEFAULTS['quality'][$format] ?? 80;
		}

		return max(1, min(100, (int)$quality));
	}

	/**
	 * Companion filename scheme: 'name' (photo.webp) or 'filename' (photo.jpg.webp)
	 */
	public static function scheme(App|null $kirby = null): string
	{
		return static::option('scheme', $kirby) === 'filename' ? 'filename' : 'name';
	}

	/**
	 * Whether a file is a valid conversion source
	 */
	public static function convertible(File $file): bool
	{
		$extension = strtolower($file->extension());

		if (in_array($extension, static::FORMATS) === true) {
			return false; // already webp/avif
		}

		if (in_array($extension, static::SOURCES) === false) {
			return false;
		}

		if (is_file($file->root()) === false) {
			return false;
		}

		$sources = (array)static::option('sources', $file->kirby());

		if (in_array($file->mime(), $sources) === false) {
			return false;
		}

		$minSize = (int)static::option('minSize', $file->kirby());

		if ($minSize > 0 && F::size($file->root()) < $minSize) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a filename looks like a companion of the plugin
	 */
	public static function isCompanion(string $filename): bool
	{
		return in_array(strtolower(F::extension($filename)), static::FORMATS) === true;
	}

	/**
	 * Absolute path of the companion for a file + format,
	 * honoring the configured naming scheme
	 */
	public static function companionRoot(File $file, string $format, string|null $scheme = null): string
	{
		$scheme ??= static::scheme($file->kirby());
		$base     = $scheme === 'filename' ? $file->filename() : $file->name();

		return dirname($file->root()) . '/' . $base . '.' . $format;
	}

	/**
	 * All possible companion paths for an original file root
	 * (both schemes, all formats) — used for cleanup
	 */
	public static function companionCandidates(string $root): array
	{
		$dir      = dirname($root);
		$filename = basename($root);
		$name     = F::name($filename);
		$roots    = [];

		foreach (static::FORMATS as $format) {
			$roots[] = $dir . '/' . $filename . '.' . $format; // filename scheme
			$roots[] = $dir . '/' . $name . '.' . $format;     // name scheme
		}

		return array_unique($roots);
	}

	/**
	 * Generates companions for a file. Returns a per-format status report:
	 * created | fresh | skipped-larger | unsupported | failed (…)
	 */
	public static function generate(File $file, bool $force = false): array
	{
		$report = [];

		foreach (static::formats($file->kirby()) as $format) {
			try {
				$report[$format] = static::generateFormat($file, $format, $force);
			} catch (Throwable $e) {
				$report[$format] = 'failed (' . $e->getMessage() . ')';
				error_log('kirby3-webp: ' . $file->root() . ' -> ' . $format . ': ' . $e->getMessage());
			}
		}

		return $report;
	}

	/**
	 * Generates a single companion format for a file
	 */
	protected static function generateFormat(File $file, string $format, bool $force = false): string
	{
		$kirby = $file->kirby();
		$src   = $file->root();
		$dst   = static::companionRoot($file, $format);

		if ($force === false && is_file($dst) === true && F::modified($dst) >= F::modified($src)) {
			return 'fresh';
		}

		$engine = static::engineFor($format, $kirby);

		if ($engine === null) {
			return 'unsupported';
		}

		if ($engine === 'webp-convert') {
			static::convertWithWebPConvert($src, $dst, $kirby);
		} else {
			$kirby->thumb($src, $dst, [
				'format'  => $format,
				'quality' => static::qualityFor($format, $kirby),
			]);
		}

		if (is_file($dst) === false) {
			return 'failed (no output written)';
		}

		if (static::option('skipLarger', $kirby) === true && F::size($dst) >= F::size($src)) {
			F::remove($dst);
			return 'skipped-larger';
		}

		return 'created';
	}

	/**
	 * Resolves the conversion engine for a format;
	 * null if the format cannot be produced in this environment
	 */
	public static function engineFor(string $format, App|null $kirby = null): string|null
	{
		$kirby ??= App::instance();
		$engine  = static::option('engine', $kirby);
		$library = class_exists('WebPConvert\\WebPConvert') === true && $format === 'webp';

		if ($engine === 'webp-convert') {
			return $library === true ? 'webp-convert' : null;
		}

		if ($engine === 'kirby') {
			return static::supported($format, $kirby) === true ? 'kirby' : null;
		}

		// auto: prefer Kirby's own thumb pipeline, fall back to webp-convert
		if (static::supported($format, $kirby) === true) {
			return 'kirby';
		}

		return $library === true ? 'webp-convert' : null;
	}

	/**
	 * Whether the configured thumb driver can write the given format
	 */
	public static function supported(string $format, App|null $kirby = null): bool
	{
		$kirby ??= App::instance();
		$driver  = strtolower((string)$kirby->option('thumbs.driver', 'gd'));
		$key     = $driver . ':' . $format;

		return static::$support[$key] ??= match (true) {
			$driver === 'gd'      => function_exists('image' . $format) === true,
			$driver === 'imagick' => class_exists('Imagick') === true
				&& \Imagick::queryFormats(strtoupper($format)) !== [],
			// im (binary) or custom drivers: optimistic, failures are caught upstream
			default => true,
		};
	}

	/**
	 * Conversion via rosell-dk/webp-convert, honoring the legacy v1 options
	 */
	protected static function convertWithWebPConvert(string $src, string $dst, App $kirby): void
	{
		$options = array_merge([
			'quality'         => static::option('quality', $kirby),
			'max-quality'     => static::option('maxQuality', $kirby) ?? 85,
			'default-quality' => static::option('defaultQuality', $kirby) ?? 85,
			'metadata'        => static::option('metadata', $kirby) ?? 'none',
			'encoding'        => static::option('encoding', $kirby) ?? 'auto',
			'skip'            => static::option('skip', $kirby) ?? false,
		], (array)($kirby->option('felixhaeberle.kirby3-webp.webpConvert') ?? []));

		// the library accepts int|'auto' for quality; arrays are ours
		if (is_array($options['quality']) === true) {
			$options['quality'] = static::qualityFor('webp', $kirby);
		}

		\WebPConvert\WebPConvert::convert($src, $dst, $options);
	}

	/**
	 * Removes all companions (and their meta files) of an original
	 */
	public static function cleanup(File|string $file): void
	{
		$root = $file instanceof File ? $file->root() : $file;

		foreach (static::companionCandidates($root) as $companion) {
			// name-scheme guard: keep photo.webp if another original
			// (photo.jpg/photo.png) still owns it
			if (static::hasOtherOwner($companion, $root) === true) {
				continue;
			}

			static::removeWithMeta($companion);
		}
	}

	/**
	 * Whether a (name-scheme) companion is still owned by another original
	 */
	protected static function hasOtherOwner(string $companion, string $deletedRoot): bool
	{
		$dir  = dirname($companion);
		$name = F::name(basename($companion));

		// filename-scheme companions (photo.jpg.webp) are unambiguous
		if (str_contains($name, '.') === true) {
			return false;
		}

		foreach (static::SOURCES as $extension) {
			$candidate = $dir . '/' . $name . '.' . $extension;

			if ($candidate !== $deletedRoot && is_file($candidate) === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes a companion file plus any content/meta files
	 * (photo.webp.txt, photo.webp.en.txt, …)
	 */
	protected static function removeWithMeta(string $root): void
	{
		if (is_file($root) === true) {
			F::remove($root);
		}

		$dir      = dirname($root);
		$filename = basename($root);

		foreach (Dir::read($dir) as $entry) {
			if (str_starts_with($entry, $filename . '.') === true && str_ends_with($entry, '.txt') === true) {
				F::remove($dir . '/' . $entry);
			}
		}
	}

	/**
	 * Moves companions when the original was renamed
	 */
	public static function moveCompanions(string $oldRoot, File $newFile): void
	{
		$dir         = dirname($oldRoot);
		$oldFilename = basename($oldRoot);
		$oldName     = F::name($oldFilename);

		foreach (static::FORMATS as $format) {
			$moves = [
				// filename scheme
				$dir . '/' . $oldFilename . '.' . $format => static::companionRoot($newFile, $format, 'filename'),
				// name scheme
				$dir . '/' . $oldName . '.' . $format     => static::companionRoot($newFile, $format, 'name'),
			];

			foreach ($moves as $from => $to) {
				if (is_file($from) === false || $from === $to) {
					continue;
				}

				F::move($from, $to, true);

				// move meta files along (photo.webp.txt -> renamed.webp.txt)
				$fromFilename = basename($from);
				$toFilename   = basename($to);

				foreach (Dir::read($dir) as $entry) {
					if (str_starts_with($entry, $fromFilename . '.') === true && str_ends_with($entry, '.txt') === true) {
						F::move(
							$dir . '/' . $entry,
							dirname($to) . '/' . $toFilename . substr($entry, strlen($fromFilename)),
							true
						);
					}
				}
			}
		}
	}

	/**
	 * Whether a companion for the file exists on disk
	 * (either naming scheme)
	 */
	public static function companionExists(File $file, string $format = 'webp'): bool
	{
		foreach (['filename', 'name'] as $scheme) {
			if (is_file(static::companionRoot($file, $format, $scheme)) === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Finds the companion of a file as a Kirby File object.
	 * Freshly generated companions are indexed on the next request.
	 */
	public static function companionFile(File $file, string $format = 'webp'): File|null
	{
		$siblings = $file->siblings();

		foreach (['filename', 'name'] as $scheme) {
			$filename = basename(static::companionRoot($file, $format, $scheme));

			if ($companion = $siblings->findBy('filename', $filename)) {
				return $companion;
			}
		}

		return null;
	}

	/**
	 * Filters companion files out of a files collection
	 * (for clean template loops and panel-independent listings)
	 */
	public static function filterCompanions(Files $files): Files
	{
		return $files->filter(function (File $file) use ($files): bool {
			if (static::isCompanion($file->filename()) === false) {
				return true;
			}

			$base = $file->name();

			// filename scheme: photo.jpg.webp -> original "photo.jpg"
			if (str_contains($base, '.') === true && $files->findBy('filename', $base) !== null) {
				return false;
			}

			// name scheme: photo.webp -> original "photo.jpg|jpeg|png"
			foreach (static::SOURCES as $extension) {
				if ($files->findBy('filename', $base . '.' . $extension) !== null) {
					return false;
				}
			}

			return true;
		});
	}

	/**
	 * All files of the whole installation (site, pages incl. drafts, users)
	 */
	public static function index(App $kirby): Generator
	{
		yield from $kirby->site()->files();

		foreach ($kirby->site()->index(true) as $page) {
			yield from $page->files();
		}

		foreach ($kirby->users() as $user) {
			yield from $user->files();
		}
	}

	/**
	 * Bulk generation for all convertible files (used by `kirby webp:generate`)
	 */
	public static function generateAll(App $kirby, bool $force = false): array
	{
		$report = ['files' => 0, 'created' => 0, 'fresh' => 0, 'skipped-larger' => 0, 'unsupported' => 0, 'failed' => 0];

		foreach (static::index($kirby) as $file) {
			if (static::convertible($file) === false) {
				continue;
			}

			$report['files']++;

			foreach (static::generate($file, $force) as $status) {
				$key = str_starts_with($status, 'failed') === true ? 'failed' : $status;
				$report[$key] = ($report[$key] ?? 0) + 1;
			}
		}

		return $report;
	}

	/**
	 * Bulk cleanup of all companions (used by `kirby webp:clean`)
	 */
	public static function cleanAll(App $kirby): int
	{
		$removed = 0;

		foreach (static::index($kirby) as $file) {
			// companions of existing originals
			if (static::convertible($file) === true) {
				foreach (static::companionCandidates($file->root()) as $companion) {
					if (is_file($companion) === true) {
						static::removeWithMeta($companion);
						$removed++;
					}
				}
				continue;
			}

			// orphaned filename-scheme companions (photo.jpg.webp without photo.jpg)
			if (
				static::isCompanion($file->filename()) === true
				&& str_contains($file->name(), '.') === true
				&& is_file(dirname($file->root()) . '/' . $file->name()) === false
			) {
				static::removeWithMeta($file->root());
				$removed++;
			}
		}

		return $removed;
	}
}

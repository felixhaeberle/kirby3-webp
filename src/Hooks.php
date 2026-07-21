<?php

namespace FelixHaeberle\Webp;

use Kirby\Cms\File;

/**
 * Thin hook layer. Kirby's hook argument names changed between
 * major versions (e.g. $newFile/$oldFile vs. $file), so index.php
 * binds adaptively and delegates here.
 */
class Hooks
{
	/**
	 * Old file roots stashed in changeName:before,
	 * consumed in changeName:after (FIFO within one request)
	 */
	protected static array $renameStash = [];

	public static function created(File|null $file): void
	{
		if ($file !== null && Webp::enabled($file->kirby()) === true && Webp::convertible($file) === true) {
			Webp::generate($file);
		}
	}

	public static function replaced(File|null $file): void
	{
		if ($file === null) {
			return;
		}

		// content changed, previous companions are stale either way
		Webp::cleanup($file);

		if (Webp::enabled($file->kirby()) === true && Webp::convertible($file) === true) {
			Webp::generate($file, true);
		}
	}

	public static function renameBefore(File|null $file): void
	{
		if ($file !== null) {
			static::$renameStash[] = $file->root();
		}
	}

	public static function renamed(File|null $file): void
	{
		$oldRoot = array_shift(static::$renameStash);

		if ($file === null) {
			return;
		}

		if ($oldRoot !== null && $oldRoot !== $file->root()) {
			Webp::moveCompanions($oldRoot, $file);
		}

		if (Webp::enabled($file->kirby()) === true && Webp::convertible($file) === true) {
			Webp::generate($file);
		}
	}

	public static function deleted(File|null $file): void
	{
		if ($file !== null) {
			// runs regardless of the companion option so leftovers
			// from previously enabled setups are removed as well
			Webp::cleanup($file);
		}
	}
}

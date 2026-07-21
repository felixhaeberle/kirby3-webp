<?php

namespace FelixHaeberle\Webp;

use Kirby\Cms\File;

/**
 * Backwards-compatible shim for the v1 API (WebP\Convert).
 * @deprecated use FelixHaeberle\Webp\Webp::generate() instead
 */
class Convert
{
	public function generateWebP(File $file): void
	{
		if (Webp::convertible($file) === true) {
			Webp::generate($file, true);
		}
	}
}

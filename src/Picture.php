<?php

namespace FelixHaeberle\Webp;

use Kirby\Cms\File;
use Kirby\Toolkit\Html;

/**
 * Builds responsive <picture> markup and format-converted
 * thumb versions on top of Kirby's native thumb API.
 */
class Picture
{
	/**
	 * Extensions the picture builder can create sources for
	 */
	public const RESIZABLE = ['jpg', 'jpeg', 'png', 'webp'];

	/**
	 * Returns a thumb version of the file in the given format,
	 * e.g. $file->webp() or $file->avif(['width' => 800])
	 */
	public static function version(File $file, string $format, array $thumb = []): mixed
	{
		if (strtolower($file->extension()) === $format) {
			// already in the target format; only apply resize options
			return $thumb === [] ? $file : $file->thumb($thumb);
		}

		$thumb['format']    = $format;
		$thumb['quality'] ??= Webp::qualityFor($format, $file->kirby());

		return $file->thumb($thumb);
	}

	/**
	 * Renders a responsive <picture> element with WebP/AVIF sources,
	 * srcset/sizes and CLS-safe width/height attributes
	 */
	public static function html(File $file, array $options = []): string
	{
		$kirby     = $file->kirby();
		$extension = strtolower($file->extension());
		$alt       = $options['alt'] ?? $file->content()->get('alt')->value() ?? '';
		$lazy      = ($options['priority'] ?? false) === true ? false : ($options['lazy'] ?? true);

		$imgAttrs = array_merge([
			'alt'      => $alt,
			'loading'  => $lazy === true ? 'lazy' : 'eager',
			'decoding' => 'async',
			'class'    => $options['class'] ?? null,
		], $options['imgAttrs'] ?? []);

		if (($options['priority'] ?? false) === true) {
			$imgAttrs['fetchpriority'] = 'high';
		}

		$maxWidth = $file->width() ?? 0;

		// SVG, GIF & co. (or unreadable dimensions): no re-encoding, plain <img>
		if ($file->type() !== 'image' || in_array($extension, static::RESIZABLE) === false || $maxWidth < 1) {
			return Html::img($file->url(), $imgAttrs);
		}

		$widths   = $options['widths'] ?? Webp::option('srcset', $kirby);
		$widths   = array_map(fn ($width) => min((int)$width, $maxWidth), (array)$widths);
		$widths   = array_values(array_unique(array_filter($widths)));
		sort($widths);

		if ($widths === []) {
			$widths = [$maxWidth];
		}

		$sizes   = $options['sizes'] ?? Webp::option('sizes', $kirby);
		$quality = $options['quality'] ?? null;

		$srcset = function (string|null $format) use ($file, $widths, $quality): string {
			$set = [];

			foreach ($widths as $width) {
				$thumb = ['width' => $width];

				if ($format !== null) {
					$thumb['format']  = $format;
					$thumb['quality'] = $quality ?? Webp::qualityFor($format, $file->kirby());
				} elseif ($quality !== null) {
					$thumb['quality'] = $quality;
				}

				$set[] = $file->thumb($thumb)->url() . ' ' . $width . 'w';
			}

			return implode(', ', $set);
		};

		// modern format <source> elements (skip formats the driver can't write
		// and the original's own format — that one is covered by the <img>)
		$html = [];

		foreach ($options['formats'] ?? Webp::formats($kirby) as $format) {
			if ($format === $extension || Webp::supported($format, $kirby) === false) {
				continue;
			}

			$html[] = Html::tag('source', null, [
				'type'   => 'image/' . $format,
				'srcset' => $srcset($format),
				'sizes'  => $sizes,
			]);
		}

		// CLS-safe intrinsic dimensions based on the largest rendered width
		$largest = max($widths);
		$ratio   = $maxWidth > 0 ? ($file->height() / $maxWidth) : 0;

		$html[] = Html::img($file->thumb(['width' => $largest])->url(), array_merge($imgAttrs, [
			'srcset' => $srcset(null),
			'sizes'  => $sizes,
			'width'  => $largest,
			'height' => $ratio > 0 ? (int)round($largest * $ratio) : null,
		]));

		return Html::tag('picture', [implode('', $html)]);
	}
}

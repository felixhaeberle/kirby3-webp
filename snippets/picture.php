<?php
/**
 * Responsive <picture> snippet
 *
 * Usage:
 *   <?php snippet('webp/picture', ['image' => $page->image(), 'options' => [
 *     'sizes'  => '(min-width: 60rem) 50vw, 100vw',
 *     'alt'    => 'Alt text',
 *     'class'  => 'my-image',
 *   ]]) ?>
 *
 * @var Kirby\Cms\File|null $image
 * @var array|null $options
 */

$image ??= $file ?? null;

if ($image instanceof Kirby\Cms\File) {
	echo $image->picture($options ?? []);
}

<?php

namespace FelixHaeberle\Webp\Tests;

/**
 * The full companion lifecycle through real Kirby file actions
 * (upload, replace, rename, delete) — this also verifies that the
 * hook argument binding works on the installed Kirby version.
 */
class CompanionLifecycleTest extends TestCase
{
	public function testDisabledByDefault(): void
	{
		$this->app();
		$this->upload('photo.jpg');

		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp');
	}

	public function testCreateGeneratesCompanion(): void
	{
		$this->app(['kirby3-webp' => true]); // legacy v1 activation
		$file = $this->upload('photo.jpg');

		$companion = $this->contentDir() . '/photo.webp';

		$this->assertIsWebp($companion);

		// dimensions must be preserved (no accidental resizing)
		[$width, $height] = getimagesize($companion);

		$this->assertSame(800, $width);
		$this->assertSame(600, $height);
	}

	public function testPngAlphaIsPreserved(): void
	{
		if (function_exists('imagecreatefromwebp') === false) {
			$this->markTestSkipped('GD cannot read WebP');
		}

		$this->app(['kirby3-webp' => true]);
		$this->upload('trans.png');

		$companion = $this->contentDir() . '/trans.webp';
		$this->assertIsWebp($companion);

		$image = imagecreatefromwebp($companion);
		$alpha = (imagecolorat($image, imagesx($image) - 2, 5) >> 24) & 0x7F;
		imagedestroy($image);

		$this->assertGreaterThan(32, $alpha, 'right edge of the PNG gradient should stay (semi-)transparent');
	}

	public function testReplaceRegeneratesCompanion(): void
	{
		$this->app(['kirby3-webp' => true]);

		$file      = $this->upload('photo.jpg', $this->jpegFixture(800, 600, [200, 60, 60]));
		$companion = $this->contentDir() . '/photo.webp';

		$this->assertIsWebp($companion);
		$before = md5_file($companion);

		$file->replace($this->jpegFixture(400, 300, [30, 60, 220]));

		$this->assertIsWebp($companion);
		$this->assertNotSame($before, md5_file($companion), 'companion must be rebuilt from the new content');

		[$width] = getimagesize($companion);
		$this->assertSame(400, $width);
	}

	public function testRenameMovesCompanion(): void
	{
		$this->app(['kirby3-webp' => true]);

		$file = $this->upload('photo.jpg');
		$this->assertIsWebp($this->contentDir() . '/photo.webp');

		$file->changeName('renamed');

		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp');
		$this->assertIsWebp($this->contentDir() . '/renamed.webp');
	}

	public function testDeleteRemovesCompanionAndMeta(): void
	{
		$this->app(['kirby3-webp' => true]);

		$file      = $this->upload('photo.jpg');
		$companion = $this->contentDir() . '/photo.webp';

		$this->assertIsWebp($companion);

		// simulate panel meta files for the companion (also multilang)
		file_put_contents($companion . '.txt', 'Alt: companion');
		file_put_contents($companion . '.en.txt', 'Alt: companion');

		$file->delete();

		$this->assertFileDoesNotExist($companion);
		$this->assertFileDoesNotExist($companion . '.txt');
		$this->assertFileDoesNotExist($companion . '.en.txt');
	}

	public function testDeleteKeepsCompanionOwnedByAnotherOriginal(): void
	{
		$this->app(['kirby3-webp' => true]);

		$jpg = $this->upload('photo.jpg');
		$png = $this->upload('photo.png');

		$companion = $this->contentDir() . '/photo.webp';
		$this->assertIsWebp($companion);

		// photo.jpg still owns photo.webp -> must survive deleting photo.png
		$png->delete();
		$this->assertIsWebp($companion);

		// last owner deleted -> companion goes too
		$jpg->delete();
		$this->assertFileDoesNotExist($companion);
	}
}

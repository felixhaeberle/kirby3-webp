<?php

namespace FelixHaeberle\Webp\Tests;

class CollectionTest extends TestCase
{
	public function testWithoutCompanions(): void
	{
		$this->app(['kirby3-webp' => true]);

		$this->upload('photo.jpg');        // -> generates photo.webp (name scheme)
		$this->upload('standalone.webp');  // genuine upload, no original

		// filename-scheme companion placed manually
		copy($this->webpFixture(), $this->contentDir() . '/banner.png.webp');
		copy($this->pngFixture(), $this->contentDir() . '/banner.png');

		$this->app(['kirby3-webp' => true]); // re-index
		$files = $this->page()->files();

		$this->assertNotNull($files->findBy('filename', 'photo.webp'));

		$filtered  = $files->withoutCompanions();
		$filenames = $filtered->pluck('filename');

		$this->assertContains('photo.jpg', $filenames);
		$this->assertContains('banner.png', $filenames);
		$this->assertContains('standalone.webp', $filenames, 'genuine webp uploads must survive the filter');
		$this->assertNotContains('photo.webp', $filenames);
		$this->assertNotContains('banner.png.webp', $filenames);
	}

	public function testCompanionLookup(): void
	{
		$this->app(['kirby3-webp' => true]);

		$file = $this->upload('photo.jpg');

		// disk-based check works within the same request
		$this->assertTrue($file->hasCompanion('webp'));
		$this->assertFalse($file->hasCompanion('avif'));

		// the File object is available once the companion is indexed
		$this->app(['kirby3-webp' => true]);
		$file = $this->page()->file('photo.jpg');

		$this->assertSame('photo.webp', $file->companion('webp')->filename());
	}
}

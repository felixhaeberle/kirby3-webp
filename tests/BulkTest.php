<?php

namespace FelixHaeberle\Webp\Tests;

use FelixHaeberle\Webp\Webp;
use Kirby\Filesystem\Dir;

/**
 * The logic behind `kirby webp:generate` and `kirby webp:clean`
 */
class BulkTest extends TestCase
{
	protected function seedContent(): void
	{
		// second page + draft + site file
		Dir::make($this->tmp . '/content/other');
		file_put_contents($this->tmp . '/content/other/default.txt', 'Title: Other');
		copy($this->jpegFixture(320, 240), $this->tmp . '/content/other/two.jpg');

		Dir::make($this->tmp . '/content/_drafts/hidden');
		file_put_contents($this->tmp . '/content/_drafts/hidden/default.txt', 'Title: Draft');
		copy($this->jpegFixture(320, 240), $this->tmp . '/content/_drafts/hidden/three.jpg');

		copy($this->jpegFixture(320, 240), $this->tmp . '/content/cover.jpg');
		copy($this->jpegFixture(320, 240), $this->contentDir() . '/one.jpg');
	}

	public function testGenerateAllAndCleanAll(): void
	{
		$this->seedContent();
		$this->app();

		$report = Webp::generateAll($this->kirby);

		$this->assertSame(4, $report['files'], 'site file, page files and draft files must all be included');
		$this->assertSame(4, $report['created']);

		$this->assertIsWebp($this->contentDir() . '/one.webp');
		$this->assertIsWebp($this->tmp . '/content/other/two.webp');
		$this->assertIsWebp($this->tmp . '/content/_drafts/hidden/three.webp');
		$this->assertIsWebp($this->tmp . '/content/cover.webp');

		// second run: everything is fresh
		$report = Webp::generateAll($this->kirby);
		$this->assertSame(4, $report['fresh']);
		$this->assertSame(0, $report['created']);

		// force regenerates
		$report = Webp::generateAll($this->kirby, true);
		$this->assertSame(4, $report['created']);

		$removed = Webp::cleanAll($this->kirby);
		$this->assertSame(4, $removed);
		$this->assertFileDoesNotExist($this->contentDir() . '/one.webp');
	}

	public function testCleanAllRemovesOrphanedFilenameSchemeCompanions(): void
	{
		$this->app();

		// filename-scheme companion whose original is gone -> orphan
		copy($this->webpFixture(), $this->contentDir() . '/gone.jpg.webp');
		// leftover intermediate from v1's webp-convert engine (issue #7)
		copy($this->webpFixture(), $this->contentDir() . '/photo.webp.lossy.webp');
		// genuine webp upload -> must not be touched
		copy($this->webpFixture(), $this->contentDir() . '/keep.webp');

		$this->app(); // re-index

		Webp::cleanAll($this->kirby);

		$this->assertFileDoesNotExist($this->contentDir() . '/gone.jpg.webp');
		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp.lossy.webp');
		$this->assertFileExists($this->contentDir() . '/keep.webp');
	}
}

<?php

namespace FelixHaeberle\Webp\Tests;

use FelixHaeberle\Webp\Webp;

class OptionsTest extends TestCase
{
	public function testNamespacedOptions(): void
	{
		$this->app([
			'felixhaeberle.kirby3-webp' => [
				'companion' => true,
				'quality'   => ['webp' => 50],
			],
		]);

		$this->assertTrue(Webp::enabled($this->kirby));
		$this->assertSame(50, Webp::qualityFor('webp', $this->kirby));

		$this->upload('photo.jpg');
		$this->assertIsWebp($this->contentDir() . '/photo.webp');
	}

	public function testLegacyFlatOptions(): void
	{
		$this->app([
			'kirby3-webp'         => true,
			'kirby3-webp.quality' => 42,
		]);

		$this->assertTrue(Webp::enabled($this->kirby));
		$this->assertSame(42, Webp::qualityFor('webp', $this->kirby));
	}

	public function testDefaults(): void
	{
		$this->app();

		$this->assertFalse(Webp::enabled($this->kirby));
		$this->assertSame(['webp'], Webp::formats($this->kirby));
		$this->assertSame(80, Webp::qualityFor('webp', $this->kirby));
		$this->assertSame(60, Webp::qualityFor('avif', $this->kirby));
		$this->assertSame('name', Webp::scheme($this->kirby));
	}

	public function testFilenameSchemeAvoidsCollisions(): void
	{
		$this->app([
			'felixhaeberle.kirby3-webp' => [
				'companion' => true,
				'scheme'    => 'filename',
			],
		]);

		$this->upload('photo.jpg');
		$this->upload('photo.png');

		$this->assertIsWebp($this->contentDir() . '/photo.jpg.webp');
		$this->assertIsWebp($this->contentDir() . '/photo.png.webp');
		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp');
	}

	public function testMinSizeSkipsSmallFiles(): void
	{
		$this->app([
			'felixhaeberle.kirby3-webp' => [
				'companion' => true,
				'minSize'   => 50 * 1024 * 1024,
			],
		]);

		$this->upload('photo.jpg');
		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp');
	}

	public function testUnavailableEngineReportsUnsupported(): void
	{
		$this->app([
			'felixhaeberle.kirby3-webp' => [
				'companion' => true,
				// rosell-dk/webp-convert is deliberately not installed in the test env
				'engine'    => 'webp-convert',
			],
		]);

		$file = $this->upload('photo.jpg');

		$this->assertFileDoesNotExist($this->contentDir() . '/photo.webp');
		$this->assertSame(['webp' => 'unsupported'], Webp::generate($file));
	}

	public function testUppercaseExtensionsAreConvertible(): void
	{
		$this->app(['kirby3-webp' => true]);

		// legacy content placed directly on disk (bypasses upload normalization)
		copy($this->jpegFixture(320, 240), $this->contentDir() . '/UPPER.JPG');
		$this->app(['kirby3-webp' => true]); // re-index

		$file = $this->page()->file('UPPER.JPG');

		$this->assertNotNull($file);
		$this->assertTrue(Webp::convertible($file));
		$this->assertSame(['webp' => 'created'], Webp::generate($file));
		$this->assertIsWebp($this->contentDir() . '/UPPER.webp');
	}

	public function testAvifGeneration(): void
	{
		if (function_exists('imageavif') === false) {
			$this->markTestSkipped('GD cannot write AVIF');
		}

		$this->app([
			'felixhaeberle.kirby3-webp' => [
				'companion' => true,
				'formats'   => ['webp', 'avif'],
			],
		]);

		$this->upload('photo.jpg');

		$this->assertIsWebp($this->contentDir() . '/photo.webp');
		$this->assertFileExists($this->contentDir() . '/photo.avif');
	}

	public function testCompanionsAreNeverSourcesThemselves(): void
	{
		$this->app(['kirby3-webp' => true]);

		$file = $this->upload('genuine.webp');

		$this->assertFalse(Webp::convertible($file));
		$this->assertFileDoesNotExist($this->contentDir() . '/genuine.webp.webp');
	}
}

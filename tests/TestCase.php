<?php

namespace FelixHaeberle\Webp\Tests;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	protected string $tmp;
	protected App $kirby;

	protected function setUp(): void
	{
		$this->tmp = dirname(__DIR__) . '/tests/tmp/' . uniqid('kirby_', true);

		Dir::make($this->tmp . '/content/test');
		file_put_contents($this->tmp . '/content/site.txt', 'Title: Test Site');
		file_put_contents($this->tmp . '/content/test/default.txt', 'Title: Test Page');
	}

	protected function tearDown(): void
	{
		// no App::destroy() here — it would wipe Kirby's static plugin
		// registry and unregister this plugin for all following tests
		Dir::remove($this->tmp);
	}

	/**
	 * Boots a fresh Kirby app on the tmp content dir
	 */
	protected function app(array $options = []): App
	{
		$this->kirby = new App([
			'roots' => [
				'index'    => $this->tmp,
				'base'     => $this->tmp,
				'content'  => $this->tmp . '/content',
				'media'    => $this->tmp . '/media',
				'sessions' => $this->tmp . '/sessions',
				'accounts' => $this->tmp . '/accounts',
				'cache'    => $this->tmp . '/cache',
			],
			'options' => $options,
		]);

		$this->kirby->impersonate('kirby');

		return $this->kirby;
	}

	protected function page(string $id = 'test'): Page
	{
		return $this->kirby->page($id);
	}

	/**
	 * Uploads a generated image into the test page (triggers hooks)
	 */
	protected function upload(string $filename, string|null $source = null): File
	{
		$source ??= match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
			'png'   => $this->pngFixture(),
			'webp'  => $this->webpFixture(),
			'svg'   => $this->svgFixture(),
			default => $this->jpegFixture(),
		};

		return $this->page()->createFile([
			'source'   => $source,
			'filename' => $filename,
		]);
	}

	protected function fixtureDir(): string
	{
		$dir = $this->tmp . '/fixtures';
		Dir::make($dir);

		return $dir;
	}

	/**
	 * Generates a JPEG fixture (gradient so it has realistic size)
	 */
	protected function jpegFixture(int $width = 800, int $height = 600, array $rgb = [200, 60, 60]): string
	{
		$path  = $this->fixtureDir() . '/' . uniqid('img_') . '.jpg';
		$image = imagecreatetruecolor($width, $height);

		for ($x = 0; $x < $width; $x += 4) {
			$shade = (int)(($x / $width) * 128);
			$color = imagecolorallocate($image, min(255, $rgb[0] + $shade), $rgb[1], $rgb[2]);
			imagefilledrectangle($image, $x, 0, $x + 3, $height, $color);
		}

		imagejpeg($image, $path, 90);
		imagedestroy($image);

		return $path;
	}

	/**
	 * Generates a PNG fixture with an alpha gradient
	 */
	protected function pngFixture(int $width = 400, int $height = 300): string
	{
		$path  = $this->fixtureDir() . '/' . uniqid('img_') . '.png';
		$image = imagecreatetruecolor($width, $height);

		imagealphablending($image, false);
		imagesavealpha($image, true);

		for ($x = 0; $x < $width; $x += 4) {
			$alpha = (int)(($x / $width) * 127);
			$color = imagecolorallocatealpha($image, 60, 120, 200, $alpha);
			imagefilledrectangle($image, $x, 0, $x + 3, $height, $color);
		}

		imagepng($image, $path);
		imagedestroy($image);

		return $path;
	}

	protected function webpFixture(int $width = 400, int $height = 300): string
	{
		$path  = $this->fixtureDir() . '/' . uniqid('img_') . '.webp';
		$image = imagecreatetruecolor($width, $height);
		$color = imagecolorallocate($image, 80, 180, 90);

		imagefilledrectangle($image, 0, 0, $width, $height, $color);
		imagewebp($image, $path, 80);
		imagedestroy($image);

		return $path;
	}

	protected function svgFixture(): string
	{
		$path = $this->fixtureDir() . '/' . uniqid('img_') . '.svg';
		file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red"/></svg>');

		return $path;
	}

	/**
	 * Asserts that a file exists and is a valid WebP (RIFF....WEBP)
	 */
	protected function assertIsWebp(string $path): void
	{
		$this->assertFileExists($path);

		$header = file_get_contents($path, false, null, 0, 12);

		$this->assertSame('RIFF', substr($header, 0, 4), $path . ' is not a RIFF container');
		$this->assertSame('WEBP', substr($header, 8, 4), $path . ' is not a WebP file');
	}

	protected function contentDir(): string
	{
		return $this->tmp . '/content/test';
	}
}

<?php

namespace FelixHaeberle\Webp\Tests;

class PictureTest extends TestCase
{
	public function testPictureMarkup(): void
	{
		$this->app();

		$file = $this->upload('photo.jpg', $this->jpegFixture(1600, 1200));
		$html = $file->picture();

		$this->assertStringStartsWith('<picture>', $html);
		$this->assertStringContainsString('<source', $html);
		$this->assertStringContainsString('type="image/webp"', $html);
		$this->assertStringContainsString('400w', $html);
		$this->assertStringContainsString('1600w', $html);

		// 2000w from the default ladder must be capped at the original width
		$this->assertStringNotContainsString('2000w', $html);

		// CLS-safe dimensions + sane loading defaults
		$this->assertStringContainsString('width="1600"', $html);
		$this->assertStringContainsString('height="1200"', $html);
		$this->assertStringContainsString('loading="lazy"', $html);
		$this->assertStringContainsString('decoding="async"', $html);
		$this->assertStringContainsString('sizes="100vw"', $html);
		$this->assertStringContainsString('alt=""', $html);
	}

	public function testPictureOptions(): void
	{
		$this->app();

		$file = $this->upload('photo.jpg', $this->jpegFixture(1600, 1200));
		$html = $file->picture([
			'widths'   => [321, 4000],
			'sizes'    => '(min-width: 60rem) 50vw, 100vw',
			'alt'      => 'A "quoted" alt',
			'class'    => 'hero',
			'priority' => true,
		]);

		$this->assertStringContainsString('321w', $html);
		$this->assertStringContainsString('1600w', $html); // 4000 capped
		$this->assertStringNotContainsString('4000w', $html);
		$this->assertStringContainsString('sizes="(min-width: 60rem) 50vw, 100vw"', $html);
		$this->assertStringContainsString('alt="A &quot;quoted&quot; alt"', $html);
		$this->assertStringContainsString('class="hero"', $html);
		$this->assertStringContainsString('fetchpriority="high"', $html);
		$this->assertStringContainsString('loading="eager"', $html);
	}

	public function testSvgFallsBackToPlainImg(): void
	{
		$this->app();

		$file = $this->upload('icon.svg');
		$html = $file->picture();

		$this->assertStringStartsWith('<img', $html);
		$this->assertStringNotContainsString('<source', $html);
	}

	public function testWebpOriginalGetsNoRedundantSource(): void
	{
		$this->app();

		$file = $this->upload('native.webp');
		$html = $file->picture();

		// the <img> itself is already webp — no <source type="image/webp">
		$this->assertStringNotContainsString('type="image/webp"', $html);
	}

	public function testFileMethods(): void
	{
		$this->app();

		$file = $this->upload('photo.jpg');

		$this->assertStringContainsString('.webp', $file->webp()->url());
		$this->assertStringContainsString('.avif', $file->avif(['width' => 300])->url());

		// webp original: ->webp() without options returns the file itself
		$native = $this->upload('native.webp');
		$this->assertSame($native, $native->webp());
	}
}

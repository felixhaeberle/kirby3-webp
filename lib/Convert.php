<?php

namespace WebP;

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use WebPConvert\WebPConvert;

class Convert
{
    protected $quality;
    protected $strip;
    protected $serve;
    protected $log;
    protected $converters;

    public function __construct()
    {
        $this->quality = kirby()->option('kirby3-webp.quality', 90);
        $this->maxQuality = kirby()->option('kirby3-webp.maxQuality', 85);
        $this->defaultQuality = kirby()->option('kirby3-webp.defaultQuality', 85);
        $this->metadata = kirby()->option('kirby3-webp.metadata', "none");
        $this->encoding = kirby()->option('kirby3-webp.encoding', "auto");
        $this->skip = kirby()->option('kirby3-webp.skip', false);
    }

    public function generateWebP($file)
    {
        try {
            // Checking file type since only images are processed
            if ($file->type() == 'image') {
                // WebPConvert options
                $path = $file->contentFileDirectory() . '/';
                $input = $path . $file->filename();
                $output = $path . $file->name() . '.webp';

                // Generating WebP image & placing it alongside the original version
                WebPConvert::convert($input, $output, $option = [
                    'quality' => $this->quality,
                    'max-quality' => $this->maxQuality,
                    'default-quality' => $this->defaultQuality,
                    'metadata' => $this->metadata,
                    'encoding' => $this->encoding,
                    'skip' => $this->skip,
                ]);
            }
        } catch (Exception $e) {
            return response::error($e->getMessage());
        }
    }
}

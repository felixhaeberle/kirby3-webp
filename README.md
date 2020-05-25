# kirby3-webp
Kirby 3 CMS plugin for converting JPG, JPEG and PNG into much smaller WEBP – speed up your website!

![kirby3-webp](https://user-images.githubusercontent.com/34959078/82842741-f6450900-9eda-11ea-8f63-1cc3fe1eb5f0.png)

## Installation

### Composer
```
composer require felixhaeberle/kirby3-webp
```
### Git Submodule
```
git submodule add https://github.com/felixhaeberle/kirby3-webp.git site/plugins/kirby3-webp
```
### Clone or download
1. (Clone)[https://github.com/felixhaeberle/kirby3-webp.git] or (download)[https://github.com/felixhaeberle/kirby3-webp/archive/master.zip] this repository.
2. Unzip / Move the folder to `site/plugins`.

### Activate the plugin
Activate the plugin in the `config-php` file with `kirby3-webp => true`.
```
return [
  'kirby3-webp' => true
]
```

## Getting started

## Options
You have multiple options when using `kirby3-webp` to configure it to your needs

| Option  | Type  | Default  | Description  |
|---|---|---|---|
| `kirby3-webp.quality`  | Integer  | `90`  | See the "Auto quality" section above.  |
| `kirby3-webp.maxQuality`  | Integer  | `85`  | Only relevant for jpegs and when quality is set to "auto".  |
| `kirby3-webp.defaultQuality`  | Integer  | `85`  |   |
| `kirby3-webp.metadata`  | Array  | `"none"`  | Valid values: "all", "none", "exif", "icc", "xmp".

Note: Currently only cwebp supports all values. gd will always remove all metadata. ewww, imagick and gmagick can either strip all, or keep all (they will keep all, unless metadata is set to none)  |
| `kirby3-webp.encoding`  |  Array |  `"auto"` | See the "Auto selecting between lossless/lossy encoding" section above  |
| `kirby3-webp.skip`  | Boolean  | `false`  | 	If true, conversion will be skipped (ie for skipping png conversion for some converters)  |



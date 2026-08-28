

# kirby-webp

![license](https://img.shields.io/github/license/felixhaeberle/kirby3-webp)
![kirby](https://img.shields.io/badge/kirby-4%20%2B%205-black)
![php](https://img.shields.io/badge/php-%3E%3D8.1-777bb3)

**WebP & AVIF for Kirby** — serve modern image formats without thinking about it. 🚀

Two ways to use it, combinable:

1. **`<picture>` helpers (recommended)** — `$file->picture()`, `$file->webp()`, `$file->avif()` build
   responsive markup on top of Kirby's native thumb API. Works on every server, no rewrite rules,
   CDN-safe, lazy generation via Kirby's media system.
2. **Companion mode (classic)** — generates a `.webp`/`.avif` twin next to every uploaded image so
   your web server can transparently swap formats via content negotiation. This is what v1 of this
   plugin did — now with a fixed lifecycle (replace, rename, delete), AVIF, collision-safe naming
   and a CLI for bulk conversion.

## Requirements

- Kirby **4** or **5**
- PHP **8.1+**
- A thumb driver that can write WebP/AVIF: GD (default, needs `imagewebp`/`imageavif`),
  Imagick (`thumbs.driver => 'imagick'`, Kirby 5.1+) or ImageMagick binaries.
  Optional: [`rosell-dk/webp-convert`](https://github.com/rosell-dk/webp-convert) is picked up
  automatically for WebP if it is installed — useful when your GD build lacks WebP support.

## Installation

### Composer

```
composer require felixhaeberle/kirby3-webp
```

### Git submodule

```
git submodule add https://github.com/felixhaeberle/kirby3-webp.git site/plugins/kirby3-webp
```

### Download

[Download](https://github.com/felixhaeberle/kirby3-webp/archive/master.zip) and copy the folder to `site/plugins/kirby3-webp`.

## 1. Responsive `<picture>` (recommended)

```php
// template: one call, done
<?= $page->image()->picture([
    'sizes' => '(min-width: 60rem) 50vw, 100vw',
    'alt'   => 'Description',
]) ?>
```

renders (with `formats => ['avif', 'webp']`):

```html
<picture>
  <source type="image/avif" srcset="….avif 400w, ….avif 800w, …" sizes="…">
  <source type="image/webp" srcset="….webp 400w, ….webp 800w, …" sizes="…">
  <img src="….jpg" srcset="…" sizes="…" width="1600" height="1200"
       alt="Description" loading="lazy" decoding="async">
</picture>
```

- srcset widths are capped at the original image width (no upscaling)
- `width`/`height` are always set (no layout shift)
- formats the current thumb driver can't produce are skipped automatically
- `'priority' => true` switches to `loading="eager"` + `fetchpriority="high"` for LCP images

More helpers:

```php
$file->webp()                    // full-size WebP version (Kirby FileVersion)
$file->webp(['width' => 800])    // resized WebP thumb
$file->avif(['width' => 800])    // same for AVIF
snippet('webp/picture', ['image' => $image, 'options' => ['sizes' => '100vw']])
```

> [!TIP]
> If all you want is *every thumb as WebP*, Kirby can do that natively since 3.6 —
> set `'thumbs' => ['format' => 'webp']` in your config. This plugin builds on exactly
> that machinery and adds the multi-format `<picture>`/srcset layer, the companion
> workflow and the CLI on top.

## 2. Companion mode (classic v1 behavior)

Enable it in `site/config/config.php`:

```php
return [
    'felixhaeberle.kirby3-webp' => [
        'companion' => true,
        'formats'   => ['webp'],          // add 'avif' if you like
    ],
];
```

The old v1 config (`'kirby3-webp' => true`) keeps working.

Every uploaded JPEG/PNG now gets a twin (`photo.jpg` → `photo.webp`) that is
**kept in sync for the whole file lifecycle**: replacing regenerates it, renaming moves it,
deleting removes it (including its meta files).

### Serving companions

> [!IMPORTANT]
> Kirby serves page files through hashed `/media/...` URLs, where a rewrite cannot find the
> companion (each file gets its own hash folder). Companion mode is therefore for setups that
> serve images **directly from `/content`** (or via a custom `file::url` component / your own
> asset pipeline). If that's not you, use the `<picture>` helpers above — they work everywhere.

**Apache** (`.htaccess`), for the default naming scheme (`photo.webp`):

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTP_ACCEPT} image/webp
  RewriteCond %{DOCUMENT_ROOT}/$1.webp -f
  RewriteRule ^(.+)\.(?:jpe?g|png)$ $1.webp [T=image/webp,L]
</IfModule>

<IfModule mod_headers.c>
  <FilesMatch "\.(jpe?g|png)$">
    Header append Vary Accept
  </FilesMatch>
</IfModule>

AddType image/webp .webp
```

With `'scheme' => 'filename'` (`photo.jpg.webp`, collision-safe, recommended for new sites)
the rule gets even simpler:

```apache
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_FILENAME}.webp -f
RewriteRule .* %{REQUEST_URI}.webp [T=image/webp,L]
```

**nginx** (note: the `map` goes into the `http` block):

```nginx
map $http_accept $webp_suffix {
    default   "";
    "~*webp"  ".webp";
}

server {
    # ...

    # scheme 'filename' (photo.jpg.webp):
    location ~* \.(?:jpe?g|png)$ {
        add_header Vary Accept;
        try_files $uri$webp_suffix $uri =404;
    }

    # scheme 'name' (photo.webp) instead:
    # location ~* ^(?<base>.+)\.(?:jpe?g|png)$ {
    #     add_header Vary Accept;
    #     try_files $base$webp_suffix $uri =404;
    # }
}
```

### Keeping companions out of your template loops

Companions live in the content folder, so they show up in `$page->files()`. Filter them:

```php
foreach ($page->images()->withoutCompanions() as $image) { ... }
```

`$file->hasCompanion()` / `$file->companion()` give you access to the twin file.

## CLI

With the [Kirby CLI](https://github.com/getkirby/cli) installed:

```
kirby webp:generate            # companions for all existing images (site, pages, drafts, users)
kirby webp:generate --force    # regenerate even if up to date
kirby webp:clean               # remove all companions (incl. orphans)
```

## Options

All options live under the `felixhaeberle.kirby3-webp` namespace:

| Option | Default | Description |
|---|---|---|
| `companion` | `false` | Generate companion files on upload/replace/rename |
| `formats` | `['webp']` | Companion/picture formats: `webp`, `avif` |
| `quality` | `['webp' => 80, 'avif' => 60]` | Per-format quality (or a single int for all) |
| `sources` | `['image/jpeg', 'image/png']` | MIME types that get converted |
| `scheme` | `'name'` | Companion naming: `'name'` → `photo.webp` (v1-compatible), `'filename'` → `photo.jpg.webp` (collision-safe) |
| `skipLarger` | `true` | Drop companions that turn out larger than the original |
| `minSize` | `0` | Skip files smaller than this (bytes) |
| `engine` | `'auto'` | `auto` / `kirby` (thumb driver) / `webp-convert` (rosell-dk library) |
| `srcset` | `[400, 800, 1200, 1600, 2000]` | Default width ladder for `picture()` (override per call with `'widths'`) |
| `sizes` | `'100vw'` | Default `sizes` attribute for `picture()` |
| `webpConvert` | `[]` | Extra options passed through to rosell-dk/webp-convert |

**Legacy v1/v2 options keep working:** `kirby3-webp` (bool), `kirby3-webp.quality`,
`kirby3-webp.maxQuality`, `kirby3-webp.defaultQuality`, `kirby3-webp.metadata`,
`kirby3-webp.encoding`, `kirby3-webp.skip` (the last five apply to the webp-convert engine).

## Upgrading from v1/v2

1. Nothing to do for basic setups — `'kirby3-webp' => true` still enables companion mode.
2. The bundled `vendor/` directory is gone. Composer installs pull dependencies automatically;
   if you relied on the rosell-dk converter stack, `composer require rosell-dk/webp-convert`
   in your project and it is used again automatically (or set `'engine' => 'webp-convert'`).
   Without it, conversion now runs through Kirby's own thumb driver.
3. New sites: consider `'scheme' => 'filename'` (collision-safe) and the `<picture>` helpers.
4. Run `kirby webp:generate` once to fill gaps for existing content.

## Good to know

- Conversion runs through Kirby's thumb driver by default (GD/Imagick/IM) — CMYK JPEGs are
  converted to RGB, PNG alpha is preserved, animated GIFs and SVGs are never touched.
- Failed conversions never break an upload; they are logged to the PHP error log instead.
- Companion generation is skipped when the driver can't write the format
  (e.g. GD without `imagewebp`), and `picture()` silently drops such formats.

## Credits

- [rosell-dk/webp-convert](https://github.com/rosell-dk/webp-convert) (optional engine)
- [S1SYPHOS/kirby-webp](https://github.com/S1SYPHOS/kirby-webp) (original inspiration)
- [getkirby](https://getkirby.com)

## License

MIT

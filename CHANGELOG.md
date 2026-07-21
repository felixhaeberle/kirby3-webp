# Changelog

## 3.0.0 — 2026-07-21

Complete rewrite for **Kirby 4 & 5** (PHP 8.1–8.5). Existing v1/v2 configs keep working.

### Fixed

- **Kirby 4/5 compatibility** (#15): the removed `contentFileDirectory()` call, hook argument
  names that changed between Kirby majors, and the Kirby-2-era `response::error()` are gone.
- **PHP 8.2+ deprecation warnings** (#14): no more dynamic properties.
- Exceptions during conversion no longer break uploads: the old `catch (Exception)` referenced
  the non-existent `WebP\Exception` class, so every converter error crashed the panel upload.
- `file.replace` regenerates the companion reliably (stale companions are cleaned up first).
- Renaming a file now moves its companions along (`file.changeName` was not handled at all).
- Deleting `photo.png` no longer deletes `photo.webp` while `photo.jpg` still owns it.
- Uppercase extensions (`PHOTO.JPG`) are converted.
- Meta files of companions (`photo.webp.txt`, `photo.webp.en.txt`, …) are cleaned up on delete.
- The broken include of the non-existent `src/webp.php` and the wrong nested
  `vendor/autoload.php` path are gone; the plugin now autoloads itself for composer,
  submodule and manual installs alike.
- Corrected the Apache/nginx examples in the README (the old nginx snippet referenced an
  undefined variable and a wrong capture group) and documented when content negotiation
  actually applies on a stock Kirby setup.

### Added

- **Responsive `<picture>` helpers**: `$file->picture()`, `$file->webp()`, `$file->avif()`,
  `snippet('webp/picture')` — srcset/sizes, width/height (no CLS), lazy loading,
  `fetchpriority` support, no upscaling.
- **AVIF** companions via `'formats' => ['webp', 'avif']`.
- **Kirby CLI commands**: `kirby webp:generate [--force]` and `kirby webp:clean`
  (covers site files, all pages incl. drafts, and user files).
- **Collision-safe naming scheme**: `'scheme' => 'filename'` → `photo.jpg.webp`.
- `$files->withoutCompanions()`, `$file->companion()`, `$file->hasCompanion()`.
- `skipLarger` (default on): companions that end up larger than the original are dropped.
- `minSize`, `sources` (MIME filter), per-format `quality`, `engine` selection.
- Namespaced options (`felixhaeberle.kirby3-webp.*`) with full fallback to the v1 flat keys.
- Test suite (PHPUnit, real Kirby instance) + CI matrix for PHP 8.1–8.4 × Kirby 4/5.

### Changed

- Conversion uses **Kirby's own thumb driver** (GD/Imagick/IM) by default.
  [rosell-dk/webp-convert](https://github.com/rosell-dk/webp-convert) became an optional
  dependency — if it is installed, it is picked up automatically for WebP (`engine: auto`).
- The committed `vendor/` directory and `composer.lock` were removed from the repository.
- New WebP default quality is 80 (v1: 90). Set `'quality' => 90` to keep the old output.

### Deprecated

- `WebP\Convert` still works as an alias but is deprecated —
  use `FelixHaeberle\Webp\Webp::generate($file)` instead.

## 2.0.0 and earlier

See the [GitHub releases](https://github.com/felixhaeberle/kirby3-webp/releases) —
these versions targeted Kirby 3 and bundled rosell-dk/webp-convert in a committed
`vendor/` directory.

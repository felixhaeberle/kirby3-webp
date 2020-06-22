# kirby3-webp
![license](https://img.shields.io/github/license/felixhaeberle/kirby3-webp)
![issues-open](https://img.shields.io/github/issues/felixhaeberle/kirby3-webp)
![tweet](https://img.shields.io/twitter/url?url=https%3A%2F%2Fgithub.com%2Ffelixhaeberle%2Fkirby3-webp)
<br><br>Kirby 3 CMS plugin for converting **JPG**, **JPEG** and **PNG** into much smaller **WEBP** – speed up your website! 🚀🔥<br>

<img width="50%" src="https://user-images.githubusercontent.com/34959078/82842741-f6450900-9eda-11ea-8f63-1cc3fe1eb5f0.png"/>
<img width="50%" src="https://user-images.githubusercontent.com/34959078/83130175-332d1d80-a0de-11ea-850f-1fe8abc36e83.gif"/>
... and get the Lighthouse statistic you are dreaming of! 😍

## 🥁 Installation

### Composer
```
composer require felixhaeberle/kirby3-webp
```
### Git Submodule
```
git submodule add https://github.com/felixhaeberle/kirby3-webp.git site/plugins/kirby3-webp
```
### Clone or download
1. [Clone](https://github.com/felixhaeberle/kirby3-webp.git) or [download](https://github.com/felixhaeberle/kirby3-webp/archive/master.zip) this repository.
2. Unzip / Move the folder to `site/plugins`.

## 1️⃣  Activate the plugin
Activate the plugin in the `site/config/config.php` file with `kirby3-webp => true`.
```
return [
  'kirby3-webp' => true
]
```

## 2️⃣  Getting started
After installing and activating the plugin, you need to **serve webp files to the frontend** with your server configuration.

### Apache
Add the following to your `.htaccess`:
```
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Checking for WebP browser support ..
  RewriteCond %{HTTP_ACCEPT} image/webp

  # .. and if there's a WebP version for the requested image
  RewriteCond %{DOCUMENT_ROOT}/$1.webp -f

  # Well, then go for it & serve WebP instead
  RewriteRule (.+)\.(jpe?g|png)$ $1.webp [T=image/webp,E=accept:1]
</IfModule>

<IfModule mod_headers.c>
  Header append Vary Accept env=REDIRECT_accept
</IfModule>

<IfModule mod_mime.c>
  AddType image/webp .webp
</IfModule>
```

### NGINX
For NGINX, use the following virtual host configuration:
```
// First, make sure that NGINX' `mime.types` file includes 'image/webp webp'
include /etc/nginx/mime.types;

// Checking if HTTP's `ACCEPT` header contains 'webp'
map $http_accept $webp_suffix {
  default "";
  "~*webp" ".webp";
}

server {
  // ...

  // Checking if there's a WebP version for the requested image ..
  location ~* ^.+\.(jpe?g|png)$ {
    add_header Vary Accept;
    // .. and if so, serving it
    try_files $1$webp_ext $uri =404;
  }
}
```


## ⚙️  Options
You have multiple options when using `kirby3-webp` to configure it to your needs:

| Option  | Type  | Default  | Description  |
|---|---|---|---|
| `kirby3-webp.quality`  | Integer  | `90`  | See the [Auto quality](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-quality) section.  |
| `kirby3-webp.maxQuality`  | Integer  | `85`  | Only relevant for jpegs and when quality is set to "auto".  |
| `kirby3-webp.defaultQuality`  | Integer  | `85`  |   |
| `kirby3-webp.metadata`  | Array  | `"none"`  | Valid values: "all", "none", "exif", "icc", "xmp". Note: Currently only cwebp supports all values.<br><br> gd will always remove all metadata. ewww, imagick and gmagick can either strip all, or keep all (they will keep all, unless metadata is set to none)  |
| `kirby3-webp.encoding`  |  Array |  `"auto"` | See the [Auto selecting between lossless/lossy encoding](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-selecting-between-losslesslossy-encoding) section.  |
| `kirby3-webp.skip`  | Boolean  | `false`  | 	If true, conversion will be skipped (ie for skipping png conversion for some converters)  |

## 👏  Credit
- [S1SYPHOS/kirby-webp](https://github.com/S1SYPHOS/kirby-webp)
- [rosell-dk/webp-convert](https://github.com/rosell-dk/webp-convert)
- [getkirby](https://github.com/getkirby)

## 🤩  How this plugin works
![kirby3-webp](https://user-images.githubusercontent.com/34959078/82845567-60fb4200-9ee5-11ea-8214-df65ea018f27.gif)

## 🤯  Good to know
Sometimes, if the pictures are really big (multiple MB's) the converting process takes naturally longer, but does complete for sure. The .webp gets generated, but not selected, because if the client can accept .webp, the .webp is sent to the client instead of the .png, .jpeg or .jpg. Therefore, you are in need of the Apache/nginx configuration.


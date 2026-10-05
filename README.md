# Kookye Cat Gallery

A self-hosted, responsive photo gallery built with PHP, JavaScript, HTML, and CSS. It includes a private admin area for managing uploads, photo metadata, categories, visual themes, and gallery layouts.

> This repository contains the application code only. Personal photographs, original uploads, private configuration, credentials, and production deployment settings are intentionally excluded.

## Features

- Responsive gallery layouts, hover effects, palettes, and header styles
- Photo uploads with generated desktop and mobile WebP versions
- Per-photo titles, descriptions, categories, ordering, drafts, and favorites
- Optional map display for coordinates provided with uploaded photos
- Basic rich-text editors for the legal pages and project section
- Progressive web app support and offline page
- Optional AI-assisted photo descriptions; the gallery works without API keys

## Requirements

- PHP 8.1 or later
- PHP extensions: GD, EXIF, Fileinfo, JSON, and cURL (cURL is only needed for AI descriptions)
- Apache with `.htaccess` support, or an equivalent web server configuration
- HTTPS for the admin session cookie and production use

## Quick start

1. Clone this repository into a PHP-enabled web root.
2. Configure the web root to point at the repository root and enable URL rewriting.
3. Create a private configuration directory outside the web root and set `GALLERY_PRIVATE_DIR` in PHP-FPM or your web server.
4. Create `upload-auth.php` in that directory:

   ```php
   <?php
   return [
       'username' => 'choose-a-unique-username',
       'password_hash' => password_hash('replace-with-a-long-unique-password', PASSWORD_DEFAULT),
   ];
   ```

   Generate the hash with PHP and never commit this file or a real password.

5. Optionally create `config.php` in the same private directory to enable AI descriptions:

   ```php
   <?php
   return [
       'ai_enabled' => false,
       'gemini_api_key' => '',
       'openrouter_api_key' => '',
   ];
   ```

6. Make `img/`, `imagenes/`, and `data/` writable by the PHP user. Keep `img/` and `data/` blocked from direct HTTP access.
7. Visit the site over HTTPS and open `/admin.php` to sign in.

The application creates missing runtime folders when possible. Original uploads and generated image derivatives are runtime data and should be backed up separately from this repository.

## Configuration

- `GALLERY_PRIVATE_DIR`: absolute path outside the web root that contains `upload-auth.php`, `config.php`, and `site-settings.json`. Defaults to the local `var/` directory, which is blocked from HTTP access and ignored by Git.
- Public URL: set `GALLERY_PUBLIC_URL` to the canonical HTTPS URL for correct canonical links and sharing.

AI keys are optional and must only be stored in the private configuration directory. Never put credentials in source files, issues, or commits.

## Photo uploads

Use the admin panel to upload JPEG or PNG originals. The processor creates responsive WebP derivatives. The original uploads remain in `img/`; generated files go in `imagenes/desktop/` and `imagenes/mobile/`. Add only photos you own or have permission to publish. Remove location metadata from photos when you do not want it exposed.

## Development

Run the repository validation workflow locally with the required PHP extensions and Node.js installed:

```bash
php tests/site-settings.php
php tests/photo-navigation.php
node tests/photo-presets.cjs
node tests/photo-navigation.cjs
node tests/gallery-layout.cjs
node tests/site-design.cjs
node tests/admin-layout.cjs
node tests/photo-likes.cjs
node tests/photo-editor.cjs
```

## License

The source code is available under the **Kookye Cat Gallery Non-Commercial License** in [LICENSE](LICENSE). You may use, modify, and redistribute it for non-commercial purposes. Commercial use and use intended to generate financial benefit require separate written permission.

This is a source-available, non-commercial license, not an OSI-approved open-source license. External services, fonts, map libraries, and platform marks remain subject to their respective terms.

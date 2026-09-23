# Baseline

Baseline hardening, cleanup and mime-type support for WordPress. Everything is opt-out
through filters, so a consumer can disable any individual piece without forking.

**Status: private, pre-1.0.** The public surface is not stable yet and breaking changes
land without deprecation cycles until 1.0.

## Requirements

- PHP 8.1+
- WordPress 6.4+

## Install

```bash
composer require navas/baseline
```

## Boot

```php
use Navas\Baseline\Baseline;

Baseline::init();
```

## What it does

**Security** — security headers (including a configurable CSP), login hardening, author
enumeration blocking, XML-RPC and pingback disabling, REST user-endpoint restriction,
version-string removal, and blocking PHP execution in the uploads directory.

**Cleanup** — emoji scripts, dashboard widgets, admin-bar items, default widgets, the
"Howdy" greeting, and core update emails.

**Comments** — disable comments entirely, or just the comment blocks.

**Mime types** — opt-in SVG, JSON and Lottie uploads, each sanitised or validated on the
way in, behind a capability gate.

**Utilities** — duplicate-post support.

## Hooks

Every hook is prefixed `navas_baseline_`, derived from `Core\Constants::HOOK_PREFIX` —
never hardcoded, so the prefix lives in one place.

Enable an opt-in mime type:

```php
add_filter( 'navas_baseline_enable_svg_uploads', '__return_true' );
add_filter( 'navas_baseline_enable_lottie_uploads', '__return_true' );
add_filter( 'navas_baseline_enable_json_uploads', '__return_true' );
```

Change who may upload them:

```php
add_filter( 'navas_baseline_mime_upload_capability', fn() => 'edit_posts' );
```

Adjust the security headers:

```php
add_filter( 'navas_baseline_security_headers', function ( array $headers ) {
	$headers['X-Frame-Options'] = 'SAMEORIGIN';
	return $headers;
} );

add_filter( 'navas_baseline_security_headers_csp', function ( array $directives ) {
	$directives['script-src'][] = 'https://example.com';
	return $directives;
} );
```

Turn a piece off:

```php
add_filter( 'navas_baseline_disable_comments', '__return_false' );
add_filter( 'navas_baseline_login_security', '__return_false' );
add_filter( 'navas_baseline_enable_security_headers', '__return_false' );
```

## Development

```bash
composer test
composer lint
```

## License

GPL-2.0-or-later

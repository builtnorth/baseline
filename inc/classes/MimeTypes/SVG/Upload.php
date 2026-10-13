<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * SVG Upload
 * ------------------------------------------------------------------
 *
 * Allow SVG uploads, record their dimensions and fix the thumbnail display.
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\MimeTypes\SVG;

use Siratik\Baseline\MimeTypes\Capability;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Upload
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		add_filter('upload_mimes', [$this, 'add_svg_mime_type']);
		add_filter('wp_check_filetype_and_ext', [$this, 'check_svg_filetype'], 10, 4);
		add_filter('wp_generate_attachment_metadata', [$this, 'add_svg_dimensions'], 10, 2);
		add_action('admin_head', [$this, 'fix_svg_thumbnail_display']);
	}

	/**
	 * Add SVG mime type.
	 */
	public function add_svg_mime_type($mimes)
	{
		if (!Capability::current_user_can_upload('svg')) {
			return $mimes;
		}

		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Check SVG file type.
	 */
	public function check_svg_filetype($data, $file, $filename, $mimes)
	{
		$file_extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
		if ('svg' !== $file_extension) {
			return $data;
		}

		$filetype = wp_check_filetype($filename, $mimes);

		if (empty($filetype['ext']) || empty($filetype['type'])) {
			return $data;
		}

		return [
			'ext'             => $filetype['ext'],
			'type'            => $filetype['type'],
			'proper_filename' => $data['proper_filename']
		];
	}

	/**
	 * Record an SVG's width and height in its attachment metadata.
	 *
	 * WordPress only measures raster images, so SVGs otherwise render with
	 * width="1" height="1" and give blocks no aspect ratio to work with.
	 *
	 * @param mixed $metadata      Attachment metadata.
	 * @param mixed $attachment_id Attachment ID.
	 * @return mixed
	 */
	public function add_svg_dimensions($metadata, $attachment_id)
	{
		if (!is_array($metadata) || !empty($metadata['width']) || 'image/svg+xml' !== get_post_mime_type((int) $attachment_id)) {
			return $metadata;
		}

		$file = get_attached_file((int) $attachment_id);
		if (!$file || !is_readable($file)) {
			return $metadata;
		}

		$dimensions = self::get_dimensions((string) file_get_contents($file));
		if (null === $dimensions) {
			return $metadata;
		}

		$metadata['width']  = $dimensions['width'];
		$metadata['height'] = $dimensions['height'];

		return $metadata;
	}

	/**
	 * Read width and height from an SVG's root element.
	 *
	 * Uses the width and height attributes when both are plain numbers (or px),
	 * otherwise the viewBox size.
	 *
	 * @return array{width: int, height: int}|null Null when no usable size is found.
	 */
	public static function get_dimensions(string $svg): ?array
	{
		if (!preg_match('/<svg\b[^>]*>/i', $svg, $tag)) {
			return null;
		}

		$attribute = static function (string $name) use ($tag): ?string {
			return preg_match('/\s' . $name . '\s*=\s*(["\'])(.*?)\1/i', $tag[0], $match) ? trim($match[2]) : null;
		};
		$length = static function (?string $value): ?float {
			return null !== $value && preg_match('/^(\d*\.?\d+)(px)?$/i', $value, $match) ? (float) $match[1] : null;
		};

		$width  = $length($attribute('width'));
		$height = $length($attribute('height'));

		if (null === $width || null === $height) {
			$view_box = preg_split('/[\s,]+/', (string) $attribute('viewBox'));
			if (!is_array($view_box) || 4 !== count($view_box) || !is_numeric($view_box[2]) || !is_numeric($view_box[3])) {
				return null;
			}
			$width  = (float) $view_box[2];
			$height = (float) $view_box[3];
		}

		$width  = (int) round($width);
		$height = (int) round($height);

		return $width > 0 && $height > 0 ? ['width' => $width, 'height' => $height] : null;
	}

	/**
	 * Fix SVG thumbnail display.
	 */
	public function fix_svg_thumbnail_display()
	{
		echo '<style type="text/css">
                .attachment-266x266, .thumbnail img {
                    width: 100% !important;
                    height: auto !important;
                }
              </style>';
	}
}

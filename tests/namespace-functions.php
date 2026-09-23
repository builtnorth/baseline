<?php
/**
 * Pre-define WordPress functions in namespaces to prevent undefined function errors
 * 
 * This file creates stub functions in the namespaces used by this package's classes.
 * These stubs will be replaced by WP_Mock during testing.
 * 
 * @package Navas\Baseline\Tests
 */

// Cleanup namespace functions
namespace Navas\Baseline\Cleanup {
	if (!function_exists(__NAMESPACE__ . '\remove_action')) {
		function remove_action() { return null; }
		function remove_filter() { return null; }
		function add_action() { return null; }
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
		function __() { return func_get_arg(0); }
		function update_option() { return true; }
		function get_option() { return array(); }
	}
}

// Security namespace functions
namespace Navas\Baseline\Security {
	if (!function_exists(__NAMESPACE__ . '\add_action')) {
		function add_action() { return null; }
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
		function is_user_logged_in() { return false; }
		function is_admin() { return false; }
	}
}

// MimeTypes namespace functions
namespace Navas\Baseline\MimeTypes {
	if (!function_exists(__NAMESPACE__ . '\add_filter')) {
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
	}
}

// MimeTypes\SVG namespace functions
namespace Navas\Baseline\MimeTypes\SVG {
	if (!function_exists(__NAMESPACE__ . '\add_filter')) {
		function add_filter() { return null; }
		function current_user_can() { return false; }
		function __() { return func_get_arg(0); }
		function wp_die() { die(); }
	}
}

// Utilities namespace functions
namespace Navas\Baseline\Utilities {
	if (!function_exists(__NAMESPACE__ . '\add_action')) {
		function add_action() { return null; }
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
		function doing_filter() { return false; }
	}
}

// Main namespace functions
namespace Navas\Baseline {
	if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
		function apply_filters() { return func_get_arg(1); }
	}
}
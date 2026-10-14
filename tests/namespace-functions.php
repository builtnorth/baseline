<?php
/**
 * Pre-define WordPress functions in namespaces to prevent undefined function errors
 * 
 * This file creates stub functions in the namespaces used by this package's classes.
 * These stubs will be replaced by WP_Mock during testing.
 * 
 * @package Siratik\Baseline\Tests
 */

// Cleanup namespace functions
namespace Siratik\Baseline\Cleanup {
	if (!function_exists(__NAMESPACE__ . '\remove_action')) {
		function remove_action() { return null; }
		function remove_filter() { return null; }
		// Hook registrations and option writes are recorded so tests can assert them.
		function add_action(...$args) { $GLOBALS['siratik_baseline_test_hooks'][] = ['action', ...$args]; return null; }
		function add_filter(...$args) { $GLOBALS['siratik_baseline_test_hooks'][] = ['filter', ...$args]; return null; }
		function apply_filters() { return func_get_arg(1); }
		function __() { return func_get_arg(0); }
		function update_option(...$args) { $GLOBALS['siratik_baseline_test_option_writes'][] = $args; return true; }
		function get_option() { return array(); }
	}
}

// Security namespace functions
namespace Siratik\Baseline\Security {
	if (!function_exists(__NAMESPACE__ . '\add_action')) {
		// Hook registrations are recorded so tests can assert them.
		function add_action(...$args) { $GLOBALS['siratik_baseline_test_hooks'][] = ['action', ...$args]; return null; }
		function add_filter(...$args) { $GLOBALS['siratik_baseline_test_hooks'][] = ['filter', ...$args]; return null; }
		function apply_filters() { return func_get_arg(1); }
		function is_user_logged_in() { return false; }
		function is_admin() { return false; }
	}
}

// MimeTypes namespace functions
namespace Siratik\Baseline\MimeTypes {
	if (!function_exists(__NAMESPACE__ . '\add_filter')) {
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
	}
}

// MimeTypes\SVG namespace functions
namespace Siratik\Baseline\MimeTypes\SVG {
	if (!function_exists(__NAMESPACE__ . '\add_filter')) {
		function add_filter() { return null; }
		function current_user_can() { return false; }
		function __() { return func_get_arg(0); }
		function wp_die() { die(); }
	}
}

// Utilities namespace functions
namespace Siratik\Baseline\Utilities {
	if (!function_exists(__NAMESPACE__ . '\add_action')) {
		function add_action() { return null; }
		function add_filter() { return null; }
		function apply_filters() { return func_get_arg(1); }
		function doing_filter() { return false; }
	}
}

// Main namespace functions
namespace Siratik\Baseline {
	if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
		function apply_filters() { return func_get_arg(1); }
	}
}
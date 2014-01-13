<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Get path to file from include_path
 *
 * @param string $file_path
 * @param string $new_file_path
 * @return boolean
 * @staticvar array|null
 */

//  $file_path, &$new_file_path

function smarty_core_get_include_path(&$params, &$smarty)
{
	static $_include_path = null;

	if (function_exists('stream_resolve_include_path')) {
		// available since PHP 5.3.2
		return stream_resolve_include_path($params['file_path']);
	}

	if ($_include_path === null) {
		$_include_path = explode(PATH_SEPARATOR, get_include_path());
	}

	foreach ($_include_path as $_path) {
		if (file_exists($_path . DIRECTORY_SEPARATOR . $params['file_path'])) {
			return $_path . DIRECTORY_SEPARATOR . $params['file_path'];
		}
	}

	return false;
}

/* vim: set expandtab: */

?>

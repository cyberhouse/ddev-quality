<?php
declare(strict_types=1);

/*
 * This file is (c) 2026 by Cyberhouse GmbH
 *
 * It is free software; you can redistribute it and/or
 * modify it under the terms of the GPLv3 license
 *
 * For the full copyright and license information see
 * <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// #ddev-generated

// Router of the PHP built-in server that serves the test instance. Without one the server answers
// every URL with a file extension that is no file - every page, if the site has a ".html" suffix -
// with a 404. index.php itself cannot be the router, the server would then report the page path
// as SCRIPT_NAME.
if (is_file($_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';

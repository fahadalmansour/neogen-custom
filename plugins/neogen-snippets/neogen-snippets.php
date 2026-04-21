<?php
/**
 * Plugin Name: NeoGen Snippets
 * Description: Toggle-able PHP snippets for NeoGen Store.
 * Version: 1.0.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

// Auto-require every .php snippet in this directory except this file
$dir = __DIR__;
foreach (glob($dir . '/*.php') as $file) {
    if (basename($file) === basename(__FILE__)) continue;
    require_once $file;
}

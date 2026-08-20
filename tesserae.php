<?php

/*
 * Plugin Name:       Tesserae
 * Plugin URI:        https://github.com/vincentvdree/tesserae
 * Description:       A code-first, frontend block editor for WordPress. Blocks are plain PHP templates plus a YAML config; editing happens on the live page. No Gutenberg, no ACF.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Vincent van der Ree
 * Author URI:        https://github.com/vincentvdree
 * License:           GPL-3.0-only
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tesserae
 */

declare(strict_types=1);

namespace Tesserae;

if (!\defined('ABSPATH')) {
    exit;
}

// Tesserae has no Composer dependencies of its own, so a small PSR-4-style
// autoloader is all `src/` needs — no `vendor/autoload.php` is shipped or
// required at runtime. (composer.json still exists, but only to pull in
// PHPUnit for the test suite; see tests/README.md.)
spl_autoload_register(static function (string $class): void {
    $prefix = 'Tesserae\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__.'/src/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__.'/functions.php';

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->boot(plugins_url('', __FILE__));
});

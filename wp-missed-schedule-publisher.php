<?php
/**
 * Plugin Name: Missed Schedule Publisher
 * Description: Catches scheduled posts that have been missed and publishes them.
 * Author: Austin Passy
 * Author URI: https://austin.passy.co/
 * Version: 1.0.0
 * Requires at least: 6.6
 * Tested up to: 6.7.1
 * Requires PHP: 8.1
 * Plugin URI: https://github.com/thefrosty/wp-missed-schedule-publisher
 * GitHub Plugin URI: https://github.com/thefrosty/wp-missed-schedule-publisher
 * Primary Branch: main
 * Release Asset: true
 */

namespace TheFrosty\WpMissedSchedulePublisher;

defined('ABSPATH') || exit;

use TheFrosty\WpUtilities\Plugin\PluginFactory;
use TheFrosty\WpUtilities\WpAdmin\DisablePluginUpdateCheck;
use function add_action;
use function defined;
use function is_admin;
use function is_readable;

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('missed-schedule-publisher');

if (is_admin()) {
    $plugin
        ->add(new DisablePluginUpdateCheck())
        ->add(new Integration\SimpleHistory())
        ->add(new WpAdmin\MissedSchedulePublisher());
}

add_action('plugins_loaded', static function () use ($plugin): void {
    $plugin->initialize();
});

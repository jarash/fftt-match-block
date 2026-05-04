<?php
/**
 * Plugin Name: FFTT Match Block
 * Description: Bloc Gutenberg pour afficher un match FFTT (score des equipes + parties avec vainqueur).
 * Version: 1.0.0
 * Author: Vincent Rousseau
 * Update URI: https://github.com/jarash/fftt-match-block
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FFTT_MATCH_BLOCK_VERSION', '1.0.0');
define('FFTT_MATCH_BLOCK_PATH', plugin_dir_path(__FILE__));
define('FFTT_MATCH_BLOCK_URL', plugin_dir_url(__FILE__));

require_once FFTT_MATCH_BLOCK_PATH . 'vendor/autoload.php';

require_once FFTT_MATCH_BLOCK_PATH . 'includes/Settings.php';
require_once FFTT_MATCH_BLOCK_PATH . 'includes/Api.php';
require_once FFTT_MATCH_BLOCK_PATH . 'includes/Rest.php';
require_once FFTT_MATCH_BLOCK_PATH . 'includes/Block.php';

// Auto-update depuis GitHub Releases
add_action('init', static function (): void {
    if (!class_exists(\YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
        return;
    }
    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/VOTRE_USERNAME/fftt-match-block',
        __FILE__,
        'fftt-match-block'
    );
    $updateChecker->getVcsApi()->enableReleaseAssets();
});

add_action('plugins_loaded', static function (): void {
    FFTTMatchBlock\Settings::boot();
    FFTTMatchBlock\Rest::boot();
    FFTTMatchBlock\Block::boot();
});

<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    public const OPTION_KEY = 'fftt_match_block_options';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_post_fftt_match_block_clear_cache', [self::class, 'clearCache']);
    }

    public static function defaults(): array
    {
        return [
            'api_id' => '',
            'api_password' => '',
            'club_id' => '',
            'matches_limit' => 8,
            'render_cache_ttl' => 600,
        ];
    }

    public static function getOptions(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return wp_parse_args($raw, self::defaults());
    }

    public static function registerMenu(): void
    {
        add_options_page(
            'FFTT Match Block',
            'FFTT Match Block',
            'manage_options',
            'fftt-match-block',
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting(
            'fftt_match_block_group',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default' => self::defaults(),
            ]
        );

        add_settings_section(
            'fftt_match_block_main',
            'Parametres FFTT',
            static function (): void {
                echo '<p>Renseigne les identifiants API FFTT et l\'ID club. L\'equipe sera choisie directement dans le bloc.</p>';
            },
            'fftt-match-block'
        );

        self::addField('api_id', 'Identifiant API FFTT');
        self::addField('api_password', 'Mot de passe / cle API FFTT', 'password');
        self::addField('club_id', 'ID Club FFTT');
        self::addField('matches_limit', 'Nombre de matchs proposes', 'number');
        self::addField('render_cache_ttl', 'Duree du cache HTML (secondes, 0 = desactive)', 'number');
    }

    private static function addField(string $key, string $label, string $type = 'text'): void
    {
        add_settings_field(
            $key,
            $label,
            static function () use ($key, $type): void {
                $opts = self::getOptions();
                $value = $opts[$key] ?? '';
                $attrs = [
                    'class' => 'regular-text',
                ];

                if ($type === 'number') {
                    $attrs['min'] = '0';
                    $attrs['max'] = '86400';
                    $attrs['step'] = '1';
                    $attrs['class'] = 'small-text';

                    if ($key === 'matches_limit') {
                        $attrs['min'] = '1';
                        $attrs['max'] = '20';
                    }
                }

                printf(
                    '<input type="%s" name="%s[%s]" value="%s" %s />',
                    esc_attr($type),
                    esc_attr(self::OPTION_KEY),
                    esc_attr($key),
                    esc_attr((string) $value),
                    self::attrs($attrs)
                );
            },
            'fftt-match-block',
            'fftt_match_block_main'
        );
    }

    private static function attrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            $parts[] = sprintf('%s="%s"', esc_attr((string) $name), esc_attr((string) $value));
        }

        return implode(' ', $parts);
    }

    public static function sanitize($input): array
    {
        $defaults = self::defaults();
        if (!is_array($input)) {
            return $defaults;
        }

        $out = $defaults;
        $out['api_id'] = sanitize_text_field((string) ($input['api_id'] ?? ''));
        $out['api_password'] = sanitize_text_field((string) ($input['api_password'] ?? ''));
        $out['club_id'] = preg_replace('/[^0-9]/', '', (string) ($input['club_id'] ?? '')) ?: '';

        $limit = (int) ($input['matches_limit'] ?? $defaults['matches_limit']);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 20) {
            $limit = 20;
        }
        $out['matches_limit'] = $limit;

        $ttl = (int) ($input['render_cache_ttl'] ?? $defaults['render_cache_ttl']);
        if ($ttl < 0) {
            $ttl = 0;
        }
        if ($ttl > 86400) {
            $ttl = 86400;
        }
        $out['render_cache_ttl'] = $ttl;

        return $out;
    }

    public static function clearCache(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('fftt_match_block_clear_cache');

        global $wpdb;
        $deleted = 0;

        if ($wpdb instanceof \wpdb) {
            $prefix = Block::getCacheKeyPrefix();
            $transientPrefix = $wpdb->esc_like('_transient_' . $prefix) . '%';
            $timeoutPrefix = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';

            $deleted += (int) $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $transientPrefix
                )
            );
            $deleted += (int) $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $timeoutPrefix
                )
            );
        }

        $url = add_query_arg(
            [
                'page' => 'fftt-match-block',
                'cache_cleared' => max(0, $deleted),
            ],
            admin_url('options-general.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $cacheCleared = isset($_GET['cache_cleared']) ? (int) $_GET['cache_cleared'] : -1;
        ?>
        <div class="wrap">
            <h1>FFTT Match Block</h1>
            <p>Ce plugin propose dans Gutenberg la liste des equipes du club configure et affiche les matchs de l'equipe choisie dans le bloc.</p>

            <?php if ($cacheCleared >= 0): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sprintf('Cache FFTT vide. %d entree(s) supprimee(s).', $cacheCleared)); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('fftt_match_block_group');
                do_settings_sections('fftt-match-block');
                submit_button('Enregistrer');
                ?>
            </form>

            <hr />
            <h2>Maintenance du cache</h2>
            <p>Vide manuellement le cache HTML du bloc FFTT.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fftt_match_block_clear_cache" />
                <?php wp_nonce_field('fftt_match_block_clear_cache'); ?>
                <?php submit_button('Vider le cache HTML FFTT', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}

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
    }

    public static function defaults(): array
    {
        return [
            'api_id' => '',
            'api_password' => '',
            'club_id' => '',
            'matches_limit' => 8,
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
                    $attrs['min'] = '1';
                    $attrs['max'] = '20';
                    $attrs['step'] = '1';
                    $attrs['class'] = 'small-text';
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

        return $out;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        ?>
        <div class="wrap">
            <h1>FFTT Match Block</h1>
            <p>Ce plugin propose dans Gutenberg la liste des equipes du club configure et affiche les matchs de l'equipe choisie dans le bloc.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields('fftt_match_block_group');
                do_settings_sections('fftt-match-block');
                submit_button('Enregistrer');
                ?>
            </form>
        </div>
        <?php
    }
}

<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

if (!defined('ABSPATH')) {
    exit;
}

final class Block
{
    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        wp_register_script(
            'fftt-match-block-editor',
            FFTT_MATCH_BLOCK_URL . 'assets/block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch'],
            FFTT_MATCH_BLOCK_VERSION,
            true
        );

        wp_register_style(
            'fftt-match-block-style',
            FFTT_MATCH_BLOCK_URL . 'assets/style.css',
            [],
            FFTT_MATCH_BLOCK_VERSION
        );

        register_block_type('fftt/match', [
            'editor_script' => 'fftt-match-block-editor',
            'style' => 'fftt-match-block-style',
            'render_callback' => [self::class, 'render'],
            'attributes' => [
                'matchLink' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'matchLabel' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    public static function render(array $attributes): string
    {
        $link = isset($attributes['matchLink']) ? (string) $attributes['matchLink'] : '';
        $clubA = isset($attributes['matchClubA']) ? (string) $attributes['matchClubA'] : '';
        $clubB = isset($attributes['matchClubB']) ? (string) $attributes['matchClubB'] : '';
        if ($link === '') {
            return '<p>Choisis un match FFTT dans le bloc.</p>';
        }

        try {
            $details = Api::getMatchDetailsByLink($link, $clubA, $clubB);
        } catch (\Throwable $e) {
            return '<p>Impossible de charger ce match FFTT : ' . esc_html($e->getMessage()) . '</p>';
        }

        ob_start();
        ?>
        <div class="fftt-match-block">
            <div class="fftt-match-score">
                <span class="fftt-team"><?php echo esc_html($details['teamA']); ?></span>
                <strong class="fftt-score"><?php echo (int) $details['scoreA']; ?> - <?php echo (int) $details['scoreB']; ?></strong>
                <span class="fftt-team"><?php echo esc_html($details['teamB']); ?></span>
            </div>

            <table class="fftt-parties-table">
                <thead>
                    <tr>
                        <th>Joueur 1</th>
                        <th>Score</th>
                        <th>Joueur 2</th>
                        <th>Details des sets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details['parties'] as $partie): ?>
                        <?php
                        $winnerSide = isset($partie['winnerSide']) ? (string) $partie['winnerSide'] : '';
                        $playerA = (string) ($partie['playerA'] ?? '');
                        $playerB = (string) ($partie['playerB'] ?? '');
                        $sets = isset($partie['setDetails']) && is_array($partie['setDetails']) ? $partie['setDetails'] : [];
                        ?>
                        <tr>
                            <td>
                                <?php if ($winnerSide === 'A'): ?>
                                    <strong><?php echo esc_html($playerA); ?></strong>
                                <?php else: ?>
                                    <?php echo esc_html($playerA); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $partie['scoreA']; ?> - <?php echo (int) $partie['scoreB']; ?></td>
                            <td>
                                <?php if ($winnerSide === 'B'): ?>
                                    <strong><?php echo esc_html($playerB); ?></strong>
                                <?php else: ?>
                                    <?php echo esc_html($playerB); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($sets)): ?>
                                    <span class="fftt-set-score">-</span>
                                <?php else: ?>
                                    <span class="fftt-sets">
                                        <?php foreach ($sets as $setScore): ?>
                                            <span class="fftt-set-score"><?php echo esc_html((string) $setScore); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}

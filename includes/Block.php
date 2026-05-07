<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

if (!defined('ABSPATH')) {
    exit;
}

final class Block
{
    private const CACHE_KEY_PREFIX = 'fftt_mb_html_';

    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);
        add_action('save_post', [self::class, 'invalidateCacheOnPostUpdate'], 10, 3);
    }

    public static function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }

    private static function getCacheTtl(): int
    {
        $opts = Settings::getOptions();
        $defaultTtl = (int) ($opts['render_cache_ttl'] ?? 600);
        $ttl = (int) apply_filters('fftt_match_block_render_cache_ttl', $defaultTtl);
        if ($ttl < 0) {
            $ttl = 0;
        }

        return $ttl;
    }

    private static function getCacheKey(string $link, string $clubA, string $clubB): string
    {
        $payload = [
            'link' => $link,
            'clubA' => $clubA,
            'clubB' => $clubB,
            'blog' => (int) get_current_blog_id(),
        ];

        return self::CACHE_KEY_PREFIX . md5((string) wp_json_encode($payload));
    }

    public static function deleteCacheForMatch(string $link, string $clubA = '', string $clubB = ''): void
    {
        $link = trim($link);
        if ($link === '') {
            return;
        }

        delete_transient(self::getCacheKey($link, $clubA, $clubB));
    }

    private static function collectCacheKeysFromBlocks(array $blocks, array &$keys): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockName = isset($block['blockName']) ? (string) $block['blockName'] : '';
            if ($blockName === 'fftt/match') {
                $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
                $link = isset($attrs['matchLink']) ? (string) $attrs['matchLink'] : '';
                if ($link !== '') {
                    $clubA = isset($attrs['matchClubA']) ? (string) $attrs['matchClubA'] : '';
                    $clubB = isset($attrs['matchClubB']) ? (string) $attrs['matchClubB'] : '';
                    $keys[] = self::getCacheKey($link, $clubA, $clubB);
                }
            }

            $innerBlocks = isset($block['innerBlocks']) && is_array($block['innerBlocks']) ? $block['innerBlocks'] : [];
            if (!empty($innerBlocks)) {
                self::collectCacheKeysFromBlocks($innerBlocks, $keys);
            }
        }
    }

    public static function invalidateCacheOnPostUpdate(int $postId, \WP_Post $post, bool $update): void
    {
        if (!$update) {
            return;
        }
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $content = (string) ($post->post_content ?? '');
        if ($content === '' || strpos($content, '<!-- wp:fftt/match') === false) {
            return;
        }
        if (!function_exists('parse_blocks')) {
            return;
        }

        $blocks = parse_blocks($content);
        if (!is_array($blocks) || empty($blocks)) {
            return;
        }

        $keys = [];
        self::collectCacheKeysFromBlocks($blocks, $keys);
        if (empty($keys)) {
            return;
        }

        foreach (array_unique($keys) as $cacheKey) {
            delete_transient((string) $cacheKey);
        }
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

        wp_register_style(
            'fftt-match-block-editor-style',
            FFTT_MATCH_BLOCK_URL . 'assets/editor.css',
            ['wp-edit-blocks', 'fftt-match-block-style'],
            FFTT_MATCH_BLOCK_VERSION
        );

        wp_register_script(
            'fftt-match-block-view',
            FFTT_MATCH_BLOCK_URL . 'assets/view.js',
            [],
            FFTT_MATCH_BLOCK_VERSION,
            true
        );

        register_block_type('fftt/match', [
            'editor_script' => 'fftt-match-block-editor',
            'editor_style' => 'fftt-match-block-editor-style',
            'view_script' => 'fftt-match-block-view',
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
                'teamA' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'teamB' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'scoreA' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'scoreB' => [
                    'type' => 'number',
                    'default' => 0,
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

        $cacheKey = self::getCacheKey($link, $clubA, $clubB);
        $cachedHtml = get_transient($cacheKey);
        if (is_string($cachedHtml) && $cachedHtml !== '') {
            return $cachedHtml;
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

            <?php
            $parties = isset($details['parties']) && is_array($details['parties']) ? $details['parties'] : [];
            $hiddenCount = max(0, count($parties) - 4);
            $tableId = 'fftt-parties-' . substr(md5($cacheKey), 0, 10);
            ?>
            <div class="fftt-parties-wrap<?php echo $hiddenCount > 0 ? ' is-collapsed' : ''; ?>" id="<?php echo esc_attr($tableId); ?>">
                <table class="fftt-parties-table">
                    <thead>
                        <tr>
                            <th>Joueur 1</th>
                            <th>Score</th>
                            <th>Joueur 2</th>
                            <th>Sets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parties as $index => $partie): ?>
                            <?php
                            $winnerSide = isset($partie['winnerSide']) ? (string) $partie['winnerSide'] : '';
                            $playerA = (string) ($partie['playerA'] ?? '');
                            $playerB = (string) ($partie['playerB'] ?? '');
                            $sets = isset($partie['setDetails']) && is_array($partie['setDetails']) ? $partie['setDetails'] : [];
                            $rowClass = $index >= 4 ? 'fftt-partie-hidden' : '';
                            ?>
                            <tr class="<?php echo esc_attr($rowClass); ?>">
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

                <?php if ($hiddenCount > 0): ?>
                    <div class="fftt-parties-fade" aria-hidden="true"></div>
                    <button type="button" class="fftt-parties-toggle" data-target="<?php echo esc_attr($tableId); ?>" aria-expanded="false">
                        <span class="fftt-parties-toggle-label">Voir toutes les parties</span>
                        <span class="fftt-parties-toggle-arrow" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php

        $html = (string) ob_get_clean();
        $ttl = self::getCacheTtl();

        if ($ttl > 0 && $html !== '') {
            set_transient($cacheKey, $html, $ttl);
        }

        return $html;
    }
}

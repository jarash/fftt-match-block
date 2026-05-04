<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

if (!defined('ABSPATH')) {
    exit;
}

final class Rest
{
    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('fftt-match/v1', '/teams', [
            'methods' => 'GET',
            'permission_callback' => [self::class, 'canEdit'],
            'callback' => [self::class, 'teams'],
        ]);

        register_rest_route('fftt-match/v1', '/matches', [
            'methods' => 'GET',
            'permission_callback' => [self::class, 'canEdit'],
            'callback' => [self::class, 'matches'],
            'args' => [
                'teamId' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]);

        register_rest_route('fftt-match/v1', '/match-details', [
            'methods' => 'GET',
            'permission_callback' => [self::class, 'canEdit'],
            'callback' => [self::class, 'matchDetails'],
            'args' => [
                'lien' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'clubA' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'clubB' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    public static function canEdit(): bool
    {
        return current_user_can('edit_posts');
    }

    public static function teams(): \WP_REST_Response
    {
        try {
            $items = Api::listTeamsByClub();
            return new \WP_REST_Response(['items' => $items], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'message' => $e->getMessage(),
                'items' => [],
            ], 400);
        }
    }

    public static function matches(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $teamId = (int) $request->get_param('teamId');
            $items = Api::listLatestMatches($teamId);
            return new \WP_REST_Response(['items' => $items], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'message' => $e->getMessage(),
                'items' => [],
            ], 400);
        }
    }

    public static function matchDetails(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $lien = sanitize_text_field((string) $request->get_param('lien'));
            $clubA = sanitize_text_field((string) $request->get_param('clubA'));
            $clubB = sanitize_text_field((string) $request->get_param('clubB'));
            $details = Api::getMatchDetailsByLink($lien, $clubA, $clubB);
            return new \WP_REST_Response(['item' => $details], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

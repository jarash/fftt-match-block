<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

use Alamirault\FFTTApi\Service\FFTTApi;

if (!defined('ABSPATH')) {
    exit;
}

final class Api
{
    private const CACHE_KEY_PREFIX = 'fftt_mb_api_';

    private static function loadVendor(): void
    {
        $autoload = FFTT_MATCH_BLOCK_PATH . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    public static function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }

    private static function getCacheTtl(): int
    {
        $opts = Settings::getOptions();
        $ttl = (int) ($opts['api_cache_ttl'] ?? 3600);
        $ttl = (int) apply_filters('fftt_match_block_api_cache_ttl', $ttl);
        if ($ttl < 0) {
            $ttl = 0;
        }

        return $ttl;
    }

    private static function getCacheKey(string $group, array $payload = []): string
    {
        $payload['group'] = $group;
        $payload['blog'] = (int) get_current_blog_id();

        return self::CACHE_KEY_PREFIX . md5((string) wp_json_encode($payload));
    }

    private static function getTodayMarker(): string
    {
        return wp_date('Y-m-d');
    }

    private static function getTodayCutoffTimestamp(): int
    {
        $now = new \DateTimeImmutable('now', wp_timezone());
        return $now->setTime(23, 59, 59)->getTimestamp();
    }

    private static function formatMatchDateLabel(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return wp_date('d/m/Y', $timestamp);
    }

    private static function getCurrentPhase(): int
    {
        $month = (int) wp_date('n');
        return $month >= 1 && $month <= 6 ? 2 : 1;
    }

    private static function extractPhase(string $label): int
    {
        if (preg_match('/\bphase\s+([12])\b/i', $label, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private static function stripPhaseSuffix(string $label): string
    {
        return trim((string) preg_replace('/\s*-\s*phase\s+[12]\b/i', '', $label));
    }

    private static function getPhasePriority(int $phase): int
    {
        if ($phase === self::getCurrentPhase()) {
            return 2;
        }

        if ($phase > 0) {
            return 1;
        }

        return 0;
    }

    private static function remember(string $cacheKey, callable $resolver): array
    {
        $ttl = self::getCacheTtl();
        if ($ttl <= 0) {
            return $resolver();
        }

        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $value = $resolver();
        set_transient($cacheKey, $value, $ttl);

        return $value;
    }

    private static function createClient(): FFTTApi
    {
        self::loadVendor();

        $opts = Settings::getOptions();
        $apiId = (string) ($opts['api_id'] ?? '');
        $apiPassword = (string) ($opts['api_password'] ?? '');

        if ($apiId === '' || $apiPassword === '') {
            throw new \RuntimeException('Identifiants API FFTT manquants dans les reglages du plugin.');
        }

        return new FFTTApi($apiId, $apiPassword);
    }

    private static function normalize(string $value): string
    {
        $value = remove_accents($value);
        $value = strtolower($value);
        return preg_replace('/\s+/', ' ', trim($value)) ?: '';
    }

    private static function namesMatch(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right || str_starts_with($left, $right) || str_starts_with($right, $left);
    }

    private static function buildClubTeamContexts(FFTTApi $api, string $clubId): array
    {
        $equipes = $api->listEquipesByClub($clubId);
        $contexts = [];

        foreach ($equipes as $equipe) {
            $divisionLink = $equipe->getLienDivision();
            $clubTeamLabel = (string) $equipe->getLibelle();
            $clubTeamName = self::stripPhaseSuffix($clubTeamLabel);
            $phase = self::extractPhase($clubTeamLabel);
            $normalizedClubTeamName = self::normalize($clubTeamName);
            $equipesPoule = $api->listEquipePouleByLienDivision($divisionLink);

            $clubByTeamName = [];
            foreach ($equipesPoule as $equipePouleEntry) {
                $normalizedName = self::normalize((string) $equipePouleEntry->getNomEquipe());
                $clubByTeamName[$normalizedName] = (string) $equipePouleEntry->getIdCLub();
            }

            $matched = null;
            foreach ($equipesPoule as $equipePoule) {
                $poolTeamName = (string) $equipePoule->getNomEquipe();
                $normalizedPoolTeamName = self::normalize($poolTeamName);

                if (self::namesMatch($normalizedClubTeamName, $normalizedPoolTeamName)) {
                    $matched = $equipePoule;
                    break;
                }
            }

            if ($matched === null) {
                continue;
            }

            $teamId = (int) $matched->getIdEquipe();
            if ($teamId <= 0) {
                continue;
            }

            $context = [
                'id' => $teamId,
                'team_name' => (string) $matched->getNomEquipe(),
                'division_link' => $divisionLink,
                'club_by_team_name' => $clubByTeamName,
                'phase' => $phase,
            ];

            if (isset($contexts[$teamId])) {
                $existingPhase = (int) ($contexts[$teamId]['phase'] ?? 0);
                if (self::getPhasePriority($phase) <= self::getPhasePriority($existingPhase)) {
                    continue;
                }
            }

            $contexts[$teamId] = $context;
        }

        return $contexts;
    }

    private static function getClubIdFromSettings(): string
    {
        $opts = Settings::getOptions();
        $clubId = (string) ($opts['club_id'] ?? '');

        if ($clubId === '') {
            throw new \RuntimeException('ID club manquant dans les reglages du plugin.');
        }

        return $clubId;
    }

    public static function listTeamsByClub(): array
    {
        $clubId = self::getClubIdFromSettings();
        $cacheKey = self::getCacheKey('teams', [
            'clubId' => $clubId,
            'phase' => self::getCurrentPhase(),
        ]);

        return self::remember($cacheKey, static function () use ($clubId): array {
            $api = self::createClient();
            $contexts = self::buildClubTeamContexts($api, $clubId);
            $teams = [];
            foreach ($contexts as $context) {
                $teams[] = [
                    'id' => (int) $context['id'],
                    'name' => (string) $context['team_name'],
                ];
            }

            usort($teams, static function (array $left, array $right): int {
                return strnatcasecmp((string) $left['name'], (string) $right['name']);
            });

            return $teams;
        });
    }

    private static function findTeamContext(FFTTApi $api, string $clubId, int $teamId): array
    {
        $contexts = self::findTeamContexts($api, $clubId, $teamId);
        if (empty($contexts)) {
            throw new \RuntimeException('Equipe non trouvee pour ce club.');
        }

        usort($contexts, static function (array $left, array $right): int {
            $leftPhase = (int) ($left['phase'] ?? 0);
            $rightPhase = (int) ($right['phase'] ?? 0);
            return self::getPhasePriority($rightPhase) <=> self::getPhasePriority($leftPhase);
        });

        return $contexts[0];
    }

    private static function findTeamContexts(FFTTApi $api, string $clubId, int $teamId): array
    {
        $contexts = [];
        $equipes = $api->listEquipesByClub($clubId);

        foreach ($equipes as $equipe) {
            $divisionLink = (string) $equipe->getLienDivision();
            $clubTeamLabel = (string) $equipe->getLibelle();
            $clubTeamName = self::stripPhaseSuffix($clubTeamLabel);
            $phase = self::extractPhase($clubTeamLabel);
            $normalizedClubTeamName = self::normalize($clubTeamName);

            $equipesPoule = $api->listEquipePouleByLienDivision($divisionLink);
            $clubByTeamName = [];
            foreach ($equipesPoule as $equipePouleEntry) {
                $normalizedName = self::normalize((string) $equipePouleEntry->getNomEquipe());
                $clubByTeamName[$normalizedName] = (string) $equipePouleEntry->getIdCLub();
            }

            $matched = null;
            foreach ($equipesPoule as $equipePoule) {
                $poolTeamName = (string) $equipePoule->getNomEquipe();
                $normalizedPoolTeamName = self::normalize($poolTeamName);

                if (self::namesMatch($normalizedClubTeamName, $normalizedPoolTeamName)) {
                    $matched = $equipePoule;
                    break;
                }
            }

            if ($matched === null) {
                continue;
            }

            $matchedTeamId = (int) $matched->getIdEquipe();
            if ($matchedTeamId !== $teamId) {
                continue;
            }

            $contexts[$divisionLink] = [
                'id' => $matchedTeamId,
                'team_name' => (string) $matched->getNomEquipe(),
                'division_link' => $divisionLink,
                'club_by_team_name' => $clubByTeamName,
                'phase' => $phase,
            ];
        }

        return array_values($contexts);
    }

    public static function listLatestMatches(int $teamId): array
    {
        $clubId = self::getClubIdFromSettings();
        if ($teamId <= 0) {
            throw new \RuntimeException('Equipe invalide.');
        }

        $opts = Settings::getOptions();
        $limit = (int) ($opts['matches_limit'] ?? 8);
        $cacheKey = self::getCacheKey('matches', [
            'clubId' => $clubId,
            'teamId' => $teamId,
            'limit' => $limit,
            'today' => self::getTodayMarker(),
        ]);

        return self::remember($cacheKey, static function () use ($clubId, $teamId, $limit): array {
            $api = self::createClient();
            $teamContexts = self::findTeamContexts($api, $clubId, $teamId);
            if (empty($teamContexts)) {
                throw new \RuntimeException('Equipe non trouvee pour ce club.');
            }
            $todayCutoff = self::getTodayCutoffTimestamp();
            $rows = [];
            $seen = [];
            foreach ($teamContexts as $teamContext) {
                $teamName = (string) ($teamContext['team_name'] ?? '');
                $divisionLink = (string) ($teamContext['division_link'] ?? '');
                $clubByTeamName = (array) ($teamContext['club_by_team_name'] ?? []);
                if ($divisionLink === '' || $teamName === '') {
                    continue;
                }

                $rencontres = $api->listRencontrePouleByLienDivision($divisionLink);
                $normTeam = self::normalize($teamName);
                foreach ($rencontres as $rencontre) {
                    $nameA = (string) $rencontre->getNomEquipeA();
                    $nameB = (string) $rencontre->getNomEquipeB();
                    $normA = self::normalize($nameA);
                    $normB = self::normalize($nameB);

                    if ($normA !== $normTeam && $normB !== $normTeam) {
                        continue;
                    }

                    $dateReelle = $rencontre->getDateReelle();
                    $datePrevue = $rencontre->getDatePrevue();
                    $dateReelleTs = $dateReelle ? $dateReelle->getTimestamp() : 0;
                    $datePrevueTs = $datePrevue ? $datePrevue->getTimestamp() : 0;
                    $timestamp = $dateReelleTs > 0 ? $dateReelleTs : $datePrevueTs;
                    if ($timestamp <= 0 || $timestamp > $todayCutoff) {
                        continue;
                    }

                    $lien = (string) $rencontre->getLien();
                    $dedupeKey = md5($lien . '|' . $timestamp . '|' . $nameA . '|' . $nameB);
                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }
                    $seen[$dedupeKey] = true;

                    $dateIso = gmdate('c', $timestamp);
                    $dateLabel = self::formatMatchDateLabel($timestamp);
                    $clubA = (string) ($clubByTeamName[$normA] ?? '');
                    $clubB = (string) ($clubByTeamName[$normB] ?? '');

                    $rows[] = [
                        'label' => sprintf('%s - %s vs %s (%d-%d)', $dateLabel, $nameA, $nameB, $rencontre->getScoreEquipeA(), $rencontre->getScoreEquipeB()),
                        'lien' => $lien,
                        'date' => $dateIso,
                        'dateLabel' => $dateLabel,
                        'timestamp' => $timestamp,
                        'teamA' => $nameA,
                        'teamB' => $nameB,
                        'scoreA' => (int) $rencontre->getScoreEquipeA(),
                        'scoreB' => (int) $rencontre->getScoreEquipeB(),
                        'clubA' => $clubA,
                        'clubB' => $clubB,
                        'dateReelleTs' => $dateReelleTs,
                        'datePrevueTs' => $datePrevueTs,
                    ];
                }
            }

            usort($rows, static function (array $left, array $right): int {
                $leftReal = (int) ($left['dateReelleTs'] ?? 0);
                $rightReal = (int) ($right['dateReelleTs'] ?? 0);
                if ($leftReal !== $rightReal) {
                    return $rightReal <=> $leftReal;
                }

                $leftPlanned = (int) ($left['datePrevueTs'] ?? 0);
                $rightPlanned = (int) ($right['datePrevueTs'] ?? 0);
                if ($leftPlanned !== $rightPlanned) {
                    return $rightPlanned <=> $leftPlanned;
                }

                return strnatcasecmp((string) ($right['label'] ?? ''), (string) ($left['label'] ?? ''));
            });

            $rows = array_slice($rows, 0, $limit);
            $cleaned = array_map(static function (array $row): array {
                unset($row['timestamp']);
                unset($row['dateReelleTs']);
                unset($row['datePrevueTs']);
                return $row;
            }, $rows);

            return $cleaned;
        });
    }

    public static function getMatchDetailsByLink(string $link, string $clubA = '', string $clubB = ''): array
    {
        $link = trim($link);
        if ($link === '') {
            throw new \RuntimeException('Lien de rencontre manquant.');
        }

        $clubA = preg_replace('/[^0-9]/', '', $clubA) ?: '';
        $clubB = preg_replace('/[^0-9]/', '', $clubB) ?: '';
        if ($clubA === '' || $clubB === '') {
            throw new \RuntimeException('Impossible de determiner les clubs des deux equipes pour ce match.');
        }

        $cacheKey = self::getCacheKey('match-details', [
            'link' => $link,
            'clubA' => $clubA,
            'clubB' => $clubB,
        ]);

        return self::remember($cacheKey, static function () use ($link, $clubA, $clubB): array {
            $api = self::createClient();
            $details = $api->retrieveRencontreDetailsByLien($link, $clubA, $clubB);

            $teamA = (string) $details->getNomEquipeA();
            $teamB = (string) $details->getNomEquipeB();
            $scoreA = (int) $details->getScoreEquipeA();
            $scoreB = (int) $details->getScoreEquipeB();

            $winnerTeam = 'Egalite';
            if ($scoreA > $scoreB) {
                $winnerTeam = $teamA;
            } elseif ($scoreB > $scoreA) {
                $winnerTeam = $teamB;
            }

            $parties = [];
            foreach ($details->getParties() as $partie) {
                $pScoreA = (int) $partie->getScoreA();
                $pScoreB = (int) $partie->getScoreB();

                $winnerSide = '';
                if ($pScoreA > $pScoreB) {
                    $winnerSide = 'A';
                } elseif ($pScoreB > $pScoreA) {
                    $winnerSide = 'B';
                }

                $parties[] = [
                    'playerA' => (string) $partie->getAdversaireA(),
                    'playerB' => (string) $partie->getAdversaireB(),
                    'scoreA' => $pScoreA,
                    'scoreB' => $pScoreB,
                    'winnerSide' => $winnerSide,
                    'setDetails' => $partie->getSetDetails(),
                ];
            }

            return [
                'teamA' => $teamA,
                'teamB' => $teamB,
                'scoreA' => $scoreA,
                'scoreB' => $scoreB,
                'winnerTeam' => $winnerTeam,
                'parties' => $parties,
                'lien' => $link,
            ];
        });
    }
}

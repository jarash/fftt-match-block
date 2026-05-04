<?php

declare(strict_types=1);

namespace FFTTMatchBlock;

use Alamirault\FFTTApi\Service\FFTTApi;

if (!defined('ABSPATH')) {
    exit;
}

final class Api
{
    private static function loadVendor(): void
    {
        $autoload = FFTT_MATCH_BLOCK_PATH . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
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
            $clubTeamName = (string) $equipe->getLibelle();
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

            if (isset($contexts[$teamId])) {
                continue;
            }

            $contexts[$teamId] = [
                'id' => $teamId,
                'team_name' => (string) $matched->getNomEquipe(),
                'division_link' => $divisionLink,
                'club_by_team_name' => $clubByTeamName,
            ];
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
    }

    private static function findTeamContext(FFTTApi $api, string $clubId, int $teamId): array
    {
        $contexts = self::buildClubTeamContexts($api, $clubId);
        if (isset($contexts[$teamId])) {
            return $contexts[$teamId];
        }

        throw new \RuntimeException('Equipe non trouvee pour ce club.');
    }

    public static function listLatestMatches(int $teamId): array
    {
        $clubId = self::getClubIdFromSettings();
        if ($teamId <= 0) {
            throw new \RuntimeException('Equipe invalide.');
        }

        $opts = Settings::getOptions();
        $limit = (int) ($opts['matches_limit'] ?? 8);

        $api = self::createClient();
        $teamContext = self::findTeamContext($api, $clubId, $teamId);

        $teamName = (string) $teamContext['team_name'];
        $divisionLink = (string) $teamContext['division_link'];
        $clubByTeamName = (array) ($teamContext['club_by_team_name'] ?? []);

        $rencontres = $api->listRencontrePouleByLienDivision($divisionLink);

        $normTeam = self::normalize($teamName);
        $rows = [];
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
            $dateIso = $timestamp > 0 ? gmdate('c', $timestamp) : '';

            $clubA = (string) ($clubByTeamName[$normA] ?? '');
            $clubB = (string) ($clubByTeamName[$normB] ?? '');

            $rows[] = [
                'label' => sprintf('%s vs %s (%d-%d)', $nameA, $nameB, $rencontre->getScoreEquipeA(), $rencontre->getScoreEquipeB()),
                'lien' => (string) $rencontre->getLien(),
                'date' => $dateIso,
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
    }
}

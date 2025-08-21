<?php
declare(strict_types=1);

/**
 * Erwartungswert nach Elo.
 */
function elo_expected(int $ra, int $rb): float {
    return 1.0 / (1 + pow(10, ($rb - $ra) / 400));
}

/**
 * Neue Ratings berechnen.
 * Draw = 0.5 / 0.5. Kleiner Margin-Boost je Tor-Differenz (max +8 K).
 */
function elo_update(int $ra, int $rb, int $scoreA, int $scoreB, int $k = 24): array {
    $Sa = ($scoreA === $scoreB) ? 0.5 : (($scoreA > $scoreB) ? 1.0 : 0.0);
    $Ea = elo_expected($ra, $rb);
    $Eb = 1 - $Ea;

    $margin = max(0, abs($scoreA - $scoreB) - 1); // 0..∞
    $kAdj = $k + min(8, $margin);                 // +0..+8

    $raNew = (int) round($ra + $kAdj * ($Sa - $Ea));
    $rbNew = (int) round($rb + $kAdj * ((1 - $Sa) - $Eb));
    return [$raNew, $rbNew];
}
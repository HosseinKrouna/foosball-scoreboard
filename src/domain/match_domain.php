<?php
declare(strict_types=1);

/** Normalisiert Team-Eingabe zu 'A' | 'B' | '' */
function md_team(string $raw): string {
    $t = strtoupper($raw[0] ?? '');
    return ($t === 'A' || $t === 'B') ? $t : '';
}

/** Normalisiert Delta auf -1 | 0 | +1 */
function md_delta($raw): int {
    $d = (int)$raw;
    return $d < 0 ? -1 : ($d > 0 ? 1 : 0);
}

/** Hat jemand das Ziel erreicht? */
function md_reached(int $a, int $b, int $target): bool {
    return $a >= $target || $b >= $target;
}

/** Wer führt? 'A' | 'B' | 'tie' */
function md_leader(int $a, int $b): string {
    return $a > $b ? 'A' : ($b > $a ? 'B' : 'tie');
}
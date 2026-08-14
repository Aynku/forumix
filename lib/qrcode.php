<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * lib/qrcode.php – QR-Erzeuger (Byte-Modus, Fehlerkorrektur M)
 *
 * Selbst gebaut, damit fuer den 2FA-QR kein fremder Dienst und keine fremde
 * Bibliothek noetig ist. Ausgabe ist SVG: keine GD-Abhaengigkeit, beliebig
 * skalierbar, und die Grafik entsteht auf dem eigenen Server.
 *
 * Umfang bewusst eng: Byte-Modus, Stufe M, Versionen 1–10 (bis 154 Zeichen).
 * Das deckt otpauth-URLs ab; alles darueber wirft eine Ausnahme, statt still
 * etwas Falsches zu liefern.
 */

/** Datentabelle je Version fuer Stufe M: [EC-Codewoerter je Block, [ [Bloecke, Datencodewoerter], … ] ] */
const QR_M_BLOCKS = [
    1  => [10, [[1, 16]]],
    2  => [16, [[1, 28]]],
    3  => [26, [[1, 44]]],
    4  => [18, [[2, 32]]],
    5  => [24, [[2, 43]]],
    6  => [16, [[4, 27]]],
    7  => [18, [[4, 31]]],
    8  => [22, [[2, 38], [2, 39]]],
    9  => [22, [[3, 36], [2, 37]]],
    10 => [26, [[4, 43], [1, 44]]],
];

/** Mittelpunkte der Ausrichtungsmuster je Version */
const QR_ALIGN = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
];

/* ─────────── Galois-Feld GF(256), Generator 0x11d ─────────── */

function qr_gf_tables(): array {
    static $t = null;
    if ($t !== null) return $t;
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11d;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return $t = [$exp, $log];
}

function qr_gf_mul(int $a, int $b): int {
    if ($a === 0 || $b === 0) return 0;
    [$exp, $log] = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}

/** Generatorpolynom vom Grad $degree */
function qr_rs_generator(int $degree): array {
    $g = [1];
    [$exp] = qr_gf_tables();
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($g) + 1, 0);
        foreach ($g as $k => $coef) {
            $next[$k]     ^= qr_gf_mul($coef, $exp[$i]);
            $next[$k + 1] ^= $coef;
        }
        $g = $next;
    }
    return $g;
}

/** Rest der Polynomdivision = Fehlerkorrektur-Codewoerter */
function qr_rs_remainder(array $data, int $ecLen): array {
    $gen = qr_rs_generator($ecLen);
    $buf = array_merge($data, array_fill(0, $ecLen, 0));
    for ($i = 0; $i < count($data); $i++) {
        $factor = $buf[$i];
        if ($factor === 0) continue;
        foreach ($gen as $k => $coef) {
            $buf[$i + $k] ^= qr_gf_mul($coef, $factor);
        }
    }
    return array_slice($buf, count($data));
}

/* ─────────── Funktionsmuster ─────────── */

/**
 * Legt das Raster an und markiert alle Funktionsmuster.
 * Rueckgabe: [$modules, $reserved] – beide size×size, Werte 0/1 bzw. true/false.
 */
function qr_function_patterns(int $version): array {
    $size = $version * 4 + 17;
    $m = array_fill(0, $size, array_fill(0, $size, 0));
    $r = array_fill(0, $size, array_fill(0, $size, false));

    $finder = function (int $row, int $col) use (&$m, &$r, $size) {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $rr = $row + $dr; $cc = $col + $dc;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                $inner = max(abs($dr - 3), abs($dc - 3));
                $m[$rr][$cc] = ($inner !== 2 && $inner <= 3) ? 1 : 0;
                $r[$rr][$cc] = true;
            }
        }
    };
    $finder(0, 0);
    $finder(0, $size - 7);
    $finder($size - 7, 0);

    // Taktmuster
    for ($i = 8; $i < $size - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        $m[6][$i] = $bit; $r[6][$i] = true;
        $m[$i][6] = $bit; $r[$i][6] = true;
    }

    // Ausrichtungsmuster (nicht ueber die Suchmuster legen)
    $centers = QR_ALIGN[$version];
    foreach ($centers as $cr) {
        foreach ($centers as $cc) {
            if (($cr <= 8 && $cc <= 8) || ($cr <= 8 && $cc >= $size - 9) || ($cr >= $size - 9 && $cc <= 8)) {
                continue;
            }
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $inner = max(abs($dr), abs($dc));
                    $m[$cr + $dr][$cc + $dc] = ($inner !== 1) ? 1 : 0;
                    $r[$cr + $dr][$cc + $dc] = true;
                }
            }
        }
    }

    // Versionsinformation: ab Version 7 zwei Bloecke zu 6×3 Modulen.
    // Ohne sie landen Datenmodule an dieser Stelle und der Code ist unlesbar.
    if ($version >= 7) {
        $vinfo = $version;
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1f25);
        }
        $vinfo = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($vinfo >> $i) & 1;
            $a = intdiv($i, 3);
            $b = $size - 11 + ($i % 3);
            $m[$b][$a] = $bit; $r[$b][$a] = true;   // links unten
            $m[$a][$b] = $bit; $r[$a][$b] = true;   // rechts oben
        }
    }

    // Dunkles Modul + Formatbereiche reservieren
    $m[$size - 8][8] = 1; $r[$size - 8][8] = true;
    for ($i = 0; $i <= 8; $i++) {
        if (!$r[8][$i])            { $r[8][$i] = true; }
        if (!$r[$i][8])            { $r[$i][8] = true; }
    }
    for ($i = 0; $i < 8; $i++) {
        $r[8][$size - 1 - $i] = true;
        $r[$size - 1 - $i][8] = true;
    }

    return [$m, $r, $size];
}

/** Zaehlt die fuer Daten nutzbaren Codewoerter einer Version (aus dem Raster, nicht aus einer Tabelle) */
function qr_total_codewords(int $version): int {
    [, $r, $size] = qr_function_patterns($version);
    $free = 0;
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if (!$r[$row][$col]) $free++;
        }
    }
    return intdiv($free, 8);
}

/* ─────────── Datenteil ─────────── */

function qr_choose_version(int $byteLen): int {
    foreach (QR_M_BLOCKS as $v => [$ecLen, $groups]) {
        $dataCw = 0;
        foreach ($groups as [$count, $cw]) $dataCw += $count * $cw;
        $countBits = ($v <= 9) ? 8 : 16;
        $capacity  = intdiv($dataCw * 8 - 4 - $countBits, 8);
        if ($byteLen <= $capacity) return $v;
    }
    throw new RuntimeException('Text zu lang fuer QR-Version 10 (Stufe M).');
}

function qr_build_codewords(string $text, int $version): array {
    [$ecLen, $groups] = QR_M_BLOCKS[$version];
    $dataCwTotal = 0;
    foreach ($groups as [$count, $cw]) $dataCwTotal += $count * $cw;

    // Bitstrom: Modus 0100, Laengenfeld, Nutzdaten
    $bits = '0100';
    $countBits = ($version <= 9) ? 8 : 16;
    $bits .= str_pad(decbin(strlen($text)), $countBits, '0', STR_PAD_LEFT);
    for ($i = 0; $i < strlen($text); $i++) {
        $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }

    $capacityBits = $dataCwTotal * 8;
    // Abschluss (max. 4 Null-Bits) und Auffuellen auf volle Codewoerter
    $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - strlen($bits) % 8);
    }
    // Fuellbytes im Wechsel
    $pad = ['11101100', '00010001'];
    $i = 0;
    while (strlen($bits) < $capacityBits) {
        $bits .= $pad[$i++ % 2];
    }

    $data = [];
    foreach (str_split($bits, 8) as $byte) $data[] = bindec($byte);

    // In Bloecke schneiden, je Block Fehlerkorrektur rechnen
    $dataBlocks = []; $ecBlocks = []; $pos = 0;
    foreach ($groups as [$count, $cw]) {
        for ($b = 0; $b < $count; $b++) {
            $block = array_slice($data, $pos, $cw);
            $pos += $cw;
            $dataBlocks[] = $block;
            $ecBlocks[]   = qr_rs_remainder($block, $ecLen);
        }
    }

    // Verschachteln: erst Daten spaltenweise, dann Fehlerkorrektur spaltenweise
    $out = [];
    $maxData = max(array_map('count', $dataBlocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($dataBlocks as $blk) {
            if (isset($blk[$i])) $out[] = $blk[$i];
        }
    }
    for ($i = 0; $i < $ecLen; $i++) {
        foreach ($ecBlocks as $blk) $out[] = $blk[$i];
    }
    return $out;
}

/* ─────────── Platzierung, Maske, Formatinfo ─────────── */

function qr_place_data(array $m, array $r, int $size, array $codewords): array {
    $bits = '';
    foreach ($codewords as $cw) $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
    $len = strlen($bits);
    $idx = 0;
    $upward = true;

    for ($right = $size - 1; $right > 0; $right -= 2) {
        if ($right === 6) $right = 5; // Taktspalte ueberspringen
        for ($v = 0; $v < $size; $v++) {
            $row = $upward ? ($size - 1 - $v) : $v;
            foreach ([$right, $right - 1] as $col) {
                if ($r[$row][$col]) continue;
                $m[$row][$col] = ($idx < $len) ? (int) $bits[$idx] : 0;
                $idx++;
            }
        }
        $upward = !$upward;
    }
    return $m;
}

function qr_mask_condition(int $mask, int $row, int $col): bool {
    return match ($mask) {
        0 => ($row + $col) % 2 === 0,
        1 => $row % 2 === 0,
        2 => $col % 3 === 0,
        3 => ($row + $col) % 3 === 0,
        4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
        5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
        6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
        7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
    };
}

/** Formatinfo: 5 Bit (Stufe+Maske) + BCH(15,5), danach XOR 0x5412 */
function qr_format_bits(int $mask): int {
    $data = (0b00 << 3) | $mask;   // Stufe M = 00
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    return (($data << 10) | $rem) ^ 0x5412;
}

function qr_apply_format(array $m, int $size, int $mask): array {
    $fmt = qr_format_bits($mask);
    for ($i = 0; $i < 15; $i++) {
        $bit = ($fmt >> $i) & 1;
        // Kopie 1: um das linke obere Suchmuster
        if ($i < 6)       { $m[8][$i] = $bit; }
        elseif ($i === 6) { $m[8][7] = $bit; }
        elseif ($i === 7) { $m[8][8] = $bit; }
        elseif ($i === 8) { $m[7][8] = $bit; }
        else              { $m[14 - $i][8] = $bit; }
        // Kopie 2: rechts oben und links unten
        if ($i < 8) { $m[$size - 1 - $i][8] = $bit; }
        else        { $m[8][$size - 15 + $i] = $bit; }
    }
    $m[$size - 8][8] = 1; // dunkles Modul
    return $m;
}

/** Bewertung nach den vier Regeln der Norm – kleiner ist besser */
function qr_penalty(array $m, int $size): int {
    $score = 0;

    // Regel 1: Lauflaengen ab 5
    foreach ([true, false] as $horizontal) {
        for ($a = 0; $a < $size; $a++) {
            $run = 1;
            for ($b = 1; $b < $size; $b++) {
                $cur  = $horizontal ? $m[$a][$b]     : $m[$b][$a];
                $prev = $horizontal ? $m[$a][$b - 1] : $m[$b - 1][$a];
                if ($cur === $prev) {
                    $run++;
                } else {
                    if ($run >= 5) $score += 3 + ($run - 5);
                    $run = 1;
                }
            }
            if ($run >= 5) $score += 3 + ($run - 5);
        }
    }

    // Regel 2: gleichfarbige 2×2-Bloecke
    for ($row = 0; $row < $size - 1; $row++) {
        for ($col = 0; $col < $size - 1; $col++) {
            $v = $m[$row][$col];
            if ($v === $m[$row][$col + 1] && $v === $m[$row + 1][$col] && $v === $m[$row + 1][$col + 1]) {
                $score += 3;
            }
        }
    }

    // Regel 3: Muster 1:1:3:1:1 mit vier hellen Modulen daneben
    $p1 = [1,0,1,1,1,0,1,0,0,0,0];
    $p2 = [0,0,0,0,1,0,1,1,1,0,1];
    foreach ([true, false] as $horizontal) {
        for ($a = 0; $a < $size; $a++) {
            for ($b = 0; $b <= $size - 11; $b++) {
                $slice = [];
                for ($k = 0; $k < 11; $k++) {
                    $slice[] = $horizontal ? $m[$a][$b + $k] : $m[$b + $k][$a];
                }
                if ($slice === $p1 || $slice === $p2) $score += 40;
            }
        }
    }

    // Regel 4: Abweichung vom halben Dunkelanteil
    $dark = 0;
    foreach ($m as $row) $dark += array_sum($row);
    $percent = $dark * 100 / ($size * $size);
    $score += (int) (abs($percent - 50) / 5) * 10;

    return $score;
}

/* ─────────── Oeffentliche Schnittstelle ─────────── */

/** Erzeugt die fertige Modulmatrix (0/1) fuer $text */
function qr_matrix(string $text): array {
    $version = qr_choose_version(strlen($text));

    // Kreuzprobe: die Tabellenzeile muss zum selbst gezaehlten Raster passen.
    [$ecLen, $groups] = QR_M_BLOCKS[$version];
    $sum = 0;
    foreach ($groups as [$count, $cw]) $sum += $count * ($cw + $ecLen);
    if ($sum !== qr_total_codewords($version)) {
        throw new RuntimeException("QR-Tabelle inkonsistent fuer Version $version.");
    }

    $codewords = qr_build_codewords($text, $version);
    [$base, $reserved, $size] = qr_function_patterns($version);
    $placed = qr_place_data($base, $reserved, $size, $codewords);

    $best = null; $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $cand = $placed;
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($reserved[$row][$col]) continue;
                if (qr_mask_condition($mask, $row, $col)) $cand[$row][$col] ^= 1;
            }
        }
        $cand = qr_apply_format($cand, $size, $mask);
        $score = qr_penalty($cand, $size);
        if ($score < $bestScore) { $bestScore = $score; $best = $cand; }
    }
    return $best;
}

/**
 * Liefert den QR als SVG-Zeichenkette.
 * $scale = Pixel je Modul, $margin = Ruhezone in Modulen (Norm: 4).
 */
function qr_svg(string $text, int $scale = 6, int $margin = 4): string {
    $m    = qr_matrix($text);
    $size = count($m);
    $dim  = ($size + 2 * $margin) * $scale;

    $path = '';
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if ($m[$row][$col] !== 1) continue;
            $x = ($col + $margin) * $scale;
            $y = ($row + $margin) * $scale;
            $path .= "M{$x} {$y}h{$scale}v{$scale}h-{$scale}z";
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
         . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" '
         . 'aria-label="QR-Code zur 2FA-Einrichtung">'
         . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
         . '<path d="' . $path . '" fill="#000000"/>'
         . '</svg>';
}

/** SVG als data:-URI, direkt in ein src="" einsetzbar */
function qr_data_uri(string $text, int $scale = 6, int $margin = 4): string {
    return 'data:image/svg+xml;base64,' . base64_encode(qr_svg($text, $scale, $margin));
}

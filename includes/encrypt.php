<?php
/**
 * Purble Pairs — Custom Encryption Library
 *
 * Implements a Row Transposition Cipher (Phases 1–3) followed by
 * a Key-Based Caesar Cipher (Phase 4).
 *
 * Key requirements:
 *   - Minimum of 8 characters
 *   - All characters must be non-repeating (each letter used only once)
 *   - The key is loaded from the .env file (ENCRYPTION_KEY)
 *
 * Usage:
 *   require 'includes/encrypt.php';
 *   $cipher = customEncrypt('HELLO WORLD', 'PURBLEAC');
 */

function customEncrypt(string $plaintext, string $key): string
{
    // Sanitise key: keep only alpha characters, uppercase
    $key = strtoupper(preg_replace('/[^a-zA-Z]/', '', $key));
    if ($key === '') {
        return $plaintext; // nothing to do without a key
    }

    // Uppercase the plaintext
    $text = strtoupper($plaintext);

    $keyLen  = strlen($key);
    $textLen = strlen($text);

    // Phase 1 — Build matrix (pad with 'X' if needed)
    $cols    = $keyLen;
    $rows    = (int) ceil($textLen / $cols);
    $padded  = str_pad($text, $rows * $cols, 'X');

    $matrix = [];
    for ($r = 0; $r < $rows; $r++) {
        $matrix[$r] = [];
        for ($c = 0; $c < $cols; $c++) {
            $matrix[$r][$c] = $padded[$r * $cols + $c];
        }
    }

    // Phase 2 — Columnar transposition
    // Sort key chars alphabetically; ties broken by original index.
    $keyChars = str_split($key);
    $order    = range(0, $cols - 1);
    usort($order, function (int $a, int $b) use ($keyChars): int {
        $cmp = strcmp($keyChars[$a], $keyChars[$b]);
        return $cmp !== 0 ? $cmp : $a - $b;
    });

    // Reorder columns
    $reordered = [];
    for ($r = 0; $r < $rows; $r++) {
        $reordered[$r] = [];
        foreach ($order as $newCol => $origCol) {
            $reordered[$r][$newCol] = $matrix[$r][$origCol];
        }
    }

    // Phase 3 — Zigzag reading (even rows L-R, odd rows R-L)
    $transposed = '';
    for ($r = 0; $r < $rows; $r++) {
        $row = $reordered[$r];
        if ($r % 2 === 0) {
            $transposed .= implode('', $row);
        } else {
            $transposed .= implode('', array_reverse($row));
        }
    }

    // Phase 4 — Key-based Caesar shift (A=1 … Z=26)
    $keyNums = array_map(fn(string $ch): int => ord($ch) - ord('A') + 1, $keyChars);

    $ciphertext = '';
    $tLen       = strlen($transposed);

    for ($i = 0; $i < $tLen; $i++) {
        $ch    = $transposed[$i];
        $shift = $keyNums[$i % $keyLen];

        if ($ch === ' ') {
            $newVal = (0 + $shift) % 27;
            $ciphertext .= ($newVal === 0) ? ' ' : chr(ord('A') + $newVal - 1);
        } elseif ($ch >= 'A' && $ch <= 'Z') {
            $charVal    = ord($ch) - ord('A') + 1;
            $newVal     = (($charVal - 1 + $shift) % 26) + 1;
            $ciphertext .= chr(ord('A') + $newVal - 1);
        } else {
            $ciphertext .= $ch;
        }
    }

    return $ciphertext;
}

<?php

namespace App\Billing;

/** Amounts are stored in centimes (integers): no rounding drift on totals. */
class Money
{
    /** 120050 -> "1 200,50 DH" */
    public static function format(int $cents, bool $currency = true): string
    {
        $s = number_format($cents / 100, 2, ",", "\u{00A0}");

        return $currency ? $s.' DH' : $s;
    }

    /** "1 200,50", "1200.5", "1200" -> 120050 */
    public static function parse(string|int|float|null $input): int
    {
        $s = str_replace(["\u{202F}", "\u{00A0}", ' ', 'DH', 'dh'], '', (string) $input);
        $s = str_contains($s, ',') ? str_replace(['.', ','], ['', '.'], $s) : $s;

        return (int) round(((float) $s) * 100);
    }
}

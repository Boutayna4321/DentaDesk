<?php

namespace App\Billing;

/**
 * "Arrêtée la présente facture à la somme de : mille deux cents dirhams."
 * French rules: vingt/cent take an s only when multiplied and last, "et un", soixante-et-onze…
 */
class AmountInWords
{
    private const UNITS = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze',
        'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];

    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function dirhams(int $cents): string
    {
        $dh = intdiv($cents, 100);
        $c = $cents % 100;
        $out = self::number($dh).' dirham'.($dh > 1 ? 's' : '');
        if ($c) {
            $out .= ' et '.self::number($c).' centime'.($c > 1 ? 's' : '');
        }

        return $out;
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }
        $parts = [];
        if ($m = intdiv($n, 1_000_000)) {
            $parts[] = $m === 1 ? 'un million' : self::below1000($m).' millions';
        }
        if ($k = intdiv($n % 1_000_000, 1000)) {
            // "mille" never takes un, and cent/vingt before mille stay singular.
            $parts[] = $k === 1 ? 'mille' : self::below1000($k, plural: false).' mille';
        }
        if ($r = $n % 1000) {
            $parts[] = self::below1000($r);
        }

        return implode(' ', $parts);
    }

    private static function below1000(int $n, bool $plural = true): string
    {
        $h = intdiv($n, 100);
        $r = $n % 100;
        $out = match (true) {
            $h === 0 => '',
            $h === 1 => 'cent',
            default => self::UNITS[$h].' cent'.($r === 0 && $plural ? 's' : ''),
        };
        if ($r) {
            $out = trim($out.' '.self::below100($r, $plural));
        }

        return $out;
    }

    private static function below100(int $n, bool $plural): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }
        $t = intdiv($n, 10);
        $u = $n % 10;

        return match ($t) {
            7 => 'soixante'.($u === 1 ? ' et onze' : '-'.self::UNITS[10 + $u]),
            8 => 'quatre-vingt'.($u === 0 ? ($plural ? 's' : '') : '-'.self::UNITS[$u]),
            9 => 'quatre-vingt-'.self::UNITS[10 + $u],
            default => self::TENS[$t].($u === 0 ? '' : ($u === 1 ? ' et un' : '-'.self::UNITS[$u])),
        };
    }
}

<?php

namespace Tests\Unit;

use App\Billing\AmountInWords;
use App\Billing\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            [1, 'un'], [17, 'dix-sept'], [21, 'vingt et un'], [71, 'soixante et onze'], [80, 'quatre-vingts'], [81, 'quatre-vingt-un'],
            [91, 'quatre-vingt-onze'], [100, 'cent'], [200, 'deux cents'], [250, 'deux cent cinquante'], [1000, 'mille'],
            [1200, 'mille deux cents'], [13700, 'treize mille sept cents'], [80000, 'quatre-vingt mille'], [200000, 'deux cent mille'],
            [1_000_000, 'un million'], [2_350_480, 'deux millions trois cent cinquante mille quatre cent quatre-vingts'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_french_numbers(int $n, string $words): void
    {
        $this->assertSame($words, AmountInWords::number($n));
    }

    public function test_dirhams_and_centimes(): void
    {
        $this->assertSame('mille deux cents dirhams', AmountInWords::dirhams(120000));
        $this->assertSame('un dirham et cinquante centimes', AmountInWords::dirhams(150));
    }

    public function test_money_round_trip(): void
    {
        $this->assertSame(120050, Money::parse('1 200,50'));
        $this->assertSame(120050, Money::parse('1200.5'));
        $this->assertSame(70000, Money::parse('700'));
        $this->assertSame("1\u{00A0}200,50 DH", Money::format(120050));
    }
}

<?php

namespace App\Accounting;

use Illuminate\Validation\ValidationException;

final class Money
{
    // Conservative operational ceiling leaves ample headroom for exact intermediate arithmetic.
    public const MAX = 9_000_000_000_000;

    public static function amount(mixed $value, bool $zero = false): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^[0-9]{1,13}$/D', $value))) {
            self::fail('مبلغ باید عدد صحیح ریال باشد.');
        }
        $n = (int) $value;
        if ($n < ($zero ? 0 : 1) || $n > self::MAX) {
            self::fail('مبلغ خارج از محدوده مجاز است.');
        }

        return $n;
    }

    public static function item(array $item): int
    {
        $discount = self::amount($item['discount'] ?? 0, true);
        if (isset($item['quantity']) && $item['quantity'] !== '') {
            $quantity = (string) $item['quantity'];
            if (! preg_match('/^[0-9]{1,9}(\.[0-9]{1,6})?$/D', $quantity) || bccomp($quantity, '0', 6) <= 0) {
                self::fail('مقدار باید اعشار مثبت با حداکثر شش رقم اعشار باشد.');
            }
            $price = self::amount($item['unit_price'] ?? null);
            $exact = bcmul($quantity, (string) $price, 6);
            $rounded = bcdiv(bcadd($exact, '0.5', 6), '1', 0);
            $gross = self::amount($rounded);
            if ($discount >= $gross) {
                self::fail('تخفیف باید کمتر از مبلغ ردیف باشد.');
            }

            return self::amount($gross - $discount);
        }
        $gross = self::amount($item['amount'] ?? null);
        if ($discount >= $gross) {
            self::fail('تخفیف باید کمتر از مبلغ ردیف باشد.');
        }

        return $gross - $discount;
    }

    public static function fee(int $amount, int $bps): int
    {
        if ($bps < 0 || $bps > 10000) {
            self::fail('درصد نامعتبر است.');
        }

        return intdiv($amount * $bps + 5000, 10000);
    }

    public static function allocate(int $total, array $shares): array
    {
        if (array_sum($shares) !== 10000 || min($shares ?: [-1]) < 0) {
            self::fail('مجموع سهم مالکان باید ۱۰۰ درصد باشد.');
        }
        ksort($shares, SORT_NUMERIC);
        $result = [];
        $remainders = [];
        foreach ($shares as $id => $bps) {
            $result[$id] = intdiv($total * $bps, 10000);
            $remainders[$id] = ($total * $bps) % 10000;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $total - array_sum($result);
        foreach ($remainders as $id => $_) {
            if ($left-- <= 0) {
                break;
            } $result[$id]++;
        }

        return $result;
    }

    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['accounting' => $message]);
    }
}

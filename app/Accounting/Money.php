<?php
namespace App\Accounting;
use Illuminate\Validation\ValidationException;
final class Money
{
    // Conservative operational ceiling leaves ample headroom for exact intermediate arithmetic.
    public const MAX = 9_000_000_000_000;
    public static function amount(mixed $value, bool $zero = false): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^[0-9]{1,13}$/D', $value))) self::fail('مبلغ باید عدد صحیح ریال باشد.');
        $n = (int) $value;
        if ($n < ($zero ? 0 : 1) || $n > self::MAX) self::fail('مبلغ خارج از محدوده مجاز است.');
        return $n;
    }
    public static function fee(int $amount, int $bps): int
    {
        if ($bps < 0 || $bps > 10000) self::fail('درصد نامعتبر است.');
        return intdiv($amount * $bps + 5000, 10000);
    }
    public static function allocate(int $total, array $shares): array
    {
        if (array_sum($shares) !== 10000 || min($shares ?: [-1]) < 0) self::fail('مجموع سهم مالکان باید ۱۰۰ درصد باشد.');
        ksort($shares, SORT_NUMERIC);
        $result = []; $remainders = [];
        foreach ($shares as $id => $bps) {
            $result[$id] = intdiv($total * $bps, 10000);
            $remainders[$id] = ($total * $bps) % 10000;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $total - array_sum($result);
        foreach ($remainders as $id => $_) { if ($left-- <= 0) break; $result[$id]++; }
        return $result;
    }
    public static function fail(string $message): never { throw ValidationException::withMessages(['accounting' => $message]); }
}

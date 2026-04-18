<?php

namespace Jdclzn\PayrollEngine\Support;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

final class MoneyHelper
{
    private const DEFAULT_CURRENCY = 'PHP';

    /**
     * Returns the shared package currency instance.
     *
     * Passing null falls back to the package default currency. A Currency
     * instance or an existing Money object may also be supplied to preserve
     * the current currency context.
     *
     * @param  string|Currency|Money|null  $currency
     */
    public static function currency(string|Currency|Money|null $currency = null): Currency
    {
        static $currencies = [];

        if ($currency instanceof Money) {
            return $currency->getCurrency();
        }

        if ($currency instanceof Currency) {
            return $currency;
        }

        $code = strtoupper(trim((string) ($currency ?: self::DEFAULT_CURRENCY)));

        if ($code === '') {
            $code = self::DEFAULT_CURRENCY;
        }

        if (! array_key_exists($code, $currencies)) {
            $currencies[$code] = new Currency($code);
        }

        return $currencies[$code];
    }

    /**
     * Creates a zero-value Money object in the resolved currency context.
     *
     * @param  string|Currency|Money|null  $currency
     */
    public static function zero(string|Currency|Money|null $currency = null): Money
    {
        return new Money(0, self::currency($currency));
    }

    /**
     * Converts a major-unit numeric amount into a Money instance.
     *
     * The incoming value is treated as a major-unit decimal amount for the
     * resolved currency and converted into the correct minor-unit precision
     * using ISO currency subunits. Null or empty values are normalized to zero.
     *
     * @param  int|float|string|null  $amount  Amount in major units, such as 1250.75.
     * @param  string|Currency|Money|null  $currency
     */
    public static function fromNumeric(int|float|string|null $amount, string|Currency|Money|null $currency = null): Money
    {
        if ($amount === null || $amount === '') {
            return self::zero($currency);
        }

        return self::decimalParser()->parse(
            self::normalizeDecimal($amount),
            self::currency($currency),
        );
    }

    /**
     * Sums a list of Money values that share the same currency.
     *
      * @param  iterable<Money>  $items
     * @param  string|Currency|Money|null  $currency  Currency to use when the iterable is empty.
     */
    public static function sum(iterable $items, string|Currency|Money|null $currency = null): Money
    {
        $total = null;

        foreach ($items as $item) {
            $total = $total instanceof Money ? $total->add($item) : self::zero($item)->add($item);
        }

        return $total ?? self::zero($currency);
    }

    /**
     * Multiplies a Money amount by a numeric factor using half-up rounding.
     *
     * This is used for payroll multipliers such as daily-rate conversions,
     * overtime premiums, and prorated adjustments.
     *
     * @param  int|float|string  $multiplier  Decimal factor such as 1.25 or 0.5.
     */
    public static function multiply(Money $money, int|float|string $multiplier): Money
    {
        return $money->multiply(self::normalizeFactor($multiplier), Money::ROUND_HALF_UP);
    }

    /**
     * Divides a Money amount by a numeric factor using half-up rounding.
     *
     * This is commonly used when deriving rates, schedules, or prorated values
     * from a larger payroll amount.
     *
     * @param  int|float|string  $divisor  Decimal divisor such as 2, 22, or 26.
     */
    public static function divide(Money $money, int|float|string $divisor): Money
    {
        return $money->divide(self::normalizeFactor($divisor), Money::ROUND_HALF_UP);
    }

    /**
     * Computes a percentage of a Money amount.
     *
     * The rate is expressed as a whole-number percentage, so passing 10 means
     * 10% and not 0.10.
     */
    public static function percentage(Money $money, float $rate): Money
    {
        return self::multiply($money, $rate / 100);
    }

    /**
     * Returns the greater of two Money amounts.
     */
    public static function max(Money $left, Money $right): Money
    {
        return $left->greaterThanOrEqual($right) ? $left : $right;
    }

    /**
     * Returns the smaller of two Money amounts.
     */
    public static function min(Money $left, Money $right): Money
    {
        return $left->lessThanOrEqual($right) ? $left : $right;
    }

    /**
     * Converts a Money amount from minor units into a rounded float.
     *
     * This is intended for output and serialization only; internal payroll math
     * should continue using Money to avoid floating-point drift. The decimal
     * scale is resolved from the Money currency instead of assuming two digits.
     */
    public static function toFloat(Money $money): float
    {
        return round((float) self::decimalFormatter()->format($money), self::subunitFor($money));
    }

    /**
     * Returns the raw minor-unit integer amount stored by Money.
     *
     * The exact meaning depends on the currency subunit, for example cents for
     * USD, centavos for PHP, or whole yen for JPY.
     */
    public static function minorAmount(Money $money): int
    {
        return (int) $money->getAmount();
    }

    /**
     * Normalizes numeric factors into the string format expected by moneyphp/money.
     *
     * Float inputs are converted to a trimmed fixed-point decimal string so
     * multiplication and division remain explicit and deterministic.
     */
    private static function normalizeFactor(int|float|string $factor): string
    {
        if (is_int($factor)) {
            return (string) $factor;
        }

        if (is_string($factor)) {
            return $factor;
        }

        $normalized = rtrim(rtrim(sprintf('%.14F', $factor), '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }

    /**
     * Normalizes a numeric amount into a decimal string accepted by the parser.
     *
     * @param  int|float|string  $amount
     */
    private static function normalizeDecimal(int|float|string $amount): string
    {
        if (is_string($amount)) {
            $normalized = str_replace(',', '', trim($amount));

            return $normalized === '' ? '0' : $normalized;
        }

        return self::normalizeFactor($amount);
    }

    private static function subunitFor(string|Currency|Money|null $currency = null): int
    {
        return self::isoCurrencies()->subunitFor(self::currency($currency));
    }

    private static function isoCurrencies(): ISOCurrencies
    {
        static $currencies = null;

        if ($currencies instanceof ISOCurrencies) {
            return $currencies;
        }

        $currencies = new ISOCurrencies();

        return $currencies;
    }

    private static function decimalParser(): DecimalMoneyParser
    {
        static $parser = null;

        if ($parser instanceof DecimalMoneyParser) {
            return $parser;
        }

        $parser = new DecimalMoneyParser(self::isoCurrencies());

        return $parser;
    }

    private static function decimalFormatter(): DecimalMoneyFormatter
    {
        static $formatter = null;

        if ($formatter instanceof DecimalMoneyFormatter) {
            return $formatter;
        }

        $formatter = new DecimalMoneyFormatter(self::isoCurrencies());

        return $formatter;
    }
}

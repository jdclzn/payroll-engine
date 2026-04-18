<?php

namespace Jdclzn\PayrollEngine\Tests;

use Jdclzn\PayrollEngine\Support\MoneyHelper;

it('creates and formats usd amounts using the selected currency', function () {
    $money = MoneyHelper::fromNumeric('1234.56', 'USD');

    expect($money->getCurrency()->getCode())->toBe('USD')
        ->and(MoneyHelper::minorAmount($money))->toBe(123456)
        ->and(MoneyHelper::toFloat($money))->toBe(1234.56);
});

it('respects zero-decimal currencies when converting numeric amounts', function () {
    $money = MoneyHelper::fromNumeric('1234.5', 'JPY');

    expect($money->getCurrency()->getCode())->toBe('JPY')
        ->and(MoneyHelper::minorAmount($money))->toBe(1235)
        ->and(MoneyHelper::toFloat($money))->toBe(1235.0);
});

it('sums non-default currencies without forcing php as the seed currency', function () {
    $total = MoneyHelper::sum([
        MoneyHelper::fromNumeric(10, 'USD'),
        MoneyHelper::fromNumeric(2.5, 'USD'),
    ]);

    expect($total->getCurrency()->getCode())->toBe('USD')
        ->and(MoneyHelper::toFloat($total))->toBe(12.5);
});

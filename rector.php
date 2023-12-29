<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Command',
        __DIR__ . '/Console',
        __DIR__ . '/Controller',
        __DIR__ . '/Cron',
        __DIR__ . '/DependencyInjection',
        __DIR__ . '/Entity',
        __DIR__ . '/Event',
        __DIR__ . '/Exception',
        __DIR__ . '/Retry',
        __DIR__ . '/Twig',
        __DIR__ . '/View',
    ]);

    // register a single rule
    //$rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    //$rectorConfig->rule(ClassPropertyAssignToConstructorPromotionRector::class);

    // define sets of rules
    $rectorConfig->sets([
        //LevelSetList::UP_TO_PHP_80
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY

    ]);
};

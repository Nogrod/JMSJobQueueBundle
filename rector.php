<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony44\Rector\ClassMethod\ConsoleExecuteReturnIntRector;
use Rector\Symfony\Symfony61\Rector\Class_\CommandPropertyToAttributeRector;

return RectorConfig::configure()
    ->withPaths([
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
                ])
    ->withSkip([
                   RemoveUnusedPrivateMethodRector::class,
               ])
    ->withAttributesSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        //naming: true,
        rectorPreset: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withRules([
                    InlineConstructorDefaultToPropertyRector::class,
                    ClassPropertyAssignToConstructorPromotionRector::class,
                    CommandPropertyToAttributeRector::class,
                    ConsoleExecuteReturnIntRector::class
                ])
    ->withSets([
                   LevelSetList::UP_TO_PHP_84,

                   SymfonySetList::SYMFONY_72,
                   SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,

                   DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
                   DoctrineSetList::DOCTRINE_DBAL_30,
                   DoctrineSetList::DOCTRINE_ORM_214,
                   DoctrineSetList::DOCTRINE_CODE_QUALITY,
               ])
    ;
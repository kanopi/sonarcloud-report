<?php

use Rector\CodingStyle\Enum\PreferenceSelfThis;
use Rector\CodingStyle\Rector\ClassMethod\ReturnArrayClassMethodToYieldRector;
use Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector;
use Rector\CodingStyle\ValueObject\ReturnArrayClassMethodToYield;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::NAMING,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        SetList::CODING_STYLE,
    ]);

    $rectorConfig->ruleWithConfiguration(
        PreferThisOrSelfMethodCallRector::class,
        [
            'PHPUnit\Framework\TestCase' => PreferenceSelfThis::PREFER_THIS,
        ]
    );

    $rectorConfig->ruleWithConfiguration(ReturnArrayClassMethodToYieldRector::class, [
        new ReturnArrayClassMethodToYield('PHPUnit\Framework\TestCase', '*provide*'),
    ]);

    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->importNames();
    $rectorConfig->parallel();

    $rectorConfig->skip([
        \Rector\Php80\Rector\FunctionLike\UnionTypesRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector::class,
        \Rector\Php80\Rector\FunctionLike\MixedTypeRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector::class
    ]);

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};

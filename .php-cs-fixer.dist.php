<?php

declare(strict_types=1);

use EchoFusion\CodeStandard\PhpCsFixer\Factory;
use EchoFusion\CodeStandard\PhpCsFixer\EchoFusionRuleSet;

$config = Factory::fromRuleSet(new EchoFusionRuleSet());

$config->setUsingCache(false);

$config
    ->getFinder()
    ->ignoreDotFiles(false)
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('.php_cs');

return $config;
<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

$config = new Config();
$config = $config->setRiskyAllowed(true)
                ->setRules([
                    '@auto' => true,
                    '@auto:risky' => true,
                    'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
                    '@PER-CS' => true,
                    'strict_param' => true,
                    'declare_strict_types' => true
                ])
                ->setFinder($finder);

return $config;
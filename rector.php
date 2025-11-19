<?php

/**
 * @file
 * Rector configuration.
 *
 * Rector automatically refactors PHP code to:
 * - Upgrade deprecated Drupal APIs
 * - Modernize PHP syntax to leverage new language features
 * - Improve code quality and maintainability
 *
 * @see https://github.com/palantirnet/drupal-rector
 * @see https://getrector.com/documentation
 * @see https://getrector.com/documentation/set-lists
 */

declare(strict_types=1);

use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
  ->withPaths([
    '/app/docs.php',
    '/app/scripts',
    '/app/src',
    '/app/tests/behat/bootstrap',
    '/app/tests/behat/fixtures_drupal/d10/web/modules/custom',
    '/app/tests/behat/fixtures_drupal/d11/web/modules/custom',
    '/app/tests/phpunit/src',
  ])
  ->withSkip([
    // Specific rules to skip based on project coding standards.
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    CountArrayToEmptyArrayComparisonRector::class,
    DisallowedEmptyRuleFixerRector::class,
    FirstClassCallableRector::class,
    InlineArrayReturnAssignRector::class,
    NewlineAfterStatementRector::class,
    NewlineBeforeNewAssignSetRector::class,
    RemoveAlwaysTrueIfConditionRector::class,
    RenameForeachValueVariableToMatchExprVariableRector::class,
    RenameParamToMatchTypeRector::class,
    RenameVariableToMatchMethodCallReturnTypeRector::class,
    RenameVariableToMatchNewTypeRector::class,
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    // Directories to skip.
    '*/vendor/*',
    '*/node_modules/*',
    __DIR__ . '/tests/behat/bootstrap/BehatCliContext.php',
  ])
  // PHP version upgrade sets - modernizes syntax to PHP 8.2.
  // Includes all rules from PHP 5.3 through 8.2.
  ->withPhpSets(php82: TRUE)
  // Code quality improvement sets.
  ->withPreparedSets(
    codeQuality: TRUE,
    codingStyle: TRUE,
    deadCode: TRUE,
    naming: TRUE,
    privatization: TRUE,
    typeDeclarations: TRUE,
  )
  // Additional rules.
  ->withRules([
    DeclareStrictTypesRector::class,
  ])
  // Configure Drupal autoloading.
  ->withAutoloadPaths((function (): array {
    return [
      '/app/build/web/core',
      '/app/build/web/modules',
      '/app/build/web/themes',
      '/app/build/web/profiles',
    ];
  })())
  // Drupal file extensions.
  ->withFileExtensions([
    'php',
    'module',
    'install',
    'profile',
    'theme',
    'inc',
    'engine',
  ])
  // Import configuration.
  ->withImportNames(importNames: FALSE, importDocBlockNames: FALSE);

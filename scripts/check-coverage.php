<?php

/**
 * @file
 * Check code coverage for a specific trait.
 *
 * Usage:
 * php check-coverage.php <TraitName> [coverage_file_path]
 *
 * Where:
 * - TraitName: The name of the trait to check (e.g., "ElementTrait")
 * - coverage_file_path: Optional path to the cobertura.xml file.
 *   Defaults to '/app/.logs/coverage/behat_cli/cobertura.xml'.
 *
 * Examples:
 * php check-coverage.php ElementTrait
 * php check-coverage.php ResponsiveTrait .logs/coverage/behat/cobertura.xml
 */

declare(strict_types=1);

if (empty($argv[1])) {
  echo "Error: Trait name is required.\n\n";
  echo "Usage: php check-coverage.php <TraitName> [coverage_file_path]\n";
  echo "Example: php check-coverage.php ElementTrait\n";
  exit(1);
}

$trait_name = $argv[1];
$default_coverage_file = file_exists('/app/.logs/coverage/behat_cli/cobertura.xml')
  ? '/app/.logs/coverage/behat_cli/cobertura.xml'
  : __DIR__ . '/../.logs/coverage/behat_cli/cobertura.xml';
$coverage_file = $argv[2] ?? $default_coverage_file;

if (!file_exists($coverage_file)) {
  echo sprintf("Error: Coverage file not found: %s\n", $coverage_file);
  exit(1);
}

$xml = simplexml_load_file($coverage_file);
if ($xml === FALSE) {
  echo sprintf("Error: Failed to parse coverage file: %s\n", $coverage_file);
  exit(1);
}

$xml->registerXPathNamespace('c', 'http://cobertura.sourceforge.net/xml/coverage-04.dtd');

$classes = $xml->xpath(sprintf('//class[contains(@name, "%s")]', $trait_name));

if (empty($classes)) {
  echo sprintf("Error: Trait '%s' not found in coverage report.\n", $trait_name);
  exit(1);
}

foreach ($classes as $class) {
  $class_name = (string) $class['name'];
  $line_rate = (float) $class['line-rate'];
  $percentage = number_format($line_rate * 100, 2);

  echo sprintf("Class: %s\n", $class_name);
  echo sprintf("Line rate: %s (%s%%)\n\n", $line_rate, $percentage);

  $uncovered = [];
  if (property_exists($class->lines, 'line') && $class->lines->line !== NULL) {
    foreach ($class->lines->line as $line) {
      if ((string) $line['hits'] === '0') {
        $uncovered[] = (string) $line['number'];
      }
    }
  }

  echo "Uncovered lines:\n";
  if (!empty($uncovered)) {
    echo implode(', ', $uncovered) . "\n";
  }
  else {
    echo "None (100% coverage)\n";
  }

  echo "\n";
}

exit(0);

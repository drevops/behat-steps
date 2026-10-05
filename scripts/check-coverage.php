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
 * - coverage_file_path: Optional path to the cobertura.xml file. Defaults to
 *   '.logs/coverage/behat_cli/cobertura.xml', read from '/app' when the file
 *   exists there and from the repository root otherwise.
 *
 * Examples:
 * php check-coverage.php ElementTrait
 * php check-coverage.php ResponsiveTrait .logs/coverage/behat/cobertura.xml
 */

declare(strict_types=1);

if (empty($argv[1])) {
  echo 'Error: Trait name is required.' . PHP_EOL . PHP_EOL;
  echo 'Usage: php check-coverage.php <TraitName> [coverage_file_path]' . PHP_EOL;
  echo 'Example: php check-coverage.php ElementTrait' . PHP_EOL;
  exit(1);
}

$trait_name = $argv[1];
$default_coverage_file = file_exists('/app/.logs/coverage/behat_cli/cobertura.xml')
  ? '/app/.logs/coverage/behat_cli/cobertura.xml'
  : __DIR__ . '/../.logs/coverage/behat_cli/cobertura.xml';
$coverage_file = $argv[2] ?? $default_coverage_file;

if (!file_exists($coverage_file)) {
  echo sprintf('Error: Coverage file not found: %s' . PHP_EOL, $coverage_file);
  exit(1);
}

$xml = simplexml_load_file($coverage_file);
if ($xml === FALSE) {
  echo sprintf('Error: Failed to parse coverage file: %s' . PHP_EOL, $coverage_file);
  exit(1);
}

$xml->registerXPathNamespace('c', 'http://cobertura.sourceforge.net/xml/coverage-04.dtd');

$classes = $xml->xpath(sprintf('//class[contains(@name, "%s")]', $trait_name));

if (empty($classes)) {
  echo sprintf("Error: Trait '%s' not found in coverage report." . PHP_EOL, $trait_name);
  exit(1);
}

foreach ($classes as $class) {
  $class_name = (string) $class['name'];
  $line_rate = (float) $class['line-rate'];
  $percentage = number_format($line_rate * 100, 2);

  echo sprintf('Class: %s' . PHP_EOL, $class_name);
  echo sprintf('Line rate: %s (%s%%)' . PHP_EOL . PHP_EOL, $line_rate, $percentage);

  $uncovered = [];
  if (property_exists($class->lines, 'line') && $class->lines->line !== NULL) {
    foreach ($class->lines->line as $line) {
      if ((string) $line['hits'] === '0') {
        $uncovered[] = (string) $line['number'];
      }
    }
  }

  echo 'Uncovered lines:' . PHP_EOL;
  if (!empty($uncovered)) {
    echo implode(', ', $uncovered) . PHP_EOL;
  }
  else {
    echo 'None (100% coverage)' . PHP_EOL;
  }

  echo PHP_EOL;
}

exit(0);

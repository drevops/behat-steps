<?php

/**
 * @file
 * Layer dependency check.
 *
 * Two layers promise to run without a dependency loaded, and each promise
 * holds only while the layer references nothing from the namespaces it
 * excludes. This script reads every file of each layer and fails on any code
 * reference into those namespaces.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Random;

/**
 * Layers to check, each with its paths, forbidden roots and allowances.
 *
 * A path is a directory that is walked or a single file. An allowance names
 * one symbol under a forbidden root that the layer may still reference.
 */
const LAYERS = [
  [
    'name' => 'src/Driver',
    'paths' => ['src/Driver'],
    'forbidden' => ['Behat', 'Mink'],
    'allowed' => [],
  ],
  [
    'name' => 'the web half',
    'paths' => ['src/Steps/Web', 'src/Behat/Context/WebRawContext.php', 'src/Behat/Context/WebContext.php'],
    'forbidden' => ['Drupal'],
    // 'drupal/core-utility' ships the random generator and is a hard
    // requirement of the package, Drupal site or not.
    'allowed' => [Random::class],
  ],
];

// Execute the entry function only when the script is run directly, not when
// included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'lint-layers.php') {
  $options = getopt('', ['path::']);
  lint_layers($options);
}
// @codeCoverageIgnoreEnd

/**
 * Reports every forbidden reference in each declared layer.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function lint_layers(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : dirname(__DIR__);
  $violations = [];
  $checked = 0;

  foreach (LAYERS as $layer) {
    foreach ($layer['paths'] as $path) {
      $full_path = $base_path . DIRECTORY_SEPARATOR . $path;

      if (!file_exists($full_path)) {
        echo sprintf("Error: %s does not exist.\n", $full_path);
        exit(1);
      }

      foreach (layer_files($full_path) as $file) {
        $checked++;

        foreach (layer_file_violations($file, $layer['forbidden'], $layer['allowed']) as $violation) {
          $relative = substr($file, strlen($base_path) + 1);
          $violations[] = sprintf('%s: %s:%d references %s', $layer['name'], $relative, $violation['line'], $violation['symbol']);
        }
      }
    }
  }

  if ($violations !== []) {
    echo "Layer boundaries crossed:\n\n";
    echo implode("\n", $violations) . "\n";
    exit(1);
  }

  echo sprintf("Every layer holds its boundary: %d files checked.\n", $checked);
  exit(0);
}

// @codeCoverageIgnoreEnd

/**
 * Lists the PHP files a path covers.
 *
 * @param string $path
 *   Absolute path to a directory to walk or to a single file.
 *
 * @return array<int, string>
 *   Absolute file paths, sorted.
 */
function layer_files(string $path): array {
  if (!is_dir($path)) {
    return [$path];
  }

  $files = [];
  $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

  foreach ($iterator as $file) {
    if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
      $files[] = $file->getPathname();
    }
  }

  sort($files);

  return $files;
}

/**
 * Finds references to forbidden root namespaces in one file.
 *
 * @param string $file
 *   Absolute path to the file to read.
 * @param array<int, string> $forbidden_roots
 *   Root namespaces that must not be referenced.
 * @param array<int, string> $allowed
 *   Symbols under a forbidden root that the layer may reference.
 *
 * @return array<int, array{line: int, symbol: string}>
 *   One row per reference, in source order.
 */
function layer_file_violations(string $file, array $forbidden_roots, array $allowed = []): array {
  $violations = [];

  foreach (token_get_all((string) file_get_contents($file)) as $token) {
    if (!is_array($token)) {
      continue;
    }

    $symbol = layer_referenced_symbol($token[0], $token[1]);

    if ($symbol === NULL) {
      continue;
    }

    $qualified = ltrim($symbol, '\\');

    if (in_array($qualified, $allowed, TRUE)) {
      continue;
    }

    if (!in_array(strtok($qualified, '\\'), $forbidden_roots, TRUE)) {
      continue;
    }

    $violations[] = ['line' => $token[2], 'symbol' => $symbol];
  }

  return $violations;
}

/**
 * Reads the symbol a token refers to, if it refers to one.
 *
 * Qualified names cover imports, type declarations and inline references. A
 * single-quoted literal shaped like a qualified name is included too, because
 * a class name reached through a string skips the compiler but not the
 * autoloader. Comments and docblocks are not code, so a prose mention of
 * Behat is not a reference.
 *
 * @param int $type
 *   The token type.
 * @param string $text
 *   The token text.
 *
 * @return string|null
 *   The referenced symbol, or NULL when the token refers to none.
 */
function layer_referenced_symbol(int $type, string $text): ?string {
  if ($type === T_NAME_QUALIFIED || $type === T_NAME_FULLY_QUALIFIED) {
    return $text;
  }

  if ($type !== T_CONSTANT_ENCAPSED_STRING) {
    return NULL;
  }

  $literal = str_replace('\\\\', '\\', substr($text, 1, -1));

  return preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $literal) === 1 ? $literal : NULL;
}

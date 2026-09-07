<?php

/**
 * @file
 * Layer dependency check.
 *
 * The driver layer is usable without Behat loaded, which only holds while it
 * references nothing from Behat or Mink. This script reads every file under
 * src/Driver and fails on any code reference into those namespaces.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

/**
 * Root namespaces the driver layer must not reference.
 */
const LAYER_FORBIDDEN_ROOTS = ['Behat', 'Mink'];

/**
 * Directory holding the driver layer, relative to the repository root.
 */
const LAYER_DIRECTORY = 'src/Driver';

// Execute the entry function only when the script is run directly, not when included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'lint-layers.php') {
  $options = getopt('', ['path::']);
  lint_layers($options);
}
// @codeCoverageIgnoreEnd

/**
 * Reports every forbidden reference in the driver layer.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function lint_layers(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : dirname(__DIR__);
  $directory = $base_path . DIRECTORY_SEPARATOR . LAYER_DIRECTORY;

  if (!is_dir($directory)) {
    echo sprintf("Error: %s does not exist.\n", $directory);
    exit(1);
  }

  $files = layer_files($directory);
  $violations = [];

  foreach ($files as $file) {
    foreach (layer_file_violations($file, LAYER_FORBIDDEN_ROOTS) as $violation) {
      $relative = substr($file, strlen($base_path) + 1);
      $violations[] = sprintf('%s:%d references %s', $relative, $violation['line'], $violation['symbol']);
    }
  }

  if ($violations !== []) {
    echo sprintf("The %s layer must not reference %s:\n\n", LAYER_DIRECTORY, implode(' or ', LAYER_FORBIDDEN_ROOTS));
    echo implode("\n", $violations) . "\n";
    exit(1);
  }

  echo sprintf("%s references neither %s: %d files checked.\n", LAYER_DIRECTORY, implode(' nor ', LAYER_FORBIDDEN_ROOTS), count($files));
  exit(0);
}

// @codeCoverageIgnoreEnd

/**
 * Lists the PHP files in a directory tree.
 *
 * @param string $directory
 *   Absolute path to the directory to walk.
 *
 * @return array<int, string>
 *   Absolute file paths, sorted.
 */
function layer_files(string $directory): array {
  $files = [];
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

  foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
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
 *
 * @return array<int, array{line: int, symbol: string}>
 *   One row per reference, in source order.
 */
function layer_file_violations(string $file, array $forbidden_roots): array {
  $violations = [];

  foreach (token_get_all((string) file_get_contents($file)) as $token) {
    if (!is_array($token)) {
      continue;
    }

    $symbol = layer_referenced_symbol($token[0], $token[1]);

    if ($symbol === NULL) {
      continue;
    }

    $root = strtok(ltrim($symbol, '\\'), '\\');

    if (!in_array($root, $forbidden_roots, TRUE)) {
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

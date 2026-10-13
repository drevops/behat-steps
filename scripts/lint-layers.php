<?php

/**
 * @file
 * Layer dependency check.
 *
 * Each layer is guaranteed to reference nothing from the namespaces it
 * excludes: the backend runs without Behat loaded, the neutral root of each
 * layer runs without Drupal, the Drupal step traits reach Drupal only through
 * a backend, and no layer reaches the one above it. The guarantee holds only
 * while the layer references nothing it excludes.
 *
 * This script reads every file of each layer and fails on any code reference
 * into those namespaces.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\WebContext;
use DrevOps\BehatSteps\WebRawContext;

/**
 * Layers to check, each with its paths, exclusions and prefixes.
 *
 * A path is a directory that is walked or a single file, and an exclusion
 * is a directory or a file under a path that is skipped. A forbidden prefix
 * is a root namespace or a longer namespace prefix: a symbol equal to it or
 * below it is a violation.
 */
const LAYERS = [
  [
    'name' => 'src/Backend',
    'paths' => ['src/Backend'],
    'exclude' => [],
    'forbidden' => [
      'Behat',
      'Mink',
      'DrevOps\\BehatSteps\\Extension',
      'DrevOps\\BehatSteps\\Steps',
      WebRawContext::class,
      WebContext::class,
      DrupalContext::class,
    ],
  ],
  [
    'name' => 'the neutral root of src/Backend',
    'paths' => ['src/Backend'],
    'exclude' => ['src/Backend/Drupal'],
    'forbidden' => ['Drupal', 'DrevOps\\BehatSteps\\Backend\\Drupal'],
  ],
  [
    'name' => 'src/Extension',
    'paths' => ['src/Extension'],
    // The generator writes the root context into the starter class it emits.
    'exclude' => ['src/Extension/Generator/ClassGenerator.php'],
    'forbidden' => [
      'DrevOps\\BehatSteps\\Steps',
      WebRawContext::class,
      WebContext::class,
      DrupalContext::class,
    ],
  ],
  [
    'name' => 'the neutral root of src/Extension',
    'paths' => ['src/Extension'],
    // The extension class is the composition root that lists the shipped
    // backend providers.
    'exclude' => ['src/Extension/Drupal', 'src/Extension/ServiceContainer/BehatStepsExtension.php'],
    'forbidden' => ['Drupal', 'DrevOps\\BehatSteps\\Backend\\Drupal', 'DrevOps\\BehatSteps\\Extension\\Drupal'],
  ],
  [
    'name' => 'the web half',
    'paths' => [
      'src/Steps/Web',
      'src/Steps/Helper/Web',
      'src/WebRawContext.php',
      'src/WebContext.php',
    ],
    'exclude' => [],
    'forbidden' => ['Drupal'],
  ],
  [
    'name' => 'the Drupal step traits',
    'paths' => ['src/Steps/Drupal', 'src/Steps/Helper/Drupal'],
    // These traits load or save Drupal entity objects through Drupal's own
    // API, so they reference its entity classes.
    'exclude' => [
      'src/Steps/Drupal/BlockTrait.php',
      'src/Steps/Drupal/ContentBlockTrait.php',
      'src/Steps/Drupal/ContentTrait.php',
      'src/Steps/Drupal/DraggableviewsTrait.php',
      'src/Steps/Drupal/EckTrait.php',
      'src/Steps/Drupal/FileTrait.php',
      'src/Steps/Drupal/MediaTrait.php',
      'src/Steps/Drupal/MenuTrait.php',
      'src/Steps/Drupal/ParagraphsTrait.php',
      'src/Steps/Drupal/RedirectTrait.php',
      'src/Steps/Drupal/SearchApiTrait.php',
      'src/Steps/Drupal/TaxonomyTrait.php',
      'src/Steps/Drupal/UserTrait.php',
      'src/Steps/Drupal/WebformTrait.php',
      'src/Steps/Helper/Drupal/EntityLifecycleTrait.php',
    ],
    'forbidden' => ['Drupal'],
  ],
];

// The entry function runs only when the script is run directly, not when it
// is included.
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
      if (!file_exists($base_path . '/' . $path)) {
        echo sprintf('Error: %s does not exist.' . PHP_EOL, $base_path . '/' . $path);
        exit(1);
      }
    }

    foreach (layer_paths_files($layer['paths'], $layer['exclude'], $base_path) as $file) {
      $checked++;

      foreach (layer_file_violations($file, $layer['forbidden']) as $violation) {
        $relative = substr($file, strlen($base_path) + 1);
        $violations[] = sprintf('%s: %s:%d references %s', $layer['name'], $relative, $violation['line'], $violation['symbol']);
      }
    }
  }

  if ($violations !== []) {
    echo 'Layer boundaries crossed:' . PHP_EOL . PHP_EOL;
    echo implode(PHP_EOL, $violations) . PHP_EOL;
    exit(1);
  }

  echo sprintf('Every layer holds its boundary: %d files checked.' . PHP_EOL, $checked);
  exit(0);
}

// @codeCoverageIgnoreEnd

/**
 * Lists the PHP files a layer's paths cover, minus its exclusions.
 *
 * @param array<int, string> $paths
 *   Directories to walk or single files, relative to the base path.
 * @param array<int, string> $exclude
 *   Directories or files under the paths to skip, relative to the base path.
 * @param string $base_path
 *   Absolute path to the repository root.
 *
 * @return array<int, string>
 *   Absolute file paths, sorted.
 */
function layer_paths_files(array $paths, array $exclude, string $base_path): array {
  $files = [];

  foreach ($paths as $path) {
    $files = array_merge($files, layer_files($base_path . '/' . $path));
  }

  $files = array_filter($files, static function (string $file) use ($exclude, $base_path): bool {
    foreach ($exclude as $excluded) {
      $excluded_path = $base_path . '/' . $excluded;

      if ($file === $excluded_path || str_starts_with($file, $excluded_path . '/')) {
        return FALSE;
      }
    }

    return TRUE;
  });

  sort($files);

  return $files;
}

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
 * Finds references to forbidden namespace prefixes in one file.
 *
 * @param string $file
 *   Absolute path to the file to read.
 * @param array<int, string> $forbidden
 *   Namespace prefixes that must not be referenced.
 *
 * @return array<int, array{line: int, symbol: string}>
 *   1 row per reference, in source order.
 */
function layer_file_violations(string $file, array $forbidden): array {
  $violations = [];

  foreach (token_get_all((string) file_get_contents($file)) as $token) {
    if (!is_array($token)) {
      continue;
    }

    $symbol = layer_referenced_symbol($token[0], $token[1]);

    if ($symbol === NULL) {
      continue;
    }

    if (!layer_is_forbidden(ltrim($symbol, '\\'), $forbidden)) {
      continue;
    }

    $violations[] = ['line' => $token[2], 'symbol' => $symbol];
  }

  return $violations;
}

/**
 * Checks whether a symbol sits under one of the forbidden prefixes.
 *
 * A prefix matches the symbol equal to it and every symbol below it, so a
 * root namespace forbids a whole vendor and a longer prefix forbids 1
 * namespace or 1 class.
 *
 * @param string $symbol
 *   The qualified symbol, without a leading separator.
 * @param array<int, string> $forbidden
 *   The forbidden prefixes.
 */
function layer_is_forbidden(string $symbol, array $forbidden): bool {
  foreach ($forbidden as $prefix) {
    if ($symbol === $prefix || str_starts_with($symbol, $prefix . '\\')) {
      return TRUE;
    }
  }

  return FALSE;
}

/**
 * Reads the symbol a token refers to, if it refers to one.
 *
 * Qualified names cover imports, type declarations and inline references. A
 * string literal without interpolation shaped like a qualified name is
 * included too: the compiler does not resolve it, but the autoloader does.
 *
 * Comments and docblocks are not code, so a prose mention of Behat is not a
 * reference.
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

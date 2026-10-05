<?php

/**
 * @file
 * Trait composition check.
 *
 * A trait's directory determines its kind: 'src/Steps' holds the step
 * vocabulary and 'src/Helper' holds the plumbing shared by step traits. Each
 * is split into a 'Web' and a 'Drupal' half.
 *
 * This script reads both trees and fails when a step trait composes another
 * step trait, or when a helper trait registers Gherkin.
 *
 * A helper may register a hook: the trait that owns a teardown carries the
 * hook that runs it.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

/**
 * Directory holding the step vocabulary, relative to the repository root.
 */
const TRAITS_STEPS_DIRECTORY = 'src/Steps';

/**
 * Directory holding the plumbing, relative to the repository root.
 */
const TRAITS_HELPER_DIRECTORY = 'src/Helper';

/**
 * Attributes registering Gherkin against a method.
 */
const TRAITS_VOCABULARY_ATTRIBUTES = ['Given', 'When', 'Then', 'Transform'];

// The entry function runs only when the script is run directly, not when it
// is included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'lint-traits.php') {
  $options = getopt('', ['path::']);
  lint_traits($options);
}
// @codeCoverageIgnoreEnd

/**
 * Reports every composition rule a shipped trait breaks.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function lint_traits(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : dirname(__DIR__);
  $traits = traits_collect($base_path);
  $violations = traits_violations($traits);

  if ($violations !== []) {
    echo 'Trait composition is inconsistent:' . PHP_EOL . PHP_EOL;
    echo implode(PHP_EOL, $violations) . PHP_EOL;
    exit(1);
  }

  echo sprintf('Every trait composes what its directory allows: %d traits checked.' . PHP_EOL, count($traits));
  exit(0);
}

// @codeCoverageIgnoreEnd

/**
 * Reads the traits of a repository, keyed by short name.
 *
 * @param string $base_path
 *   Absolute path to the repository root.
 *
 * @return array<string, array{kind: string, composed: array<int, string>, members: array<int, string>}>
 *   One entry per trait, sorted by short name.
 */
function traits_collect(string $base_path): array {
  $traits = [];

  foreach (['steps' => TRAITS_STEPS_DIRECTORY, 'helper' => TRAITS_HELPER_DIRECTORY] as $kind => $directory) {
    $path = $base_path . '/' . $directory;

    if (!is_dir($path)) {
      continue;
    }

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $traits[$file->getBasename('.php')] = ['kind' => $kind] + traits_file_facts($file->getPathname());
    }
  }

  ksort($traits);

  return $traits;
}

/**
 * Reads the composition facts of one trait file.
 *
 * A trait declaration splits the file: an attribute after it belongs to a
 * member, and a 'use' after it composes another trait.
 *
 * @param string $file
 *   Absolute path to the file to read.
 *
 * @return array{composed: array<int, string>, members: array<int, string>}
 *   The traits it composes and the attributes on its members.
 */
function traits_file_facts(string $file): array {
  $facts = ['composed' => [], 'members' => []];

  $is_declared = FALSE;
  $attribute = 0;
  $parentheses = 0;
  $is_composing = FALSE;

  foreach (token_get_all((string) file_get_contents($file)) as $token) {
    $type = is_array($token) ? $token[0] : NULL;
    $text = is_array($token) ? $token[1] : $token;

    if ($type === T_ATTRIBUTE) {
      $attribute = 1;
      $parentheses = 0;

      continue;
    }

    if ($attribute > 0) {
      $attribute += (int) ($text === '[') - (int) ($text === ']');
      $parentheses += (int) ($text === '(') - (int) ($text === ')');
      $name = $attribute === 1 && $parentheses === 0 ? traits_name($type, $text) : NULL;

      if ($name !== NULL && $is_declared) {
        $facts['members'][] = $name;
      }

      continue;
    }

    if ($type === T_TRAIT) {
      $is_declared = TRUE;

      continue;
    }

    if ($is_composing) {
      $is_composing = $text !== ';' && $text !== '{';
      $name = traits_name($type, $text);

      if ($name !== NULL) {
        $facts['composed'][] = $name;
      }

      continue;
    }

    $is_composing = $is_declared && $type === T_USE;
  }

  return $facts;
}

/**
 * Reads the short name a token declares, if it declares one.
 *
 * @param int|null $type
 *   The token type, or NULL for a character token.
 * @param string $text
 *   The token text.
 *
 * @return string|null
 *   The short name, or NULL when the token names nothing.
 */
function traits_name(?int $type, string $text): ?string {
  if (!in_array($type, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE)) {
    return NULL;
  }

  $separator = strrchr($text, '\\');

  return $separator === FALSE ? $text : substr($separator, 1);
}

/**
 * Finds every composition rule the given traits break.
 *
 * @param array<string, array{kind: string, composed: array<int, string>, members: array<int, string>}> $traits
 *   The traits to check, keyed by short name.
 *
 * @return array<int, string>
 *   One row per violation, in trait order.
 */
function traits_violations(array $traits): array {
  $violations = [];

  foreach ($traits as $name => $facts) {
    if ($facts['kind'] === 'steps') {
      foreach ($facts['composed'] as $composed) {
        if (($traits[$composed]['kind'] ?? NULL) === 'steps') {
          $violations[] = sprintf('%s composes the step trait %s', $name, $composed);
        }
      }

      continue;
    }

    foreach ($facts['members'] as $member) {
      if (in_array($member, TRAITS_VOCABULARY_ATTRIBUTES, TRUE)) {
        $violations[] = sprintf('%s registers Gherkin through #[%s]', $name, $member);
      }
    }
  }

  return $violations;
}

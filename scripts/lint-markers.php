<?php

/**
 * @file
 * Trait marker check.
 *
 * Every trait the package ships is either vocabulary or plumbing, and the
 * marker attribute it carries says which. This script reads the traits under
 * the marked directories and fails when a marker is missing, when a
 * vocabulary trait composes another one, or when a plumbing trait registers
 * Gherkin.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

/**
 * Directories holding marked traits, relative to the repository root.
 */
const MARKER_DIRECTORIES = ['src/Steps', 'src/Helper'];

/**
 * Attribute marking a trait as vocabulary.
 */
const MARKER_STEPS = 'Steps';

/**
 * Attribute marking a trait as plumbing.
 */
const MARKER_HELPER = 'Helper';

/**
 * Attributes registering Gherkin against a method.
 */
const MARKER_VOCABULARY_ATTRIBUTES = ['Given', 'When', 'Then', 'Transform'];

/**
 * Plumbing traits allowed to register a hook.
 *
 * A scenario that creates Drupal content has to clean it up again, so the
 * trait carrying that lifecycle carries the hooks that close it.
 */
const MARKER_HOOK_ALLOWED = ['DrupalApiTrait'];

// Execute the entry function only when the script is run directly, not when
// included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'lint-markers.php') {
  $options = getopt('', ['path::']);
  lint_markers($options);
}
// @codeCoverageIgnoreEnd

/**
 * Reports every marker rule a shipped trait breaks.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function lint_markers(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : dirname(__DIR__);
  $traits = marker_traits($base_path);
  $violations = marker_violations($traits);

  if ($violations !== []) {
    echo "Trait markers are inconsistent:\n\n";
    echo implode("\n", $violations) . "\n";
    exit(1);
  }

  echo sprintf("Every trait declares its marker: %d traits checked.\n", count($traits));
  exit(0);
}

// @codeCoverageIgnoreEnd

/**
 * Reads the marked traits of a repository.
 *
 * @param string $base_path
 *   Absolute path to the repository root.
 *
 * @return array<string, array{markers: array<int, string>, composed: array<int, string>, members: array<int, string>}>
 *   One entry per trait, keyed by short name and sorted by that name.
 */
function marker_traits(string $base_path): array {
  $traits = [];

  foreach (MARKER_DIRECTORIES as $directory) {
    $path = $base_path . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($path)) {
      continue;
    }

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $traits[$file->getBasename('.php')] = marker_file_facts($file->getPathname());
    }
  }

  ksort($traits);

  return $traits;
}

/**
 * Reads the marker facts of one trait file.
 *
 * A trait declaration splits the file: an attribute before it belongs to the
 * trait, and one after it to a member.
 *
 * @param string $file
 *   Absolute path to the file to read.
 *
 * @return array{markers: array<int, string>, composed: array<int, string>, members: array<int, string>}
 *   The attributes on the trait, the traits it composes and the attributes on
 *   its members.
 */
function marker_file_facts(string $file): array {
  $facts = ['markers' => [], 'composed' => [], 'members' => []];

  $declared = FALSE;
  $attribute = 0;
  $parentheses = 0;
  $composing = FALSE;

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
      $name = $attribute === 1 && $parentheses === 0 ? marker_name($type, $text) : NULL;

      if ($name !== NULL) {
        $facts[$declared ? 'members' : 'markers'][] = $name;
      }

      continue;
    }

    if ($type === T_TRAIT) {
      $declared = TRUE;

      continue;
    }

    if ($composing) {
      $composing = $text !== ';' && $text !== '{';
      $name = marker_name($type, $text);

      if ($name !== NULL) {
        $facts['composed'][] = $name;
      }

      continue;
    }

    $composing = $declared && $type === T_USE;
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
function marker_name(?int $type, string $text): ?string {
  if (!in_array($type, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE)) {
    return NULL;
  }

  $separator = strrchr($text, '\\');

  return $separator === FALSE ? $text : substr($separator, 1);
}

/**
 * Finds every marker rule the given traits break.
 *
 * @param array<string, array{markers: array<int, string>, composed: array<int, string>, members: array<int, string>}> $traits
 *   The traits to check, keyed by short name.
 *
 * @return array<int, string>
 *   One row per violation, in trait order.
 */
function marker_violations(array $traits): array {
  $violations = [];
  $markers = [];

  foreach ($traits as $name => $facts) {
    $found = array_values(array_unique(array_intersect($facts['markers'], [MARKER_STEPS, MARKER_HELPER])));

    if (count($found) === 1) {
      $markers[$name] = $found[0];

      continue;
    }

    $violations[] = sprintf('%s carries %d markers, and exactly one of #[Steps] or #[Helper] is required', $name, count($found));
  }

  foreach ($traits as $name => $facts) {
    $marker = $markers[$name] ?? NULL;

    foreach ($facts['composed'] as $composed) {
      if ($marker === MARKER_STEPS && ($markers[$composed] ?? NULL) === MARKER_STEPS) {
        $violations[] = sprintf('%s composes the vocabulary trait %s', $name, $composed);
      }
    }

    if ($marker !== MARKER_HELPER) {
      continue;
    }

    foreach ($facts['members'] as $member) {
      if (in_array($member, MARKER_VOCABULARY_ATTRIBUTES, TRUE)) {
        $violations[] = sprintf('%s registers Gherkin through #[%s]', $name, $member);

        continue;
      }

      if (!marker_is_hook($member) || in_array($name, MARKER_HOOK_ALLOWED, TRUE)) {
        continue;
      }

      $violations[] = sprintf('%s registers a hook through #[%s]', $name, $member);
    }
  }

  return $violations;
}

/**
 * Determines whether an attribute name registers a hook.
 *
 * Behat and this package both name a hook attribute after the point it fires
 * at, so the prefix identifies one without a list to maintain.
 *
 * @param string $name
 *   The attribute short name.
 *
 * @return bool
 *   TRUE when the attribute registers a hook.
 */
function marker_is_hook(string $name): bool {
  return str_starts_with($name, 'Before') || str_starts_with($name, 'After');
}

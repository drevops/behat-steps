<?php

/**
 * @file
 * Documentation generator.
 */

declare(strict_types=1);

require_once __DIR__ . '/build/vendor/autoload.php';
require_once __DIR__ . '/tests/behat/bootstrap/FeatureContextTrait.php';
require_once __DIR__ . '/tests/behat/bootstrap/FeatureContext.php';

$info = extract_info(FeatureContext::class, [FeatureContextTrait::class]);

print_report($info);

$markdown = PHP_EOL . info_to_content($info) . PHP_EOL;

$readme_file = 'steps.md';
$readme = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $readme_file);

if ($readme === FALSE) {
  printf('Failed to read %s.' . PHP_EOL, $readme_file);
  exit(1);
}

$readme_replaced = replace_content($readme, '# Available steps', '[//]: # (END)', $markdown);

if ($readme_replaced === $readme) {
  echo 'Documentation is up to date. No changes were made.' . PHP_EOL;
  exit(0);
}

$fail_on_change = ($argv[1] ?? '') === '--fail-on-change';
if ($fail_on_change && $readme_replaced !== $readme) {
  echo 'Documentation is outdated. No changes were made.' . PHP_EOL;
  exit(1);
}
else {
  file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . $readme_file, $readme_replaced);
  echo 'Documentation updated.' . PHP_EOL;
}

/**
 * Parse info from the class.
 *
 * @param class-string $class_name
 *   The class name.
 * @param array<int, string> $exclude
 *   Array of trait names to exclude.
 *
 * @return array<string, array<int,array<string, array<int, string>|string>>>
 *   Array of info with 'name', 'steps', 'description', and 'example' keys.
 *
 * @throws \ReflectionException
 */
function extract_info(string $class_name, array $exclude = []): array {
  $reflection = new ReflectionClass($class_name);

  $traits = $reflection->getTraits();

  $result = [];
  foreach ($traits as $trait) {
    $trait_name = $trait->getShortName();

    if (in_array($trait_name, $exclude, TRUE)) {
      continue;
    }

    $trait_prefix = str_replace('Trait', '', $trait_name);

    $methods = $trait->getMethods(ReflectionMethod::IS_PUBLIC);

    $info = [];
    foreach ($methods as $method) {
      if (!str_starts_with(strtolower($method->getName()), strtolower($trait_prefix))) {
        continue;
      }

      $comment = $method->getDocComment();

      if ($comment) {
        $parsed = parse_method_comment($comment);
        if ($parsed) {
          $info[] = $parsed + ['name' => $method->getName()];
        }
      }
    }

    if (!empty($info)) {
      // Sort info by Given, When, Then.
      usort($info, static function (array $a, array $b): int {
        $order = ['@Given', '@When', '@Then'];

        $get_order_index = function ($step) use ($order): int {
          foreach ($order as $index => $prefix) {
            if (str_starts_with($step, $prefix)) {
              return $index;
            }
          }

          return PHP_INT_MAX;
        };

        $a_step = $a['steps'][0] ?? '';
        $b_step = $b['steps'][0] ?? '';

        $a_index = $get_order_index($a_step);
        $b_index = $get_order_index($b_step);

        return $a_index <=> $b_index;
      });

      $result[$trait->getShortName()] = $info;
    }
  }

  return $result;
}

/**
 * Parse comment.
 *
 * @param string $comment
 *   The comment.
 *
 * @return array<string, array<int, string>|string>|null
 *   Array of 'steps', 'description', and 'example' keys or NULL if steps were
 *   not found in the comment.
 */
function parse_method_comment(string $comment): ?array {
  $return = [
    'steps' => [],
    'description' => '',
    'example' => '',
  ];

  $lines = explode(PHP_EOL, $comment);

  $example_start = FALSE;
  foreach ($lines as $line) {
    $line = str_replace('/*', '', $line);
    $line = str_replace('/**', '', $line);
    $line = str_replace('*/', '', $line);
    $line = preg_replace('/^\s*\*/', '', $line);
    $line = rtrim((string) $line, " \t\n\r\0\x0B");
    // All docblock lines start with a space.
    $line = substr($line, 1);

    if (str_starts_with($line, '@code')) {
      $example_start = TRUE;
    }
    elseif (str_starts_with($line, '@endcode')) {
      $example_start = FALSE;
    }
    elseif (str_starts_with($line, '@Given') || str_starts_with($line, '@When') || str_starts_with($line, '@Then')) {
      $line = trim($line, " \t\n\r\0\x0B");
      $return['steps'][] = $line;
    }
    else {
      if (!$example_start && empty($line)) {
        continue;
      }

      if ($example_start) {
        $line = rtrim($line, "\t\n\r\0\x0B");
        $return['example'] .= $line . PHP_EOL;
      }

      if (empty($return['description'])) {
        $line = trim($line);
        $return['description'] .= $line . ' ';
      }
    }
  }

  if ($example_start) {
    throw new \Exception('Example not closed');
  }

  if (!empty($return['steps'])) {
    // Sort the steps by Given, When, Then.
    $sorted = [];
    foreach (['@Given', '@When', '@Then'] as $step) {
      foreach ($return['steps'] as $step_item) {
        if (str_starts_with($step_item, $step)) {
          $sorted[] = $step_item;
        }
      }
    }
    $return['steps'] = $sorted;

    $return['description'] = trim($return['description']);
  }

  return empty($return['steps']) ? NULL : $return;
}

/**
 * Convert info to content.
 *
 * @param array<string, array<int, array<string, array<int, string>|string>>> $info
 *   Array of info items with 'name', 'from', and 'to' keys.
 *
 * @return string
 *   Markdown table.
 */
function info_to_content(array $info): string {
  $output = '';

  $index_output = '';

  foreach ($info as $trait => $methods) {
    $src_file = sprintf('src/%s.php', $trait);

    if (!file_exists($src_file)) {
      throw new \Exception(sprintf('Source file %s does not exist', $src_file));
    }

    $example_name = camel_to_snake(str_replace('Trait', '', $trait));
    $example_file = sprintf('tests/behat/features/%s.feature', $example_name);

    if (!file_exists($example_file)) {
      throw new \Exception(sprintf('Example file %s does not exist', $example_file));
    }

    $output .= sprintf('### %s', $trait) . PHP_EOL . PHP_EOL;
    $output .= sprintf('[Source](%s), [Example](%s)', $src_file, $example_file) . PHP_EOL . PHP_EOL;

    $index_output .= sprintf('- [%s](#%s)' . PHP_EOL, $trait, strtolower($trait)) . PHP_EOL;

    foreach ($methods as $method) {
      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      $steps = array_reduce($method['steps'], function (string $carry, $item): string {
        return $carry . sprintf("```gherkin\n%s\n```\n", $item);
      }, '');

      $steps = rtrim($steps, "\n");

      $description = rtrim($method['description'], '.');

      $output .= '#### ' . $description . PHP_EOL . PHP_EOL;
      $output .= $steps . PHP_EOL;

      if (!empty($method['example'])) {
        $example = sprintf("```gherkin\n%s```", $method['example']);
        $output .= 'Example:' . PHP_EOL;
        $output .= $example . PHP_EOL;
      }

      $output .= PHP_EOL;
    }
  }

  return $index_output . PHP_EOL . $output;
}

/**
 * Print report.
 *
 * @param array<string, array<int, array<string, array<int, string>|string>>> $info
 *   Array of info items with 'name', 'from', and 'to' keys.
 */
function print_report(array $info): void {
  printf('Report:' . PHP_EOL);

  foreach ($info as $trait => $methods) {
    foreach ($methods as $method) {
      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['name'] = is_string($method['name']) ? $method['name'] : '';
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      $step = (string) $method['steps'][0];

      if (str_starts_with($step, '@Given') && str_ends_with($step, ':') && !str_contains($step, 'following')) {
        printf('  %s::%s - %s' . PHP_EOL, $trait, $method['name'], 'Missing "following" in the step');
      }

      if (str_starts_with($step, '@When') && !str_contains($step, 'I ')) {
        printf('  %s::%s - %s' . PHP_EOL, $trait, $method['name'], 'Missing "I " in the step');
      }

      if (str_starts_with($step, '@Then') && !str_contains($method['name'], 'Assert')) {
        printf('  %s::%s - %s' . PHP_EOL, $trait, $method['name'], 'Missing "Assert" in the method name');
      }

      if (str_starts_with($step, '@Then') && !str_contains($step, 'should')) {
        printf('  %s::%s - %s' . PHP_EOL, $trait, $method['name'], 'Missing "should" in the step');
      }

      if (str_starts_with($step, '@Then') && !str_contains($step, 'the')) {
        printf('  %s::%s - %s' . PHP_EOL, $trait, $method['name'], 'Missing "the" in the step');
      }

      if (empty($method['example'])) {
        printf('  %s::%s - Missing example' . PHP_EOL, $trait, $method['name']);
      }
    }
  }
}

/**
 * Convert a string to snake case.
 *
 * @param string $string
 *   The string to convert.
 * @param string $separator
 *   The separator.
 *
 * @return string
 *   The converted string.
 */
function camel_to_snake(string $string, string $separator = '_'): string {
  $string = preg_replace_callback('/([^0-9])(\d+)/', static function (array $matches) use ($separator): string {
    return $matches[1] . $separator . $matches[2];
  }, $string);

  $replacements = [];
  foreach (mb_str_split((string) $string) as $key => $char) {
    $lower_case_char = mb_strtolower($char);
    if ($lower_case_char !== $char && $key !== 0) {
      $replacements[$char] = $separator . $char;
    }
  }
  $string = str_replace(array_keys($replacements), array_values($replacements), (string) $string);

  $string = trim($string, $separator);

  return mb_strtolower($string);
}

/**
 * Replace content in a string.
 *
 * @param string $haystack
 *   The content to search and replace in.
 * @param string $start
 *   The start of the content to replace.
 * @param string $end
 *   The end of the content to replace.
 * @param string $replacement
 *   The replacement content.
 */
function replace_content(string $haystack, string $start, string $end, string $replacement): string {
  if (!str_contains($haystack, $start)) {
    throw new \Exception('Start not found in the haystack');
  }

  if (!str_contains($haystack, $end)) {
    throw new \Exception('End not found in the haystack');
  }

  // Start should be before the end.
  if (strpos($haystack, $start) > strpos($haystack, $end)) {
    throw new \Exception('Start is after the end');
  }

  $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
  $replacement = $start . PHP_EOL . $replacement . PHP_EOL . $end;

  return (string) preg_replace($pattern, $replacement, $haystack);
}

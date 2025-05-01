<?php

/**
 * @file
 * Documentation generator.
 *
 * This script generates the documentation for the steps in the Behat
 * features.
 *
 * It parses the docblock comments of the classes and methods in the
 * src directory and generates steps.md file.
 *
 * It also validates the steps and checks if they are in the correct
 * format.
 *
 * Run with --fail-on-change to fail if the documentation is not up to date.
 */

declare(strict_types=1);

require_once __DIR__ . '/build/vendor/autoload.php';
require_once __DIR__ . '/tests/behat/bootstrap/FeatureContextTrait.php';
require_once __DIR__ . '/tests/behat/bootstrap/FeatureContext.php';

$info = extract_info(FeatureContext::class, [FeatureContextTrait::class]);

$errors = validate($info);

if (!empty($errors)) {
  echo 'Errors found:' . PHP_EOL;
  foreach ($errors as $error) {
    echo $error;
  }
  exit(1);
}

$markdown = PHP_EOL . render_info($info) . PHP_EOL;

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
  usort(
    $traits,
    static fn(\ReflectionClass $a, \ReflectionClass $b): int => strcasecmp($a->getShortName(), $b->getShortName())
  );

  $result = [];
  foreach ($traits as $trait) {
    $trait_name = $trait->getShortName();

    if (in_array($trait_name, $exclude, TRUE)) {
      continue;
    }

    $trait_prefix = str_replace('Trait', '', $trait_name);

    $methods = $trait->getMethods(ReflectionMethod::IS_PUBLIC);

    $info = [];

    $class_description = $trait->getDocComment();
    if (empty($class_description)) {
      throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
    }
    $clean = preg_replace('#^/\*\*|^\s*\*\/$#m', '', $class_description);
    $lines = array_values(
      array_filter(
        array_map(static fn($l): string => ltrim($l, " *\t"), explode(PHP_EOL, (string) $clean))
      )
    );
    if (empty($lines)) {
      throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
    }
    $class_description = trim($lines[0]);

    if (empty($class_description)) {
      throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
    }
    if (str_starts_with($class_description, 'Trait ')) {
      throw new \Exception(sprintf('Class comment should have a descriptive content for %s', $trait_name));
    }

    foreach ($methods as $method) {
      if (!str_starts_with(strtolower($method->getName()), strtolower($trait_prefix))) {
        continue;
      }

      $comment = $method->getDocComment();

      if ($comment) {
        $parsed = parse_method_comment($comment);
        if ($parsed) {
          $info[] = $parsed + [
            'name' => $method->getName(),
            'class_description' => $class_description,
            'class_name' => $trait_name,
          ];
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

    if (!empty($return['example'])) {
      // Remove indentation from the example, using the first line as a
      // reference.
      $lines = explode(PHP_EOL, $return['example']);
      $first_line = '';
      foreach ($lines as $l) {
        if ($l !== '') {
          $first_line = $l;
          break;
        }
      }
      $indentation = strspn($first_line, ' ');
      foreach ($lines as $key => $line) {
        $line = rtrim($line);
        if (strlen($line) > $indentation) {
          $lines[$key] = substr($line, $indentation);
        }
      }
      $return['example'] = implode(PHP_EOL, $lines);
    }
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
function render_info(array $info): string {
  $output = '';

  $index_rows = [];

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

    // Section header.
    $output .= sprintf('## %s', $trait) . PHP_EOL . PHP_EOL;
    $output .= sprintf('[Source](%s), [Example](%s)', $src_file, $example_file) . PHP_EOL . PHP_EOL;

    foreach ($methods as $method) {
      $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
      $class_description = is_string($method['class_description']) ? $method['class_description'] : '';
      $index_rows[$class_name] = [
        sprintf('[%s](#%s)', $class_name, strtolower($class_name)),
        $class_description,
      ];

      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      $method['steps'] = array_reduce($method['steps'], function (string $carry, $item): string {
        return $carry . sprintf("%s\n", $item);
      }, '');
      $method['steps'] = rtrim($method['steps'], "\n");

      $method['description'] = rtrim($method['description'], '.');

      $template = <<<EOT
<details>
  <summary><code>[step]</code></summary>

```gherkin
[example]
```
</details>

EOT;

      $output .= strtr(
        $template,
        [
          '[description]' => $method['description'],
          '[step]' => $method['steps'],
          '[example]' => $method['example'],
        ]
      );

      $output .= PHP_EOL;
    }
  }

  $index_output = array_to_markdown_table(['Class', 'Description'], $index_rows);

  return $index_output . PHP_EOL . $output;
}

/**
 * Validate the info.
 *
 * @param array<string, array<int, array<string, array<int, string>|string>>> $info
 *   Array of info items with 'name', 'from', and 'to' keys.
 *
 * @return array<string>
 *   Array of errors.
 */
function validate(array $info): array {
  $errors = [];
  foreach ($info as $methods) {
    foreach ($methods as $method) {
      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['name'] = is_string($method['name']) ? $method['name'] : '';
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      if (count($method['steps']) > 1) {
        $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Multiple steps found');
      }

      $step = (string) $method['steps'][0];

      if (str_starts_with($step, '@Given') && str_ends_with($step, ':') && !str_contains($step, 'following')) {
        $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "following" in the step');
      }

      if (str_starts_with($step, '@When') && !str_contains($step, 'I ')) {
        $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "I " in the step');
      }

      if (str_starts_with($step, '@Then')) {
        if (!str_contains($method['name'], 'Assert')) {
          $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "Assert" in the method name');
        }

        if (str_contains($method['name'], 'Should')) {
          $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Assert method contains "Should" but should not.');
        }

        if (!str_contains($step, ' should ')) {
          $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "should" in the step');
        }

        if (!(str_contains($step, ' the ') || str_contains($step, ' a ') || str_contains($step, ' no '))) {
          $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "the", "a" or "no" in the step');
        }
      }

      if (empty($method['example'])) {
        $class_name = is_string($method['class_name']) ? $method['class_name'] : '';
        $errors[] = sprintf('  %s::%s - Missing example' . PHP_EOL, $class_name, $method['name']);
      }
    }
  }

  return $errors;
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

/**
 * Convert an array to a markdown table.
 *
 * @param array<int, string> $headers
 *   The headers for the table.
 * @param array<string, array<int, string>> $rows
 *   The rows for the table.
 *
 * @return string
 *   The markdown table.
 */
function array_to_markdown_table(array $headers, array $rows): string {
  if (empty($headers) || empty($rows)) {
    return '';
  }

  $header_row = '| ' . implode(' | ', $headers) . ' |';
  $separator_row = '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |';
  $data_rows = array_map(function ($row): string {
    return '| ' . implode(' | ', $row) . ' |';
  }, $rows);

  return implode("\n", array_merge([$header_row, $separator_row], $data_rows));
}

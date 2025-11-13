<?php

/**
 * @file
 * Documentation generator.
 *
 * This script generates the documentation for the steps in the Behat
 * features.
 *
 * It parses the docblock comments of the classes and methods in the
 * src directory and generates STEPS.md file.
 *
 * It also validates the steps and checks if they are in the correct
 * format.
 *
 * Run with --fail-on-change to fail if the documentation is not up to date.
 * Run with --path=path/to/dir to specify a custom path for the output file.
 */

declare(strict_types=1);

// Execute the main function only when the script is run directly, not when included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'docs.php') {
  $options = getopt('', ['fail-on-change', 'path::']);
  main($options);
}
// @codeCoverageIgnoreEnd

/**
 * Main function to handle the documentation generation process.
 *
 * @param array<string, bool|string|array<int, string>> $options
 *   Command line options.
 *
 * @codeCoverageIgnoreStart
 */
function main(array $options = []): void {
  $base_path = is_string($options['path'] ?? NULL) ? $options['path'] : __DIR__;

  require_once $base_path . '/build/vendor/autoload.php';
  require_once $base_path . '/tests/behat/bootstrap/FeatureContextTrait.php';
  require_once $base_path . '/tests/behat/bootstrap/FeatureContext.php';

  $info = extract_info(FeatureContext::class, [FeatureContextTrait::class], $base_path);

  $errors = validate($info);

  if (!empty($errors)) {
    echo 'Errors found:' . PHP_EOL;
    foreach ($errors as $error) {
      echo $error;
    }
    exit(1);
  }

  $steps_markdown = PHP_EOL . render_info($info, $base_path) . PHP_EOL;
  $readme_markdown = PHP_EOL . render_info($info, $base_path, 'STEPS.md') . PHP_EOL;

  $steps_file = 'STEPS.md';
  $steps_contents = file_get_contents($base_path . DIRECTORY_SEPARATOR . $steps_file);
  if ($steps_contents === FALSE) {
    printf('Failed to read %s.' . PHP_EOL, $steps_file);
    exit(1);
  }
  $steps_replaced = replace_content($steps_contents, '# Available steps', '[//]: # (END)', $steps_markdown);

  $readme_file = 'README.md';
  $readme_contents = file_get_contents($base_path . DIRECTORY_SEPARATOR . $readme_file);
  if ($readme_contents === FALSE) {
    printf('Failed to read %s.' . PHP_EOL, $readme_file);
    exit(1);
  }
  $readme_replaced = replace_content($readme_contents, '## Available steps', '[//]: # (END)', $readme_markdown);

  if ($steps_replaced === $steps_contents && $readme_replaced === $readme_contents) {
    echo 'Documentation is up to date. No changes were made.' . PHP_EOL;
    exit(0);
  }

  $fail_on_change = isset($options['fail-on-change']);
  if ($fail_on_change && ($steps_replaced !== $steps_contents || $readme_replaced !== $readme_contents)) {
    echo 'Documentation is outdated. No changes were made.' . PHP_EOL;
    exit(1);
  }
  else {
    file_put_contents($base_path . DIRECTORY_SEPARATOR . $steps_file, $steps_replaced);
    file_put_contents($base_path . DIRECTORY_SEPARATOR . $readme_file, $readme_replaced);
    echo 'Documentation updated.' . PHP_EOL;
  }
}

// @codeCoverageIgnoreEnd

/**
 * Parse info from the class.
 *
 * @param class-string $class_name
 *   The class name.
 * @param array<int, string> $exclude
 *   Array of trait names to exclude.
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<string,array<string, array<int, array<string, array<int,string>|string>>|string>>
 *   Array of info with 'name', 'steps', 'description', and 'example' keys.
 *
 * @throws \ReflectionException
 */
function extract_info(string $class_name, array $exclude = [], string $base_path = __DIR__): array {
  $info = [];

  // Collect all traits in the src directory to validate that they all present
  // in the $class_name class.
  $traits_path = $base_path . DIRECTORY_SEPARATOR . 'src';
  $traits_files = [];
  if (is_dir($traits_path)) {
    $files = scandir($traits_path) ?: [];
    foreach ($files as $file) {
      $file_path = $traits_path . DIRECTORY_SEPARATOR . $file;
      if (is_file($file_path)) {
        $traits_files[] = basename($file, '.php');
      }
      elseif (is_dir($file_path) && $file !== '.' && $file !== '..') {
        $subdir_files = scandir($file_path) ?: [];
        foreach ($subdir_files as $subdir_file) {
          if (is_file($file_path . DIRECTORY_SEPARATOR . $subdir_file)) {
            $traits_files[] = basename($subdir_file, '.php');
          }
        }
      }
    }
    sort($traits_files);
  }

  // Collect all traits in the $class_name class.
  $reflection = new ReflectionClass($class_name);
  $traits = $reflection->getTraits();
  usort(
    $traits,
    static fn(\ReflectionClass $a, \ReflectionClass $b): int => strcasecmp($a->getShortName(), $b->getShortName())
  );

  // Extract info from the traits.
  foreach ($traits as $trait) {
    $trait_name = $trait->getShortName();

    // Mark as processed.
    if (in_array($trait_name, $traits_files, TRUE)) {
      unset($traits_files[array_search($trait_name, $traits_files, TRUE)]);
    }

    if (in_array($trait_name, $exclude, TRUE)) {
      continue;
    }

    // Determine the context based on the directory structure.
    // Get the trait source file path and determine its directory.
    $trait_class = $trait->getName();
    $trait_reflection = new ReflectionClass($trait_class);
    $trait_file_path = $trait_reflection->getFileName();

    // @codeCoverageIgnoreStart
    if (!$trait_file_path) {
      throw new \Exception(sprintf('Trait %s does not have a file path', $trait_name));
    }
    // @codeCoverageIgnoreEnd
    $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR, '', $trait_file_path);
    $path_parts = explode(DIRECTORY_SEPARATOR, $relative_path);
    // If the file is in a subdirectory, use that as the context, otherwise use 'Generic'.
    $context = count($path_parts) > 1 ? $path_parts[0] : 'Generic';

    $class_info = [
      'name' => $trait_name,
      'name_contextual' => ($context !== 'Generic' ? $context . '\\' : '') . $trait_name,
      'context' => $context,
      'methods' => [],
    ];
    $class_info += parse_class_comment($trait_name, (string) $trait->getDocComment());

    $methods = $trait->getMethods(ReflectionMethod::IS_PUBLIC);
    $trait_prefix = str_replace('Trait', '', $trait_name);
    foreach ($methods as $method) {
      if (!str_starts_with(strtolower($method->getName()), strtolower($trait_prefix))) {
        continue;
      }

      $parsed_comment = parse_method_comment((string) $method->getDocComment());
      if ($parsed_comment) {
        $class_info['methods'][] = $parsed_comment + ['name' => $method->getName()];
      }
    }

    if (!empty($class_info['methods'])) {
      // Sort info by Given, When, Then.
      usort($class_info['methods'], static function (array $a, array $b): int {
        $order = ['@Given', '@When', '@Then'];

        $get_order_index = function ($step) use ($order): int {
          foreach ($order as $index => $prefix) {
            if (str_starts_with($step, $prefix)) {
              return $index;
            }
          }

          // @codeCoverageIgnoreStart
          return PHP_INT_MAX;
          // @codeCoverageIgnoreEnd
        };

        $a_step = $a['steps'][0] ?? '';
        $b_step = $b['steps'][0] ?? '';

        $a_index = $get_order_index($a_step);
        $b_index = $get_order_index($b_step);

        return $a_index <=> $b_index;
      });
    }

    $info[$trait->getShortName()] = $class_info;
  }

  if (!empty($traits_files)) {
    throw new \Exception(sprintf('The following traits were not found in the class: %s', implode(', ', $traits_files)));
  }

  return $info;
}

/**
 * Parse class comment.
 *
 * @param string $trait_name
 *   The trait name.
 * @param string $comment
 *   The comment.
 *
 * @return array<string, string>
 *   Array of 'description' and 'description_full' keys.
 */
function parse_class_comment(string $trait_name, string $comment): array {
  if (empty($comment)) {
    throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
  }

  $comment = preg_replace('#^/\*\*|^\s*\*\/$#m', '', $comment);
  $lines = explode(PHP_EOL, (string) $comment);
  // Remove docblock asterisk and up to one space, but preserve remaining indentation.
  $lines = array_map(static fn(string $l): string => preg_replace('/^\s*\* ?/', '', $l), $lines);

  // Remove first and last empty lines.
  if (count($lines) > 1 && empty($lines[0])) {
    array_shift($lines);
  }
  if (count($lines) > 1 && empty($lines[count($lines) - 1])) {
    array_pop($lines);
  }

  // Trim lines, but preserve indentation within @code blocks.
  $in_code_block = FALSE;
  $lines = array_map(static function (string $l) use (&$in_code_block): string {
    if (str_starts_with(trim($l), '@code')) {
      $in_code_block = TRUE;
      return trim($l);
    }
    elseif (str_starts_with(trim($l), '@endcode')) {
      $in_code_block = FALSE;
      return trim($l);
    }
    elseif ($in_code_block) {
      // Preserve indentation within code blocks.
      return rtrim($l);
    }
    return trim($l);
  }, $lines);

  // @codeCoverageIgnoreStart
  if (empty($lines)) {
    throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
  }
  // @codeCoverageIgnoreEnd
  $description = $lines[0];
  if (empty($description)) {
    throw new \Exception(sprintf('Class comment for %s is empty', $trait_name));
  }

  if (str_starts_with($description, 'Trait ')) {
    throw new \Exception(sprintf('Class comment should have a descriptive content for %s', $trait_name));
  }

  $full_description = implode(PHP_EOL, $lines);

  if (substr_count($full_description, '`') % 2 !== 0) {
    throw new \Exception(sprintf('Class inline code block is not closed for %s', $trait_name));
  }

  return [
    'description' => $description,
    'description_full' => $full_description,
  ];
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
  if (empty($comment)) {
    return NULL;
  }

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
 * @param array<string,array<string, array<int, array<string, array<int,string>|string>>|string>> $info
 *   Array of info items with 'name', 'from', and 'to' keys.
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return string
 *   Markdown table.
 */
function render_info(array $info, string $base_path = __DIR__, ?string $path_for_links = NULL): string {
  $content_output = [];

  $index_rows = [];

  foreach ($info as $trait => $trait_info) {
    // Check if the file exists in the root src directory.
    $src_file = sprintf('src/%s.php', $trait);
    $src_file_path = $base_path . DIRECTORY_SEPARATOR . $src_file;
    $context = $trait_info['context'];

    // Fallback to the context-specific sub-directory, whatever it is.
    if (!file_exists($src_file_path)) {
      $context_dir = $context;
      // @phpstan-ignore-next-line
      $src_file = sprintf('src/%s/%s.php', $context_dir, $trait);
      $src_file_path = $base_path . DIRECTORY_SEPARATOR . $src_file;
    }

    if (!file_exists($src_file_path)) {
      throw new \Exception(sprintf('Source file %s does not exist', $src_file_path));
    }

    $example_name = camel_to_snake(str_replace('Trait', '', $trait));
    // @phpstan-ignore-next-line
    $prefix = strtolower($context) !== 'generic'
      // @phpstan-ignore-next-line
      ? strtolower($context) . '_'
      : '';
    $example_file = sprintf('tests/behat/features/%s%s.feature', $prefix, $example_name);
    $example_file_path = $base_path . DIRECTORY_SEPARATOR . $example_file;

    // @codeCoverageIgnoreStart
    if (!file_exists($example_file_path)) {
      throw new \Exception(sprintf('Example file %s does not exist', $example_file_path));
    }
    // @codeCoverageIgnoreEnd
    // @phpstan-ignore-next-line
    $content_output[$context] ??= '';
    // @phpstan-ignore-next-line
    $content_output[$context] .= sprintf('## %s', $trait_info['name_contextual']) . PHP_EOL . PHP_EOL;
    // @phpstan-ignore-next-line
    $content_output[$context] .= sprintf('[Source](%s), [Example](%s)', $src_file, $example_file) . PHP_EOL . PHP_EOL;

    // Add description as markdown-safe accommodating for lists.
    $description_full = '';
    // @phpstan-ignore-next-line
    $lines = explode(PHP_EOL, $trait_info['description_full']);
    $was_list = FALSE;
    $in_code_block = FALSE;
    $code_block = '';
    foreach ($lines as $line) {
      $trimmed_line = trim($line);

      // Handle @code tag - start collecting code block.
      if (str_starts_with($trimmed_line, '@code')) {
        $in_code_block = TRUE;
        $code_block = '';
        continue;
      }

      // Handle @endcode tag - wrap collected code in markdown code block.
      if (str_starts_with($trimmed_line, '@endcode')) {
        $in_code_block = FALSE;
        $description_full .= '```' . PHP_EOL;
        $description_full .= rtrim($code_block) . PHP_EOL;
        $description_full .= '```' . PHP_EOL;
        $code_block = '';
        continue;
      }

      // If inside code block, collect lines without processing.
      if ($in_code_block) {
        $code_block .= $line . PHP_EOL;
        continue;
      }

      $is_list = str_starts_with($trimmed_line, '-');

      if (!$is_list) {
        if (empty($line) && !$was_list) {
          $description_full .= $line . '<br/><br/>' . PHP_EOL;
        }
        else {
          $description_full .= $line . PHP_EOL;
        }
        $was_list = FALSE;
      }
      else {
        if (str_ends_with($description_full, '<br/><br/>' . PHP_EOL)) {
          $description_full = rtrim($description_full, '<br/><br/>' . PHP_EOL) . PHP_EOL;
        }

        $description_full .= $line . PHP_EOL;
        $was_list = TRUE;
      }
    }

    $description_full = preg_replace('/^/m', '>  ', $description_full);
    // @phpstan-ignore-next-line
    $content_output[$context] .= $description_full . PHP_EOL . PHP_EOL;
    // Add to index.
    // @phpstan-ignore-next-line
    $index_rows_path = '#' . preg_replace('/[^A-Za-z0-9_\-]/', '', strtolower((string) $trait_info['name_contextual']));
    if ($path_for_links) {
      $index_rows_path = $path_for_links . $index_rows_path;
    }
    // @phpstan-ignore-next-line
    $index_rows[$context][] = [
      // @phpstan-ignore-next-line
      sprintf('[%s](%s)', $trait_info['name_contextual'], $index_rows_path),
      $trait_info['description'],
    ];

    // @phpstan-ignore-next-line
    foreach ($trait_info['methods'] as $method) {
      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      $method['steps'] = array_reduce($method['steps'], fn(string $carry, string $item): string => $carry . sprintf("%s\n", $item), '');
      $method['steps'] = rtrim((string) $method['steps'], "\n");

      $method['description'] = rtrim((string) $method['description'], '.');

      $template = <<<EOT
<details>
  <summary><code>[step]</code></summary>

<br/>
[description]
<br/><br/>

```gherkin
[example]
```

</details>

EOT;

      // @phpstan-ignore-next-line
      $content_output[$context] .= strtr(
        $template,
        [
          '[description]' => $method['description'],
          '[step]' => $method['steps'],
          '[example]' => $method['example'],
        ]
      );

      // @phpstan-ignore-next-line
      $content_output[$context] .= PHP_EOL;
    }
  }

  // Make sure 'Generic' key exists.
  $index_rows['Generic'] ??= [];
  $index_rows = array_merge(
    ['Generic' => $index_rows['Generic']],
    array_diff_key($index_rows, ['Generic' => []])
  );

  $index_output = '';
  foreach ($index_rows as $index_rows_context_name => $index_rows_contextual) {
    $index_output .= sprintf('### Index of %s steps', $index_rows_context_name) . PHP_EOL . PHP_EOL;
    // @phpstan-ignore-next-line
    $index_output .= array_to_markdown_table(['Class', 'Description'], $index_rows_contextual) . PHP_EOL . PHP_EOL;
  }

  // Make sure 'Generic' key exists.
  $content_output['Generic'] ??= '';
  $content_output = array_merge(
    ['Generic' => $content_output['Generic']],
    array_diff_key($content_output, ['Generic' => []])
  );
  $content_output = implode(PHP_EOL . PHP_EOL, $content_output);

  $output = '';

  $output .= $index_output . PHP_EOL;

  // Render content if this is not a path for links.
  if (!$path_for_links) {
    $output .= '---' . PHP_EOL . PHP_EOL;
    $output .= $content_output . PHP_EOL;
  }

  return $output;
}

/**
 * Validate the info.
 *
 * @param array<string,array<string, array<int, array<string, array<int,string>|string>>|string>> $info
 *   Array of info items with 'name', 'from', and 'to' keys.
 *
 * @return array<string>
 *   Array of errors.
 */
function validate(array $info): array {
  $errors = [];

  foreach ($info as $class_info) {
    $class_name = is_string($class_info['name']) ? $class_info['name'] : '';

    // @phpstan-ignore-next-line
    foreach ($class_info['methods'] as $method) {
      $method['steps'] = is_array($method['steps']) ? $method['steps'] : [$method['steps']];
      $method['name'] = is_string($method['name']) ? $method['name'] : '';
      $method['description'] = is_string($method['description']) ? $method['description'] : '';
      $method['example'] = is_string($method['example']) ? $method['example'] : '';

      if (count($method['steps']) > 1) {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Multiple steps found');
      }

      $step = (string) $method['steps'][0];

      if (str_starts_with($step, '@Given') && str_ends_with($step, ':') && !str_contains($step, 'following')) {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "following" in the step');
      }

      if (str_starts_with($step, '@When') && !str_contains($step, 'I ')) {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "I " in the step');
      }

      if (str_starts_with($step, '@Then')) {
        if (!str_contains((string) $method['name'], 'Assert')) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "Assert" in the method name');
        }

        if (str_contains((string) $method['name'], 'Should')) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Assert method contains "Should" but should not.');
        }

        if (!str_contains($step, ' should ')) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "should" in the step');
        }

        if (!(str_contains($step, ' the ') || str_contains($step, ' a ') || str_contains($step, ' no '))) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Missing "the", "a" or "no" in the step');
        }
      }

      if (empty($method['example'])) {
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
  $string = preg_replace_callback('/([^0-9])(\d+)/', static fn(array $matches): string => $matches[1] . $separator . $matches[2], $string);

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
  $data_rows = array_map(fn(array $row): string => '| ' . implode(' | ', $row) . ' |', $rows);

  return implode("\n", array_merge([$header_row, $separator_row], $data_rows));
}

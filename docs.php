<?php

/**
 * @file
 * Reference documentation generator.
 *
 * This script is the single generator behind every reference document the
 * package publishes. It parses the PHP attributes and docblock comments of the
 * traits under the vocabulary directory and writes:
 *
 * - STEPS.md, the step vocabulary, and the step index in README.md.
 * - HELPERS.md, the toolbox a project calls from its own domain steps.
 * - The generated tables in docs/configuration.md.
 *
 * It also validates that the steps read in the documented format, that every
 * published helper carries a summary, that tags resolve against the registry,
 * and that every environment variable the source reads is documented.
 *
 * Run with --fail-on-change to fail if the documentation is not up to date.
 * Run with --path=path/to/dir to specify a custom path for the output file.
 */

declare(strict_types=1);

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * Matches a first-person pronoun anywhere in a step.
 *
 * A `When` narrates what the person does and reads in the first person. A
 * `Given` states a precondition and a `Then` names the entity it asserts on,
 * so neither refers to the person at all.
 */
const FIRST_PERSON = '/\b(I|[Mm]y|[Mm]e|[Mm]yself|[Ww]e|[Uu]s|[Oo]ur)\b/';

/**
 * Directory holding the step vocabulary, relative to the repository root.
 *
 * Each directory directly below it is a context and gives its traits their
 * context name.
 */
const STEPS_DIRECTORY = 'src/Steps';

/**
 * Attribute namespaces that mark a method as registered with Behat.
 *
 * A method carrying one of these is a step, a hook or a transformation, so it
 * is not part of the toolbox.
 */
const REGISTERED_ATTRIBUTE_PREFIXES = [
  'Behat\\Step\\',
  'Behat\\Hook\\',
  'Behat\\Transformation\\',
  'DrevOps\\BehatSteps\\Behat\\Hook\\Attribute\\',
];

/**
 * Classes published in the toolbox reference alongside the step traits.
 *
 * A context base class contributes the scenario lifecycle a domain step is
 * written against, so that lifecycle is part of the toolbox too.
 */
const TOOLBOX_CLASSES = [RawContext::class];

/**
 * File holding the toolbox reference, relative to the repository root.
 */
const HELPERS_FILE = 'HELPERS.md';

/**
 * File holding the configuration reference, relative to the repository root.
 */
const CONFIGURATION_FILE = 'docs/configuration.md';

// Execute the main function only when the script is run directly, not when
// included.
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

  $exclude = [FeatureContextTrait::class, 'HelperTrait'];
  $info = extract_info(FeatureContext::class, $exclude, $base_path);
  $helpers = extract_helpers(FeatureContext::class, $exclude, $base_path);

  $errors = validate($info);
  $errors = array_merge($errors, validate_helpers($helpers));
  $errors = array_merge($errors, validate_tags($info, $base_path));
  $errors = array_merge($errors, validate_env_vars($base_path));

  if (!empty($errors)) {
    echo 'Errors found:' . PHP_EOL;
    foreach ($errors as $error) {
      echo $error;
    }
    exit(1);
  }

  $targets = [
    'STEPS.md' => [
      ['# Available steps', '[//]: # (END)', PHP_EOL . render_info($info, $base_path) . PHP_EOL],
    ],
    'README.md' => [
      ['## Available steps', '[//]: # (END)', PHP_EOL . render_info($info, $base_path, 'STEPS.md') . PHP_EOL],
    ],
    HELPERS_FILE => [
      ['# Available helpers', '[//]: # (END)', PHP_EOL . render_helpers($helpers, $base_path) . PHP_EOL],
    ],
    CONFIGURATION_FILE => [
      [
        '[//]: # (START_EXTENSION_OPTIONS)',
        '[//]: # (END_EXTENSION_OPTIONS)',
        PHP_EOL . render_extension_options() . PHP_EOL,
      ],
      ['[//]: # (START_TAGS)', '[//]: # (END_TAGS)', PHP_EOL . render_tag_reference() . PHP_EOL],
    ],
  ];

  $outdated = [];
  $updated = [];

  foreach ($targets as $file => $regions) {
    $path = $base_path . DIRECTORY_SEPARATOR . $file;

    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      printf('Failed to read %s.' . PHP_EOL, $file);
      exit(1);
    }

    $replaced = $contents;
    foreach ($regions as $region) {
      $replaced = replace_content($replaced, $region[0], $region[1], $region[2]);
    }

    if ($replaced !== $contents) {
      $outdated[] = $file;
      $updated[$path] = $replaced;
    }
  }

  if ($outdated === []) {
    echo 'Documentation is up to date. No changes were made.' . PHP_EOL;
    exit(0);
  }

  if (isset($options['fail-on-change'])) {
    printf('Documentation is outdated: %s. No changes were made.' . PHP_EOL, implode(', ', $outdated));
    exit(1);
  }

  foreach ($updated as $path => $contents) {
    file_put_contents($path, $contents);
  }

  printf('Documentation updated: %s.' . PHP_EOL, implode(', ', $outdated));
}

// @codeCoverageIgnoreEnd

/**
 * Check whether a PHP file declares a trait.
 *
 * @param string $file_path
 *   Path to the PHP file.
 *
 * @return bool
 *   TRUE when the file declares a trait.
 */
function file_declares_trait(string $file_path): bool {
  $contents = file_get_contents($file_path);

  return $contents !== FALSE && preg_match('/^\s*trait\s+\w+/m', $contents) === 1;
}

/**
 * Collect the vocabulary traits composed into a class.
 *
 * Every trait declared under the vocabulary directory has to be composed into
 * the class, so that a reference document covers the whole vocabulary rather
 * than the part one context happens to use.
 *
 * @param class-string $class_name
 *   The class name.
 * @param array<int, string> $exclude
 *   Array of trait names to exclude.
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<string, array{reflection: \ReflectionClass<object>, context: string}>
 *   The trait reflection and its context, keyed by trait short name and sorted
 *   by that name.
 *
 * @throws \ReflectionException
 */
function collect_step_traits(string $class_name, array $exclude = [], string $base_path = __DIR__): array {
  $traits_path = $base_path . DIRECTORY_SEPARATOR . STEPS_DIRECTORY;
  $traits_files = [];

  if (is_dir($traits_path)) {
    $contexts = scandir($traits_path) ?: [];
    foreach ($contexts as $context) {
      $context_path = $traits_path . DIRECTORY_SEPARATOR . $context;
      if (!is_dir($context_path) || $context === '.' || $context === '..') {
        continue;
      }

      $context_files = scandir($context_path) ?: [];
      foreach ($context_files as $context_file) {
        $context_file_path = $context_path . DIRECTORY_SEPARATOR . $context_file;
        if (is_file($context_file_path) && file_declares_trait($context_file_path)) {
          $traits_files[] = basename($context_file, '.php');
        }
      }
    }
    sort($traits_files);
  }

  $reflection = new \ReflectionClass($class_name);
  $traits = $reflection->getTraits();
  usort(
    $traits,
    static fn(\ReflectionClass $a, \ReflectionClass $b): int => strcasecmp($a->getShortName(), $b->getShortName())
  );

  $collected = [];
  foreach ($traits as $trait) {
    $trait_name = $trait->getShortName();

    if (in_array($trait_name, $traits_files, TRUE)) {
      unset($traits_files[array_search($trait_name, $traits_files, TRUE)]);
    }

    if (in_array($trait_name, $exclude, TRUE)) {
      continue;
    }

    $trait_file_path = $trait->getFileName();

    // @codeCoverageIgnoreStart
    if (!$trait_file_path) {
      throw new \Exception(sprintf('Trait %s does not have a file path', $trait_name));
    }
    // @codeCoverageIgnoreEnd
    $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR . STEPS_DIRECTORY . DIRECTORY_SEPARATOR, '', $trait_file_path);
    // The directory a trait sits in under the vocabulary root is its context.
    $context = explode(DIRECTORY_SEPARATOR, $relative_path)[0];

    $collected[$trait_name] = ['reflection' => $trait, 'context' => $context];
  }

  if (!empty($traits_files)) {
    throw new \Exception(sprintf('The following traits were not found in the class: %s', implode(', ', $traits_files)));
  }

  return $collected;
}

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

  foreach (collect_step_traits($class_name, $exclude, $base_path) as $trait_name => $collected) {
    $trait = $collected['reflection'];
    $context = $collected['context'];

    $class_info = [
      'name' => $trait_name,
      'name_contextual' => ($context !== 'Generic' ? $context . '\\' : '') . $trait_name,
      'context' => $context,
      'methods' => [],
    ];
    $class_info += parse_class_comment($trait_name, (string) $trait->getDocComment());

    $methods = $trait->getMethods(\ReflectionMethod::IS_PUBLIC);
    $trait_prefix = str_replace('Trait', '', $trait_name);
    foreach ($methods as $method) {
      if (!str_starts_with(strtolower($method->getName()), strtolower($trait_prefix))) {
        continue;
      }

      $steps = extract_method_steps($method);
      if (empty($steps)) {
        continue;
      }

      $parsed_comment = parse_method_comment((string) $method->getDocComment());
      $class_info['methods'][] = [
        'steps' => $steps,
        'description' => $parsed_comment['description'] ?? '',
        'example' => $parsed_comment['example'] ?? '',
        'name' => $method->getName(),
      ];
    }

    if (!empty($class_info['methods'])) {
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

    $info[$trait_name] = $class_info;
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
  // Remove docblock asterisk and up to one space, but preserve remaining
  // indentation.
  $lines = array_map(static fn(string $line): string => preg_replace('/^\s*\* ?/', '', $line), $lines);

  if (count($lines) > 1 && empty($lines[0])) {
    array_shift($lines);
  }
  if (count($lines) > 1 && empty($lines[count($lines) - 1])) {
    array_pop($lines);
  }

  // Trim lines, but preserve indentation within @code blocks.
  $in_code_block = FALSE;
  $lines = array_map(static function (string $line) use (&$in_code_block): string {
    if (str_starts_with(trim($line), '@code')) {
      $in_code_block = TRUE;
      return trim($line);
    }

    if (str_starts_with(trim($line), '@endcode')) {
      $in_code_block = FALSE;
      return trim($line);
    }

    if ($in_code_block) {
      return rtrim($line);
    }

    return trim($line);
  }, $lines);

  // Static-analysis annotations state the trait's contract for tooling, not
  // for the reader of the generated documentation.
  $lines = array_values(array_filter($lines, static fn(string $line): bool => !str_starts_with($line, '@phpstan-')));

  while ($lines !== [] && end($lines) === '') {
    array_pop($lines);
  }

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
 * Extracts description and example from the docblock comment.
 *
 * @param string $comment
 *   The comment.
 *
 * @return array<string, string>|null
 *   Array of 'description' and 'example' keys or NULL if the comment is empty.
 */
function parse_method_comment(string $comment): ?array {
  if (empty($comment)) {
    return NULL;
  }

  $return = [
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

  $return['description'] = trim($return['description']);

  if (!empty($return['example'])) {
    // Remove indentation from the example, using the first line as a
    // reference.
    $lines = explode(PHP_EOL, $return['example']);
    $first_line = '';
    foreach ($lines as $line) {
      if ($line !== '') {
        $first_line = $line;
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

  return $return;
}

/**
 * Extract step patterns from method attributes.
 *
 * @param \ReflectionMethod $method
 *   The reflection method.
 *
 * @return array<int, string>
 *   Array of step patterns prefixed with @Given, @When, or @Then.
 */
function extract_method_steps(\ReflectionMethod $method): array {
  $step_classes = [
    Given::class => '@Given',
    When::class => '@When',
    Then::class => '@Then',
  ];

  $steps = [];
  foreach ($step_classes as $class => $prefix) {
    $attributes = $method->getAttributes($class);
    foreach ($attributes as $attribute) {
      $args = $attribute->getArguments();
      $pattern = $args[0] ?? $args['pattern'] ?? NULL;
      if ($pattern !== NULL) {
        $steps[] = $prefix . ' ' . $pattern;
      }
    }
  }

  $sorted = [];
  foreach (['@Given', '@When', '@Then'] as $step_prefix) {
    foreach ($steps as $step) {
      if (str_starts_with($step, $step_prefix)) {
        $sorted[] = $step;
      }
    }
  }

  return $sorted;
}

/**
 * Check whether a method is registered with Behat.
 *
 * @param \ReflectionMethod $method
 *   The reflection method.
 *
 * @return bool
 *   TRUE when the method carries a step, hook or transformation attribute.
 */
function method_is_registered(\ReflectionMethod $method): bool {
  foreach ($method->getAttributes() as $attribute) {
    foreach (REGISTERED_ATTRIBUTE_PREFIXES as $prefix) {
      if (str_starts_with($attribute->getName(), $prefix)) {
        return TRUE;
      }
    }
  }

  return FALSE;
}

/**
 * Check whether a docblock withdraws the member from the published API.
 *
 * @param string $comment
 *   The docblock comment.
 *
 * @return bool
 *   TRUE when the comment carries an '@internal' tag.
 */
function comment_is_internal(string $comment): bool {
  return preg_match('/^\s*\*\s*@internal\b/m', $comment) === 1;
}

/**
 * Render a type declaration as it reads in a signature.
 *
 * @param \ReflectionType|null $type
 *   The reflection type, or NULL when the declaration has none.
 *
 * @return string
 *   The type with class names shortened, or an empty string when untyped.
 */
function render_type(?\ReflectionType $type): string {
  $short = static function (string $name): string {
    $position = strrpos($name, '\\');

    return $position === FALSE ? $name : substr($name, $position + 1);
  };

  if ($type instanceof \ReflectionNamedType) {
    $name = $short($type->getName());
    $nullable = $type->allowsNull() && !in_array($name, ['mixed', 'null'], TRUE);

    return ($nullable ? '?' : '') . $name;
  }

  if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
    $glue = $type instanceof \ReflectionUnionType ? '|' : '&';
    $parts = array_map(render_type(...), $type->getTypes());

    return implode($glue, $parts);
  }

  return '';
}

/**
 * Render a value as it reads in PHP source.
 *
 * @param mixed $value
 *   The value.
 *
 * @return string
 *   The rendered value.
 */
function render_value(mixed $value): string {
  if ($value === NULL) {
    return 'NULL';
  }

  if (is_bool($value)) {
    return $value ? 'TRUE' : 'FALSE';
  }

  if (is_array($value)) {
    return $value === [] ? '[]' : '[...]';
  }

  if (is_string($value)) {
    return "'" . $value . "'";
  }

  return is_scalar($value) ? (string) $value : 'object';
}

/**
 * Render a method signature.
 *
 * @param \ReflectionMethod $method
 *   The reflection method.
 *
 * @return string
 *   The signature, with class names shortened to their class part.
 */
function render_method_signature(\ReflectionMethod $method): string {
  $parameters = [];

  foreach ($method->getParameters() as $parameter) {
    $type = render_type($parameter->getType());
    $rendered = $type === '' ? '' : $type . ' ';
    $rendered .= ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName();

    if ($parameter->isDefaultValueAvailable()) {
      $rendered .= ' = ' . render_value($parameter->getDefaultValue());
    }

    $parameters[] = $rendered;
  }

  $return = render_type($method->getReturnType());

  return sprintf(
    '%s%s function %s(%s)%s',
    $method->isPublic() ? 'public' : 'protected',
    $method->isStatic() ? ' static' : '',
    $method->getName(),
    implode(', ', $parameters),
    $return === '' ? '' : ': ' . $return
  );
}

/**
 * Render the anchor a trait heading resolves to.
 *
 * @param string $name
 *   The contextual trait name, such as 'Drupal\ContentTrait'.
 *
 * @return string
 *   The anchor, without the leading hash.
 */
function heading_anchor(string $name): string {
  return (string) preg_replace('/[^A-Za-z0-9_\-]/', '', strtolower($name));
}

/**
 * Parse the toolbox helpers from the class.
 *
 * A helper is a method a project calls from its own domain steps: one carrying
 * no Behat attribute and no '@internal' tag.
 *
 * @param class-string $class_name
 *   The class name.
 * @param array<int, string> $exclude
 *   Array of trait names to exclude.
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<string, array<string, mixed>>
 *   Array of info with 'name', 'context' and 'helpers' keys, keyed by trait
 *   short name. Traits contributing no helper are left out.
 *
 * @throws \ReflectionException
 */
function extract_helpers(string $class_name, array $exclude = [], string $base_path = __DIR__): array {
  $info = [];

  foreach (collect_step_traits($class_name, $exclude, $base_path) as $trait_name => $collected) {
    $trait = $collected['reflection'];
    $context = $collected['context'];

    $helpers = collect_helper_methods($trait, str_replace('Trait', '', $trait_name));
    if ($helpers === []) {
      continue;
    }

    $name_contextual = ($context !== 'Generic' ? $context . '\\' : '') . $trait_name;

    $class_info = [
      'name' => $trait_name,
      'name_contextual' => $name_contextual,
      'context' => $context,
      'source' => sprintf('%s/%s/%s.php', STEPS_DIRECTORY, $context, $trait_name),
      'steps_anchor' => heading_anchor($name_contextual),
      'helpers' => $helpers,
    ];
    $class_info += parse_class_comment($trait_name, (string) $trait->getDocComment());

    $info[$trait_name] = $class_info;
  }

  foreach (TOOLBOX_CLASSES as $toolbox_class) {
    $reflection = new \ReflectionClass($toolbox_class);
    $short_name = $reflection->getShortName();

    $helpers = collect_helper_methods($reflection);
    // @codeCoverageIgnoreStart
    if ($helpers === []) {
      continue;
    }
    // @codeCoverageIgnoreEnd
    $class_info = [
      'name' => $short_name,
      'name_contextual' => $short_name,
      'context' => 'Context',
      'source' => relative_source_path((string) $reflection->getFileName(), $base_path),
      'steps_anchor' => NULL,
      'helpers' => $helpers,
    ];
    $class_info += parse_class_comment($short_name, (string) $reflection->getDocComment());

    $info[$short_name] = $class_info;
  }

  return $info;
}

/**
 * Collect the toolbox methods a class or trait contributes.
 *
 * Visibility is the marker: a public method that Behat does not register is
 * the toolbox, and a protected one is an implementation detail carrying no
 * promise to a consuming project.
 *
 * @param \ReflectionClass<object> $reflection
 *   The class or trait reflection.
 * @param string|null $prefix
 *   Method name prefix to require, or NULL to take every method the class
 *   declares itself.
 *
 * @return array<int, array<string, string>>
 *   Helper entries with 'name', 'signature', 'description' and 'example',
 *   sorted by name.
 */
function collect_helper_methods(\ReflectionClass $reflection, ?string $prefix = NULL): array {
  $helpers = [];

  foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
    if ($prefix === NULL) {
      if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
        continue;
      }
    }
    elseif (!str_starts_with(strtolower($method->getName()), strtolower($prefix))) {
      continue;
    }

    if (method_is_registered($method)) {
      continue;
    }

    $comment = resolve_inherited_comment($method);
    if (comment_is_internal($comment)) {
      continue;
    }

    $parsed_comment = parse_method_comment($comment);
    $helpers[] = [
      'name' => $method->getName(),
      'signature' => render_method_signature($method),
      'description' => $parsed_comment['description'] ?? '',
      'example' => $parsed_comment['example'] ?? '',
    ];
  }

  usort($helpers, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

  return $helpers;
}

/**
 * Read a method's docblock, following '{@inheritdoc}' to its declaration.
 *
 * @param \ReflectionMethod $method
 *   The reflection method.
 *
 * @return string
 *   The docblock that carries the description, or the method's own when the
 *   declaration it inherits from has none.
 */
function resolve_inherited_comment(\ReflectionMethod $method): string {
  $comment = (string) $method->getDocComment();

  if (stripos($comment, '{@inheritdoc}') === FALSE) {
    return $comment;
  }

  $declaring = $method->getDeclaringClass();
  $candidates = array_values($declaring->getInterfaces());

  $parent = $declaring->getParentClass();
  if ($parent instanceof \ReflectionClass) {
    array_unshift($candidates, $parent);
  }

  foreach ($candidates as $candidate) {
    if (!$candidate->hasMethod($method->getName())) {
      continue;
    }

    $inherited = (string) $candidate->getMethod($method->getName())->getDocComment();
    if ($inherited !== '' && stripos($inherited, '{@inheritdoc}') === FALSE) {
      return $inherited;
    }
  }

  return $comment;
}

/**
 * Express a source file path relative to the repository it belongs to.
 *
 * @param string $file_path
 *   Absolute path to the file.
 * @param string $base_path
 *   Base path for the repository being documented.
 *
 * @return string
 *   The path relative to the repository root, or unchanged when it sits
 *   outside both the documented repository and this one.
 */
function relative_source_path(string $file_path, string $base_path = __DIR__): string {
  foreach ([$base_path, __DIR__] as $root) {
    if (str_starts_with($file_path, $root . DIRECTORY_SEPARATOR)) {
      return substr($file_path, strlen($root) + 1);
    }
  }

  // @codeCoverageIgnoreStart
  return $file_path;
  // @codeCoverageIgnoreEnd
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
    $context = $trait_info['context'];
    // @phpstan-ignore-next-line
    $src_file = sprintf('%s/%s/%s.php', STEPS_DIRECTORY, $context, $trait);
    $src_file_path = $base_path . DIRECTORY_SEPARATOR . $src_file;

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

    $description_full = '';
    // @phpstan-ignore-next-line
    $lines = explode(PHP_EOL, $trait_info['description_full']);
    $was_list = FALSE;
    $in_code_block = FALSE;
    $code_block = '';
    foreach ($lines as $line) {
      $trimmed_line = trim($line);

      if (str_starts_with($trimmed_line, '@code')) {
        $in_code_block = TRUE;
        $code_block = '';
        continue;
      }

      if (str_starts_with($trimmed_line, '@endcode')) {
        $in_code_block = FALSE;
        $description_full .= '```' . PHP_EOL;
        $description_full .= rtrim($code_block) . PHP_EOL;
        $description_full .= '```' . PHP_EOL;
        $code_block = '';
        continue;
      }

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
    // @phpstan-ignore-next-line
    $index_rows_path = '#' . heading_anchor((string) $trait_info['name_contextual']);
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

  $content_output['Generic'] ??= '';
  $content_output = array_merge(
    ['Generic' => $content_output['Generic']],
    array_diff_key($content_output, ['Generic' => []])
  );
  $content_output = implode(PHP_EOL . PHP_EOL, $content_output);

  $output = '';

  $output .= $index_output . PHP_EOL;

  if (!$path_for_links) {
    $output .= '---' . PHP_EOL . PHP_EOL;
    $output .= $content_output . PHP_EOL;
  }

  return $output;
}

/**
 * Convert helper info to content.
 *
 * @param array<string, array<string, mixed>> $info
 *   Array of helper info items from extract_helpers().
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return string
 *   Markdown content.
 */
function render_helpers(array $info, string $base_path = __DIR__): string {
  $content_output = [];
  $index_rows = [];

  foreach ($info as $trait_info) {
    $context = (string) $trait_info['context'];
    $name_contextual = (string) $trait_info['name_contextual'];
    $anchor = heading_anchor($name_contextual);
    $helpers = is_array($trait_info['helpers']) ? $trait_info['helpers'] : [];

    $src_file = (string) $trait_info['source'];
    if (!file_exists($base_path . DIRECTORY_SEPARATOR . $src_file)) {
      throw new \Exception(sprintf('Source file %s does not exist', $base_path . DIRECTORY_SEPARATOR . $src_file));
    }

    $steps_anchor = $trait_info['steps_anchor'] ?? NULL;
    $links = sprintf('[Source](%s)', $src_file);
    if (is_string($steps_anchor)) {
      $links .= sprintf(', [Steps](STEPS.md#%s)', $steps_anchor);
    }

    $content_output[$context] ??= '';
    $content_output[$context] .= sprintf('## %s', $name_contextual) . PHP_EOL . PHP_EOL;
    $content_output[$context] .= $links . PHP_EOL . PHP_EOL;
    $content_output[$context] .= '> ' . $trait_info['description'] . PHP_EOL . PHP_EOL;

    foreach ($helpers as $helper) {
      $example = (string) $helper['example'];

      $content_output[$context] .= '<details>' . PHP_EOL;
      $content_output[$context] .= sprintf('  <summary><code>%s</code></summary>', (string) $helper['signature']) . PHP_EOL . PHP_EOL;
      $content_output[$context] .= '<br/>' . PHP_EOL;
      $content_output[$context] .= rtrim((string) $helper['description'], '.') . PHP_EOL;
      $content_output[$context] .= '<br/><br/>' . PHP_EOL . PHP_EOL;

      if ($example !== '') {
        $content_output[$context] .= '```' . PHP_EOL . rtrim($example) . PHP_EOL . '```' . PHP_EOL . PHP_EOL;
      }

      $content_output[$context] .= '</details>' . PHP_EOL . PHP_EOL;
    }

    $index_rows[$context][] = [
      sprintf('[%s](#%s)', $name_contextual, $anchor),
      (string) count($helpers),
      (string) $trait_info['description'],
    ];
  }

  $index_rows['Generic'] ??= [];
  $index_rows = array_merge(['Generic' => $index_rows['Generic']], array_diff_key($index_rows, ['Generic' => []]));

  $output = '';
  foreach ($index_rows as $index_context => $rows) {
    $output .= sprintf('### Index of %s helpers', $index_context) . PHP_EOL . PHP_EOL;
    $output .= array_to_markdown_table(['Class', 'Helpers', 'Description'], $rows) . PHP_EOL . PHP_EOL;
  }

  $content_output['Generic'] ??= '';
  $content_output = array_merge(['Generic' => $content_output['Generic']], array_diff_key($content_output, ['Generic' => []]));

  $output .= '---' . PHP_EOL . PHP_EOL;
  $output .= implode('', $content_output);

  return rtrim($output) . PHP_EOL;
}

/**
 * Validate the published helpers.
 *
 * @param array<string, array<string, mixed>> $info
 *   Array of helper info items from extract_helpers().
 *
 * @return array<string>
 *   Array of errors.
 */
function validate_helpers(array $info): array {
  $errors = [];

  foreach ($info as $trait_info) {
    $class_name = is_string($trait_info['name'] ?? NULL) ? $trait_info['name'] : '';

    foreach ((is_array($trait_info['helpers'] ?? NULL) ? $trait_info['helpers'] : []) as $helper) {
      $name = is_string($helper['name'] ?? NULL) ? $helper['name'] : '';
      $description = is_string($helper['description'] ?? NULL) ? $helper['description'] : '';

      if (trim($description) === '') {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $name, 'Published helper has no summary. Write one, or mark the helper @internal');
      }
    }
  }

  return $errors;
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
  $non_descriptive_placeholders = non_descriptive_placeholders();

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

      if (str_starts_with($step, '@Given') && preg_match(FIRST_PERSON, $step) === 1) {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Given step is in the first person but should state a precondition');
      }

      // "I " also sits inside an acronym such as "API ", so the first person is
      // only established by the step opening with it.
      if (str_starts_with($step, '@When') && !str_starts_with($step, '@When I ')) {
        $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'When step does not start with "I "');
      }

      if (str_starts_with($step, '@Then')) {
        if (preg_match(FIRST_PERSON, $step) === 1) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], 'Then step is in the first person but should start with the asserted entity');
        }

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

      // Match names that the snake_case rule rejects too, so a violation is
      // reported rather than truncated to a legal prefix.
      preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $step, $placeholders);

      foreach ($placeholders[1] as $placeholder) {
        if (in_array($placeholder, $non_descriptive_placeholders, TRUE)) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], sprintf('Non-descriptive placeholder ":%s" in the step', $placeholder));
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $placeholder) !== 1) {
          $errors[] = sprintf('  %s::%s - %s' . PHP_EOL, $class_name, $method['name'], sprintf('Placeholder ":%s" in the step is not snake_case', $placeholder));
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
 * Placeholder names that name a value's type instead of its role.
 *
 * A placeholder is the only description a step gives of what a consumer must
 * pass, so it names the thing (`:tolerance`, `:selector`, `:count`) rather than
 * the PHP type it arrives as or the bare category it belongs to. Add a name
 * here to keep it out of step patterns.
 *
 * @return array<int, string>
 *   List of rejected placeholder names, without the leading colon.
 */
function non_descriptive_placeholders(): array {
  return [
    'arg',
    'argument',
    'array',
    'bool',
    'boolean',
    'data',
    'float',
    'int',
    'integer',
    'number',
    'string',
    'type',
    'var',
  ];
}

/**
 * Canonical registry of the library's special Behat tags.
 *
 * The separator between a tag and its value is always a colon: a `parametrized`
 * tag is written `@prefix:value`, never `@prefix-value`. Hyphens only join
 * words inside a tag name (e.g. `@disable-form-validation`). A `flag` tag
 * stands alone and takes no value.
 *
 * Add every new special tag here so that validate_tags() can guard its format
 * and prevent separator drift.
 *
 * @return array<string, array{form: string, description: string}>
 *   Map of tag prefix to its form, one of 'parametrized' or 'flag', and the
 *   one-line description rendered into the configuration reference.
 */
function tag_registry(): array {
  return [
    'behat-steps-skip' => [
      'form' => 'parametrized',
      'description' => 'Turn a hook off, named either by its method (`emailBeforeScenario`) or by the trait it belongs to (`ElementTrait`).',
    ],
    'behat-steps-entity-cleanup-skip' => [
      'form' => 'parametrized',
      'description' => 'Keep entities of the named entity type after the scenario. Repeat the tag to keep several types.',
    ],
    'module' => [
      'form' => 'parametrized',
      'description' => 'Enable the named module for the scenario, or disable it when the name is prefixed with `!`. The original state is restored afterwards.',
    ],
    'breakpoint' => [
      'form' => 'parametrized',
      'description' => 'Resize the viewport to the named breakpoint before the first step. One tag per scenario, and the scenario has to be `@javascript`.',
    ],
    'email' => [
      'form' => 'parametrized',
      'description' => 'Collect email for the scenario with the named handler type. A bare `@email` uses the `default` handler.',
    ],
    'watchdog' => [
      'form' => 'parametrized',
      'description' => 'Track the named Watchdog message type in addition to `php`, which is always tracked.',
    ],
    'disable-config-override' => [
      'form' => 'parametrized',
      'description' => 'Disable `settings.php` overrides for the named configuration object for the duration of the scenario.',
    ],
    'accessibility' => [
      'form' => 'parametrized',
      'description' => 'Assess every page the scenario visits. The value sets the impact threshold that fails the scenario: `critical`, `serious`, `moderate`, `minor`, `any`, `warning` or `strict`.',
    ],
    'bigpipe' => [
      'form' => 'flag',
      'description' => 'Render BigPipe placeholders server-side, for a driver without JavaScript.',
    ],
    'disable-form-validation' => [
      'form' => 'flag',
      'description' => 'Strip HTML5 validation from every form on the page so a scenario can submit values the browser would block.',
    ],
    'js-errors' => [
      'form' => 'flag',
      'description' => 'Allow JavaScript errors, which otherwise fail the scenario.',
    ],
    'download' => [
      'form' => 'flag',
      'description' => 'Prepare the download directory for the scenario and clean it up afterwards.',
    ],
    'testmode' => [
      'form' => 'flag',
      'description' => 'Enable the Testmode module for the scenario.',
    ],
    'debug' => [
      'form' => 'flag',
      'description' => 'Print detailed diagnostics while the scenario runs.',
    ],
    'error' => [
      'form' => 'flag',
      'description' => 'Expect the scenario to log an error, which turns the Watchdog check off.',
    ],
  ];
}

/**
 * Render the tag reference table.
 *
 * @return string
 *   Markdown table of every tag in the registry.
 */
function render_tag_reference(): string {
  $rows = [];

  foreach (tag_registry() as $tag => $definition) {
    $rows[] = [
      $definition['form'] === 'parametrized' ? sprintf('`@%s:VALUE`', $tag) : sprintf('`@%s`', $tag),
      $definition['description'],
    ];
  }

  return array_to_markdown_table(['Tag', 'Description'], $rows);
}

/**
 * Render the extension options table.
 *
 * The table is built from the extension's own configuration tree, so an option
 * cannot be added to the code without appearing in the reference.
 *
 * @return string
 *   Markdown table of every option the extension accepts.
 */
function render_extension_options(): string {
  $builder = new TreeBuilder(BehatStepsExtension::CONFIG_KEY);
  (new BehatStepsExtension())->configure($builder->getRootNode());

  $root = $builder->buildTree();
  $rows = [];

  if ($root instanceof ArrayNode) {
    $rows = extension_option_rows($root->getChildren());
  }

  return array_to_markdown_table(['Option', 'Type', 'Default', 'Description'], $rows);
}

/**
 * Flatten configuration nodes into table rows.
 *
 * @param array<string, \Symfony\Component\Config\Definition\NodeInterface> $nodes
 *   The child nodes to render.
 * @param string $prefix
 *   Dotted path of the parent node, empty at the root.
 *
 * @return array<int, array<int, string>>
 *   Rows of option path, type, default and description.
 */
function extension_option_rows(array $nodes, string $prefix = ''): array {
  $rows = [];

  foreach ($nodes as $name => $node) {
    $path = $prefix === '' ? (string) $name : $prefix . '.' . $name;
    $children = $node instanceof ArrayNode && !$node instanceof PrototypedArrayNode ? $node->getChildren() : [];

    $default = '-';
    if ($node->isRequired()) {
      $default = 'required';
    }
    elseif ($node->hasDefaultValue()) {
      $default = '`' . render_value($node->getDefaultValue()) . '`';
    }

    $rows[] = [
      '`' . $path . '`',
      extension_option_type($node),
      $children === [] ? $default : '-',
      extension_option_description($node),
    ];

    $rows = array_merge($rows, extension_option_rows($children, $path));
  }

  return $rows;
}

/**
 * Name the type of a configuration node.
 *
 * @param \Symfony\Component\Config\Definition\NodeInterface $node
 *   The configuration node.
 *
 * @return string
 *   The type as it reads in the reference.
 */
function extension_option_type(NodeInterface $node): string {
  if ($node instanceof PrototypedArrayNode) {
    return 'map';
  }

  if ($node instanceof ArrayNode) {
    return 'section';
  }

  if ($node instanceof IntegerNode) {
    return 'integer';
  }

  if ($node instanceof BooleanNode) {
    return 'boolean';
  }

  return 'string';
}

/**
 * Render the description of a configuration node as one table cell.
 *
 * @param \Symfony\Component\Config\Definition\NodeInterface $node
 *   The configuration node.
 *
 * @return string
 *   The description with line breaks and pipes made table-safe.
 */
function extension_option_description(NodeInterface $node): string {
  $info = method_exists($node, 'getInfo') ? (string) $node->getInfo() : '';

  $lines = array_filter(array_map(trim(...), explode(PHP_EOL, $info)), static fn(string $line): bool => $line !== '');

  return str_replace('|', '\\|', implode('<br>', $lines));
}

/**
 * Validate that every environment variable the source reads is documented.
 *
 * The variables have no registry to generate from, so the reference is written
 * by hand and this check keeps it complete.
 *
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<string>
 *   Array of errors.
 */
function validate_env_vars(string $base_path = __DIR__): array {
  $source = $base_path . DIRECTORY_SEPARATOR . 'src';
  $reference = $base_path . DIRECTORY_SEPARATOR . CONFIGURATION_FILE;

  if (!is_dir($source) || !is_file($reference)) {
    return [];
  }

  $documented = (string) file_get_contents($reference);
  $errors = [];

  $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
  $files = [];
  foreach ($iterator as $file) {
    if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
      $files[] = $file->getPathname();
    }
  }
  sort($files);

  foreach ($files as $file) {
    // Comments carry examples of what a consuming project reads, which is a
    // different contract from what this source reads.
    $code = '';
    foreach (token_get_all((string) file_get_contents($file)) as $token) {
      if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }

      $code .= is_array($token) ? $token[1] : $token;
    }

    preg_match_all('/getenv\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\)/', $code, $matches);

    foreach (array_unique($matches[1]) as $variable) {
      // The boundary is one of name characters rather than '\b', which does
      // not separate a name from a following underscore.
      if (preg_match('/(?<![A-Z0-9_])' . preg_quote($variable, '/') . '(?![A-Z0-9_])/', $documented) === 1) {
        continue;
      }

      $relative = substr($file, strlen($base_path) + 1);
      $errors[$variable] = sprintf('  %s - Environment variable %s is read but not documented in %s' . PHP_EOL, $relative, $variable, CONFIGURATION_FILE);
    }
  }

  return array_values($errors);
}

/**
 * Extract Behat tag names from a block of text.
 *
 * Matches `@tag` tokens while ignoring email addresses (the `@` in
 * `user@example.com` is preceded by a word character) and `@@`. Trailing prose
 * punctuation is stripped so a tag at the end of a sentence is captured cleanly.
 *
 * @param string $text
 *   The text to scan: a docblock, an example, or a feature file.
 *
 * @return array<int, string>
 *   Unique tag names without the leading `@`.
 */
function extract_tags(string $text): array {
  preg_match_all('/(?<![\w@])@([a-zA-Z0-9][a-zA-Z0-9:!._-]*)/', $text, $matches);

  $tags = [];
  foreach ($matches[1] as $tag) {
    $tag = rtrim($tag, '.,;');
    if ($tag !== '') {
      $tags[$tag] = $tag;
    }
  }

  return array_values($tags);
}

/**
 * Validate a single tag against the separator convention.
 *
 * @param string $tag
 *   The tag name without the leading `@`.
 * @param array<string, array{form: string, description: string}> $registry
 *   The tag registry from tag_registry().
 *
 * @return string|null
 *   An error message when a parametrized tag uses `-` instead of `:` as its
 *   value separator, or NULL when the tag conforms or is not a library tag.
 */
function validate_tag(string $tag, array $registry): ?string {
  $prefixes = array_keys($registry);
  // Longest prefix first so a prefix that shares a leading segment with a
  // shorter one is matched against its full, most specific form.
  usort($prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

  foreach ($prefixes as $prefix) {
    if ($tag === $prefix || str_starts_with($tag, $prefix . ':')) {
      return NULL;
    }

    if (str_starts_with($tag, $prefix . '-')) {
      if ($registry[$prefix]['form'] !== 'parametrized') {
        return NULL;
      }

      $value = substr($tag, strlen($prefix) + 1);

      return sprintf('"@%s" must use ":" as the value separator (use "@%s:%s", not "@%s-%s").', $prefix, $prefix, $value, $prefix, $value);
    }
  }

  return NULL;
}

/**
 * Validate tag separators across documentation and feature files.
 *
 * Scans every trait description, every step example, and every feature file for
 * tag tokens, then checks each against the registry. A parametrized tag written
 * with a `-` separator instead of a `:` is reported so the documented format
 * cannot drift apart from the established convention.
 *
 * @param array<string, mixed> $info
 *   The extracted trait info from extract_info().
 * @param string $base_path
 *   Base path for the repository.
 *
 * @return array<int, string>
 *   Array of error messages, one per offending tag and source.
 */
function validate_tags(array $info, string $base_path = __DIR__): array {
  $registry = tag_registry();
  $errors = [];

  foreach ($info as $trait => $trait_info) {
    if (!is_array($trait_info)) {
      continue;
    }

    $label = is_string($trait_info['name'] ?? NULL) ? $trait_info['name'] : (string) $trait;

    $texts = [];
    if (is_string($trait_info['description_full'] ?? NULL)) {
      $texts[] = $trait_info['description_full'];
    }
    foreach ((is_array($trait_info['methods'] ?? NULL) ? $trait_info['methods'] : []) as $method) {
      if (is_array($method) && is_string($method['example'] ?? NULL)) {
        $texts[] = $method['example'];
      }
    }

    foreach ($texts as $text) {
      foreach (extract_tags($text) as $tag) {
        $message = validate_tag($tag, $registry);
        if ($message !== NULL) {
          $errors[$label . '|' . $tag] = sprintf('  %s - Tag %s' . PHP_EOL, $label, $message);
        }
      }
    }
  }

  $features_dir = $base_path . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'behat' . DIRECTORY_SEPARATOR . 'features';
  if (is_dir($features_dir)) {
    foreach (glob($features_dir . DIRECTORY_SEPARATOR . '*.feature') ?: [] as $file) {
      $contents = file_get_contents($file);
      // @codeCoverageIgnoreStart
      if ($contents === FALSE) {
        continue;
      }
      // @codeCoverageIgnoreEnd
      $relative = 'tests/behat/features/' . basename($file);
      foreach (extract_tags($contents) as $tag) {
        $message = validate_tag($tag, $registry);
        if ($message !== NULL) {
          $errors[$relative . '|' . $tag] = sprintf('  %s - Tag %s' . PHP_EOL, $relative, $message);
        }
      }
    }
  }

  return array_values($errors);
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
  $string = str_replace(array_keys($replacements), array_values($replacements), $string);

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
 * @param array<array-key, array<int, string>> $rows
 *   The rows for the table. Keys are ignored; the rows render in their
 *   current order.
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

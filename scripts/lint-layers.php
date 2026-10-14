<?php

/**
 * @file
 * Layer dependency check.
 *
 * Each layer is guaranteed to reference nothing from the namespaces it
 * excludes: the backend runs without Behat loaded, the neutral root of each
 * layer runs without Drupal, the Drupal step traits and their context reach
 * Drupal only through a capability, and no layer reaches the one above it.
 * The guarantee holds only while the layer references nothing it excludes.
 *
 * This script reads every file of each layer and fails on any code reference
 * into those namespaces, and on any call the layer forbids.
 *
 * Run with --path=path/to/repo to check a tree other than this repository.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\WebContext;
use DrevOps\BehatSteps\WebRawContext;

/**
 * Layers to check, each with its paths, exclusions and prefixes.
 *
 * A path is a directory that is walked or a single file, and an exclusion
 * is a directory or a file under a path that is skipped. A forbidden prefix
 * is a root namespace or a longer namespace prefix: a symbol equal to it or
 * below it is a violation, unless an allowed prefix at least as long matches
 * it too.
 *
 * A layer may also name methods it must not call, and may hold its function
 * calls to the functions PHP itself defines, which rules out a call whose
 * target only runtime knows as well.
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
      'src/Steps/Exception',
      'src/WebRawContext.php',
      'src/WebContext.php',
    ],
    'exclude' => [],
    'forbidden' => ['Drupal'],
    'internal_functions_only' => TRUE,
  ],
  [
    'name' => 'the Drupal half',
    'paths' => ['src/Steps/Drupal', 'src/Steps/Helper/Drupal', 'src/DrupalContext.php'],
    'exclude' => [],
    // This half reaches Drupal through capabilities only, so the backend's
    // own classes and the core that 'getCore()' returns are forbidden too,
    // along with the capability that declares 'getCore()'.
    'forbidden' => ['Drupal', 'DrevOps\\BehatSteps\\Backend\\Drupal', CoreCapabilityInterface::class],
    'allowed' => ['DrevOps\\BehatSteps\\Backend\\Drupal\\Capability'],
    'forbidden_methods' => ['getCore'],
    // Drupal's procedural API sits in the global namespace, so only the
    // functions PHP defines may be called.
    'internal_functions_only' => TRUE,
  ],
];

/**
 * Functions PHP defines that call another function by name.
 *
 * A call through 1 of them names its target in a value the check does not
 * read, so a layer held to PHP's own functions forbids them.
 */
const LAYER_DISPATCH_FUNCTIONS = [
  'call_user_func',
  'call_user_func_array',
  'forward_static_call',
  'forward_static_call_array',
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

      $references = layer_file_violations($file, $layer['forbidden'], $layer['allowed'] ?? []);
      $calls = layer_file_call_violations($file, $layer['forbidden_methods'] ?? [], $layer['internal_functions_only'] ?? FALSE);

      foreach (array_merge($references, $calls) as $violation) {
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
 * @param array<int, string> $allowed
 *   Namespace prefixes that may be referenced under a forbidden one.
 *
 * @return array<int, array{line: int, symbol: string}>
 *   1 row per reference, in source order.
 */
function layer_file_violations(string $file, array $forbidden, array $allowed = []): array {
  $violations = [];
  $tokens = token_get_all((string) file_get_contents($file));
  $depth = 0;

  foreach ($tokens as $index => $token) {
    $opens_body = is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE);

    if ($token === '{' || $token === '}' || $opens_body) {
      $depth += $token === '}' ? -1 : 1;
      continue;
    }

    if (!is_array($token)) {
      continue;
    }

    // A 'use' outside every body is an import, which may name a global class
    // by 1 bare token.
    $symbol = $token[0] === T_USE && $depth === 0 ? layer_imported_symbol($tokens, $index) : layer_referenced_symbol($token[0], $token[1]);

    if ($symbol === NULL) {
      continue;
    }

    if (!layer_is_forbidden(ltrim($symbol, '\\'), $forbidden, $allowed)) {
      continue;
    }

    $violations[] = ['line' => $token[2], 'symbol' => $symbol];
  }

  return $violations;
}

/**
 * Reads the global class an import names by a bare token, if it names one.
 *
 * 'use Drupal;' carries the class as 1 unqualified token, which the
 * qualified-name tokens never cover, so the name is read from the statement.
 *
 * @param array<int, array{int, string, int}|string> $tokens
 *   The file's tokens.
 * @param int $index
 *   The index of the 'use' token.
 *
 * @return string|null
 *   The imported class, or NULL when the import is qualified, or is a
 *   function, a constant or a closure's variables.
 */
function layer_imported_symbol(array $tokens, int $index): ?string {
  for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
    $token = $tokens[$i];

    if (is_array($token) && $token[0] === T_WHITESPACE) {
      continue;
    }

    return is_array($token) && $token[0] === T_STRING ? $token[1] : NULL;
  }

  return NULL;
}

/**
 * Checks whether the longest prefix a symbol matches is a forbidden one.
 *
 * A prefix matches the symbol equal to it and every symbol below it, so a
 * root namespace forbids a whole vendor and a longer prefix forbids 1
 * namespace or 1 class. An allowed prefix reopens a namespace below a
 * forbidden one, and a longer forbidden prefix closes part of it again. An
 * allowed prefix as long as the forbidden one wins.
 *
 * @param string $symbol
 *   The qualified symbol, without a leading separator.
 * @param array<int, string> $forbidden
 *   The forbidden prefixes.
 * @param array<int, string> $allowed
 *   The allowed prefixes.
 */
function layer_is_forbidden(string $symbol, array $forbidden, array $allowed = []): bool {
  return layer_matching_prefix_length($symbol, $forbidden) > layer_matching_prefix_length($symbol, $allowed);
}

/**
 * Measures the longest of the prefixes a symbol is equal to or below.
 *
 * @param string $symbol
 *   The qualified symbol, without a leading separator.
 * @param array<int, string> $prefixes
 *   The namespace prefixes.
 *
 * @return int
 *   The length of the longest matching prefix, or 0 when none matches.
 */
function layer_matching_prefix_length(string $symbol, array $prefixes): int {
  $length = 0;

  foreach ($prefixes as $prefix) {
    if ($symbol === $prefix || str_starts_with($symbol, $prefix . '\\')) {
      $length = max($length, strlen($prefix));
    }
  }

  return $length;
}

/**
 * Finds the calls a layer forbids in one file.
 *
 * @param string $file
 *   Absolute path to the file to read.
 * @param array<int, string> $forbidden_methods
 *   Names of the methods that must not be called, in any case.
 * @param bool $internal_functions_only
 *   Whether every function called must be one PHP defines.
 *
 * @return array<int, array{line: int, symbol: string}>
 *   1 row per forbidden call, in source order.
 */
function layer_file_call_violations(string $file, array $forbidden_methods, bool $internal_functions_only): array {
  $tokens = layer_code_tokens((string) file_get_contents($file));
  $methods = array_map(strtolower(...), $forbidden_methods);
  $closures = layer_closure_variables($tokens);
  $violations = [];

  foreach (array_keys($tokens) as $index) {
    $call = layer_call($tokens, $index);

    if ($call === NULL || !layer_is_forbidden_call($call, $methods, $internal_functions_only, $closures)) {
      continue;
    }

    $violations[] = ['line' => $call['line'], 'symbol' => $call['operator'] . $call['name'] . '()'];
  }

  return $violations;
}

/**
 * Checks whether a layer forbids a call.
 *
 * A layer held to the functions PHP defines also forbids a call whose target
 * only runtime knows, unless it calls a closure the file assigns to the
 * variable, and a call through a function that calls another by name.
 *
 * @param array{name: string, operator: string|null, line: int, is_dynamic: bool} $call
 *   The call, as 'layer_call()' reads it.
 * @param array<int, string> $forbidden_methods
 *   Names of the methods that must not be called, in lower case.
 * @param bool $internal_functions_only
 *   Whether every function called must be one PHP defines.
 * @param array<int, string> $closures
 *   The variables the file assigns a closure or an arrow function to.
 */
function layer_is_forbidden_call(array $call, array $forbidden_methods, bool $internal_functions_only, array $closures): bool {
  if ($call['is_dynamic']) {
    return $internal_functions_only && ($call['operator'] !== NULL || !in_array($call['name'], $closures, TRUE));
  }

  if ($call['operator'] !== NULL) {
    return in_array(strtolower($call['name']), $forbidden_methods, TRUE);
  }

  return $internal_functions_only && (!layer_is_internal_function($call['name']) || in_array(strtolower(ltrim($call['name'], '\\')), LAYER_DISPATCH_FUNCTIONS, TRUE));
}

/**
 * Reads the tokens of PHP code that can make a call.
 *
 * Whitespace and comments are dropped, and so is every attribute, because
 * an attribute's name is followed by its arguments as a function's name is.
 *
 * @param string $code
 *   The PHP code.
 *
 * @return list<array{int, string, int}|string>
 *   The tokens, in source order.
 */
function layer_code_tokens(string $code): array {
  $tokens = [];
  $attribute_depth = 0;

  foreach (token_get_all($code) as $token) {
    $type = is_array($token) ? $token[0] : $token;

    if (in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
      continue;
    }

    if ($attribute_depth > 0 || $type === T_ATTRIBUTE) {
      $attribute_depth += match ($type) {
        T_ATTRIBUTE, '[' => 1,
        ']' => -1,
        default => 0,
      };

      continue;
    }

    $tokens[] = $token;
  }

  return $tokens;
}

/**
 * Reads the call a token makes, if it makes one.
 *
 * A name called after '->', '?->' or '::' is a method. A variable or a
 * braced expression called in place of a name is dynamic, because only
 * runtime knows what it calls.
 *
 * @param list<array{int, string, int}|string> $tokens
 *   The code tokens, as 'layer_code_tokens()' returns them.
 * @param int $index
 *   The index of the token.
 *
 * @return array{name: string, operator: string|null, line: int, is_dynamic: bool}|null
 *   The called name, the operator before it for a method, the line and
 *   whether the target is dynamic, or NULL when the token calls nothing.
 */
function layer_call(array $tokens, int $index): ?array {
  $token = $tokens[$index];
  $previous = $tokens[$index - 1] ?? NULL;
  $operator = layer_member_operator($previous);

  if ($token === '{') {
    return $operator === NULL || !is_array($previous) ? NULL : layer_braced_call($tokens, $index, $operator, $previous[2]);
  }

  if (!is_array($token) || ($tokens[$index + 1] ?? NULL) !== '(') {
    return NULL;
  }

  if ($token[0] === T_VARIABLE) {
    // A variable after 'new' names the class to instantiate.
    if (layer_token_type($previous) === T_NEW) {
      return NULL;
    }

    return ['name' => $token[1], 'operator' => $operator, 'line' => $token[2], 'is_dynamic' => TRUE];
  }

  if (!in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE) || layer_is_declaration($tokens, $index)) {
    return NULL;
  }

  return ['name' => $token[1], 'operator' => $operator, 'line' => $token[2], 'is_dynamic' => FALSE];
}

/**
 * Reads the call a braced member name makes, as in "->{'name'}()".
 *
 * @param list<array{int, string, int}|string> $tokens
 *   The code tokens.
 * @param int $index
 *   The index of the opening brace.
 * @param string $operator
 *   The operator before the brace.
 * @param int $line
 *   The line of the operator.
 *
 * @return array{name: string, operator: string, line: int, is_dynamic: bool}|null
 *   The call with the braced expression as its name, or NULL when the
 *   member is read rather than called.
 */
function layer_braced_call(array $tokens, int $index, string $operator, int $line): ?array {
  $depth = 0;

  for ($i = $index, $count = count($tokens); $i < $count; $i++) {
    $depth += match ($tokens[$i]) {
      '{' => 1,
      '}' => -1,
      default => 0,
    };

    if ($depth > 0) {
      continue;
    }

    if (($tokens[$i + 1] ?? NULL) !== '(') {
      return NULL;
    }

    $name = implode('', array_map(static fn(array|string $token): string => is_array($token) ? $token[1] : $token, array_slice($tokens, $index, $i - $index + 1)));

    return ['name' => $name, 'operator' => $operator, 'line' => $line, 'is_dynamic' => TRUE];
  }

  return NULL;
}

/**
 * Checks whether the name at a token is declared or instantiated.
 *
 * A name after 'new' is a class, and a name after 'function' is declared
 * rather than called.
 *
 * @param list<array{int, string, int}|string> $tokens
 *   The code tokens.
 * @param int $index
 *   The index of the name.
 */
function layer_is_declaration(array $tokens, int $index): bool {
  $previous_type = layer_token_type($tokens[$index - 1] ?? NULL);
  $is_by_reference = $previous_type === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG && layer_token_type($tokens[$index - 2] ?? NULL) === T_FUNCTION;

  return $previous_type === T_NEW || $previous_type === T_FUNCTION || $is_by_reference;
}

/**
 * Reads the member operator a token is, if it is one.
 *
 * @param array{int, string, int}|string|null $token
 *   The token.
 *
 * @return string|null
 *   '->', '?->' or '::', or NULL for any other token.
 */
function layer_member_operator(array|string|null $token): ?string {
  return is_array($token) && in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], TRUE) ? $token[1] : NULL;
}

/**
 * Reads the type of a token.
 *
 * @param array{int, string, int}|string|null $token
 *   The token.
 *
 * @return int|string|null
 *   The token constant, the character of a 1-character token, or NULL past
 *   either end of the code.
 */
function layer_token_type(array|string|null $token): int|string|null {
  return is_array($token) ? $token[0] : $token;
}

/**
 * Lists the variables a file assigns a closure or an arrow function to.
 *
 * The list covers the whole file rather than 1 scope, so a variable holding
 * a closure in 1 method counts as a closure in every other.
 *
 * @param list<array{int, string, int}|string> $tokens
 *   The code tokens.
 *
 * @return array<int, string>
 *   The variable names, each with its '$'.
 */
function layer_closure_variables(array $tokens): array {
  $variables = [];

  foreach ($tokens as $index => $token) {
    if (!is_array($token) || $token[0] !== T_VARIABLE || ($tokens[$index + 1] ?? NULL) !== '=') {
      continue;
    }

    $value_type = layer_token_type($tokens[$index + 2] ?? NULL);

    if ($value_type === T_STATIC) {
      $value_type = layer_token_type($tokens[$index + 3] ?? NULL);
    }

    if ($value_type === T_FUNCTION || $value_type === T_FN) {
      $variables[] = $token[1];
    }
  }

  return array_values(array_unique($variables));
}

/**
 * Checks whether a function name names a function PHP itself defines.
 *
 * @param string $name
 *   The function name as called, in any case.
 */
function layer_is_internal_function(string $name): bool {
  $name = ltrim($name, '\\');

  return function_exists($name) && (new \ReflectionFunction($name))->isInternal();
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

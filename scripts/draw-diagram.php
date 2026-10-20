<?php

/**
 * @file
 * Draws a draw.io diagram through the drawio-skill and exports it to SVG.
 *
 * The drawio-skill sets 'disable-model-invocation', so an agent cannot load it
 * through its Skill tool, while a typed '/drawio-skill' command still does.
 * This script starts a separate, unattended Claude Code session whose prompt
 * opens with that command. The session writes the '.drawio' source, and this
 * script exports a light and a dark SVG beside it and a PNG of each to review.
 *
 * Each SVG carries the SHA-256 of the source it was exported from, so the
 * check finds a render that fell behind its source without draw.io installed.
 *
 * Run with --help for the usage.
 *
 * @see https://github.com/Agents365-ai/drawio-skill
 */

declare(strict_types=1);

/**
 * The command that loads the drawio-skill.
 */
const DRAW_DIAGRAM_SKILL = 'drawio-skill';

/**
 * Where the drawio-skill is developed.
 */
const DRAW_DIAGRAM_SKILL_REPOSITORY = 'https://github.com/Agents365-ai/drawio-skill';

/**
 * The command that installs the drawio-skill for every project.
 */
const DRAW_DIAGRAM_SKILL_INSTALL = 'npx skills add Agents365-ai/drawio-skill -g';

/**
 * The draw.io desktop binary looked up on the PATH.
 */
const DRAW_DIAGRAM_DRAWIO = 'drawio';

/**
 * Locations of the draw.io desktop binary outside the PATH.
 */
const DRAW_DIAGRAM_DRAWIO_FALLBACKS = ['/Applications/draw.io.app/Contents/MacOS/draw.io'];

/**
 * Where draw.io desktop is downloaded from.
 */
const DRAW_DIAGRAM_DRAWIO_RELEASES = 'https://github.com/jgraph/drawio-desktop/releases';

/**
 * Seconds a drawing session runs before it is stopped.
 */
const DRAW_DIAGRAM_TIMEOUT = 3600;

/**
 * Effort of a drawing session unless --effort says otherwise.
 *
 * At a higher effort, a session plans every coordinate before it draws, and
 * the plan for a large diagram runs past the output token limit.
 */
const DRAW_DIAGRAM_EFFORT = 'medium';

/**
 * Efforts a drawing session accepts.
 */
const DRAW_DIAGRAM_EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

/**
 * Reasoning tokens between 2 progress lines about a response's reasoning.
 */
const DRAW_DIAGRAM_REASONING_STEP = 10000;

/**
 * Seconds 1 export runs before it is stopped.
 */
const DRAW_DIAGRAM_EXPORT_TIMEOUT = 120;

/**
 * Directory for review images, relative to the repository root.
 *
 * Each drawing session keeps its drafts in a directory of its own below it,
 * so sessions drawing in parallel never overwrite each other's files.
 */
const DRAW_DIAGRAM_SCRATCH_DIRECTORY = '.artifacts/tmp/diagrams';

/**
 * Export flags every export takes.
 */
const DRAW_DIAGRAM_EXPORT_FLAGS = ['--export', '--border', '16'];

/**
 * Themes each diagram is exported in, mapped to the suffix of their files.
 *
 * Each export names its theme, so its colors stay fixed whatever the viewer's
 * color scheme, and the page shows the export matching its own color mode.
 */
const DRAW_DIAGRAM_THEMES = ['light' => '', 'dark' => '.dark'];

/**
 * Export flags of the SVG the documentation embeds.
 */
const DRAW_DIAGRAM_SVG_FLAGS = ['--format', 'svg', '--embed-svg-images'];

/**
 * Export flags of the PNG to review.
 *
 * Wider images exceed what an image-reading model accepts.
 */
const DRAW_DIAGRAM_PNG_FLAGS = ['--format', 'png', '--width', '2000'];

/**
 * Tools the drawing session may use besides running commands.
 */
const DRAW_DIAGRAM_SESSION_TOOLS = ['Read', 'Write', 'Edit', 'Glob', 'Grep'];

/**
 * Pattern of the comment naming the source an SVG was exported from.
 */
const DRAW_DIAGRAM_MARKER_PATTERN = '/<!-- Exported by scripts\/draw-diagram\.php from (\S+) with sha256 ([0-9a-f]{64})\. -->\n?/';

/**
 * Options that take a value.
 */
const DRAW_DIAGRAM_VALUE_OPTIONS = ['brief', 'model', 'effort', 'budget', 'timeout'];

/**
 * Value options that may repeat.
 */
const DRAW_DIAGRAM_REPEATABLE_OPTIONS = ['brief'];

/**
 * Options that select what the script does instead of drawing.
 */
const DRAW_DIAGRAM_MODE_OPTIONS = ['export', 'check', 'help'];

/**
 * The usage printed by --help and after an argument error.
 */
const DRAW_DIAGRAM_USAGE = <<<'USAGE'
Draws a draw.io diagram through the drawio-skill and exports it to SVG.

Usage:
  php scripts/draw-diagram.php --brief=<file> [--brief=<file>]... [--model=<name>] [--effort=<level>] [--budget=<usd>] [--timeout=<seconds>] <diagram.drawio>
  php scripts/draw-diagram.php --export <diagram.drawio|directory>...
  php scripts/draw-diagram.php --check <diagram.drawio|directory>...

Drawing starts an unattended Claude Code session that runs /drawio-skill
with the briefs, then exports <diagram>.svg and <diagram>.dark.svg beside the
source, in the light and the dark theme, and a review PNG of each to
.artifacts/tmp/diagrams/. An existing diagram is updated in place.

Options:
  --brief    A file describing the diagram. Repeat it to add files in order.
  --model    The model of the session. Defaults to the configured model.
  --effort   The effort of the session: low, medium, high, xhigh or max.
             Defaults to medium.
  --budget   The most the session may spend, in US dollars.
  --timeout  The seconds the session may run. Defaults to 3600.
  --export   Exports each diagram again, after an edit in draw.io.
  --check    Fails when an SVG was not exported from its current source.
  --help     Prints this help.

Environment:
  DRAWIO_BIN  The draw.io desktop binary, when it is not "drawio" on the PATH.

USAGE;

/**
 * Prompt of the drawing session, opening with the command that loads the skill.
 */
const DRAW_DIAGRAM_PROMPT = <<<'PROMPT'
/drawio-skill {action} the draw.io diagram `{diagram}` from the brief below.

This session runs unattended, so ask nothing: settle every open point yourself and name each decision in the final report.

- {state}
- Keep every response short. Claude Code discards a response that runs past the output token limit, reasoning included, so settle the layout in a few lines rather than cell by cell, and write at most 300 lines in 1 call.
- Save the diagram to `{diagram}` as uncompressed XML, with 1 page. Keep every draft and scratch file in `{scratch}`, and change no other file.
- Run only the validator, a PHP file in `{scratch}` and the draw.io export, each by the full path given here: no other command is allowed. Read and search with the Read, Glob and Grep tools.
- Give the diagram a white background, as `background="#ffffff"` on `mxGraphModel`, so it stays readable on a dark page.
- Validate it with `python3 {skill}/scripts/validate.py {diagram} --score`, and fix every error it reports.
- Export a draft with `{drawio} --export --format png --width 2000 --output {scratch}/draft.png {diagram}`, then read the PNG. Fix overlapping shapes, clipped or unreadable labels, edges crossing shapes and stacked edges, in at most 2 rounds of repairs.
- Leave the SVG alone: the script that started this session exports it once the session ends.
- End with a short report: what the diagram shows, the validation score, and every decision and compromise.

## Brief

{briefs}
PROMPT;

/**
 * The prompt's first rule when the diagram does not exist yet.
 */
const DRAW_DIAGRAM_STATE_CREATE = '`{diagram}` does not exist yet: draw it from scratch with a PHP generator in `{scratch}`. Write the helpers that turn a container, a node or an edge into XML in 1 file, and the cells with their coordinates in 1 file per part of the diagram, each file in its own call. Then run the generator as `php {scratch}/<file>.php` to write the diagram.';

/**
 * The prompt's first rule when the diagram exists.
 */
const DRAW_DIAGRAM_STATE_UPDATE = '`{diagram}` exists: edit it in place, keep the geometry and style of every cell the brief leaves alone, and delete only what the brief removes.';

// The entry function runs only when the script is run directly, not when it
// is included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'draw-diagram.php') {
  try {
    exit(draw_diagram(array_slice($argv, 1), getenv(), (string) getcwd(), dirname(__DIR__), 'draw_diagram_run'));
  }
  catch (\RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
  }
}

// @codeCoverageIgnoreEnd

/**
 * Draws, exports or checks diagrams, as the arguments ask.
 *
 * @param array<int, string> $arguments
 *   The command line arguments, without the script name.
 * @param array<string, string> $environment
 *   The environment variables.
 * @param string $working_directory
 *   The directory relative paths resolve against.
 * @param string $root
 *   The repository root, where the session runs and the scratch files go.
 * @param callable(list<string>, string, int, (callable(string): void)|null): array{exit_code: int, output: string, errors: string, is_timed_out: bool} $runner
 *   Runs a command, as draw_diagram_run() does.
 *
 * @return int
 *   The exit code.
 *
 * @throws \RuntimeException
 *   When an argument is wrong, a tool is missing, or a step fails.
 */
function draw_diagram(array $arguments, array $environment, string $working_directory, string $root, callable $runner): int {
  $options = draw_diagram_parse_arguments($arguments);

  if ($options['mode'] === 'help') {
    echo DRAW_DIAGRAM_USAGE;

    return 0;
  }

  $paths = array_map(static fn(string $path): string => draw_diagram_resolve_path($path, $working_directory), $options['paths']);

  if ($options['mode'] === 'check') {
    $violations = draw_diagram_check($paths);

    if ($violations !== []) {
      echo 'Diagrams and their SVGs disagree:' . PHP_EOL . PHP_EOL . implode(PHP_EOL, $violations) . PHP_EOL;

      return 1;
    }

    $count = count(draw_diagram_expand($paths));
    echo sprintf('Every SVG matches its source: %d %s checked.', $count, $count === 1 ? 'diagram' : 'diagrams') . PHP_EOL;

    return 0;
  }

  $drawio = draw_diagram_find_drawio($environment);

  if ($options['mode'] === 'export') {
    foreach (draw_diagram_expand($paths) as $diagram) {
      echo draw_diagram_describe_export($diagram, draw_diagram_export($diagram, $drawio, $root, $runner)) . PHP_EOL;
    }

    return 0;
  }

  $diagram = $paths[0];

  if (!is_dir(dirname($diagram))) {
    throw new \RuntimeException(sprintf('The directory of %s does not exist.', $diagram));
  }

  $skill = draw_diagram_find_skill($root, $environment);
  $scratch = $root . '/' . DRAW_DIAGRAM_SCRATCH_DIRECTORY . '/' . basename($diagram, '.drawio');
  draw_diagram_create_directory($scratch);

  $prompt = draw_diagram_prompt($diagram, draw_diagram_read_briefs($options['briefs'], $working_directory), $skill, $drawio, $scratch);
  $command = draw_diagram_session_command($prompt, $skill, $drawio, $scratch, $options['model'], $options['effort'], $options['budget']);

  echo sprintf('Drawing %s with /%s.', $diagram, DRAW_DIAGRAM_SKILL) . PHP_EOL;

  $result = NULL;
  $on_line = static function (string $line) use (&$result): void {
    $event = json_decode($line, TRUE);

    if (!is_array($event)) {
      return;
    }

    if (($event['type'] ?? NULL) === 'result') {
      $result = $event;
    }

    foreach (draw_diagram_describe_event($event) as $description) {
      echo $description . PHP_EOL;
    }
  };

  $since = time();
  $run = $runner($command, $root, $options['timeout'], $on_line);

  echo PHP_EOL . draw_diagram_assert_session($result, $run, $options['timeout']) . PHP_EOL . PHP_EOL;

  draw_diagram_assert_written($diagram, $since);

  echo draw_diagram_describe_export($diagram, draw_diagram_export($diagram, $drawio, $root, $runner)) . PHP_EOL;

  return 0;
}

/**
 * Describes the files an export wrote.
 *
 * @param string $diagram
 *   The diagram file.
 * @param array<int, string> $pngs
 *   The review images, 1 per theme.
 *
 * @return string
 *   1 line naming the SVGs and the review images.
 */
function draw_diagram_describe_export(string $diagram, array $pngs): string {
  $svgs = array_map(static fn(string $theme): string => draw_diagram_svg_path($diagram, $theme), array_keys(DRAW_DIAGRAM_THEMES));

  return sprintf('Exported %s. Review images: %s', implode(' and ', $svgs), implode(' and ', $pngs));
}

/**
 * Parses the command line arguments.
 *
 * @param array<int, string> $arguments
 *   The command line arguments, without the script name.
 *
 * @return array{mode: string, paths: array<int, string>, briefs: array<int, string>, model: string|null, effort: string, budget: string|null, timeout: int}
 *   The selected mode, the diagram paths and the drawing options.
 *
 * @throws \RuntimeException
 *   When an argument is unknown, malformed or missing.
 */
function draw_diagram_parse_arguments(array $arguments): array {
  $modes = [];
  $values = [];
  $paths = [];

  foreach ($arguments as $argument) {
    if (!str_starts_with($argument, '--')) {
      $paths[] = $argument;

      continue;
    }

    $parts = explode('=', substr($argument, 2), 2);
    $name = $parts[0];
    $value = $parts[1] ?? NULL;

    if (in_array($name, DRAW_DIAGRAM_MODE_OPTIONS, TRUE) && $value === NULL) {
      $modes[] = $name;

      continue;
    }

    if (!in_array($name, DRAW_DIAGRAM_VALUE_OPTIONS, TRUE)) {
      throw draw_diagram_usage_error(sprintf('Unknown option "%s".', $argument));
    }

    if ($value === NULL || $value === '') {
      throw draw_diagram_usage_error(sprintf('The --%s option needs a value, as --%s=<value>.', $name, $name));
    }

    if (isset($values[$name]) && !in_array($name, DRAW_DIAGRAM_REPEATABLE_OPTIONS, TRUE)) {
      throw draw_diagram_usage_error(sprintf('The --%s option is given more than once.', $name));
    }

    $values[$name][] = $value;
  }

  $mode = draw_diagram_select_mode($modes, $values, $paths);

  foreach ($paths as $path) {
    if ($mode === 'draw' && !str_ends_with($path, '.drawio')) {
      throw draw_diagram_usage_error(sprintf('The diagram %s does not end in ".drawio".', $path));
    }
  }

  $effort = $values['effort'][0] ?? DRAW_DIAGRAM_EFFORT;

  if (!in_array($effort, DRAW_DIAGRAM_EFFORTS, TRUE)) {
    throw draw_diagram_usage_error(sprintf('The --effort option takes 1 of %s, not "%s".', implode(', ', DRAW_DIAGRAM_EFFORTS), $effort));
  }

  $budget = $values['budget'][0] ?? NULL;

  if ($budget !== NULL && (!is_numeric($budget) || (float) $budget <= 0)) {
    throw draw_diagram_usage_error(sprintf('The --budget option takes a positive amount in US dollars, not "%s".', $budget));
  }

  $timeout = $values['timeout'][0] ?? (string) DRAW_DIAGRAM_TIMEOUT;

  if (!ctype_digit($timeout) || (int) $timeout === 0) {
    throw draw_diagram_usage_error(sprintf('The --timeout option takes a positive number of seconds, not "%s".', $timeout));
  }

  return [
    'mode' => $mode,
    'paths' => $paths,
    'briefs' => $values['brief'] ?? [],
    'model' => $values['model'][0] ?? NULL,
    'effort' => $effort,
    'budget' => $budget,
    'timeout' => (int) $timeout,
  ];
}

/**
 * Selects what the script does from the parsed arguments.
 *
 * @param array<int, string> $modes
 *   The mode options given.
 * @param array<string, array<int, string>> $values
 *   The value options given, keyed by name.
 * @param array<int, string> $paths
 *   The diagram paths given.
 *
 * @return string
 *   'draw', 'export', 'check' or 'help'.
 *
 * @throws \RuntimeException
 *   When the arguments combine modes, or miss what the mode needs.
 */
function draw_diagram_select_mode(array $modes, array $values, array $paths): string {
  if (in_array('help', $modes, TRUE)) {
    return 'help';
  }

  if (count($modes) > 1) {
    throw draw_diagram_usage_error('Choose 1 of --export and --check.');
  }

  $mode = $modes[0] ?? 'draw';

  if ($mode !== 'draw') {
    if ($values !== []) {
      throw draw_diagram_usage_error(sprintf('The --%s option takes no other option.', $mode));
    }

    if ($paths === []) {
      throw draw_diagram_usage_error(sprintf('The --%s option needs at least 1 diagram or directory.', $mode));
    }

    return $mode;
  }

  if (count($paths) !== 1) {
    throw draw_diagram_usage_error('Drawing takes exactly 1 diagram.');
  }

  if (!isset($values['brief'])) {
    throw draw_diagram_usage_error('Drawing needs at least 1 --brief.');
  }

  return $mode;
}

/**
 * Builds the exception for an argument error, followed by the usage.
 *
 * @param string $message
 *   What is wrong with the arguments.
 *
 * @return \RuntimeException
 *   The exception to throw.
 */
function draw_diagram_usage_error(string $message): \RuntimeException {
  return new \RuntimeException($message . PHP_EOL . PHP_EOL . DRAW_DIAGRAM_USAGE);
}

/**
 * Resolves a path against a directory, unless it is absolute.
 *
 * @param string $path
 *   The path as given.
 * @param string $directory
 *   The directory a relative path resolves against.
 *
 * @return string
 *   The absolute path.
 */
function draw_diagram_resolve_path(string $path, string $directory): string {
  return str_starts_with($path, '/') ? $path : rtrim($directory, '/') . '/' . $path;
}

/**
 * Expands paths into the diagrams they name.
 *
 * @param array<int, string> $paths
 *   Diagram files and directories holding diagrams.
 *
 * @return array<int, string>
 *   The diagram files, a directory's in name order.
 *
 * @throws \RuntimeException
 *   When a path is neither a diagram nor a directory holding 1.
 */
function draw_diagram_expand(array $paths): array {
  $diagrams = [];

  foreach ($paths as $path) {
    if (str_ends_with($path, '.drawio')) {
      $diagrams[] = $path;

      continue;
    }

    $found = is_dir($path) ? (glob($path . '/*.drawio') ?: []) : [];

    if ($found === []) {
      throw new \RuntimeException(sprintf('%s is neither a ".drawio" file nor a directory holding 1.', $path));
    }

    array_push($diagrams, ...$found);
  }

  return $diagrams;
}

/**
 * Returns the path of the SVG exported from a diagram in a theme.
 *
 * @param string $diagram
 *   The diagram file.
 * @param string $theme
 *   A key of DRAW_DIAGRAM_THEMES.
 *
 * @return string
 *   The SVG file beside it, such as 'flow.svg' or 'flow.dark.svg'.
 */
function draw_diagram_svg_path(string $diagram, string $theme = 'light'): string {
  return substr($diagram, 0, -strlen('.drawio')) . DRAW_DIAGRAM_THEMES[$theme] . '.svg';
}

/**
 * Finds the diagram beside an SVG that the SVG could be exported from.
 *
 * Both 'flow.svg' and 'flow.dark.svg' come from 'flow.drawio'. The themes are
 * tried in the order of DRAW_DIAGRAM_THEMES, so 'night.dark.svg' is the light
 * export of 'night.dark.drawio' when that diagram exists.
 *
 * @param string $svg
 *   The SVG file.
 *
 * @return string|null
 *   The diagram file, or NULL when no diagram beside the SVG matches it.
 */
function draw_diagram_find_source(string $svg): ?string {
  foreach (DRAW_DIAGRAM_THEMES as $suffix) {
    $extension = $suffix . '.svg';
    $diagram = substr($svg, 0, -strlen($extension)) . '.drawio';

    if (str_ends_with($svg, $extension) && is_file($diagram)) {
      return $diagram;
    }
  }

  return NULL;
}

/**
 * Finds the drawio-skill where Claude Code loads skills from.
 *
 * @param string $root
 *   The repository root, whose own skills come first.
 * @param array<string, string> $environment
 *   The environment variables.
 *
 * @return string
 *   The skill directory, with symbolic links resolved.
 *
 * @throws \RuntimeException
 *   When the skill is installed in neither place.
 */
function draw_diagram_find_skill(string $root, array $environment): string {
  $candidates = [$root . '/.claude/skills/' . DRAW_DIAGRAM_SKILL];

  if (($environment['HOME'] ?? '') !== '') {
    $candidates[] = $environment['HOME'] . '/.claude/skills/' . DRAW_DIAGRAM_SKILL;
  }

  foreach ($candidates as $candidate) {
    $directory = realpath($candidate);

    if ($directory !== FALSE && is_file($directory . '/SKILL.md')) {
      return $directory;
    }
  }

  throw new \RuntimeException(sprintf('The %s skill is not installed: no SKILL.md in %s. Install it with "%s". Its source: %s', DRAW_DIAGRAM_SKILL, implode(' or ', $candidates), DRAW_DIAGRAM_SKILL_INSTALL, DRAW_DIAGRAM_SKILL_REPOSITORY));
}

/**
 * Finds the draw.io desktop binary.
 *
 * @param array<string, string> $environment
 *   The environment variables.
 * @param array<int, string> $fallbacks
 *   Locations to try after the PATH.
 *
 * @return string
 *   The path of the binary.
 *
 * @throws \RuntimeException
 *   When DRAWIO_BIN names no executable, or no binary is found.
 */
function draw_diagram_find_drawio(array $environment, array $fallbacks = DRAW_DIAGRAM_DRAWIO_FALLBACKS): string {
  $configured = $environment['DRAWIO_BIN'] ?? '';

  if ($configured !== '') {
    if (!is_file($configured) || !is_executable($configured)) {
      throw new \RuntimeException(sprintf('DRAWIO_BIN names %s, which is not an executable file.', $configured));
    }

    return $configured;
  }

  $candidates = [];

  foreach (explode(PATH_SEPARATOR, $environment['PATH'] ?? '') as $directory) {
    if ($directory !== '') {
      $candidates[] = rtrim($directory, '/') . '/' . DRAW_DIAGRAM_DRAWIO;
    }
  }

  foreach ([...$candidates, ...$fallbacks] as $candidate) {
    if (is_file($candidate) && is_executable($candidate)) {
      return $candidate;
    }
  }

  throw new \RuntimeException(sprintf('draw.io desktop is not installed: no "%s" on the PATH and none at %s. Install it with "brew install --cask drawio" on macOS or from %s, or set DRAWIO_BIN to its binary.', DRAW_DIAGRAM_DRAWIO, implode(', ', $fallbacks), DRAW_DIAGRAM_DRAWIO_RELEASES));
}

/**
 * Reads the briefs into 1 text, in the order given.
 *
 * @param array<int, string> $files
 *   The brief files.
 * @param string $working_directory
 *   The directory relative paths resolve against.
 *
 * @return string
 *   The briefs, separated by a blank line.
 *
 * @throws \RuntimeException
 *   When a brief is missing or empty.
 */
function draw_diagram_read_briefs(array $files, string $working_directory): string {
  $briefs = [];

  foreach ($files as $file) {
    $path = draw_diagram_resolve_path($file, $working_directory);
    $contents = is_file($path) ? trim((string) file_get_contents($path)) : '';

    if ($contents === '') {
      throw new \RuntimeException(sprintf('The brief %s is missing or empty.', $path));
    }

    $briefs[] = $contents;
  }

  return implode("\n\n", $briefs);
}

/**
 * Builds the prompt of the drawing session.
 *
 * @param string $diagram
 *   The diagram file to create or update.
 * @param string $briefs
 *   What the diagram shows.
 * @param string $skill
 *   The skill directory.
 * @param string $drawio
 *   The draw.io desktop binary.
 * @param string $scratch
 *   The directory for drafts.
 *
 * @return string
 *   The prompt, opening with the command that loads the skill.
 */
function draw_diagram_prompt(string $diagram, string $briefs, string $skill, string $drawio, string $scratch): string {
  $is_update = is_file($diagram);
  $template = str_replace('{state}', $is_update ? DRAW_DIAGRAM_STATE_UPDATE : DRAW_DIAGRAM_STATE_CREATE, DRAW_DIAGRAM_PROMPT);

  return strtr($template, [
    '{action}' => $is_update ? 'Update' : 'Create',
    '{diagram}' => $diagram,
    '{skill}' => $skill,
    '{drawio}' => $drawio,
    '{scratch}' => $scratch,
    '{briefs}' => $briefs,
  ]);
}

/**
 * Builds the command that starts the drawing session.
 *
 * Every option takes its value after '=', so no option that takes several
 * values swallows the prompt that follows it.
 *
 * @param string $prompt
 *   The prompt of the session.
 * @param string $skill
 *   The skill directory, which the session reads from.
 * @param string $drawio
 *   The draw.io desktop binary, which the session exports drafts with.
 * @param string $scratch
 *   The directory for drafts, whose PHP generator the session runs.
 * @param string|null $model
 *   The model of the session, or NULL for the configured model.
 * @param string $effort
 *   The effort of the session.
 * @param string|null $budget
 *   The most the session may spend in US dollars, or NULL for no cap.
 *
 * @return list<string>
 *   The command and its arguments.
 */
function draw_diagram_session_command(string $prompt, string $skill, string $drawio, string $scratch, ?string $model, string $effort, ?string $budget): array {
  // A rule for a bare interpreter also allows a one-liner, so each rule names
  // the script or subcommand the session runs.
  $validator = sprintf('Bash(python3 %s/scripts/validate.py *)', $skill);
  $generator = sprintf('Bash(php %s/*)', $scratch);
  $export = sprintf('Bash(%s --export *)', $drawio);
  $tools = [...DRAW_DIAGRAM_SESSION_TOOLS, $validator, $generator, $export];

  // Without a person to answer, a permission prompt would block the session,
  // so every tool outside the list is denied instead.
  $command = [
    'claude',
    '--print',
    '--output-format=stream-json',
    '--verbose',
    '--no-session-persistence',
    '--permission-mode=acceptEdits',
    '--permission-prompts=none',
    '--add-dir=' . $skill,
    '--allowedTools=' . implode(',', $tools),
    '--effort=' . $effort,
  ];

  if ($model !== NULL) {
    $command[] = '--model=' . $model;
  }

  if ($budget !== NULL) {
    $command[] = '--max-budget-usd=' . $budget;
  }

  $command[] = $prompt;

  return $command;
}

/**
 * Describes 1 event of the session's stream as progress lines.
 *
 * @param array<mixed> $event
 *   The decoded event.
 *
 * @return array<int, string>
 *   The lines to print, none for an event without progress.
 */
function draw_diagram_describe_event(array $event): array {
  $type = $event['type'] ?? NULL;

  if ($type === 'system') {
    return draw_diagram_describe_system_event($event);
  }

  $message = $event['message'] ?? NULL;
  $content = is_array($message) ? ($message['content'] ?? NULL) : NULL;

  if ($type !== 'assistant' || !is_array($content)) {
    return [];
  }

  $lines = [];

  foreach ($content as $block) {
    if (!is_array($block)) {
      continue;
    }

    $text = $block['text'] ?? NULL;
    $name = $block['name'] ?? NULL;

    if (($block['type'] ?? NULL) === 'text' && is_string($text) && trim($text) !== '') {
      $lines[] = '  ' . draw_diagram_shorten($text);
    }

    if (($block['type'] ?? NULL) === 'tool_use') {
      $lines[] = sprintf('  > %s: %s', is_string($name) ? $name : 'tool', draw_diagram_shorten(draw_diagram_describe_input($block['input'] ?? NULL)));
    }
  }

  return $lines;
}

/**
 * Describes 1 system event of the session's stream as progress lines.
 *
 * Reasoning streams no text, so its token estimate is the only sign of
 * progress in a response that has not produced a tool call yet. It is
 * printed once per DRAW_DIAGRAM_REASONING_STEP tokens.
 *
 * @param array<mixed> $event
 *   The decoded event.
 *
 * @return array<int, string>
 *   The lines to print, none for an event without progress.
 */
function draw_diagram_describe_system_event(array $event): array {
  $subtype = $event['subtype'] ?? NULL;

  if ($subtype === 'init') {
    $model = $event['model'] ?? NULL;

    return [sprintf('Session started on %s.', is_string($model) ? $model : 'the configured model')];
  }

  if ($subtype === 'thinking_tokens') {
    $estimate = $event['estimated_tokens'] ?? NULL;
    $delta = $event['estimated_tokens_delta'] ?? NULL;

    if (!is_int($estimate) || !is_int($delta)) {
      return [];
    }

    $step = intdiv($estimate, DRAW_DIAGRAM_REASONING_STEP);

    if ($step === intdiv(max(0, $estimate - $delta), DRAW_DIAGRAM_REASONING_STEP)) {
      return [];
    }

    return [sprintf('  Reasoning: about %d tokens so far in this response.', $step * DRAW_DIAGRAM_REASONING_STEP)];
  }

  if ($subtype === 'api_retry') {
    $reason = trim(sprintf('%s %s', $event['error_status'] ?? '', $event['error'] ?? ''));
    $reason = $reason === '' ? 'no reason given' : $reason;
    $delay = $event['retry_delay_ms'] ?? NULL;
    $seconds = is_int($delay) ? (int) ceil($delay / 1000) : 0;
    $line = sprintf('  The API call failed with %s: retry %d of %d in %d seconds.', $reason, $event['attempt'] ?? 0, $event['max_retries'] ?? 0, $seconds);

    return [$line];
  }

  return [];
}

/**
 * Describes the input of a tool call by its most telling value.
 *
 * @param mixed $input
 *   The decoded input.
 *
 * @return string
 *   The command, path or pattern the call works on, or the whole input.
 */
function draw_diagram_describe_input(mixed $input): string {
  if (!is_array($input)) {
    return '';
  }

  foreach (['command', 'file_path', 'pattern', 'path'] as $key) {
    $value = $input[$key] ?? NULL;

    if (is_string($value)) {
      return $value;
    }
  }

  return (string) json_encode($input, JSON_UNESCAPED_SLASHES);
}

/**
 * Shortens a text to its first line that is not blank.
 *
 * @param string $text
 *   The text.
 * @param int $length
 *   The most characters to keep.
 *
 * @return string
 *   The line, cut at the length with an ellipsis.
 */
function draw_diagram_shorten(string $text, int $length = 160): string {
  foreach (explode("\n", $text) as $line) {
    $line = trim($line);

    if ($line !== '') {
      return mb_strlen($line) > $length ? mb_substr($line, 0, $length - 3) . '...' : $line;
    }
  }

  return '';
}

/**
 * Asserts the drawing session succeeded, and reports what it did.
 *
 * @param array<mixed>|null $result
 *   The session's result event, or NULL when it sent none.
 * @param array{exit_code: int, output: string, errors: string, is_timed_out: bool} $run
 *   What running the session returned.
 * @param int $timeout
 *   The seconds the session was allowed.
 *
 * @return string
 *   The session's final report, with its cost and turns.
 *
 * @throws \RuntimeException
 *   When the session timed out, sent no result, or failed.
 */
function draw_diagram_assert_session(?array $result, array $run, int $timeout): string {
  if ($run['is_timed_out']) {
    throw new \RuntimeException(sprintf('The drawing session ran longer than %d seconds and was stopped. Raise --timeout or narrow the brief.', $timeout));
  }

  if ($result === NULL) {
    throw new \RuntimeException(draw_diagram_join_lines(sprintf('The drawing session sent no result and exited with code %d.', $run['exit_code']), $run['errors']));
  }

  $report = $result['result'] ?? NULL;
  $report = is_string($report) ? trim($report) : '';
  $subtype = $result['subtype'] ?? NULL;
  $subtype = is_string($subtype) ? $subtype : 'unknown';

  if (($result['is_error'] ?? FALSE) !== FALSE || $subtype !== 'success' || $run['exit_code'] !== 0) {
    throw new \RuntimeException(draw_diagram_join_lines(sprintf('The drawing session failed with "%s" and exit code %d.', $subtype, $run['exit_code']), $report, $run['errors']));
  }

  $turns = $result['num_turns'] ?? NULL;
  $cost = $result['total_cost_usd'] ?? NULL;
  $usage = [];

  if (is_int($turns)) {
    $usage[] = sprintf('%d turns', $turns);
  }

  if (is_int($cost) || is_float($cost)) {
    $usage[] = sprintf('$%.2f', $cost);
  }

  return $usage === [] ? $report : $report . PHP_EOL . PHP_EOL . 'Session: ' . implode(', ', $usage) . '.';
}

/**
 * Joins texts into lines, leaving out the blank ones.
 *
 * @param string ...$texts
 *   The texts, each trimmed.
 *
 * @return string
 *   The texts that are not blank, 1 per line.
 */
function draw_diagram_join_lines(string ...$texts): string {
  return implode(PHP_EOL, array_filter(array_map(trim(...), $texts), static fn(string $text): bool => $text !== ''));
}

/**
 * Lists what keeps a diagram from being exported as the documentation needs.
 *
 * The SVG shows the first page only, and draws the page's background. A
 * compressed page would turn every change into 1 unreadable line of diff.
 *
 * @param string $diagram
 *   The diagram file, which exists.
 *
 * @return array<int, string>
 *   1 sentence per problem, none when the diagram is fit to export.
 */
function draw_diagram_problems(string $diagram): array {
  $contents = (string) file_get_contents($diagram);
  $document = new \DOMDocument();

  $previous = libxml_use_internal_errors(TRUE);
  $is_loaded = $contents !== '' && $document->loadXML($contents);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);

  $xpath = new \DOMXPath($document);
  $pages = $is_loaded ? $xpath->query('/mxfile/diagram') : FALSE;

  if ($pages === FALSE || $pages->length === 0) {
    return [sprintf('%s is not a draw.io file: it holds no <mxfile> with a <diagram>.', $diagram)];
  }

  if ($pages->length > 1) {
    return [sprintf('%s holds %d pages, and its SVG shows only the first: keep 1 page per file.', $diagram, $pages->length)];
  }

  $models = $xpath->query('/mxfile/diagram/mxGraphModel');
  $model = $models === FALSE ? NULL : $models->item(0);

  if (!$model instanceof \DOMElement) {
    return [sprintf('%s stores its page compressed: save it as uncompressed XML, so its diff can be read.', $diagram)];
  }

  if (in_array(strtolower($model->getAttribute('background')), ['', 'none'], TRUE)) {
    return [sprintf('%s draws no background, so its SVG is transparent and unreadable on a dark page: set background="#ffffff" on <mxGraphModel>.', $diagram)];
  }

  return [];
}

/**
 * Asserts the drawing session wrote a diagram fit to export.
 *
 * @param string $diagram
 *   The diagram file.
 * @param int $since
 *   The Unix time the session started.
 *
 * @throws \RuntimeException
 *   When the diagram is missing, unchanged or unfit to export.
 */
function draw_diagram_assert_written(string $diagram, int $since): void {
  clearstatcache(TRUE, $diagram);

  if (!is_file($diagram)) {
    throw new \RuntimeException(sprintf('The drawing session did not write %s.', $diagram));
  }

  if (filemtime($diagram) < $since) {
    throw new \RuntimeException(sprintf('The drawing session left %s unchanged.', $diagram));
  }

  $problems = draw_diagram_problems($diagram);

  if ($problems !== []) {
    throw new \RuntimeException(implode(PHP_EOL, $problems));
  }
}

/**
 * Exports a diagram to an SVG beside it and a PNG to review, in each theme.
 *
 * @param string $diagram
 *   The diagram file.
 * @param string $drawio
 *   The draw.io desktop binary.
 * @param string $root
 *   The repository root, where the review images go.
 * @param callable(list<string>, string, int, (callable(string): void)|null): array{exit_code: int, output: string, errors: string, is_timed_out: bool} $runner
 *   Runs a command, as draw_diagram_run() does.
 *
 * @return array<int, string>
 *   The review images, 1 per theme in the order of DRAW_DIAGRAM_THEMES.
 *
 * @throws \RuntimeException
 *   When the diagram is missing or unfit, an export fails, or draw.io ignores
 *   the theme.
 */
function draw_diagram_export(string $diagram, string $drawio, string $root, callable $runner): array {
  if (!is_file($diagram)) {
    throw new \RuntimeException(sprintf('%s does not exist.', $diagram));
  }

  $problems = draw_diagram_problems($diagram);

  if ($problems !== []) {
    throw new \RuntimeException(implode(PHP_EOL, $problems));
  }

  $scratch = $root . '/' . DRAW_DIAGRAM_SCRATCH_DIRECTORY;
  draw_diagram_create_directory($scratch);

  $svgs = [];
  $pngs = [];
  $exports = [];

  foreach (DRAW_DIAGRAM_THEMES as $theme => $suffix) {
    $svg = draw_diagram_svg_path($diagram, $theme);
    $png = $scratch . '/' . basename($diagram, '.drawio') . $suffix . '.png';

    $svgs[$theme] = $svg;
    $pngs[] = $png;
    $exports[] = [$svg, ['--theme', $theme, ...DRAW_DIAGRAM_SVG_FLAGS]];
    $exports[] = [$png, ['--theme', $theme, ...DRAW_DIAGRAM_PNG_FLAGS]];
  }

  foreach ($exports as [$target, $flags]) {
    $since = time();
    $run = $runner([$drawio, ...DRAW_DIAGRAM_EXPORT_FLAGS, ...$flags, '--output', $target, $diagram], $root, DRAW_DIAGRAM_EXPORT_TIMEOUT, NULL);
    clearstatcache(TRUE, $target);

    // draw.io desktop exits 0 for some failed exports, so the file decides.
    if ($run['exit_code'] !== 0 || $run['is_timed_out'] || !is_file($target) || filemtime($target) < $since) {
      throw new \RuntimeException(draw_diagram_join_lines(sprintf('draw.io could not export %s.', $target), $run['output'], $run['errors']));
    }
  }

  foreach ($svgs as $theme => $svg) {
    file_put_contents($svg, draw_diagram_strip_fallbacks((string) file_get_contents($svg)));
    draw_diagram_stamp($svg, $diagram);
    draw_diagram_assert_theme($svg, $theme);
  }

  return $pngs;
}

/**
 * Asserts that an exported SVG declares the theme it was exported in.
 *
 * draw.io desktop ignores an option it doesn't know, so a release without
 * '--theme' exports the light theme for both.
 *
 * @param string $svg
 *   The SVG file.
 * @param string $theme
 *   A key of DRAW_DIAGRAM_THEMES.
 *
 * @throws \RuntimeException
 *   When the root element of the SVG declares another color scheme or none.
 */
function draw_diagram_assert_theme(string $svg, string $theme): void {
  if (preg_match('/<svg\b[^>]*\bcolor-scheme:\s*' . preg_quote($theme, '/') . '\b/', (string) file_get_contents($svg)) === 1) {
    return;
  }

  throw new \RuntimeException(sprintf('draw.io ignored "--theme %s" when it exported %s. Update draw.io desktop to a release that supports "--theme".', $theme, $svg));
}

/**
 * Removes the raster copies of the HTML labels from an exported SVG.
 *
 * The draw.io export writes a PNG copy beside every HTML label, for viewers
 * that can't draw HTML in an SVG. Every browser draws the HTML, and the
 * copies make up most of the file. A shared image that no remaining element
 * uses is removed too.
 *
 * @param string $contents
 *   The SVG.
 *
 * @return string
 *   The SVG without the copies.
 */
function draw_diagram_strip_fallbacks(string $contents): string {
  $contents = (string) preg_replace('#(</foreignObject>)<(?:image|use)\b[^>]*/>#', '$1', $contents);
  preg_match_all('/href="#(mx-image-[^"]+)"/', $contents, $matches);
  $used = array_flip($matches[1]);

  return (string) preg_replace_callback('#<symbol id="(mx-image-[^"]+)"[^>]*>.*?</symbol>#s', static fn(array $symbol): string => isset($used[$symbol[1]]) ? $symbol[0] : '', $contents);
}

/**
 * Creates a directory and its parents, unless it exists.
 *
 * @param string $directory
 *   The directory.
 *
 * @throws \RuntimeException
 *   When the directory cannot be created.
 */
function draw_diagram_create_directory(string $directory): void {
  if (!is_dir($directory) && !mkdir($directory, 0777, TRUE) && !is_dir($directory)) {
    throw new \RuntimeException(sprintf('Could not create %s.', $directory));
  }
}

/**
 * Hashes the contents of a diagram.
 *
 * Line endings are normalized, so a checkout that converts them still
 * matches the hash an export recorded.
 *
 * @param string $contents
 *   The diagram's contents.
 *
 * @return string
 *   The SHA-256, in hexadecimal.
 */
function draw_diagram_hash(string $contents): string {
  return hash('sha256', str_replace("\r\n", "\n", $contents));
}

/**
 * Builds the comment naming the source of an SVG.
 *
 * @param string $diagram
 *   The diagram file the SVG is exported from.
 *
 * @return string
 *   The comment.
 */
function draw_diagram_marker(string $diagram): string {
  return sprintf('<!-- Exported by scripts/draw-diagram.php from %s with sha256 %s. -->', basename($diagram), draw_diagram_hash((string) file_get_contents($diagram)));
}

/**
 * Records in an SVG the source it was exported from.
 *
 * @param string $svg
 *   The SVG file.
 * @param string $diagram
 *   The diagram file it was exported from.
 *
 * @throws \RuntimeException
 *   When the SVG holds no 'svg' element.
 */
function draw_diagram_stamp(string $svg, string $diagram): void {
  $contents = (string) preg_replace(DRAW_DIAGRAM_MARKER_PATTERN, '', (string) file_get_contents($svg));
  $position = strpos($contents, '<svg');

  if ($position === FALSE) {
    throw new \RuntimeException(sprintf('%s holds no <svg> element to record its source in.', $svg));
  }

  file_put_contents($svg, substr($contents, 0, $position) . draw_diagram_marker($diagram) . "\n" . substr($contents, $position));
}

/**
 * Finds every diagram whose SVG was not exported from its current source.
 *
 * A directory is also checked for an SVG with no source beside it.
 *
 * @param array<int, string> $paths
 *   Diagram files and directories holding diagrams.
 *
 * @return array<int, string>
 *   1 sentence per problem, in path order.
 *
 * @throws \RuntimeException
 *   When a path is neither a diagram nor a directory holding 1.
 */
function draw_diagram_check(array $paths): array {
  $violations = [];

  foreach (draw_diagram_expand($paths) as $diagram) {
    if (!is_file($diagram)) {
      $violations[] = sprintf('%s does not exist.', $diagram);

      continue;
    }

    array_push($violations, ...draw_diagram_problems($diagram));

    $export = sprintf('export it with: php scripts/draw-diagram.php --export %s', $diagram);

    foreach (array_keys(DRAW_DIAGRAM_THEMES) as $theme) {
      $svg = draw_diagram_svg_path($diagram, $theme);

      if (!is_file($svg)) {
        $violations[] = sprintf('%s has no %s SVG: %s', $diagram, $theme, $export);

        continue;
      }

      if (preg_match(DRAW_DIAGRAM_MARKER_PATTERN, (string) file_get_contents($svg), $matches) !== 1) {
        $violations[] = sprintf('%s was not exported by scripts/draw-diagram.php: %s', $svg, $export);

        continue;
      }

      if ($matches[1] !== basename($diagram) || $matches[2] !== draw_diagram_hash((string) file_get_contents($diagram))) {
        $violations[] = sprintf('%s is out of date with %s: %s', $svg, $diagram, $export);
      }
    }
  }

  foreach ($paths as $path) {
    foreach (is_dir($path) ? (glob($path . '/*.svg') ?: []) : [] as $svg) {
      if (draw_diagram_find_source($svg) === NULL) {
        $violations[] = sprintf('%s has no .drawio source beside it.', $svg);
      }
    }
  }

  return $violations;
}

/**
 * Runs a command, passing each line of its output to a callback.
 *
 * @param list<string> $command
 *   The command and its arguments.
 * @param string $directory
 *   The working directory.
 * @param int $timeout
 *   The seconds the command may run before it is stopped.
 * @param (callable(string): void)|null $on_line
 *   Receives each line of the output as it arrives.
 *
 * @return array{exit_code: int, output: string, errors: string, is_timed_out: bool}
 *   The exit code, the output, the error output and whether it was stopped.
 *
 * @throws \RuntimeException
 *   When the command cannot be started.
 *
 * @codeCoverageIgnore
 */
function draw_diagram_run(array $command, string $directory, int $timeout, ?callable $on_line = NULL): array {
  $pipes = [];
  $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);

  if (!is_resource($process)) {
    throw new \RuntimeException(sprintf('Could not start %s.', $command[0]));
  }

  fclose($pipes[0]);
  stream_set_blocking($pipes[1], FALSE);
  stream_set_blocking($pipes[2], FALSE);

  $deadline = time() + $timeout;
  $output = '';
  $errors = '';
  $buffer = '';
  $is_timed_out = FALSE;

  while (!feof($pipes[1]) || !feof($pipes[2])) {
    if (time() >= $deadline) {
      $is_timed_out = TRUE;
      proc_terminate($process);

      break;
    }

    $read = [];

    foreach ([$pipes[1], $pipes[2]] as $stream) {
      if (!feof($stream)) {
        $read[] = $stream;
      }
    }

    $write = NULL;
    $except = NULL;

    if (stream_select($read, $write, $except, 1) === FALSE) {
      break;
    }

    foreach ($read as $stream) {
      $chunk = (string) fread($stream, 65536);

      if ($stream === $pipes[2]) {
        $errors .= $chunk;

        continue;
      }

      $output .= $chunk;
      $buffer .= $chunk;

      while (($position = strpos($buffer, "\n")) !== FALSE) {
        $line = substr($buffer, 0, $position);
        $buffer = substr($buffer, $position + 1);

        if ($on_line !== NULL) {
          $on_line($line);
        }
      }
    }
  }

  if ($buffer !== '' && $on_line !== NULL) {
    $on_line($buffer);
  }

  fclose($pipes[1]);
  fclose($pipes[2]);
  $exit_code = proc_close($process);

  return ['exit_code' => $exit_code, 'output' => $output, 'errors' => $errors, 'is_timed_out' => $is_timed_out];
}

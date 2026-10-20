<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the script that draws diagrams through the drawio-skill.
 */
#[CoversFunction('draw_diagram')]
#[CoversFunction('draw_diagram_assert_session')]
#[CoversFunction('draw_diagram_assert_written')]
#[CoversFunction('draw_diagram_check')]
#[CoversFunction('draw_diagram_create_directory')]
#[CoversFunction('draw_diagram_describe_event')]
#[CoversFunction('draw_diagram_describe_export')]
#[CoversFunction('draw_diagram_describe_system_event')]
#[CoversFunction('draw_diagram_describe_input')]
#[CoversFunction('draw_diagram_expand')]
#[CoversFunction('draw_diagram_export')]
#[CoversFunction('draw_diagram_find_drawio')]
#[CoversFunction('draw_diagram_find_skill')]
#[CoversFunction('draw_diagram_find_source')]
#[CoversFunction('draw_diagram_hash')]
#[CoversFunction('draw_diagram_join_lines')]
#[CoversFunction('draw_diagram_marker')]
#[CoversFunction('draw_diagram_parse_arguments')]
#[CoversFunction('draw_diagram_problems')]
#[CoversFunction('draw_diagram_prompt')]
#[CoversFunction('draw_diagram_read_briefs')]
#[CoversFunction('draw_diagram_resolve_path')]
#[CoversFunction('draw_diagram_select_mode')]
#[CoversFunction('draw_diagram_session_command')]
#[CoversFunction('draw_diagram_shorten')]
#[CoversFunction('draw_diagram_stamp')]
#[CoversFunction('draw_diagram_strip_fallbacks')]
#[CoversFunction('draw_diagram_svg_path')]
#[CoversFunction('draw_diagram_usage_error')]
class DrawDiagramTest extends UnitTestCase {

  /**
   * A diagram fit to export.
   */
  protected const DIAGRAM = '<mxfile><diagram id="page" name="Page"><mxGraphModel background="#ffffff"><root><mxCell id="0"/><mxCell id="1" parent="0"/></root></mxGraphModel></diagram></mxfile>';

  /**
   * An SVG as draw.io exports it.
   */
  protected const SVG = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n<svg xmlns=\"http://www.w3.org/2000/svg\"><rect/></svg>\n";

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/draw-diagram.php';
  }

  public function testShippedDiagramsMatchTheirSources(): void {
    $directories = array_values(array_filter(glob(dirname(__DIR__, 3) . '/docs/content/*', GLOB_ONLYDIR) ?: [], static fn(string $directory): bool => (glob($directory . '/*.drawio') ?: []) !== []));

    $this->assertNotEmpty($directories);
    $this->assertSame([], draw_diagram_check($directories));
  }

  public function testHelpPrintsTheUsage(): void {
    [$exit_code, $output] = $this->runScript(['--help']);

    $this->assertSame(0, $exit_code);
    $this->assertSame(DRAW_DIAGRAM_USAGE, $output);
  }

  public function testCheckPassesMatchingDiagrams(): void {
    $this->writeExportedDiagram('diagrams/flow.drawio');
    $this->writeExportedDiagram('diagrams/layers.drawio');

    [$exit_code, $output] = $this->runScript(['--check', 'diagrams']);

    $this->assertSame(0, $exit_code);
    $this->assertSame('Every SVG matches its source: 2 diagrams checked.' . PHP_EOL, $output);
  }

  public function testCheckCountsSingleDiagram(): void {
    $this->writeExportedDiagram('flow.drawio');

    [$exit_code, $output] = $this->runScript(['--check', 'flow.drawio']);

    $this->assertSame(0, $exit_code);
    $this->assertSame('Every SVG matches its source: 1 diagram checked.' . PHP_EOL, $output);
  }

  public function testCheckFailsDiagramWithoutSvg(): void {
    $this->writeFixture('flow.drawio', static::DIAGRAM);

    [$exit_code, $output] = $this->runScript(['--check', 'flow.drawio']);

    $export = ': export it with: php scripts/draw-diagram.php --export ' . static::$tmp . '/flow.drawio';

    $this->assertSame(1, $exit_code);
    $this->assertSame(
      'Diagrams and their SVGs disagree:' . PHP_EOL . PHP_EOL
      . static::$tmp . '/flow.drawio has no light SVG' . $export . PHP_EOL
      . static::$tmp . '/flow.drawio has no dark SVG' . $export . PHP_EOL,
      $output
    );
  }

  public function testExportExportsEveryDiagram(): void {
    $this->writeFixture('diagrams/flow.drawio', static::DIAGRAM);
    $this->writeFixture('diagrams/layers.drawio', static::DIAGRAM);
    $drawio = $this->writeExecutable('bin/drawio');
    $commands = [];

    [$exit_code, $output] = $this->runScript(['--export', 'diagrams'], ['DRAWIO_BIN' => $drawio], $this->buildRunner($commands));

    $diagrams = static::$tmp . '/diagrams/';
    $images = static::$tmp . '/.artifacts/tmp/diagrams/';

    $this->assertSame(0, $exit_code);
    $this->assertCount(8, $commands);
    $this->assertSame([], draw_diagram_check([static::$tmp . '/diagrams']));
    $this->assertSame(
      'Exported ' . $diagrams . 'flow.svg and ' . $diagrams . 'flow.dark.svg. Review images: ' . $images . 'flow.png and ' . $images . 'flow.dark.png' . PHP_EOL
      . 'Exported ' . $diagrams . 'layers.svg and ' . $diagrams . 'layers.dark.svg. Review images: ' . $images . 'layers.png and ' . $images . 'layers.dark.png' . PHP_EOL,
      $output
    );
  }

  public function testDrawDrawsAndExportsTheDiagram(): void {
    $drawio = $this->writeDrawingTools();
    $commands = [];
    $lines = [
      (string) json_encode(['type' => 'system', 'subtype' => 'init', 'model' => 'claude-test']),
      'not an event',
      (string) json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use', 'name' => 'Write', 'input' => ['file_path' => 'flow.drawio']]]]]),
      (string) json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => FALSE, 'result' => 'Drew 1 box.', 'num_turns' => 3, 'total_cost_usd' => 0.5]),
    ];

    [$exit_code, $output] = $this->runScript(['--brief=brief.md', 'flow.drawio'], ['DRAWIO_BIN' => $drawio], $this->buildRunner($commands, $lines, 'flow.drawio'));

    $images = static::$tmp . '/.artifacts/tmp/diagrams/';

    $this->assertSame(0, $exit_code);
    $this->assertCount(5, $commands);
    $this->assertSame('claude', $commands[0][0]);
    $this->assertStringStartsWith('/drawio-skill Create the draw.io diagram `' . static::$tmp . '/flow.drawio`', end($commands[0]));
    $this->assertSame([], draw_diagram_check([static::$tmp . '/flow.drawio']));
    $this->assertSame(
      'Drawing ' . static::$tmp . '/flow.drawio with /drawio-skill.' . PHP_EOL
      . 'Session started on claude-test.' . PHP_EOL
      . '  > Write: flow.drawio' . PHP_EOL
      . PHP_EOL . 'Drew 1 box.' . PHP_EOL . PHP_EOL . 'Session: 3 turns, $0.50.' . PHP_EOL . PHP_EOL
      . 'Exported ' . static::$tmp . '/flow.svg and ' . static::$tmp . '/flow.dark.svg. Review images: ' . $images . 'flow.png and ' . $images . 'flow.dark.png' . PHP_EOL,
      $output
    );
  }

  public function testDrawFailsWhenTheSessionFails(): void {
    $drawio = $this->writeDrawingTools();
    $commands = [];
    $lines = [(string) json_encode(['type' => 'result', 'subtype' => 'error_max_budget_usd', 'is_error' => TRUE, 'result' => 'Over budget.'])];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The drawing session failed with "error_max_budget_usd" and exit code 0.' . PHP_EOL . 'Over budget.');

    $this->runScript(['--brief=brief.md', 'flow.drawio'], ['DRAWIO_BIN' => $drawio], $this->buildRunner($commands, $lines));
  }

  public function testDrawFailsWhenTheSessionWritesNoDiagram(): void {
    $drawio = $this->writeDrawingTools();
    $commands = [];
    $lines = [(string) json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => FALSE, 'result' => 'Done.'])];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The drawing session did not write ' . static::$tmp . '/flow.drawio.');

    $this->runScript(['--brief=brief.md', 'flow.drawio'], ['DRAWIO_BIN' => $drawio], $this->buildRunner($commands, $lines));
  }

  public function testDrawFailsForMissingDirectory(): void {
    $drawio = $this->writeDrawingTools();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The directory of ' . static::$tmp . '/absent/flow.drawio does not exist.');

    $this->runScript(['--brief=brief.md', 'absent/flow.drawio'], ['DRAWIO_BIN' => $drawio]);
  }

  /**
   * Assert that valid arguments are parsed into the options.
   *
   * @param array<int, string> $arguments
   *   The command line arguments.
   * @param array<string, mixed> $expected
   *   The expected options.
   */
  #[DataProvider('dataProviderParseArguments')]
  public function testParseArguments(array $arguments, array $expected): void {
    $this->assertSame($expected, draw_diagram_parse_arguments($arguments));
  }

  public static function dataProviderParseArguments(): array {
    $defaults = ['mode' => 'draw', 'paths' => [], 'briefs' => [], 'model' => NULL, 'effort' => 'medium', 'budget' => NULL, 'timeout' => 3600];

    return [
      'draw with 2 briefs' => [
        ['--brief=style.md', '--brief=flow.md', 'flow.drawio'],
        array_merge($defaults, ['paths' => ['flow.drawio'], 'briefs' => ['style.md', 'flow.md']]),
      ],
      'draw with every option' => [
        ['--model=opus', '--effort=max', '--budget=2.5', '--timeout=60', '--brief=flow.md', 'flow.drawio'],
        array_merge($defaults, ['paths' => ['flow.drawio'], 'briefs' => ['flow.md'], 'model' => 'opus', 'effort' => 'max', 'budget' => '2.5', 'timeout' => 60]),
      ],
      'a value holding an equals sign' => [
        ['--brief=a=b.md', 'flow.drawio'],
        array_merge($defaults, ['paths' => ['flow.drawio'], 'briefs' => ['a=b.md']]),
      ],
      'export' => [
        ['--export', 'flow.drawio', 'diagrams'],
        array_merge($defaults, ['mode' => 'export', 'paths' => ['flow.drawio', 'diagrams']]),
      ],
      'check' => [
        ['diagrams', '--check'],
        array_merge($defaults, ['mode' => 'check', 'paths' => ['diagrams']]),
      ],
      'help alone' => [
        ['--help'],
        array_merge($defaults, ['mode' => 'help']),
      ],
      'help over every other mode' => [
        ['--export', '--check', '--help', 'flow.svg'],
        array_merge($defaults, ['mode' => 'help', 'paths' => ['flow.svg']]),
      ],
    ];
  }

  /**
   * Assert that a wrong argument is reported, followed by the usage.
   *
   * @param array<int, string> $arguments
   *   The command line arguments.
   * @param string $expected_message
   *   The message expected ahead of the usage.
   */
  #[DataProvider('dataProviderParseArgumentsRejectsTheArguments')]
  public function testParseArgumentsRejectsTheArguments(array $arguments, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message . PHP_EOL . PHP_EOL . DRAW_DIAGRAM_USAGE);

    draw_diagram_parse_arguments($arguments);
  }

  public static function dataProviderParseArgumentsRejectsTheArguments(): array {
    return [
      'an unknown option' => [['--colour', 'flow.drawio'], 'Unknown option "--colour".'],
      'a mode option with a value' => [['--export=yes', 'flow.drawio'], 'Unknown option "--export=yes".'],
      'a value option without a value' => [['--model', 'flow.drawio'], 'The --model option needs a value, as --model=<value>.'],
      'a value option with an empty value' => [['--brief=', 'flow.drawio'], 'The --brief option needs a value, as --brief=<value>.'],
      'a single option repeated' => [['--brief=a.md', '--model=a', '--model=b', 'flow.drawio'], 'The --model option is given more than once.'],
      'both modes' => [['--export', '--check', 'flow.drawio'], 'Choose 1 of --export and --check.'],
      'a mode with a drawing option' => [['--check', '--model=opus', 'flow.drawio'], 'The --check option takes no other option.'],
      'a mode without a path' => [['--export'], 'The --export option needs at least 1 diagram or directory.'],
      'drawing without a diagram' => [['--brief=a.md'], 'Drawing takes exactly 1 diagram.'],
      'drawing 2 diagrams' => [['--brief=a.md', 'a.drawio', 'b.drawio'], 'Drawing takes exactly 1 diagram.'],
      'drawing without a brief' => [['flow.drawio'], 'Drawing needs at least 1 --brief.'],
      'drawing a file that is not a diagram' => [['--brief=a.md', 'flow.svg'], 'The diagram flow.svg does not end in ".drawio".'],
      'an unknown effort' => [['--brief=a.md', '--effort=huge', 'flow.drawio'], 'The --effort option takes 1 of low, medium, high, xhigh, max, not "huge".'],
      'a budget that is not a number' => [['--brief=a.md', '--budget=lots', 'flow.drawio'], 'The --budget option takes a positive amount in US dollars, not "lots".'],
      'a budget of 0' => [['--brief=a.md', '--budget=0', 'flow.drawio'], 'The --budget option takes a positive amount in US dollars, not "0".'],
      'a negative budget' => [['--brief=a.md', '--budget=-1', 'flow.drawio'], 'The --budget option takes a positive amount in US dollars, not "-1".'],
      'a timeout that is not a number' => [['--brief=a.md', '--timeout=soon', 'flow.drawio'], 'The --timeout option takes a positive number of seconds, not "soon".'],
      'a timeout of 0' => [['--brief=a.md', '--timeout=0', 'flow.drawio'], 'The --timeout option takes a positive number of seconds, not "0".'],
      'a fractional timeout' => [['--brief=a.md', '--timeout=1.5', 'flow.drawio'], 'The --timeout option takes a positive number of seconds, not "1.5".'],
    ];
  }

  /**
   * Assert that a path is resolved against the directory unless absolute.
   *
   * @param string $path
   *   The path as given.
   * @param string $directory
   *   The directory to resolve against.
   * @param string $expected
   *   The expected path.
   */
  #[DataProvider('dataProviderResolvePath')]
  public function testResolvePath(string $path, string $directory, string $expected): void {
    $this->assertSame($expected, draw_diagram_resolve_path($path, $directory));
  }

  public static function dataProviderResolvePath(): array {
    return [
      'absolute path' => ['/app/flow.drawio', '/work', '/app/flow.drawio'],
      'relative path' => ['docs/flow.drawio', '/work', '/work/docs/flow.drawio'],
      'directory with a trailing slash' => ['flow.drawio', '/work/', '/work/flow.drawio'],
    ];
  }

  public function testExpandKeepsFilesAndListsDirectories(): void {
    $this->writeFixture('diagrams/b.drawio', static::DIAGRAM);
    $this->writeFixture('diagrams/a.drawio', static::DIAGRAM);
    $this->writeFixture('diagrams/a.svg', static::SVG);

    $expanded = draw_diagram_expand([static::$tmp . '/absent.drawio', static::$tmp . '/diagrams']);

    $this->assertSame([static::$tmp . '/absent.drawio', static::$tmp . '/diagrams/a.drawio', static::$tmp . '/diagrams/b.drawio'], $expanded);
  }

  /**
   * Assert that a path naming no diagram is rejected.
   *
   * @param string $path
   *   The path, relative to the workspace.
   */
  #[DataProvider('dataProviderExpandRejectsThePath')]
  public function testExpandRejectsThePath(string $path): void {
    $this->writeFixture('empty/flow.svg', static::SVG);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(static::$tmp . '/' . $path . ' is neither a ".drawio" file nor a directory holding 1.');

    draw_diagram_expand([static::$tmp . '/' . $path]);
  }

  public static function dataProviderExpandRejectsThePath(): array {
    return [
      'a missing directory' => ['absent'],
      'a directory without diagrams' => ['empty'],
      'a file that is not a diagram' => ['empty/flow.svg'],
    ];
  }

  public function testSvgPathSitsBesideTheDiagram(): void {
    $this->assertSame('/docs/flow.svg', draw_diagram_svg_path('/docs/flow.drawio'));
    $this->assertSame('/docs/flow.svg', draw_diagram_svg_path('/docs/flow.drawio', 'light'));
    $this->assertSame('/docs/flow.dark.svg', draw_diagram_svg_path('/docs/flow.drawio', 'dark'));
  }

  /**
   * Assert that an SVG is matched to the diagram it is exported from.
   *
   * @param array<int, string> $diagrams
   *   Diagrams to write, relative to the workspace.
   * @param string $svg
   *   The SVG, relative to the workspace.
   * @param string|null $expected
   *   The expected diagram, relative to the workspace, or NULL for none.
   */
  #[DataProvider('dataProviderFindSource')]
  public function testFindSource(array $diagrams, string $svg, ?string $expected): void {
    foreach ($diagrams as $diagram) {
      $this->writeFixture($diagram, static::DIAGRAM);
    }

    $this->assertSame($expected === NULL ? NULL : static::$tmp . '/' . $expected, draw_diagram_find_source(static::$tmp . '/' . $svg));
  }

  public static function dataProviderFindSource(): array {
    return [
      'a light export' => [['d/flow.drawio'], 'd/flow.svg', 'd/flow.drawio'],
      'a dark export' => [['d/flow.drawio'], 'd/flow.dark.svg', 'd/flow.drawio'],
      'an SVG with no diagram' => [[], 'd/logo.svg', NULL],
      'a dark SVG with no diagram' => [[], 'd/logo.dark.svg', NULL],
      'the light export of a diagram named like a dark export' => [['d/night.dark.drawio', 'd/night.drawio'], 'd/night.dark.svg', 'd/night.dark.drawio'],
      'the dark export of the diagram beside it' => [['d/night.drawio'], 'd/night.dark.svg', 'd/night.drawio'],
      'a file that is not an SVG' => [['d/flow.drawio'], 'd/flow.png', NULL],
    ];
  }

  public function testFindSkillPrefersTheProjectSkill(): void {
    $this->writeFixture('root/.claude/skills/drawio-skill/SKILL.md', 'Project skill');
    $this->writeFixture('home/.claude/skills/drawio-skill/SKILL.md', 'Home skill');

    $this->assertSame(realpath(static::$tmp . '/root/.claude/skills/drawio-skill'), draw_diagram_find_skill(static::$tmp . '/root', ['HOME' => static::$tmp . '/home']));
  }

  public function testFindSkillResolvesTheHomeSkillLink(): void {
    $this->writeFixture('home/.agents/skills/drawio-skill/SKILL.md', 'Home skill');
    mkdir(static::$tmp . '/home/.claude/skills', 0777, TRUE);
    symlink(static::$tmp . '/home/.agents/skills/drawio-skill', static::$tmp . '/home/.claude/skills/drawio-skill');

    $this->assertSame(realpath(static::$tmp . '/home/.agents/skills/drawio-skill'), draw_diagram_find_skill(static::$tmp . '/root', ['HOME' => static::$tmp . '/home']));
  }

  public function testFindSkillSkipsDirectoryWithoutSkill(): void {
    $this->writeFixture('root/.claude/skills/drawio-skill/README.md', 'No skill here');
    $this->writeFixture('home/.claude/skills/drawio-skill/SKILL.md', 'Home skill');

    $this->assertSame(realpath(static::$tmp . '/home/.claude/skills/drawio-skill'), draw_diagram_find_skill(static::$tmp . '/root', ['HOME' => static::$tmp . '/home']));
  }

  /**
   * Assert that a missing skill is reported with how to install it.
   *
   * @param array<string, string> $environment
   *   The environment variables.
   * @param string $expected_places
   *   The places the message is expected to name.
   */
  #[DataProvider('dataProviderFindSkillReportsTheMissingSkill')]
  public function testFindSkillReportsTheMissingSkill(array $environment, string $expected_places): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The drawio-skill skill is not installed: no SKILL.md in %s. Install it with "npx skills add Agents365-ai/drawio-skill -g". Its source: https://github.com/Agents365-ai/drawio-skill', $expected_places));

    draw_diagram_find_skill('/root', $environment);
  }

  public static function dataProviderFindSkillReportsTheMissingSkill(): array {
    return [
      'with a home directory' => [['HOME' => '/home'], '/root/.claude/skills/drawio-skill or /home/.claude/skills/drawio-skill'],
      'without a home directory' => [[], '/root/.claude/skills/drawio-skill'],
    ];
  }

  public function testFindDrawioTakesTheConfiguredBinary(): void {
    $drawio = $this->writeExecutable('bin/draw.io');

    $this->assertSame($drawio, draw_diagram_find_drawio(['DRAWIO_BIN' => $drawio, 'PATH' => static::$tmp . '/other']));
  }

  /**
   * Assert that a configured binary that cannot run is reported.
   *
   * @param string $path
   *   The configured path, relative to the workspace.
   */
  #[DataProvider('dataProviderFindDrawioRejectsTheConfiguredBinary')]
  public function testFindDrawioRejectsTheConfiguredBinary(string $path): void {
    $this->writeFixture('bin/drawio', 'Not executable');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('DRAWIO_BIN names %s/%s, which is not an executable file.', static::$tmp, $path));

    draw_diagram_find_drawio(['DRAWIO_BIN' => static::$tmp . '/' . $path]);
  }

  public static function dataProviderFindDrawioRejectsTheConfiguredBinary(): array {
    return [
      'a missing file' => ['bin/absent'],
      'a file that is not executable' => ['bin/drawio'],
      'a directory' => ['bin'],
    ];
  }

  public function testFindDrawioSearchesThePath(): void {
    $this->writeFixture('first/drawio', 'Not executable');
    $drawio = $this->writeExecutable('second/drawio');
    $path = implode(PATH_SEPARATOR, ['', static::$tmp . '/absent', static::$tmp . '/first', static::$tmp . '/second/']);

    $this->assertSame($drawio, draw_diagram_find_drawio(['PATH' => $path], [static::$tmp . '/fallback']));
  }

  public function testFindDrawioFallsBackOutsideThePath(): void {
    $drawio = $this->writeExecutable('app/draw.io');

    $this->assertSame($drawio, draw_diagram_find_drawio(['PATH' => static::$tmp . '/empty'], [static::$tmp . '/absent', $drawio]));
  }

  public function testFindDrawioReportsTheMissingBinary(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('draw.io desktop is not installed: no "drawio" on the PATH and none at /a/draw.io, /b/draw.io. Install it with "brew install --cask drawio" on macOS or from https://github.com/jgraph/drawio-desktop/releases, or set DRAWIO_BIN to its binary.');

    draw_diagram_find_drawio([], ['/a/draw.io', '/b/draw.io']);
  }

  public function testReadBriefsJoinsThemInOrder(): void {
    $this->writeFixture('style.md', "\n# Style\n\nWhite background.\n\n");
    $this->writeFixture('briefs/flow.md', "# Flow\n");

    $this->assertSame("# Style\n\nWhite background.\n\n# Flow", draw_diagram_read_briefs(['style.md', static::$tmp . '/briefs/flow.md'], static::$tmp));
  }

  /**
   * Assert that a brief with nothing to read is reported.
   *
   * @param string $file
   *   The brief, relative to the workspace.
   */
  #[DataProvider('dataProviderReadBriefsRejectsTheBrief')]
  public function testReadBriefsRejectsTheBrief(string $file): void {
    $this->writeFixture('blank.md', " \n\n ");

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The brief %s/%s is missing or empty.', static::$tmp, $file));

    draw_diagram_read_briefs([$file], static::$tmp);
  }

  public static function dataProviderReadBriefsRejectsTheBrief(): array {
    return [
      'a missing brief' => ['absent.md'],
      'a blank brief' => ['blank.md'],
    ];
  }

  public function testPromptCreatesMissingDiagram(): void {
    $prompt = draw_diagram_prompt('/docs/flow.drawio', 'Draw a box.', '/skills/drawio-skill', '/bin/drawio', '/tmp/diagrams');

    $this->assertStringStartsWith('/drawio-skill Create the draw.io diagram `/docs/flow.drawio` from the brief below.' . "\n", $prompt);
    $this->assertStringContainsString('- `/docs/flow.drawio` does not exist yet: draw it from scratch with a PHP generator in `/tmp/diagrams`.', $prompt);
    $this->assertStringContainsString('- Keep every response short.', $prompt);
    $this->assertStringContainsString('run the generator as `php /tmp/diagrams/<file>.php`', $prompt);
    $this->assertStringContainsString('- Run only the validator, a PHP file in `/tmp/diagrams` and the draw.io export', $prompt);
    $this->assertStringContainsString('`python3 /skills/drawio-skill/scripts/validate.py /docs/flow.drawio --score`', $prompt);
    $this->assertStringContainsString('`/bin/drawio --export --format png --width 2000 --output /tmp/diagrams/draft.png /docs/flow.drawio`', $prompt);
    $this->assertStringEndsWith("## Brief\n\nDraw a box.", $prompt);
    $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', $prompt);
  }

  public function testPromptUpdatesAnExistingDiagram(): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);

    $prompt = draw_diagram_prompt($diagram, 'Add a box.', '/skills/drawio-skill', '/bin/drawio', '/tmp/diagrams');

    $this->assertStringStartsWith('/drawio-skill Update the draw.io diagram `' . $diagram . '`', $prompt);
    $this->assertStringContainsString('- `' . $diagram . '` exists: edit it in place, keep the geometry and style of every cell the brief leaves alone, and delete only what the brief removes.', $prompt);
    $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', $prompt);
  }

  /**
   * Assert that the session command carries every option before the prompt.
   *
   * @param string|null $model
   *   The model of the session.
   * @param string|null $budget
   *   The spending cap of the session.
   * @param array<int, string> $expected_options
   *   The options expected after the fixed ones.
   */
  #[DataProvider('dataProviderSessionCommand')]
  public function testSessionCommand(?string $model, ?string $budget, array $expected_options): void {
    $expected = [
      'claude',
      '--print',
      '--output-format=stream-json',
      '--verbose',
      '--no-session-persistence',
      '--permission-mode=acceptEdits',
      '--permission-prompts=none',
      '--add-dir=/skills/drawio-skill',
      '--allowedTools=Read,Write,Edit,Glob,Grep,Bash(python3 /skills/drawio-skill/scripts/validate.py *),Bash(php /scratch/flow/*),Bash(/bin/drawio --export *)',
      '--effort=medium',
      ...$expected_options,
      'Draw a box.',
    ];

    $this->assertSame($expected, draw_diagram_session_command('Draw a box.', '/skills/drawio-skill', '/bin/drawio', '/scratch/flow', $model, 'medium', $budget));
  }

  public static function dataProviderSessionCommand(): array {
    return [
      'the configured model without a cap' => [NULL, NULL, []],
      'a model and a cap' => ['opus', '2.5', ['--model=opus', '--max-budget-usd=2.5']],
    ];
  }

  /**
   * Assert that an event of the session's stream is described as progress.
   *
   * @param array<mixed> $event
   *   The decoded event.
   * @param array<int, string> $expected
   *   The expected progress lines.
   */
  #[DataProvider('dataProviderDescribeEvent')]
  public function testDescribeEvent(array $event, array $expected): void {
    $this->assertSame($expected, draw_diagram_describe_event($event));
  }

  public static function dataProviderDescribeEvent(): array {
    return [
      'the start, naming its model' => [['type' => 'system', 'subtype' => 'init', 'model' => 'claude-test'], ['Session started on claude-test.']],
      'the start, naming no model' => [['type' => 'system', 'subtype' => 'init'], ['Session started on the configured model.']],
      'another system event' => [['type' => 'system', 'subtype' => 'status'], []],
      'reasoning reaching a step' => [
        ['type' => 'system', 'subtype' => 'thinking_tokens', 'estimated_tokens' => 20400, 'estimated_tokens_delta' => 800],
        ['  Reasoning: about 20000 tokens so far in this response.'],
      ],
      'reasoning within a step' => [['type' => 'system', 'subtype' => 'thinking_tokens', 'estimated_tokens' => 20400, 'estimated_tokens_delta' => 300], []],
      'reasoning without an estimate' => [['type' => 'system', 'subtype' => 'thinking_tokens'], []],
      'a retried API call' => [
        ['type' => 'system', 'subtype' => 'api_retry', 'attempt' => 2, 'max_retries' => 10, 'retry_delay_ms' => 3500, 'error_status' => 529, 'error' => 'overloaded_error'],
        ['  The API call failed with 529 overloaded_error: retry 2 of 10 in 4 seconds.'],
      ],
      'a retried API call naming no reason' => [
        ['type' => 'system', 'subtype' => 'api_retry'],
        ['  The API call failed with no reason given: retry 0 of 0 in 0 seconds.'],
      ],
      'text' => [['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => "\nDrawing the layers.\nThen the edges."]]]], ['  Drawing the layers.']],
      'blank text' => [['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => " \n "]]]], []],
      'a tool call' => [['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use', 'name' => 'Bash', 'input' => ['command' => 'ls']]]]], ['  > Bash: ls']],
      'a tool call naming nothing' => [['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use']]]], ['  > tool: ']],
      'text and a tool call' => [
        ['type' => 'assistant', 'message' => ['content' => ['not a block', ['type' => 'text', 'text' => 'Validating.'], ['type' => 'tool_use', 'name' => 'Read', 'input' => ['file_path' => 'flow.png']]]]],
        ['  Validating.', '  > Read: flow.png'],
      ],
      'a message without content' => [['type' => 'assistant', 'message' => ['content' => 'text']], []],
      'an assistant event without a message' => [['type' => 'assistant'], []],
      'a tool result' => [['type' => 'user', 'message' => ['content' => [['type' => 'tool_result', 'content' => 'ok']]]], []],
      'the result' => [['type' => 'result', 'subtype' => 'success'], []],
    ];
  }

  /**
   * Assert that a tool call's input is described by its most telling value.
   *
   * @param mixed $input
   *   The decoded input.
   * @param string $expected
   *   The expected description.
   */
  #[DataProvider('dataProviderDescribeInput')]
  public function testDescribeInput(mixed $input, string $expected): void {
    $this->assertSame($expected, draw_diagram_describe_input($input));
  }

  public static function dataProviderDescribeInput(): array {
    return [
      'no input' => [NULL, ''],
      'a command' => [['command' => 'ls -la', 'description' => 'List'], 'ls -la'],
      'a file path' => [['file_path' => '/docs/flow.drawio', 'content' => '<mxfile/>'], '/docs/flow.drawio'],
      'a pattern' => [['pattern' => 'src/**/*.php'], 'src/**/*.php'],
      'a path' => [['path' => '/docs'], '/docs'],
      'a value that is not text' => [['command' => ['ls']], '{"command":["ls"]}'],
      'anything else' => [['url' => 'https://example.com/a'], '{"url":"https://example.com/a"}'],
    ];
  }

  /**
   * Assert that a text is shortened to its first line that is not blank.
   *
   * @param string $text
   *   The text.
   * @param string $expected
   *   The expected line.
   */
  #[DataProvider('dataProviderShorten')]
  public function testShorten(string $text, string $expected): void {
    $this->assertSame($expected, draw_diagram_shorten($text, 10));
  }

  public static function dataProviderShorten(): array {
    return [
      'nothing' => ['', ''],
      'blank lines' => [" \n\t\n", ''],
      'the first line that is not blank' => ["\n  Short.  \nMore.", 'Short.'],
      'a line at the length' => ['0123456789', '0123456789'],
      'a line over the length' => ['0123456789A', '0123456...'],
      'multibyte characters' => ['ÄÖÜäöüßÄÖÜäöü', 'ÄÖÜäöüß...'],
    ];
  }

  /**
   * Assert that a successful session is reported with its usage.
   *
   * @param array<mixed> $result
   *   The session's result event.
   * @param string $expected
   *   The expected report.
   */
  #[DataProvider('dataProviderAssertSessionReportsTheResult')]
  public function testAssertSessionReportsTheResult(array $result, string $expected): void {
    $run = ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];

    $this->assertSame($expected, draw_diagram_assert_session($result, $run, 60));
  }

  public static function dataProviderAssertSessionReportsTheResult(): array {
    $success = ['type' => 'result', 'subtype' => 'success', 'is_error' => FALSE];

    return [
      'turns and a cost' => [['result' => " Drew it.\n", 'num_turns' => 12, 'total_cost_usd' => 1.234] + $success, 'Drew it.' . PHP_EOL . PHP_EOL . 'Session: 12 turns, $1.23.'],
      'a whole cost' => [['result' => 'Drew it.', 'total_cost_usd' => 2] + $success, 'Drew it.' . PHP_EOL . PHP_EOL . 'Session: $2.00.'],
      'no usage' => [['result' => 'Drew it.', 'num_turns' => 'many'] + $success, 'Drew it.'],
      'no report' => [$success, ''],
    ];
  }

  /**
   * Assert that a failed session is reported with what it said.
   *
   * @param array<mixed>|null $result
   *   The session's result event, or NULL when it sent none.
   * @param array{exit_code: int, output: string, errors: string, is_timed_out: bool} $run
   *   What running the session returned.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderAssertSessionRejectsTheSession')]
  public function testAssertSessionRejectsTheSession(?array $result, array $run, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    draw_diagram_assert_session($result, $run, 60);
  }

  public static function dataProviderAssertSessionRejectsTheSession(): array {
    $run = ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];
    $success = ['type' => 'result', 'subtype' => 'success', 'is_error' => FALSE, 'result' => 'Drew it.'];

    return [
      'a timeout' => [$success, ['is_timed_out' => TRUE] + $run, 'The drawing session ran longer than 60 seconds and was stopped. Raise --timeout or narrow the brief.'],
      'no result' => [NULL, ['exit_code' => 1, 'errors' => "Not logged in.\n"] + $run, 'The drawing session sent no result and exited with code 1.' . PHP_EOL . 'Not logged in.'],
      'no result and no errors' => [NULL, ['exit_code' => 2] + $run, 'The drawing session sent no result and exited with code 2.'],
      'an error result' => [['is_error' => TRUE, 'subtype' => 'error_during_execution'] + $success, $run, 'The drawing session failed with "error_during_execution" and exit code 0.' . PHP_EOL . 'Drew it.'],
      'a result that is not a success' => [['subtype' => 'error_max_turns'] + $success, $run, 'The drawing session failed with "error_max_turns" and exit code 0.'],
      'a result without a subtype' => [['type' => 'result'], $run, 'The drawing session failed with "unknown" and exit code 0.'],
      'a success that exited with an error' => [$success, ['exit_code' => 1, 'errors' => 'Crashed.'] + $run, 'The drawing session failed with "success" and exit code 1.' . PHP_EOL . 'Drew it.' . PHP_EOL . 'Crashed.'],
    ];
  }

  /**
   * Assert that a diagram is checked for what keeps it from being exported.
   *
   * @param string $contents
   *   The diagram's contents.
   * @param array<int, string> $expected
   *   The expected problems, with ':file' for the diagram's path.
   */
  #[DataProvider('dataProviderProblems')]
  public function testProblems(string $contents, array $expected): void {
    $diagram = $this->writeFixture('flow.drawio', $contents);

    $this->assertSame(str_replace(':file', $diagram, $expected), draw_diagram_problems($diagram));
  }

  public static function dataProviderProblems(): array {
    $not_drawio = [':file is not a draw.io file: it holds no <mxfile> with a <diagram>.'];
    $no_background = [':file draws no background, so its SVG is transparent and unreadable on a dark page: set background="#ffffff" on <mxGraphModel>.'];

    return [
      'a diagram fit to export' => [static::DIAGRAM, []],
      'a background in capitals' => [str_replace('#ffffff', '#FFFFFF', static::DIAGRAM), []],
      'an empty file' => ['', $not_drawio],
      'broken XML' => ['<mxfile><diagram>', $not_drawio],
      'another root' => ['<svg><diagram/></svg>', $not_drawio],
      'no page' => ['<mxfile/>', $not_drawio],
      '2 pages' => ['<mxfile><diagram id="a"/><diagram id="b"/></mxfile>', [':file holds 2 pages, and its SVG shows only the first: keep 1 page per file.']],
      'a compressed page' => ['<mxfile><diagram id="a">7VpNc5swEP01XJ0hTJx7tJ</diagram></mxfile>', [':file stores its page compressed: save it as uncompressed XML, so its diff can be read.']],
      'no background' => [str_replace(' background="#ffffff"', '', static::DIAGRAM), $no_background],
      'no background color' => [str_replace('#ffffff', 'none', static::DIAGRAM), $no_background],
    ];
  }

  public function testAssertWrittenPassesFreshDiagram(): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);

    draw_diagram_assert_written($diagram, time() - 60);

    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a diagram the session failed to write is reported.
   *
   * @param string $contents
   *   The diagram's contents, or an empty string for no diagram.
   * @param int $offset
   *   Seconds from now the session started.
   * @param string $expected_message
   *   The expected message, with ':file' for the diagram's path.
   */
  #[DataProvider('dataProviderAssertWrittenRejectsTheDiagram')]
  public function testAssertWrittenRejectsTheDiagram(string $contents, int $offset, string $expected_message): void {
    $diagram = $contents === '' ? static::$tmp . '/flow.drawio' : $this->writeFixture('flow.drawio', $contents);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace(':file', $diagram, $expected_message));

    draw_diagram_assert_written($diagram, time() + $offset);
  }

  public static function dataProviderAssertWrittenRejectsTheDiagram(): array {
    return [
      'no diagram' => ['', -60, 'The drawing session did not write :file.'],
      'an unchanged diagram' => [static::DIAGRAM, 60, 'The drawing session left :file unchanged.'],
      'a diagram unfit to export' => ['<mxfile/>', -60, ':file is not a draw.io file: it holds no <mxfile> with a <diagram>.'],
    ];
  }

  public function testExportWritesTheSvgsAndTheReviewImages(): void {
    $diagram = $this->writeFixture('docs/flow.drawio', static::DIAGRAM);
    $commands = [];

    $pngs = draw_diagram_export($diagram, '/bin/drawio', static::$tmp, $this->buildRunner($commands));

    $png = static::$tmp . '/.artifacts/tmp/diagrams/flow.png';
    $dark_png = static::$tmp . '/.artifacts/tmp/diagrams/flow.dark.png';
    $this->assertSame([$png, $dark_png], $pngs);
    $this->assertFileExists($png);
    $this->assertFileExists($dark_png);
    $this->assertSame([
      ['/bin/drawio', '--export', '--border', '16', '--theme', 'light', '--format', 'svg', '--embed-svg-images', '--output', static::$tmp . '/docs/flow.svg', $diagram],
      ['/bin/drawio', '--export', '--border', '16', '--theme', 'light', '--format', 'png', '--width', '2000', '--output', $png, $diagram],
      ['/bin/drawio', '--export', '--border', '16', '--theme', 'dark', '--format', 'svg', '--embed-svg-images', '--output', static::$tmp . '/docs/flow.dark.svg', $diagram],
      ['/bin/drawio', '--export', '--border', '16', '--theme', 'dark', '--format', 'png', '--width', '2000', '--output', $dark_png, $diagram],
    ], $commands);
    $this->assertSame([], draw_diagram_check([$diagram]));
  }

  /**
   * Assert that a diagram that cannot be exported is reported.
   *
   * @param string $contents
   *   The diagram's contents, or an empty string for no diagram.
   * @param string $expected_message
   *   The expected message, with ':file' for the diagram's path.
   */
  #[DataProvider('dataProviderExportRejectsTheDiagram')]
  public function testExportRejectsTheDiagram(string $contents, string $expected_message): void {
    $diagram = $contents === '' ? static::$tmp . '/flow.drawio' : $this->writeFixture('flow.drawio', $contents);
    $commands = [];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace(':file', $diagram, $expected_message));

    draw_diagram_export($diagram, '/bin/drawio', static::$tmp, $this->buildRunner($commands));
  }

  public static function dataProviderExportRejectsTheDiagram(): array {
    return [
      'no diagram' => ['', ':file does not exist.'],
      'a diagram unfit to export' => ['<mxfile/>', ':file is not a draw.io file: it holds no <mxfile> with a <diagram>.'],
    ];
  }

  /**
   * Assert that a failed export is reported with what draw.io said.
   *
   * @param array{exit_code: int, output: string, errors: string, is_timed_out: bool} $run
   *   What running the export returns.
   * @param bool $is_written
   *   Whether the export writes its file.
   * @param string $expected_message
   *   The expected message, with ':svg' for the SVG's path.
   */
  #[DataProvider('dataProviderExportReportsTheFailedExport')]
  public function testExportReportsTheFailedExport(array $run, bool $is_written, string $expected_message): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);
    $runner = function (array $command) use ($run, $is_written): array {
      if ($is_written) {
        $this->writeFixture($this->findOutput($command), static::SVG);
      }

      return $run;
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace(':svg', static::$tmp . '/flow.svg', $expected_message));

    draw_diagram_export($diagram, '/bin/drawio', static::$tmp, $runner);
  }

  public static function dataProviderExportReportsTheFailedExport(): array {
    $run = ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];

    return [
      'an exit code' => [['exit_code' => 1, 'output' => "Exporting.\n", 'errors' => 'Error: no display'] + $run, TRUE, 'draw.io could not export :svg.' . PHP_EOL . 'Exporting.' . PHP_EOL . 'Error: no display'],
      'a timeout' => [['is_timed_out' => TRUE] + $run, TRUE, 'draw.io could not export :svg.'],
      'no file' => [$run, FALSE, 'draw.io could not export :svg.'],
    ];
  }

  /**
   * Assert that an export in a theme draw.io ignored is reported.
   *
   * @param string $svg
   *   The SVG draw.io writes for every export.
   * @param string $expected_message
   *   The expected message, with ':tmp' for the workspace.
   */
  #[DataProvider('dataProviderExportReportsAnIgnoredTheme')]
  public function testExportReportsAnIgnoredTheme(string $svg, string $expected_message): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);
    $runner = function (array $command) use ($svg): array {
      $this->writeFixture($this->findOutput($command), $svg);

      return ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace(':tmp', static::$tmp, $expected_message));

    draw_diagram_export($diagram, '/bin/drawio', static::$tmp, $runner);
  }

  public static function dataProviderExportReportsAnIgnoredTheme(): array {
    return [
      'no color scheme' => [static::SVG, 'draw.io ignored "--theme light" when it exported :tmp/flow.svg. Update draw.io desktop to a release that supports "--theme".'],
      'the light color scheme for both' => [str_replace('<svg ', '<svg style="color-scheme: light;" ', static::SVG), 'draw.io ignored "--theme dark" when it exported :tmp/flow.dark.svg. Update draw.io desktop to a release that supports "--theme".'],
    ];
  }

  public function testExportReportsAnSvgWithoutSvgElement(): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);
    $runner = function (array $command): array {
      $this->writeFixture($this->findOutput($command), 'Not an SVG');

      return ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(static::$tmp . '/flow.svg holds no <svg> element to record its source in.');

    draw_diagram_export($diagram, '/bin/drawio', static::$tmp, $runner);
  }

  /**
   * Assert that the raster copies of the labels are removed from an SVG.
   *
   * @param string $contents
   *   The SVG.
   * @param string $expected
   *   The expected SVG.
   */
  #[DataProvider('dataProviderStripFallbacks')]
  public function testStripFallbacks(string $contents, string $expected): void {
    $this->assertSame($expected, draw_diagram_strip_fallbacks($contents));
  }

  public static function dataProviderStripFallbacks(): array {
    $label = '<switch><foreignObject width="100%%"><div>Name</div></foreignObject>%s</switch>';
    $shared = '<symbol id="mx-image-shared" viewBox="0 0 1 1"><image href="data:image/png;base64,AAAA"/></symbol>';
    $kept = '<symbol id="mx-image-kept" viewBox="0 0 1 1"><image href="data:image/png;base64,BBBB"/></symbol>';

    return [
      'a label without a copy' => [sprintf($label, ''), sprintf($label, '')],
      'an inline copy' => [sprintf($label, '<image x="1" y="2" xlink:href="data:image/png;base64,iVBORw0KGgo/+="/>'), sprintf($label, '')],
      'a shared copy' => [
        '<defs>' . $shared . '</defs>' . sprintf($label, '<use x="1" href="#mx-image-shared" xlink:href="#mx-image-shared"/>'),
        '<defs></defs>' . sprintf($label, ''),
      ],
      'a shared image a shape still uses' => [
        '<defs>' . $kept . '</defs><use href="#mx-image-kept"/>' . sprintf($label, '<use href="#mx-image-kept"/>'),
        '<defs>' . $kept . '</defs><use href="#mx-image-kept"/>' . sprintf($label, ''),
      ],
      'an image that is not a copy' => ['<g><image href="data:image/png;base64,CCCC"/></g>', '<g><image href="data:image/png;base64,CCCC"/></g>'],
    ];
  }

  public function testCreateDirectoryCreatesItsParents(): void {
    draw_diagram_create_directory(static::$tmp . '/a/b/c');
    draw_diagram_create_directory(static::$tmp . '/a/b/c');

    $this->assertDirectoryExists(static::$tmp . '/a/b/c');
  }

  public function testCreateDirectoryReportsTheFailure(): void {
    $this->writeFixture('file', 'A file, not a directory');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not create ' . static::$tmp . '/file/directory.');

    $this->runWithoutWarnings(static function (): void {
      draw_diagram_create_directory(static::$tmp . '/file/directory');
    });
  }

  public function testHashIgnoresTheLineEndings(): void {
    $this->assertSame(draw_diagram_hash("<mxfile>\n</mxfile>\n"), draw_diagram_hash("<mxfile>\r\n</mxfile>\r\n"));
    $this->assertNotSame(draw_diagram_hash('<mxfile/>'), draw_diagram_hash('<mxfile />'));
  }

  public function testMarkerNamesTheSourceAndItsHash(): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);

    $this->assertSame('<!-- Exported by scripts/draw-diagram.php from flow.drawio with sha256 ' . hash('sha256', static::DIAGRAM) . '. -->', draw_diagram_marker($diagram));
  }

  public function testStampRecordsTheSourceOnce(): void {
    $diagram = $this->writeFixture('flow.drawio', static::DIAGRAM);
    $svg = $this->writeFixture('flow.svg', static::SVG);

    draw_diagram_stamp($svg, $diagram);
    draw_diagram_stamp($svg, $diagram);

    $expected = str_replace('<svg ', draw_diagram_marker($diagram) . "\n<svg ", static::SVG);
    $this->assertSame($expected, file_get_contents($svg));
  }

  /**
   * Assert that every disagreement between a diagram and its SVGs is found.
   *
   * @param array<string, string> $files
   *   Files to write, keyed by path relative to the workspace, with
   *   ':stamp(<diagram>)' standing for the marker of that diagram.
   * @param array<int, string> $paths
   *   The paths to check, relative to the workspace.
   * @param array<int, string> $expected
   *   The expected violations, with ':tmp' for the workspace.
   */
  #[DataProvider('dataProviderCheck')]
  public function testCheck(array $files, array $paths, array $expected): void {
    // A marker hashes its diagram, so the diagrams go first.
    foreach (array_filter($files, static fn(string $path): bool => str_ends_with($path, '.drawio'), ARRAY_FILTER_USE_KEY) as $path => $contents) {
      $this->writeFixture($path, $contents);
    }

    foreach (array_filter($files, static fn(string $path): bool => !str_ends_with($path, '.drawio'), ARRAY_FILTER_USE_KEY) as $path => $contents) {
      $this->writeFixture($path, (string) preg_replace_callback('/:stamp\(([^)]+)\)/', static fn(array $matches): string => draw_diagram_marker(static::$tmp . '/' . $matches[1]), $contents));
    }

    $absolute_paths = array_map(static fn(string $path): string => static::$tmp . '/' . $path, $paths);

    $this->assertSame(str_replace(':tmp', static::$tmp, $expected), draw_diagram_check($absolute_paths));
  }

  public static function dataProviderCheck(): array {
    $stamped = static fn(string $diagram): string => str_replace('<svg ', ':stamp(' . $diagram . ")\n<svg ", static::SVG);
    $marked = static fn(string $diagram, string $contents): string => str_replace('<svg ', '<!-- Exported by scripts/draw-diagram.php from ' . $diagram . ' with sha256 ' . hash('sha256', $contents) . ". -->\n<svg ", static::SVG);
    $export = ': export it with: php scripts/draw-diagram.php --export :tmp/d/flow.drawio';
    $exported = ['d/flow.drawio' => static::DIAGRAM, 'd/flow.svg' => $stamped('d/flow.drawio'), 'd/flow.dark.svg' => $stamped('d/flow.drawio')];

    return [
      'a diagram and both its SVGs' => [$exported, ['d'], []],
      'a missing diagram' => [[], ['d/flow.drawio'], [':tmp/d/flow.drawio does not exist.']],
      'a diagram unfit to export' => [
        ['d/flow.drawio' => '<mxfile/>'] + $exported,
        ['d/flow.drawio'],
        [':tmp/d/flow.drawio is not a draw.io file: it holds no <mxfile> with a <diagram>.'],
      ],
      'a diagram without SVGs' => [['d/flow.drawio' => static::DIAGRAM], ['d'], [':tmp/d/flow.drawio has no light SVG' . $export, ':tmp/d/flow.drawio has no dark SVG' . $export]],
      'a diagram without its dark SVG' => [
        ['d/flow.drawio' => static::DIAGRAM, 'd/flow.svg' => $stamped('d/flow.drawio')],
        ['d'],
        [':tmp/d/flow.drawio has no dark SVG' . $export],
      ],
      'an SVG exported elsewhere' => [['d/flow.svg' => static::SVG] + $exported, ['d'], [':tmp/d/flow.svg was not exported by scripts/draw-diagram.php' . $export]],
      'a dark SVG exported elsewhere' => [['d/flow.dark.svg' => static::SVG] + $exported, ['d'], [':tmp/d/flow.dark.svg was not exported by scripts/draw-diagram.php' . $export]],
      'an SVG exported from another diagram' => [
        ['d/flow.svg' => $marked('other.drawio', static::DIAGRAM)] + $exported,
        ['d'],
        [':tmp/d/flow.svg is out of date with :tmp/d/flow.drawio' . $export],
      ],
      'an SVG exported from an earlier version' => [
        ['d/flow.svg' => $marked('flow.drawio', '<mxfile/>')] + $exported,
        ['d'],
        [':tmp/d/flow.svg is out of date with :tmp/d/flow.drawio' . $export],
      ],
      'a dark SVG exported from an earlier version' => [
        ['d/flow.dark.svg' => $marked('flow.drawio', '<mxfile/>')] + $exported,
        ['d'],
        [':tmp/d/flow.dark.svg is out of date with :tmp/d/flow.drawio' . $export],
      ],
      'an SVG without a diagram' => [$exported + ['d/logo.svg' => static::SVG], ['d'], [':tmp/d/logo.svg has no .drawio source beside it.']],
      'a dark SVG without a diagram' => [$exported + ['d/logo.dark.svg' => static::SVG], ['d'], [':tmp/d/logo.dark.svg has no .drawio source beside it.']],
      'a diagram named like a dark export' => [
        ['d/night.dark.drawio' => static::DIAGRAM, 'd/night.dark.svg' => $stamped('d/night.dark.drawio'), 'd/night.dark.dark.svg' => $stamped('d/night.dark.drawio')],
        ['d'],
        [],
      ],
      'an SVG without a diagram outside the checked directory' => [$exported + ['d/logo.svg' => static::SVG], ['d/flow.drawio'], []],
    ];
  }

  /**
   * Run the script, capturing what it prints.
   *
   * @param array<int, string> $arguments
   *   The command line arguments.
   * @param array<string, string> $environment
   *   The environment variables.
   * @param callable|null $runner
   *   Runs a command, or NULL for a runner no command may reach.
   *
   * @return array{int, string}
   *   The exit code and the output.
   */
  protected function runScript(array $arguments, array $environment = [], ?callable $runner = NULL): array {
    $runner ??= static fn(): array => throw new \LogicException('No command is expected to run.');

    ob_start();

    try {
      $exit_code = draw_diagram($arguments, $environment, static::$tmp, static::$tmp, $runner);
    }
    finally {
      $output = (string) ob_get_clean();
    }

    return [$exit_code, $output];
  }

  /**
   * Build a runner standing in for the drawing session and draw.io.
   *
   * The session writes the diagram and sends the lines, and an export writes
   * the file it was asked for.
   *
   * @param array<int, array<int, string>> $commands
   *   Receives every command run.
   * @param array<int, string> $lines
   *   The lines the session sends.
   * @param string $diagram
   *   The diagram the session writes, relative to the workspace, or an empty
   *   string for none.
   *
   * @return callable
   *   The runner.
   */
  protected function buildRunner(array &$commands, array $lines = [], string $diagram = ''): callable {
    return function (array $command, string $directory, int $timeout, ?callable $on_line) use (&$commands, $lines, $diagram): array {
      $commands[] = $command;

      if ($command[0] !== 'claude') {
        $target = $this->findOutput($command);
        $theme = $command[(int) array_search('--theme', $command, TRUE) + 1];
        $this->writeFixture($target, str_ends_with($target, '.svg') ? str_replace('<svg ', '<svg style="color-scheme: ' . $theme . ';" ', static::SVG) : 'PNG');

        return ['exit_code' => 0, 'output' => '', 'errors' => '', 'is_timed_out' => FALSE];
      }

      if ($diagram !== '') {
        $this->writeFixture($diagram, static::DIAGRAM);
      }

      foreach ($lines as $line) {
        if ($on_line !== NULL) {
          $on_line($line);
        }
      }

      return ['exit_code' => 0, 'output' => implode("\n", $lines), 'errors' => '', 'is_timed_out' => FALSE];
    };
  }

  /**
   * Find the file an export command writes, relative to the workspace.
   *
   * @param array<int, string> $command
   *   The export command.
   *
   * @return string
   *   The path after '--output', relative to the workspace.
   */
  protected function findOutput(array $command): string {
    $index = array_search('--output', $command, TRUE);
    $target = is_int($index) ? $command[$index + 1] : '';

    return substr($target, strlen(static::$tmp) + 1);
  }

  /**
   * Write the skill, the draw.io binary and a brief a drawing needs.
   *
   * @return string
   *   The draw.io binary.
   */
  protected function writeDrawingTools(): string {
    $this->writeFixture('.claude/skills/drawio-skill/SKILL.md', 'Skill');
    $this->writeFixture('brief.md', 'Draw a box.');

    return $this->writeExecutable('bin/drawio');
  }

  /**
   * Write a diagram and the SVGs exported from it.
   *
   * @param string $path
   *   The diagram, relative to the workspace.
   */
  protected function writeExportedDiagram(string $path): void {
    $diagram = $this->writeFixture($path, static::DIAGRAM);

    foreach (array_keys(DRAW_DIAGRAM_THEMES) as $theme) {
      draw_diagram_stamp($this->writeFixture(draw_diagram_svg_path($path, $theme), static::SVG), $diagram);
    }
  }

  /**
   * Write an executable file below the per-test temporary directory.
   *
   * @param string $path
   *   Path relative to the temporary directory.
   *
   * @return string
   *   The absolute path written.
   */
  protected function writeExecutable(string $path): string {
    $file = $this->writeFixture($path, "#!/bin/sh\n");
    chmod($file, 0755);

    return $file;
  }

  /**
   * Run a callback with PHP warnings swallowed.
   *
   * A failed 'mkdir()' raises a warning before the script reports it, and the
   * suite is configured to fail on one.
   *
   * @param callable $callback
   *   The callback to run.
   */
  protected function runWithoutWarnings(callable $callback): void {
    set_error_handler(static fn(): bool => TRUE);

    try {
      $callback();
    }
    finally {
      restore_error_handler();
    }
  }

}

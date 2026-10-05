<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Behat\Definition\Pattern\PatternTransformer;
use Behat\Behat\Definition\Pattern\Policy\RegexPatternPolicy;
use Behat\Behat\Definition\Pattern\Policy\TurnipPatternPolicy;
use Behat\Config\Config;
use Behat\Gherkin\Filter\TagFilter;
use Behat\Gherkin\Keywords\CachedArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Gherkin\Parser;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Tests\Fixtures\StepCoverage\StepCoverageContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that a scenario runs every step the library registers.
 *
 * Behat resolves a step definition only for a step a scenario runs, so a
 * pattern no scenario reaches can ship unusable without the suite failing.
 * CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class StepScenarioCoverageTest extends UnitTestCase {

  /**
   * Attributes that register a method as a step.
   *
   * @var array<int, class-string<\Behat\Step\Given|\Behat\Step\When|\Behat\Step\Then>>
   */
  protected const STEP_ATTRIBUTES = [Given::class, When::class, Then::class];

  /**
   * Harness step that writes its PyString as the steps of a nested scenario.
   *
   * The first group captures the tags the nested scenario carries.
   *
   * @see \BehatCliTrait::behatCliWriteScenarioSteps()
   */
  protected const NESTED_STEPS_PATTERN = '/^scenario steps(?: tagged with "([^"]*)")?:$/';

  /**
   * Harness step that writes its PyString to a feature file of a nested run.
   *
   * @see \BehatCliContext::aFileNamedWith()
   */
  protected const NESTED_FEATURE_PATTERN = '/^(?:there is )?a file named "[^"]*\.feature" with:$/';

  public function testEveryRegisteredStepIsRun(): void {
    $root = dirname(__DIR__, 3);
    $texts = static::scenarioStepTexts(glob($root . '/tests/behat/features/*.feature') ?: [], static::suiteFilter($root . '/behat.php'));
    $steps = static::registeredSteps(DrupalContext::class, $root . '/src');

    $this->assertNotEmpty($steps);
    $this->assertSame([], static::unexercisedSteps($steps, $texts), 'Every registered step needs a scenario under tests/behat/features that runs it. A scenario the suite filters out, such as one tagged "@test-skipped", does not count.');
  }

  /**
   * Assert which step texts are collected from a feature.
   *
   * @param string $feature
   *   The feature file contents.
   * @param array<int, string> $expected
   *   The step texts, in the order they are collected.
   */
  #[DataProvider('dataProviderScenarioStepTexts')]
  public function testScenarioStepTexts(string $feature, array $expected): void {
    $file = $this->writeFixture('features/subject.feature', $feature);

    $this->assertSame($expected, static::scenarioStepTexts([$file], new TagFilter('~@test-skipped')));
  }

  public static function dataProviderScenarioStepTexts(): array {
    return [
      'keywords are stripped' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Every keyword
            Given the first step
            When the second step
            Then the third step
            And the fourth step
            But the fifth step
            * the sixth step
        FEATURE,
        ['the first step', 'the second step', 'the third step', 'the fourth step', 'the fifth step', 'the sixth step'],
      ],
      'a repeated step is collected once' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: One
            Given the step
          Scenario: Two
            Given the step
        FEATURE,
        ['the step'],
      ],
      'a commented step is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Comment
            Given the step
            # And the commented step
        FEATURE,
        ['the step'],
      ],
      'a background runs with a scenario' => [
        <<<'FEATURE'
        Feature: Subject
          Background:
            Given the background step
          Scenario: One
            When the scenario step
        FEATURE,
        ['the background step', 'the scenario step'],
      ],
      'a background does not run when every scenario is filtered out' => [
        <<<'FEATURE'
        Feature: Subject
          Background:
            Given the background step
          @test-skipped
          Scenario: Skipped
            When the scenario step
        FEATURE,
        [],
      ],
      'a filtered scenario is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Kept
            Given the kept step
          @test-skipped
          Scenario: Skipped
            Given the skipped step
        FEATURE,
        ['the kept step'],
      ],
      'a filtered feature is not collected' => [
        <<<'FEATURE'
        @test-skipped
        Feature: Subject
          Scenario: Skipped
            Given the skipped step
        FEATURE,
        [],
      ],
      'an outline runs once per row' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario Outline: Rows
            Given the value <value> exists
            Examples:
              | value   |
              | "first" |
              | second  |
        FEATURE,
        ['the value "first" exists', 'the value second exists'],
      ],
      'a filtered examples table is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario Outline: Rows
            Given the value <value> exists
            Examples:
              | value |
              | kept  |
            @test-skipped
            Examples:
              | value   |
              | skipped |
        FEATURE,
        ['the value kept exists'],
      ],
      'nested scenario steps are collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given some behat configuration
            And scenario steps:
              """
              Given the nested step
              Then the nested assertion should pass
              """
            When I run "behat --no-colors"
        FEATURE,
        ['some behat configuration', 'scenario steps:', 'the nested step', 'the nested assertion should pass', 'I run "behat --no-colors"'],
      ],
      'nested scenario steps with tags are collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given scenario steps tagged with "@api @email":
              """
              Given the nested step
              """
        FEATURE,
        ['scenario steps tagged with "@api @email":', 'the nested step'],
      ],
      'a PyString inside nested steps is an argument' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given scenario steps:
              """
              Given the nested step with:
                '''
                Given the argument line
                '''
              """
        FEATURE,
        ['scenario steps:', 'the nested step with:'],
      ],
      'a nested feature file is collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given a file named "features/nested.feature" with:
              """
              Feature: Nested
                Background:
                  Given the nested background step
                Scenario: Nested
                  When the nested step
              """
        FEATURE,
        ['a file named "features/nested.feature" with:', 'the nested background step', 'the nested step'],
      ],
      'a nested file that is not a feature is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given a file named "features/notes.txt" with:
              """
              Feature: Notes
                Scenario: Notes
                  Given the note line
              """
        FEATURE,
        ['a file named "features/notes.txt" with:'],
      ],
      'expected output is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Output
            Then it should pass with:
              """
              Given the echoed step   # FeatureContext::echoedStep()
              """
        FEATURE,
        ['it should pass with:'],
      ],
      'a filtered nested scenario is not collected' => [
        <<<'FEATURE'
        Feature: Subject
          Scenario: Nested
            Given scenario steps tagged with "@test-skipped":
              """
              Given the nested step
              """
            And a file named "features/nested.feature" with:
              """
              Feature: Nested
                @test-skipped
                Scenario: Skipped
                  Given the nested feature step
              """
        FEATURE,
        ['scenario steps tagged with "@test-skipped":', 'a file named "features/nested.feature" with:'],
      ],
    ];
  }

  public function testScenarioStepTextsWithoutFilter(): void {
    $file = $this->writeFixture('features/subject.feature', "Feature: Subject\n  @test-skipped\n  Scenario: Skipped\n    Given the skipped step\n");

    $this->assertSame(['the skipped step'], static::scenarioStepTexts([$file], NULL));
  }

  /**
   * Assert that the suite filter is read from the default profile.
   *
   * @param string $config
   *   The configuration file contents.
   * @param array<int, string> $expected
   *   The step texts collected through the filter.
   * @param string|null $exception
   *   The exception message expected, or NULL when none is.
   */
  #[DataProvider('dataProviderSuiteFilter')]
  public function testSuiteFilter(string $config, array $expected, ?string $exception = NULL): void {
    $config_file = $this->writeFixture('behat.php', $config);
    $feature_file = $this->writeFixture('features/subject.feature', "Feature: Subject\n  Scenario: Kept\n    Given the kept step\n  @test-skipped\n  Scenario: Skipped\n    Given the skipped step\n");

    if ($exception !== NULL) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($exception);
    }

    $this->assertSame($expected, static::scenarioStepTexts([$feature_file], static::suiteFilter($config_file)));
  }

  public static function dataProviderSuiteFilter(): array {
    return [
      'a tag filter' => [
        <<<'PHP'
        <?php
        use Behat\Config\Config;
        use Behat\Config\Filter\TagFilter;
        use Behat\Config\GherkinOptions;
        use Behat\Config\Profile;
        return (new Config())->withProfile((new Profile('default'))->withGherkinOptions((new GherkinOptions())->withFilter(new TagFilter('~@test-skipped'))));
        PHP,
        ['the kept step'],
      ],
      'no filter' => [
        <<<'PHP'
        <?php
        use Behat\Config\Config;
        use Behat\Config\Profile;
        return (new Config())->withProfile(new Profile('default'));
        PHP,
        ['the kept step', 'the skipped step'],
      ],
      'a filter the scan does not apply' => [
        <<<'PHP'
        <?php
        use Behat\Config\Config;
        use Behat\Config\Filter\NameFilter;
        use Behat\Config\GherkinOptions;
        use Behat\Config\Profile;
        return (new Config())->withProfile((new Profile('default'))->withGherkinOptions((new GherkinOptions())->withFilter(new NameFilter('Kept'))));
        PHP,
        [],
        'filters the suite by name, which the step scan does not apply.',
      ],
      'not a configuration' => [
        "<?php\nreturn [];\n",
        [],
        'does not return a Behat configuration.',
      ],
    ];
  }

  /**
   * Assert whether a step counts as run by the collected step texts.
   *
   * @param string $pattern
   *   The step pattern.
   * @param array<int, string> $texts
   *   The step texts the scenarios run.
   * @param bool $exercised
   *   Whether a text matches the pattern.
   */
  #[DataProvider('dataProviderUnexercisedSteps')]
  public function testUnexercisedSteps(string $pattern, array $texts, bool $exercised): void {
    $steps = [['label' => 'SubjectTrait::subjectStep()', 'pattern' => $pattern]];
    $expected = $exercised ? [] : [sprintf('SubjectTrait::subjectStep() "%s"', $pattern)];

    $this->assertSame($expected, static::unexercisedSteps($steps, $texts));
  }

  public static function dataProviderUnexercisedSteps(): array {
    return [
      'a quoted placeholder value' => ['the path should be :path', ['the path should be "/about"'], TRUE],
      'a bare placeholder value' => ['the queue should have :count items', ['the queue should have 5 items'], TRUE],
      'an optional ending' => ['I add :count item(s)', ['I add 1 item'], TRUE],
      'an alternative word' => ['the email should be sent/delivered', ['the email should be delivered'], TRUE],
      'a different letter case' => ['the path should be :path', ['The path should be "/about"'], TRUE],
      'a regex pattern' => ['/^I wait (\d+) seconds?$/', ['I wait 5 seconds'], TRUE],
      'a regex pattern is not read as Turnip' => ['/^the value is (\d+)$/', ['/^the value is (\d+)$/'], FALSE],
      'a text with extra words' => ['the path should be :path', ['the path should be "/about" eventually'], FALSE],
      'a text with other words' => ['the path should be :path', ['the path should not be "/about"'], FALSE],
      'no texts' => ['the path should be :path', [], FALSE],
    ];
  }

  public function testRegisteredSteps(): void {
    $expected = [
      ['label' => 'StepCoverageContext::contextStep()', 'pattern' => 'the context step should run'],
      ['label' => 'StepCoverageTrait::stepCoverageGiven()', 'pattern' => 'the fixture exists'],
      ['label' => 'StepCoverageTrait::stepCoverageWhen()', 'pattern' => 'I open the fixture'],
      ['label' => 'StepCoverageTrait::stepCoverageWhen()', 'pattern' => 'I visit the fixture'],
      ['label' => 'StepCoverageTrait::stepCoverageThen()', 'pattern' => 'the fixture should be open'],
    ];

    $this->assertSame($expected, static::registeredSteps(StepCoverageContext::class, __DIR__ . '/Fixtures/StepCoverage'));
  }

  public function testRegisteredStepsWithMissingDirectory(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('does not exist.');

    static::registeredSteps(StepCoverageContext::class, __DIR__ . '/Fixtures/Missing');
  }

  /**
   * Collect the steps a class registers from files under a directory.
   *
   * @param class-string $class
   *   The class to read, with the methods it inherits and composes.
   * @param string $directory
   *   The directory a method has to be declared under to count.
   *
   * @return array<int, array{label: string, pattern: string}>
   *   One entry per step attribute, labelled with the file and the method.
   */
  protected static function registeredSteps(string $class, string $directory): array {
    $root = realpath($directory);

    if ($root === FALSE) {
      throw new \RuntimeException(sprintf('The directory "%s" does not exist.', $directory));
    }

    $steps = [];

    foreach ((new \ReflectionClass($class))->getMethods() as $method) {
      $file = (string) $method->getFileName();

      if ($file === '' || !str_starts_with((string) realpath($file), $root . DIRECTORY_SEPARATOR)) {
        continue;
      }

      foreach (static::STEP_ATTRIBUTES as $step_attribute) {
        foreach ($method->getAttributes($step_attribute) as $attribute) {
          $steps[] = ['label' => sprintf('%s::%s()', basename($file, '.php'), $method->getName()), 'pattern' => (string) $attribute->newInstance()->getPattern()];
        }
      }
    }

    return $steps;
  }

  /**
   * Collect the text of every step the suite runs from the feature files.
   *
   * @param array<int, string> $files
   *   Paths to the feature files.
   * @param \Behat\Gherkin\Filter\TagFilter|null $filter
   *   The filter the suite applies, or NULL when it applies none.
   *
   * @return array<int, string>
   *   Unique step texts without their keyword, in the order they are found.
   */
  protected static function scenarioStepTexts(array $files, ?TagFilter $filter): array {
    $parser = new Parser(new Lexer(CachedArrayKeywords::withDefaultKeywords()));
    $texts = [];

    foreach ($files as $file) {
      $feature = $parser->parseFile($file);

      if ($feature instanceof FeatureNode) {
        static::collectStepTexts($feature, $filter, $parser, $texts);
      }
    }

    return array_values($texts);
  }

  /**
   * Collect the text of every step a feature runs, nested runs included.
   *
   * @param \Behat\Gherkin\Node\FeatureNode $feature
   *   The parsed feature.
   * @param \Behat\Gherkin\Filter\TagFilter|null $filter
   *   The filter the suite applies, or NULL when it applies none.
   * @param \Behat\Gherkin\Parser $parser
   *   The parser for nested Gherkin.
   * @param array<string, string> $texts
   *   The step texts collected so far, keyed by themselves.
   */
  protected static function collectStepTexts(FeatureNode $feature, ?TagFilter $filter, Parser $parser, array &$texts): void {
    if ($filter instanceof TagFilter) {
      $feature = $filter->filterFeature($feature);
    }

    $runs = [];

    // @phpstan-ignore method.deprecated
    foreach ($feature->getScenarios() as $scenario) {
      $runs = array_merge($runs, $scenario instanceof OutlineNode ? $scenario->getExamples() : [$scenario]);
    }

    // A background runs before each scenario, so it runs only when one does.
    $background = $runs === [] ? NULL : $feature->getBackground();
    $steps = $background instanceof BackgroundNode ? $background->getSteps() : [];

    foreach ($runs as $run) {
      $steps = array_merge($steps, $run->getSteps());
    }

    foreach ($steps as $step) {
      $texts[$step->getText()] = $step->getText();

      static::collectNestedStepTexts($feature, $step, $filter, $parser, $texts);
    }
  }

  /**
   * Collect the steps a harness step hands to a nested run.
   *
   * @param \Behat\Gherkin\Node\FeatureNode $feature
   *   The feature holding the step.
   * @param \Behat\Gherkin\Node\StepNode $step
   *   The step whose PyString may hold Gherkin.
   * @param \Behat\Gherkin\Filter\TagFilter|null $filter
   *   The filter the suite applies, or NULL when it applies none.
   * @param \Behat\Gherkin\Parser $parser
   *   The parser for nested Gherkin.
   * @param array<string, string> $texts
   *   The step texts collected so far, keyed by themselves.
   */
  protected static function collectNestedStepTexts(FeatureNode $feature, StepNode $step, ?TagFilter $filter, Parser $parser, array &$texts): void {
    foreach ($step->getArguments() as $argument) {
      if (!$argument instanceof PyStringNode) {
        continue;
      }

      // Both harness steps turn ''' into """ before writing, so a nested run
      // can carry PyStrings of its own.
      $gherkin = strtr($argument->getRaw(), ["'''" => '"""']);

      if (preg_match(static::NESTED_STEPS_PATTERN, $step->getText(), $matches) === 1) {
        $gherkin = sprintf("Feature: Nested\n  %s\n  Scenario: Nested\n%s", $matches[1] ?? '', $gherkin);
      }
      elseif (preg_match(static::NESTED_FEATURE_PATTERN, $step->getText()) !== 1) {
        continue;
      }

      $nested = $parser->parse($gherkin, sprintf('%s:%d', $feature->getFile(), $step->getLine()));

      if ($nested instanceof FeatureNode) {
        static::collectStepTexts($nested, $filter, $parser, $texts);
      }
    }
  }

  /**
   * Build the tag filter the default profile of a Behat configuration applies.
   *
   * @param string $file
   *   Path to the configuration file.
   *
   * @return \Behat\Gherkin\Filter\TagFilter|null
   *   The filter, or NULL when the profile declares none.
   */
  protected static function suiteFilter(string $file): ?TagFilter {
    $config = require $file;

    if (!$config instanceof Config) {
      throw new \RuntimeException(sprintf('%s does not return a Behat configuration.', $file));
    }

    $filters = (array) ($config->toArray()['default']['gherkin']['filters'] ?? []);
    $unsupported = array_diff(array_keys($filters), ['tags']);

    // Scenarios another filter removes would still count as run.
    if ($unsupported !== []) {
      throw new \RuntimeException(sprintf('%s filters the suite by %s, which the step scan does not apply.', $file, implode(', ', $unsupported)));
    }

    return is_string($filters['tags'] ?? NULL) ? new TagFilter($filters['tags']) : NULL;
  }

  /**
   * List the steps no collected step text matches.
   *
   * @param array<int, array{label: string, pattern: string}> $steps
   *   The registered steps.
   * @param array<int, string> $texts
   *   The step texts the scenarios run.
   *
   * @return array<int, string>
   *   The label and pattern of each step no text matches.
   */
  protected static function unexercisedSteps(array $steps, array $texts): array {
    // The Turnip policy accepts any pattern, so the regex policy is registered
    // first.
    $transformer = new PatternTransformer();
    $transformer->registerPatternPolicy(new RegexPatternPolicy());
    $transformer->registerPatternPolicy(new TurnipPatternPolicy());

    $unexercised = [];

    foreach ($steps as $step) {
      $regex = $transformer->transformPatternToRegex($step['pattern']);

      foreach ($texts as $text) {
        if (preg_match($regex, $text) === 1) {
          continue 2;
        }
      }

      $unexercised[] = sprintf('%s "%s"', $step['label'], $step['pattern']);
    }

    return $unexercised;
  }

}

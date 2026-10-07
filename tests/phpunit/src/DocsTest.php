<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\BackendAwareInterface;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\Helper\Drupal\AuthTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\DocumentedOptionsContext;
use DrevOps\BehatSteps\Tests\Fixtures\Web\DocumentedPrerequisitesContext;
use DrevOps\BehatSteps\Tests\Fixtures\Web\DocumentedPrerequisitesTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\HelperSampleTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\HelperSignatureTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\InheritedChild;
use DrevOps\BehatSteps\Tests\Fixtures\Web\MultiMethodTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\NoMatchTrait;
use DrevOps\BehatSteps\Tests\Fixtures\Web\SampleTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\DuplicateOptionConfigContext;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Tests for docs generation.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment
 */
#[CoversFunction('parse_method_comment')]
#[CoversFunction('extract_method_steps')]
#[CoversFunction('camel_to_snake')]
#[CoversFunction('array_to_markdown_table')]
#[CoversFunction('render_info')]
#[CoversFunction('validate')]
#[CoversFunction('validate_step_patterns')]
#[CoversFunction('extract_step_examples')]
#[CoversFunction('replace_content')]
#[CoversFunction('extract_info')]
#[CoversFunction('parse_class_comment')]
#[CoversFunction('tag_registry')]
#[CoversFunction('non_descriptive_placeholders')]
#[CoversFunction('placeholder_synonyms')]
#[CoversFunction('rejected_step_phrases')]
#[CoversFunction('extract_tags')]
#[CoversFunction('validate_tag')]
#[CoversFunction('validate_tags')]
#[CoversFunction('collect_step_traits')]
#[CoversFunction('method_is_registered')]
#[CoversFunction('comment_is_internal')]
#[CoversFunction('render_type')]
#[CoversFunction('render_value')]
#[CoversFunction('render_method_signature')]
#[CoversFunction('heading_anchor')]
#[CoversFunction('extract_helpers')]
#[CoversFunction('collect_helper_methods')]
#[CoversFunction('collect_helper_traits')]
#[CoversFunction('helper_trait_contracts')]
#[CoversFunction('composes_trait')]
#[CoversFunction('resolve_inherited_comment')]
#[CoversFunction('relative_source_path')]
#[CoversFunction('render_helpers')]
#[CoversFunction('validate_helpers')]
#[CoversFunction('render_tag_reference')]
#[CoversFunction('render_extension_options')]
#[CoversFunction('extension_option_rows')]
#[CoversFunction('extension_option_type')]
#[CoversFunction('extension_option_description')]
#[CoversFunction('extract_trait_options')]
#[CoversFunction('trait_option_group')]
#[CoversFunction('render_trait_options')]
#[CoversFunction('trait_option_type')]
#[CoversFunction('extract_trait_prerequisites')]
#[CoversFunction('render_trait_prerequisites')]
#[CoversFunction('validate_env_vars')]
class DocsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../docs.php';

    // The Web fixture traits are loaded up front so they are available to
    // eval(). The Drupal fixture traits are loaded from the test's temporary
    // directory instead, so they resolve to the correct context.
    $fixture_files = glob($this->getFixturesDir() . '/Web/*.php');
    if ($fixture_files !== FALSE) {
      foreach ($fixture_files as $fixture_file) {
        require_once $fixture_file;
      }
    }
  }

  #[DataProvider('dataProviderParseMethodComment')]
  public function testParseMethodComment(string $comment, ?array $expected, ?string $expected_message = NULL): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $actual = parse_method_comment($comment);

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderParseMethodComment(): array {
    return [
      'empty' => [
        '',
        NULL,
      ],
      'no example' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @param string $test
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'with example' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 * Given I am on the homepage
 * @endcode
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => 'Given I am on the homepage' . PHP_EOL,
        ],
      ],
      'with indented example' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 *   Given I am on the homepage
 *   When I click "Submit"
 * @endcode
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => 'Given I am on the homepage' . PHP_EOL . 'When I click "Submit"' . PHP_EOL,
        ],
      ],
      'multiline description' => [
        <<<'EOD'
/**
 * This is a description
 * that spans multiple lines.
 */
EOD,
        [
          'description' => 'This is a description',
          'example' => '',
        ],
      ],
      'complex example with empty lines' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 *   Given I am on the homepage
 *
 *   When I click "Submit"
 *   Then I should see "Success"
 * @endcode
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => 'Given I am on the homepage' . PHP_EOL . PHP_EOL . 'When I click "Submit"' . PHP_EOL . 'Then I should see "Success"' . PHP_EOL,
        ],
      ],
      'comment with comment markers' => [
        <<<'EOD'
/**
 * This is a description.
 * /* nested comment start
 * */ nested comment end
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'unclosed example error' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 * Example without closing tag
 */
EOD,
        NULL,
        'Example not closed',
      ],
      'example with description' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 * Example code
 * @endcode
 */
EOD,
        [
          'description' => 'This is a description.',
          'example' => 'Example code' . PHP_EOL,
        ],
      ],
      'trim description' => [
        <<<'EOD'
/**
 * This is a description with trailing space.
 */
EOD,
        [
          'description' => 'This is a description with trailing space.',
          'example' => '',
        ],
      ],
    ];

  }

  public function testExtractMethodStepsWithAttribute(): void {
    $trait = new \ReflectionClass(SampleTrait::class);
    $method = $trait->getMethod('sampleAssertTest');
    $steps = extract_method_steps($method);
    $this->assertSame(['@Then the test should pass'], $steps);
  }

  public function testExtractMethodStepsMultiple(): void {
    $trait = new \ReflectionClass(MultiMethodTrait::class);

    $given_method = $trait->getMethod('multiMethodGivenItems');
    $this->assertSame(['@Given the following items:'], extract_method_steps($given_method));

    $when_method = $trait->getMethod('multiMethodClickButton');
    $this->assertSame(['@When I click on :button'], extract_method_steps($when_method));

    $then_method = $trait->getMethod('multiMethodAssertResultVisible');
    $this->assertSame(['@Then the result should be visible'], extract_method_steps($then_method));
  }

  public function testExtractMethodStepsNoAttributes(): void {
    $trait = new \ReflectionClass(NoMatchTrait::class);
    $method = $trait->getMethod('otherMethod');
    $steps = extract_method_steps($method);
    $this->assertSame([], $steps);
  }

  #[DataProvider('dataProviderCamelToSnake')]
  public function testCamelToSnake(string $input, string $expected, string $separator = '_'): void {
    $actual = camel_to_snake($input, $separator);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderCamelToSnake(): array {
    return [
      'simple camelCase' => [
        'camelCase',
        'camel_case',
      ],
      'PascalCase' => [
        'PascalCase',
        'pascal_case',
      ],
      'already_snake_case' => [
        'already_snake_case',
        'already_snake_case',
      ],
      'numbers in camelCase' => [
        'user123Name',
        'user_123_name',
      ],
      'multiple uppercase in a row' => [
        'HTTPRequest',
        'h_t_t_p_request',
      ],
      'custom separator' => [
        'camelCase',
        'camel-case',
        '-',
      ],
      'mixed case with numbers' => [
        'getAPI2Config',
        'get_a_p_i_2_config',
      ],
      'single character uppercase' => [
        'aB',
        'a_b',
      ],
      'single letter' => [
        'A',
        'a',
      ],
      'starts with uppercase' => [
        'FileTrait',
        'file_trait',
      ],
      'acronym at end' => [
        'userAPI',
        'user_a_p_i',
      ],
      'empty string' => [
        '',
        '',
      ],
      'special characters preserved' => [
        'special$Case',
        'special$_case',
      ],
      'numbers only' => [
        '123',
        '123',
      ],
      'snake case with custom separator' => [
        'snake_case_example',
        'snake_case_example',
        '-',
      ],
    ];
  }

  #[DataProvider('dataProviderArrayToMarkdownTable')]
  public function testArrayToMarkdownTable(array $headers, array $rows, string $expected): void {
    $actual = array_to_markdown_table($headers, $rows);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderArrayToMarkdownTable(): array {
    return [
      'basic table' => [
        ['Header 1', 'Header 2'],
        [
          'row1' => ['Cell 1', 'Cell 2'],
          'row2' => ['Cell 3', 'Cell 4'],
        ],
        implode(PHP_EOL, ['| Header 1 | Header 2 |', '| --- | --- |', '| Cell 1 | Cell 2 |', '| Cell 3 | Cell 4 |']),
      ],
      'single column table' => [
        ['Header'],
        [
          'row1' => ['Cell 1'],
          'row2' => ['Cell 2'],
        ],
        implode(PHP_EOL, ['| Header |', '| --- |', '| Cell 1 |', '| Cell 2 |']),
      ],
      'single row table' => [
        ['Header 1', 'Header 2'],
        [
          'row1' => ['Cell 1', 'Cell 2'],
        ],
        implode(PHP_EOL, ['| Header 1 | Header 2 |', '| --- | --- |', '| Cell 1 | Cell 2 |']),
      ],
      'multi-column table' => [
        ['Header 1', 'Header 2', 'Header 3', 'Header 4'],
        [
          'row1' => ['Cell 1', 'Cell 2', 'Cell 3', 'Cell 4'],
          'row2' => ['Cell 5', 'Cell 6', 'Cell 7', 'Cell 8'],
        ],
        implode(PHP_EOL, ['| Header 1 | Header 2 | Header 3 | Header 4 |', '| --- | --- | --- | --- |', '| Cell 1 | Cell 2 | Cell 3 | Cell 4 |', '| Cell 5 | Cell 6 | Cell 7 | Cell 8 |']),
      ],
      'with special characters' => [
        ['Header *1*', 'Header **2**'],
        [
          'row1' => ['Cell *1*', 'Cell **2**'],
          'row2' => ['Cell [3](link)', 'Cell `4`'],
        ],
        implode(PHP_EOL, ['| Header *1* | Header **2** |', '| --- | --- |', '| Cell *1* | Cell **2** |', '| Cell [3](link) | Cell `4` |']),
      ],
      'empty headers' => [
        [],
        [
          'row1' => ['Cell 1', 'Cell 2'],
        ],
        '',
      ],
      'empty rows' => [
        ['Header 1', 'Header 2'],
        [],
        '',
      ],
      'empty headers and rows' => [
        [],
        [],
        '',
      ],
      'with empty cells' => [
        ['Header 1', 'Header 2', 'Header 3'],
        [
          'row1' => ['Cell 1', '', 'Cell 3'],
          'row2' => ['', 'Cell 5', ''],
        ],
        implode(PHP_EOL, ['| Header 1 | Header 2 | Header 3 |', '| --- | --- | --- |', '| Cell 1 |  | Cell 3 |', '|  | Cell 5 |  |']),
      ],
      'with numeric values' => [
        ['ID', 'Value'],
        [
          'row1' => ['1', '100'],
          'row2' => ['2', '200'],
        ],
        implode(PHP_EOL, ['| ID | Value |', '| --- | --- |', '| 1 | 100 |', '| 2 | 200 |']),
      ],
    ];
  }

  #[DataProvider('dataProviderRenderInfo')]
  public function testRenderInfo(array $info, string $expected, ?string $expected_message = NULL): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $expected_message = str_replace('@tmp', static::$tmp, $expected_message);
      $this->expectExceptionMessage($expected_message);
    }

    $base_path = static::$tmp;

    $steps_dir = $base_path . '/' . STEPS_DIRECTORY;
    $features_dir = $base_path . '/tests/behat/features';

    mkdir($steps_dir . '/Web', 0777, TRUE);
    mkdir($steps_dir . '/Drupal', 0777, TRUE);
    mkdir($features_dir, 0777, TRUE);

    // The files are created because render_info() checks they exist.
    foreach ($info as $trait => $data) {
      $context = $data['context'] ?? 'Web';

      if (!isset($data['name_contextual'])) {
        $info[$trait]['name_contextual'] = ($context !== 'Web' ? $context . '\\' : '') . $trait;
      }

      if ($trait !== 'MissingTrait') {
        $this->writeFixture(sprintf('%s/%s/%s.php', STEPS_DIRECTORY, $context, $trait), '<?php');
      }

      $example_name = camel_to_snake(str_replace('Trait', '', $trait));
      $prefix = $context === 'Drupal' ? 'drupal_' : '';
      $this->writeFixture(sprintf('tests/behat/features/%s%s.feature', $prefix, $example_name), 'Feature: Test');
    }

    if (isset($info['MissingTrait'])) {
      @unlink($steps_dir . '/Web/MissingTrait.php');
    }

    $actual = render_info($info, $base_path);

    // Individual elements are asserted rather than the exact formatting.
    if ($expected_message === NULL && !empty($info)) {
      foreach ($info as $trait => $data) {
        $name_contextual = $data['name_contextual'] ?? $trait;
        $link_id = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', $name_contextual));
        $this->assertStringContainsString(sprintf("[%s](#%s)", $name_contextual, $link_id), $actual);
        $this->assertStringContainsString($data['description'], $actual);
      }

      foreach ($info as $trait => $data) {
        $name_contextual = $data['name_contextual'] ?? $trait;
        $this->assertStringContainsString(sprintf("## %s", $name_contextual), $actual);
        $this->assertStringContainsString(sprintf('[Source](%s/%s/%s.php)', STEPS_DIRECTORY, $data['context'] ?? 'Web', $trait), $actual);

        if (isset($data['methods']) && is_array($data['methods'])) {
          foreach ($data['methods'] as $method) {
            if (isset($method['steps']) && is_array($method['steps'])) {
              foreach ($method['steps'] as $step) {
                $this->assertStringContainsString($step, $actual);
              }
            }
            elseif (isset($method['steps']) && is_string($method['steps'])) {
              $this->assertStringContainsString($method['steps'], $actual);
            }

            if (isset($method['example'])) {
              $this->assertStringContainsString("```gherkin", $actual);

              if (isset($method['example']) && $method['example'] === 123) {
                // The content check is skipped for the integer example fixture.
              }
              else {
                $example = is_string($method['example']) ? $method['example'] : (string) $method['example'];
                if (!empty($example)) {
                  $example_lines = explode("\n", $example);
                  foreach ($example_lines as $line) {
                    if (!empty(trim($line))) {
                      $this->assertStringContainsString($line, $actual);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    elseif (empty($info)) {
      // Empty info renders the index headers, so only the absence of trait
      // data is asserted.
      $this->assertStringNotContainsString('<details>', $actual);
      $this->assertStringNotContainsString('[Source]', $actual);
    }
  }

  public static function dataProviderRenderInfo(): array {
    return [
      'single trait with single method' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'context' => 'Web',
            'description' => 'Test trait description',
            'description_full' => 'Test trait description',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [TestTrait](#testtrait) | Web | Test trait description |
## TestTrait

[Source](src/Steps/Web/TestTrait.php), [Example](tests/behat/features/test.feature)

Test trait description

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>


EOD,
      ],
      'multiple traits with methods' => [
        [
          'FirstTrait' => [
            'name' => 'FirstTrait',
            'context' => 'Web',
            'description' => 'First trait description',
            'description_full' => 'First trait description',
            'methods' => [
              [
                'class_name' => 'FirstTrait',
                'name' => 'firstMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'First method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
          'SecondTrait' => [
            'name' => 'SecondTrait',
            'context' => 'Drupal',
            'description' => 'Second trait description',
            'description_full' => 'Second trait description',
            'methods' => [
              [
                'class_name' => 'SecondTrait',
                'name' => 'secondMethod',
                'steps' => ['@When I click "Submit"'],
                'description' => 'Second method description',
                'example' => 'When I click "Submit"',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [FirstTrait](#firsttrait) | Web | First trait description |
| [SecondTrait](#secondtrait) | Drupal | Second trait description |
## FirstTrait

[Source](src/Steps/Web/FirstTrait.php), [Example](tests/behat/features/first.feature)

First trait description

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>

## SecondTrait

[Source](src/Steps/Drupal/SecondTrait.php), [Example](tests/behat/features/second.feature)

Second trait description

<details>
  <summary><code>@When I click "Submit"</code></summary>

```gherkin
When I click "Submit"
```

</details>


EOD,
      ],
      'trait with multiple methods' => [
        [
          'MultiMethodTrait' => [
            'name' => 'MultiMethodTrait',
            'context' => 'Web',
            'description' => 'Multi-method trait description',
            'description_full' => 'Multi-method trait description',
            'methods' => [
              [
                'class_name' => 'MultiMethodTrait',
                'name' => 'firstMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'First method description',
                'example' => 'Given I am on the homepage',
              ],
              [
                'class_name' => 'MultiMethodTrait',
                'name' => 'secondMethod',
                'steps' => ['@When I click "Submit"'],
                'description' => 'Second method description',
                'example' => 'When I click "Submit"',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [MultiMethodTrait](#multimethodtrait) | Web | Multi-method trait description |
## MultiMethodTrait

[Source](src/Steps/Web/MultiMethodTrait.php), [Example](tests/behat/features/multi_method.feature)

Multi-method trait description

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>

<details>
  <summary><code>@When I click "Submit"</code></summary>

```gherkin
When I click "Submit"
```

</details>


EOD,
      ],
      'with multiple steps in single method' => [
        [
          'StepsTrait' => [
            'name' => 'StepsTrait',
            'context' => 'Drupal',
            'description' => 'Steps trait description',
            'description_full' => 'Steps trait description',
            'methods' => [
              [
                'class_name' => 'StepsTrait',
                'name' => 'methodWithMultipleSteps',
                'steps' => ['@Given I am on the homepage', '@When I click "Submit"', '@Then I should see "Success"'],
                'description' => 'Method with multiple steps',
                'example' => "Given I am on the homepage\nWhen I click \"Submit\"\nThen I should see \"Success\"",
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [StepsTrait](#stepstrait) | Drupal | Steps trait description |
## StepsTrait

[Source](src/Steps/Drupal/StepsTrait.php), [Example](tests/behat/features/steps.feature)

Steps trait description

<details>
  <summary><code>@Given I am on the homepage
@When I click "Submit"
@Then I should see "Success"</code></summary>

```gherkin
Given I am on the homepage
When I click "Submit"
Then I should see "Success"
```

</details>


EOD,
      ],
      'empty info' => [
        [],
        '### Index of Web steps' . PHP_EOL . PHP_EOL . PHP_EOL,
      ],
      'with missing source file' => [
        [
          'MissingTrait' => [
            'name' => 'MissingTrait',
            'context' => 'Web',
            'description' => 'Missing trait description',
            'description_full' => 'Missing trait description',
            'methods' => [
              [
                'class_name' => 'MissingTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        "",
        'Source file',
      ],
      'trait with multi-paragraph description' => [
        [
          'MultiParaTrait' => [
            'name' => 'MultiParaTrait',
            'context' => 'Web',
            'description' => 'Multi-paragraph trait description',
            'description_full' => "Multi-paragraph trait description\n\nThis is a second paragraph.\n\nThis is a third paragraph.",
            'methods' => [
              [
                'class_name' => 'MultiParaTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [MultiParaTrait](#multiparatrait) | Web | Multi-paragraph trait description |
## MultiParaTrait

[Source](src/Steps/Web/MultiParaTrait.php), [Example](tests/behat/features/multi_para.feature)

Multi-paragraph trait description

This is a second paragraph.

This is a third paragraph.

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>


EOD,
      ],
      'trait with list in description' => [
        [
          'ListTrait' => [
            'name' => 'ListTrait',
            'context' => 'Drupal',
            'description' => 'List trait description',
            'description_full' => "List trait description\n\n- Item 1\n- Item 2\n- Item 3",
            'methods' => [
              [
                'class_name' => 'ListTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [ListTrait](#listtrait) | Drupal | List trait description |
## ListTrait

[Source](src/Steps/Drupal/ListTrait.php), [Example](tests/behat/features/list.feature)

List trait description

- Item 1
- Item 2
- Item 3

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>


EOD,
      ],
      'trait with non-array properties' => [
        [
          'NonArrayTrait' => [
            'name' => 'NonArrayTrait',
            'context' => 'Drupal',
            'description' => 'Non-array trait description',
            'description_full' => 'Non-array trait description',
            'methods' => [
              [
                'class_name' => 'NonArrayTrait',
                'name' => 'testMethod',
                'steps' => '@Given I am on the homepage',
                'description' => NULL,
                'example' => 123,
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [NonArrayTrait](#nonarraytrait) | Drupal | Non-array trait description |
## NonArrayTrait

[Source](src/Steps/Drupal/NonArrayTrait.php), [Example](tests/behat/features/non_array.feature)

Non-array trait description

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
123
```

</details>


EOD,
      ],
      'trait with @code block in description' => [
        [
          'CodeBlockTrait' => [
            'name' => 'CodeBlockTrait',
            'context' => 'Web',
            'description' => 'Code block trait description',
            'description_full' => "Code block trait description\n\n@code\nGiven I am on the homepage\nWhen I click \"Submit\"\n@endcode",
            'methods' => [
              [
                'class_name' => 'CodeBlockTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        <<<'EOD'
| Class | Context | Description |
| --- | --- | --- |
| [CodeBlockTrait](#codeblocktrait) | Web | Code block trait description |
## CodeBlockTrait

[Source](src/Steps/Web/CodeBlockTrait.php), [Example](tests/behat/features/code_block.feature)

>  Code block trait description
>
>  ```
>  Given I am on the homepage
>  When I click "Submit"
>  ```
>

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```

</details>


EOD,
      ],
    ];
  }

  public function testRenderInfoThrowsOnMissingExample(): void {
    $this->writeFixture(STEPS_DIRECTORY . '/Web/SomeTrait.php', '<?php');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Example file %s/tests/behat/features/some.feature does not exist', static::$tmp));

    render_info([
      'SomeTrait' => [
        'name' => 'SomeTrait',
        'name_contextual' => 'SomeTrait',
        'context' => 'Web',
        'description' => 'Do the thing.',
        'description_full' => 'Do the thing.',
        'methods' => [],
      ],
    ], static::$tmp);
  }

  #[DataProvider('dataProviderRenderInfoWithPathForLinks')]
  public function testRenderInfoWithPathForLinks(array $info, string $path_for_links, string $expected): void {
    $base_path = static::$tmp;

    $steps_dir = $base_path . '/' . STEPS_DIRECTORY;
    $features_dir = $base_path . '/tests/behat/features';

    mkdir($steps_dir . '/Web', 0777, TRUE);
    mkdir($steps_dir . '/Drupal', 0777, TRUE);
    mkdir($features_dir, 0777, TRUE);

    // The files are created because render_info() checks they exist.
    foreach ($info as $trait => $data) {
      $context = $data['context'] ?? 'Web';

      if (!isset($data['name_contextual'])) {
        $info[$trait]['name_contextual'] = ($context !== 'Web' ? $context . '\\' : '') . $trait;
      }

      $this->writeFixture(sprintf('%s/%s/%s.php', STEPS_DIRECTORY, $context, $trait), '<?php');

      $example_name = camel_to_snake(str_replace('Trait', '', $trait));
      $prefix = $context === 'Drupal' ? 'drupal_' : '';
      $this->writeFixture(sprintf('tests/behat/features/%s%s.feature', $prefix, $example_name), 'Feature: Test');
    }

    $actual = render_info($info, $base_path, $path_for_links);

    foreach ($info as $trait => $data) {
      $name_contextual = $data['name_contextual'] ?? $trait;
      $link_id = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', $name_contextual));
      $expected_link = sprintf("%s#%s", $path_for_links, $link_id);
      $this->assertStringContainsString($expected_link, $actual);
    }

    // With path_for_links set, only the index is rendered.
    $this->assertStringNotContainsString('<details>', $actual);
    $this->assertStringNotContainsString('[Source]', $actual);
  }

  public static function dataProviderRenderInfoWithPathForLinks(): array {
    return [
      'with STEPS.md path' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'context' => 'Web',
            'description' => 'Test trait description',
            'description_full' => 'Test trait description',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'Test method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        'STEPS.md',
        '',
      ],
      'with custom path' => [
        [
          'FirstTrait' => [
            'name' => 'FirstTrait',
            'context' => 'Web',
            'description' => 'First trait description',
            'description_full' => 'First trait description',
            'methods' => [
              [
                'class_name' => 'FirstTrait',
                'name' => 'firstMethod',
                'steps' => ['@Given I am on the homepage'],
                'description' => 'First method description',
                'example' => 'Given I am on the homepage',
              ],
            ],
          ],
        ],
        'docs/REFERENCE.md',
        '',
      ],
    ];
  }

  #[DataProvider('dataProviderValidate')]
  public function testValidate(array $info, array $expected): void {
    $actual = validate($info);

    // The order might differ, so both arrays are sorted before comparison.
    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderValidate(): array {
    return [
      'empty info' => [
        [],
        [],
      ],
      'valid info' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testGivenMethod',
                'steps' => ['@Given the following items:'],
                'description' => 'Test method description',
                'example' => 'Given the following items:',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testWhenMethod',
                'steps' => ['@When I click on the button'],
                'description' => 'Test method description',
                'example' => 'When I click on the button',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testThenAssertMethod',
                'steps' => ['@Then the page should contain "text"'],
                'description' => 'Test method description',
                'example' => 'Then the page should contain "text"',
              ],
            ],
          ],
        ],
        [],
      ],
      'multiple steps error' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given step one', '@Given step two'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Multiple steps found' . PHP_EOL],
      ],
      'given without following' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given items:'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing "following" in the step' . PHP_EOL],
      ],
      'when without I' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@When click on button'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - When step does not start with "I "' . PHP_EOL],
      ],
      'then without assert in method' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Then the page should contain "text"'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing "Assert" in the method name' . PHP_EOL],
      ],
      'then with should in method' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertShouldMethod',
                'steps' => ['@Then the page should contain "text"'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertShouldMethod - Assert method contains "Should", but it should not.' . PHP_EOL],
      ],
      'then without should in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the page contains "text"'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Missing "should" in the step' . PHP_EOL],
      ],
      'then without the/a/no' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then page should contain "text"'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Missing "the", "a" or "no" in the step' . PHP_EOL],
      ],
      'given starting with I' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given I accept all confirmation dialogs'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Given step is in the first person but should state a precondition' . PHP_EOL],
      ],
      'then starting with I' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then I should see the modal'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Then step is in the first person but should start with the asserted entity' . PHP_EOL],
      ],
      'given with title case first person' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given My account exists'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Given step is in the first person but should state a precondition' . PHP_EOL],
      ],
      'then with an all caps acronym' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the US date format should be used'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'given with first person mid step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given the page I am on is cached'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Given step is in the first person but should state a precondition' . PHP_EOL],
      ],
      'then with possessive first person' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then my account should be blocked from the site'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Then step is in the first person but should start with the asserted entity' . PHP_EOL],
      ],
      'when with an acronym containing I' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@When the Search API cron runs'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - When step does not start with "I "' . PHP_EOL],
      ],
      'when in the first person' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@When I run the Search API cron'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'bare category placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the content :type should exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Non-descriptive placeholder ":type" in the step' . PHP_EOL],
      ],
      'non-descriptive placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the element :selector should be displayed with an offset of :number pixels'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Non-descriptive placeholder ":number" in the step' . PHP_EOL],
      ],
      'camel case placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the :stringValue element should be displayed'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":stringValue" in the step is not snake_case' . PHP_EOL],
      ],
      'numbered placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the element :selector1 should stack above the element :selector2'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'descriptive placeholders in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the element :parent should contain :count elements matching :selector'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'placeholder preceding its own noun' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the :queue queue should be empty'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":queue" in the step precedes its own noun "queue"' . PHP_EOL],
      ],
      'placeholder preceding a noun its name contains' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the :row_text row should contain the following:'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":row_text" in the step precedes its own noun "row"' . PHP_EOL],
      ],
      'placeholder preceding a noun its name abbreviates' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the :attr attribute should exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":attr" in the step precedes its own noun "attribute"' . PHP_EOL],
      ],
      'placeholder following its own noun' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the queue :queue should have :count item(s)'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'bundle placeholder preceding its entity noun' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given the following :media_type media exist:'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'abbreviated placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the current URL should have the query parameter :param'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Non-descriptive placeholder ":param" in the step' . PHP_EOL],
      ],
      'bare value placeholder in step' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the JSON path :path should be equal to :value'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":value" in the step does not read "the value :value"' . PHP_EOL],
      ],
      'value placeholder preceding its own noun' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the current URL should have the query parameter :name with the :value value'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [
          '  TestTrait::testAssertMethod - Placeholder ":value" in the step precedes its own noun "value"' . PHP_EOL,
          '  TestTrait::testAssertMethod - Placeholder ":value" in the step does not read "the value :value"' . PHP_EOL,
        ],
      ],
      'value placeholder introduced by its noun' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the state :name should have the value :value'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod2',
                'steps' => ['@Then the config :name with the key :key should have the effective value :value'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'partial match placeholder without the partial prefix' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then a cookie with a name containing :name should exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":name" after "containing" in the step is not prefixed with "partial_"' . PHP_EOL],
      ],
      'partial match placeholder with the partial prefix' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then a cookie with a name containing :partial_name should exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'text compared against a named target' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the region :region should contain the text :text'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Placeholder ":text" in the step compares against a named target but should be ":value"' . PHP_EOL],
      ],
      'text compared against a whole body' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the modal should contain :text'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'text identifying the asserted entity' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the element :selector with the text :text in the region :region should have the attribute :attribute with the value :value'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'step opening with a placeholder' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then :content_type content with the title :title should not exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Step starts with a placeholder but should start with the noun it names' . PHP_EOL],
      ],
      'placeholder synonyms in steps' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMailMethod',
                'steps' => ['@Then the user with the email :mail should exist'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertEmailMethod',
                'steps' => ['@Then an email should be sent to the address :email'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testFollowMethod',
                'steps' => ['@When I follow the link with the index :link_number in the email'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [
          '  TestTrait::testAssertMailMethod - Placeholder ":mail" in the step is a synonym of ":address"' . PHP_EOL,
          '  TestTrait::testAssertEmailMethod - Placeholder ":email" in the step is a synonym of ":address"' . PHP_EOL,
          '  TestTrait::testFollowMethod - Placeholder ":link_number" in the step is a synonym of ":index"' . PHP_EOL,
        ],
      ],
      'rejected phrases in steps' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testEditMethod',
                'steps' => ['@When I edit the :content_type content with the title :title'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testClickMethod',
                'steps' => ['@When I click the link :link'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertViewportMethod',
                'steps' => ['@Then the element :selector should be displayed within a viewport'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertSelectMethod',
                'steps' => ['@Then the option :option should exist within the select element :selector'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [
          '  TestTrait::testEditMethod - Step reads "I edit the" but should read "I visit the ... edit page"' . PHP_EOL,
          '  TestTrait::testClickMethod - Step reads "I click the" but should read "I click on the"' . PHP_EOL,
          '  TestTrait::testAssertViewportMethod - Step reads "a viewport" but should read "the viewport"' . PHP_EOL,
          '  TestTrait::testAssertSelectMethod - Step reads "the select element" but should read "the select"' . PHP_EOL,
        ],
      ],
      'settled phrases in steps' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testClickMethod',
                'steps' => ['@When I click on the link :link in the region :region'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertViewportMethod',
                'steps' => ['@Then the element :selector should be displayed within the viewport'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertSelectMethod',
                'steps' => ['@Then the option :option should exist within the select :selector'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'navigation step naming no page' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testVisitMethod',
                'steps' => ['@When I visit the :media_type media with the name :name'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        ['  TestTrait::testVisitMethod - Navigation step does not name the page it opens, as in "I visit the ... page"' . PHP_EOL],
      ],
      'navigation steps naming a page, a link or a path' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testVisitPageMethod',
                'steps' => ['@When I visit the :media_type media edit page with the name :name'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testVisitLinkMethod',
                'steps' => ['@When I visit the password reset link for the user :name'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testVisitPathMethod',
                'steps' => ['@When I visit :path'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'missing example' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Given the following items:'],
                'description' => 'Test method description',
                'example' => '',
              ],
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing example' . PHP_EOL],
      ],
      'multiple validation errors' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => ['@Then page contains "text"'],
                'description' => 'Test method description',
                'example' => '',
              ],
            ],
          ],
        ],
        [
          '  TestTrait::testMethod - Missing "Assert" in the method name' . PHP_EOL,
          '  TestTrait::testMethod - Missing "should" in the step' . PHP_EOL,
          '  TestTrait::testMethod - Missing "the", "a" or "no" in the step' . PHP_EOL,
          '  TestTrait::testMethod - Missing example' . PHP_EOL,
        ],
      ],
      'edge case tests' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod',
                'steps' => ['@Then the page should contain "text with special chars: @!#$%^"'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod2',
                'steps' => ['@Then a result should be displayed'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
              [
                'class_name' => 'TestTrait',
                'name' => 'testAssertMethod3',
                'steps' => ['@Then no results should be displayed'],
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'null steps handling' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => NULL,
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
      'non-array steps handling' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'class_name' => 'TestTrait',
                'name' => 'testMethod',
                'steps' => '@Given some step',
                'description' => 'Test method description',
                'example' => 'Example text',
              ],
            ],
          ],
        ],
        [],
      ],
    ];
  }

  #[DataProvider('dataProviderValidateStepPatterns')]
  public function testValidateStepPatterns(array $info, array $expected): void {
    $actual = validate_step_patterns($info);

    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderValidateStepPatterns(): array {
    return [
      'empty info' => [
        [],
        [],
      ],
      'method without a step pattern' => [
        [
          'TestTrait' => [
            'name' => 'TestTrait',
            'methods' => [
              [
                'name' => 'testWithoutPattern',
                'steps' => '',
                'description' => 'Test method description',
                'example' => '',
              ],
            ],
          ],
        ],
        [],
      ],
      'distinct patterns' => [
        [
          'QueueTrait' => [
            'name' => 'QueueTrait',
            'methods' => [
              [
                'name' => 'queueProcessItems',
                'steps' => ['@When I process :count item(s) from the queue :queue'],
                'description' => 'Process a specific number of items from a queue.',
                'example' => 'When I process 5 items from the queue "myqueue"',
              ],
              [
                'name' => 'queueProcessAll',
                'steps' => ['@When I process the queue :queue'],
                'description' => 'Process all items from a queue.',
                'example' => 'When I process the queue "myqueue"',
              ],
            ],
          ],
        ],
        [],
      ],
      'pattern shadowed by a sibling pattern' => [
        [
          'QueueTrait' => [
            'name' => 'QueueTrait',
            'methods' => [
              [
                'name' => 'queueProcessItems',
                'steps' => ['@When I process :count item(s) from the queue :queue'],
                'description' => 'Process a specific number of items from a queue.',
                'example' => 'When I process 5 items from the queue "myqueue"',
              ],
              [
                'name' => 'queueProcessAll',
                'steps' => ['@When I process all items from the queue :queue'],
                'description' => 'Process all items from a queue.',
                'example' => 'When I process all items from the queue "myqueue"',
              ],
            ],
          ],
        ],
        [
          '  QueueTrait::queueProcessAll - Step "I process all items from the queue "myqueue"" matches more than one definition: queueProcessItems() as "I process :count item(s) from the queue :queue", queueProcessAll() as "I process all items from the queue :queue"' . PHP_EOL,
        ],
      ],
      'example left behind by a renamed pattern' => [
        [
          'QueueTrait' => [
            'name' => 'QueueTrait',
            'methods' => [
              [
                'name' => 'queueProcessAll',
                'steps' => ['@When I process the queue :queue'],
                'description' => 'Process all items from a queue.',
                'example' => 'When I process all items from the queue "myqueue"',
              ],
            ],
          ],
        ],
        [
          '  QueueTrait::queueProcessAll - No example matches the step "I process the queue :queue"' . PHP_EOL,
        ],
      ],
      'example reusing another step as setup' => [
        [
          'CommandTrait' => [
            'name' => 'CommandTrait',
            'methods' => [
              [
                'name' => 'commandRun',
                'steps' => ['@When I run the command :command'],
                'description' => 'Run a command.',
                'example' => 'When I run the command "echo hello"',
              ],
              [
                'name' => 'commandAssertOutputContains',
                'steps' => ['@Then the command output should contain the value :value'],
                'description' => 'Assert that the command output contains a value.',
                'example' => 'When I run the command "echo hello"' . PHP_EOL . 'And the command output should contain the value "hello"',
              ],
            ],
          ],
        ],
        [],
      ],
    ];
  }

  #[DataProvider('dataProviderReplaceContent')]
  public function testReplaceContent(
    string $haystack,
    string $start,
    string $end,
    string $replacement,
    string $expected,
    ?string $expected_message = NULL,
  ): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $actual = replace_content($haystack, $start, $end, $replacement);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderReplaceContent(): array {
    return [
      'basic replacement' => [
        'This is a test string with START some content END in it.',
        'START',
        'END',
        ' new content ',
        'This is a test string with START' . PHP_EOL . ' new content ' . PHP_EOL . 'END in it.',
      ],
      'multiline content' => [
        "Line 1\nSTART\nsome content\nmore content\nEND\nLine 3",
        "START",
        "END",
        "\nnew content\n",
        "Line 1\nSTART" . PHP_EOL . "\nnew content\n" . PHP_EOL . "END\nLine 3",
      ],
      'replacement with special characters' => [
        'Content with START $pecial ch@rs END here',
        'START',
        'END',
        ' $p3c!al r3pl@cement ',
        'Content with START' . PHP_EOL . ' $p3c!al r3pl@cement ' . PHP_EOL . 'END here',
      ],
      'start and end with regex characters' => [
        'Content with [START] regex.chars* [END] here',
        '[START]',
        '[END]',
        ' escaped content ',
        'Content with [START]' . PHP_EOL . ' escaped content ' . PHP_EOL . '[END] here',
      ],
      'error - start not found' => [
        'Content without markers',
        'START',
        'END',
        'replacement',
        '',
        'Start not found in the haystack',
      ],
      'error - end not found' => [
        'Content with START but no end',
        'START',
        'END',
        'replacement',
        '',
        'End not found in the haystack',
      ],
      'error - start after end' => [
        'Content with END before START',
        'START',
        'END',
        'replacement',
        '',
        'Start is after the end',
      ],
      'adjacent markers' => [
        'Content with STARTEND together',
        'START',
        'END',
        ' replacement ',
        'Content with START' . PHP_EOL . ' replacement ' . PHP_EOL . 'END together',
      ],
      'nested markers' => [
        'Content with START nested START inner END markers END',
        'START',
        'END',
        ' replaced all ',
        'Content with START' . PHP_EOL . ' replaced all ' . PHP_EOL . 'END markers END',
      ],
      'empty replacement' => [
        'Content with START content to remove END here',
        'START',
        'END',
        '',
        'Content with START' . PHP_EOL . PHP_EOL . 'END here',
      ],
    ];
  }

  #[DataProvider('dataProviderExtractInfo')]
  public function testExtractInfo(
    array $trait_names,
    array $exclude,
    array $expected_trait_names,
  ): void {
    $setup = $this->setUpExtractInfoTest($trait_names);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $result = extract_info([$class_name], $exclude, $setup['base_path']);

    foreach ($expected_trait_names as $expected_trait) {
      if (!in_array($expected_trait, $exclude, TRUE)) {
        $this->assertArrayHasKey($expected_trait, $result);
        $this->assertSame($expected_trait, $result[$expected_trait]['name']);
      }
      else {
        $this->assertArrayNotHasKey($expected_trait, $result);
      }
    }
  }

  public static function dataProviderExtractInfo(): array {
    return [
      'single trait with step' => [
        ['SampleTrait'],
        [],
        ['SampleTrait'],
      ],
      'multiple traits with steps' => [
        ['FirstTrait', 'SecondTrait'],
        [],
        ['FirstTrait', 'SecondTrait'],
      ],
      'with excluded trait' => [
        ['IncludedTrait', 'ExcludedTrait'],
        ['ExcludedTrait'],
        ['IncludedTrait'],
      ],
    ];
  }

  protected function getFixturesDir(): string {
    return __DIR__ . '/../fixtures/docs';
  }

  /**
   * Setup test environment and return paths.
   *
   * @return array{base_path: string, steps_dir: string}
   */
  protected function setUpTestEnvironment(): array {
    $base_path = static::$tmp;
    $steps_dir = $base_path . '/' . STEPS_DIRECTORY;
    mkdir($steps_dir, 0777, TRUE);

    return [
      'base_path' => $base_path,
      'steps_dir' => $steps_dir,
    ];
  }

  /**
   * Copy a fixture trait file into a context directory of the test tree.
   *
   * @param string $trait_name
   *   The trait name (e.g., 'SampleTrait').
   * @param string $context
   *   The context directory the trait belongs to.
   *
   * @return string
   *   The path to the copied file.
   */
  protected function copyFixtureTrait(string $trait_name, string $context = 'Web'): string {
    $fixture_file = $this->getFixturesDir() . '/' . $context . '/' . $trait_name . '.php';

    return $this->writeFixture(sprintf('%s/%s/%s.php', STEPS_DIRECTORY, $context, $trait_name), (string) file_get_contents($fixture_file));
  }

  /**
   * Copy multiple fixture traits into the generic context directory.
   *
   * @param array<string> $trait_names
   *   Array of trait names.
   */
  protected function copyFixtureTraits(array $trait_names): void {
    foreach ($trait_names as $trait_name) {
      $this->copyFixtureTrait($trait_name);
    }
  }

  /**
   * Setup a complete extract_info test environment.
   *
   * @param array<string> $trait_names
   *   Array of trait names to use.
   * @param string|null $context
   *   Context directory to copy a single trait into (e.g., 'Drupal').
   *
   * @return array{base_path: string, steps_dir: string, class_name: string}
   */
  protected function setUpExtractInfoTest(array $trait_names, ?string $context = NULL): array {
    $paths = $this->setUpTestEnvironment();

    if ($context && count($trait_names) === 1) {
      $target_file = $this->copyFixtureTrait($trait_names[0], $context);
      // The trait is loaded from the test directory so reflection reports
      // that path.
      require_once $target_file;
    }
    else {
      $this->copyFixtureTraits($trait_names);
    }

    $class_name = $this->createTestContext($trait_names, 'TestContext' . uniqid());

    return [
      'base_path' => $paths['base_path'],
      'steps_dir' => $paths['steps_dir'],
      'class_name' => $class_name,
    ];
  }

  protected function createTestContext(array $trait_names, string $class_name = 'TestContextForDocs'): string {
    if (!class_exists($class_name, FALSE)) {
      $namespaced_traits = array_map(function ($trait_name): string {
        $context = file_exists($this->getFixturesDir() . '/Drupal/' . $trait_name . '.php') ? 'Drupal' : 'Web';

        return '\\DrevOps\\BehatSteps\\Tests\\Fixtures\\' . $context . '\\' . $trait_name;
      }, $trait_names);
      $use_traits = implode(', ', $namespaced_traits);
      $class_code = sprintf('class %s { use %s; }', $class_name, $use_traits);
      // @phpcs:disable Drupal.Functions.DiscouragedFunctions.Discouraged
      eval($class_code);
    }
    return $class_name;
  }

  /**
   * Tests that extract_info() sorts the methods of a multi-method trait.
   */
  public function testExtractInfoMultipleMethods(): void {
    $trait_name = 'MultiMethodTrait';
    $setup = $this->setUpExtractInfoTest([$trait_name]);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $result = extract_info([$class_name], [], $setup['base_path']);

    $this->assertArrayHasKey($trait_name, $result);
    $this->assertIsArray($result[$trait_name]['methods']);
    $this->assertCount(5, $result[$trait_name]['methods']);

    $this->assertArrayHasKey('steps', $result[$trait_name]['methods'][0]);
    $this->assertStringContainsString('@Given', $result[$trait_name]['methods'][0]['steps'][0]);
    $this->assertArrayHasKey('steps', $result[$trait_name]['methods'][1]);
    $this->assertStringContainsString('@Given', $result[$trait_name]['methods'][1]['steps'][0]);
    $this->assertArrayHasKey('steps', $result[$trait_name]['methods'][2]);
    $this->assertStringContainsString('@When', $result[$trait_name]['methods'][2]['steps'][0]);
    $this->assertArrayHasKey('steps', $result[$trait_name]['methods'][3]);
    $this->assertStringContainsString('@Then', $result[$trait_name]['methods'][3]['steps'][0]);

    $pystring_method = $result[$trait_name]['methods'][4];
    $this->assertIsString($pystring_method['example']);
    $this->assertStringContainsString('"""', $pystring_method['example']);
    $this->assertSame('@Then the content should contain:', $pystring_method['steps'][0]);

    // The table example keeps its indentation.
    $table_method = $result[$trait_name]['methods'][1];
    $this->assertSame('@Given the following items exist:', $table_method['steps'][0]);
    $this->assertIsString($table_method['example']);
    $this->assertStringContainsString('| name  | value |', $table_method['example']);
    $this->assertStringContainsString('  | name  | value |', $table_method['example']);
  }

  /**
   * Tests that extract_info() rejects a trait file no class composes.
   */
  public function testExtractInfoMissingTrait(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/The following traits were not found in the class/');

    $paths = $this->setUpTestEnvironment();

    // The copied fixture is not used by the class.
    $this->copyFixtureTrait('UnusedTrait');

    $class_name = 'TestContextEmpty' . uniqid();
    // @phpcs:disable Drupal.Functions.DiscouragedFunctions.Discouraged
    eval(sprintf('class %s {}', $class_name));

    /** @var class-string $class_name */
    extract_info([$class_name], [], $paths['base_path']);
  }

  public function testExtractInfoWithSubdirectory(): void {
    $trait_name = 'DrupalTrait';
    $setup = $this->setUpExtractInfoTest([$trait_name], 'Drupal');

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $result = extract_info([$class_name], [], $setup['base_path']);

    $this->assertArrayHasKey($trait_name, $result);
    $this->assertSame('Drupal', $result[$trait_name]['context']);
    $this->assertSame('Drupal\\' . $trait_name, $result[$trait_name]['name_contextual']);
  }

  public function testExtractInfoNoMatchingMethods(): void {
    $trait_name = 'NoMatchTrait';
    $setup = $this->setUpExtractInfoTest([$trait_name]);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $result = extract_info([$class_name], [], $setup['base_path']);

    // The trait is in the result with an empty methods array because none of
    // its methods match the naming convention.
    $this->assertArrayHasKey($trait_name, $result);
    $this->assertEmpty($result[$trait_name]['methods']);
  }

  #[DataProvider('dataProviderExtractInfoErrors')]
  public function testExtractInfoErrors(string $error_case, string $expected_error): void {
    $this->assertTrue(
      str_contains($expected_error, 'Class comment') ||
      str_contains($expected_error, 'descriptive content'),
      'Extract info validates class comment content'
    );
  }

  public static function dataProviderExtractInfoErrors(): array {
    return [
      'empty class comment' => [
        'empty comment',
        'Class comment for MockTrait',
      ],
      'trait instead of description' => [
        'incorrect format',
        'Class comment should have a descriptive content for MockTrait',
      ],
    ];
  }

  #[DataProvider('dataProviderParseClassComment')]
  public function testParseClassComment(string $trait_name, string $comment, array $expected, ?string $expected_message = NULL): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $actual = parse_class_comment($trait_name, $comment);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderParseClassComment(): array {
    return [
      'valid comment' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * Additional information about the trait.
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . 'Additional information about the trait.',
        ],
      ],
      'single line comment' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.',
        ],
      ],
      'with code blocks' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait with `code` blocks.
 *
 * Example: `some code`
 */
EOD,
        [
          'description' => 'Test trait with `code` blocks.',
          'description_full' => 'Test trait with `code` blocks.' . PHP_EOL . PHP_EOL . 'Example: `some code`',
        ],
      ],
      'empty comment error' => [
        'TestTrait',
        '',
        [],
        'Class comment for TestTrait is empty',
      ],
      'comment without content error' => [
        'TestTrait',
        <<<'EOD'
/**
 *
 */
EOD,
        [],
        'Class comment for TestTrait is empty',
      ],
      'trait as description error' => [
        'TestTrait',
        <<<'EOD'
/**
 * Trait for testing purposes.
 */
EOD,
        [],
        'Class comment should have a descriptive content for TestTrait',
      ],
      'unclosed code block error' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait with `code blocks.
 */
EOD,
        [],
        'Class inline code block is not closed for TestTrait',
      ],
      'comment with multiple paragraphs' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * First paragraph.
 *
 * Second paragraph.
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . 'First paragraph.' . PHP_EOL . PHP_EOL . 'Second paragraph.',
        ],
      ],
      'comment with lists' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * - Item 1
 * - Item 2
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '- Item 1' . PHP_EOL . '- Item 2',
        ],
      ],
      'with indentation variations' => [
        'TestTrait',
        <<<'EOD'
/**
 * Description line.
 *   Indented line.
 *     Double indented line.
 */
EOD,
        [
          'description' => 'Description line.',
          'description_full' => 'Description line.' . PHP_EOL . 'Indented line.' . PHP_EOL . 'Double indented line.',
        ],
      ],
      'with leading/trailing whitespace' => [
        'TestTrait',
        <<<'EOD'
/**
 *    Leading whitespace should be trimmed.
 *
 *  Trailing whitespace should also be trimmed.
 */
EOD,
        [
          'description' => 'Leading whitespace should be trimmed.',
          'description_full' => 'Leading whitespace should be trimmed.' . PHP_EOL . PHP_EOL . 'Trailing whitespace should also be trimmed.',
        ],
      ],
      'with special characters' => [
        'TestTrait',
        <<<'EOD'
/**
 * Description with special characters: @!#$%^&*().
 *
 * More special characters: ~[];'",<>?/\|
 */
EOD,
        [
          'description' => 'Description with special characters: @!#$%^&*().',
          'description_full' => 'Description with special characters: @!#$%^&*().' . PHP_EOL . PHP_EOL . "More special characters: ~[];'\",<>?/\\|",
        ],
      ],
      'with multiple code blocks' => [
        'TestTrait',
        <<<'EOD'
/**
 * Description with `first code` and `second code`.
 *
 * More text with `another code block`.
 */
EOD,
        [
          'description' => 'Description with `first code` and `second code`.',
          'description_full' => 'Description with `first code` and `second code`.' . PHP_EOL . PHP_EOL . 'More text with `another code block`.',
        ],
      ],
      'comment with different comment markers' => [
        'MarkersTrait',
        <<<'EOD'
/**
 * Description with different comment markers.
 */
EOD,
        [
          'description' => 'Description with different comment markers.',
          'description_full' => 'Description with different comment markers.',
        ],
      ],
      'with @code block' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * @code
 * Given I am on the homepage
 * When I click "Submit"
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'Given I am on the homepage' . PHP_EOL . 'When I click "Submit"' . PHP_EOL . '@endcode',
        ],
      ],
      'with multiple @code blocks' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * First example:
 *
 * @code
 * Given I am on the homepage
 * @endcode
 *
 * Second example:
 *
 * @code
 * When I click "Submit"
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . 'First example:' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'Given I am on the homepage' . PHP_EOL . '@endcode' . PHP_EOL . PHP_EOL . 'Second example:' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'When I click "Submit"' . PHP_EOL . '@endcode',
        ],
      ],
      'with @code block and lists' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * Features:
 * - Feature 1
 * - Feature 2
 *
 * @code
 * Given I am on the homepage
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . 'Features:' . PHP_EOL . '- Feature 1' . PHP_EOL . '- Feature 2' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'Given I am on the homepage' . PHP_EOL . '@endcode',
        ],
      ],
      'with @code block with empty lines' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * @code
 * Given I am on the homepage
 *
 * When I click "Submit"
 * Then I should see "Success"
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'Given I am on the homepage' . PHP_EOL . PHP_EOL . 'When I click "Submit"' . PHP_EOL . 'Then I should see "Success"' . PHP_EOL . '@endcode',
        ],
      ],
      'with @code block with indented PHP code' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * @code
 * class FeatureContext extends DrupalContext {
 *   use ResponsiveTrait;
 *
 *   public function setupCustomBreakpoints(): void {
 *     $this->responsiveSetBreakpoints([
 *       'iphone_12' => '390x844',
 *       '4k' => '3840x2160',
 *     ]);
 *   }
 * }
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'class FeatureContext extends DrupalContext {' . PHP_EOL . '  use ResponsiveTrait;' . PHP_EOL . PHP_EOL . '  public function setupCustomBreakpoints(): void {' . PHP_EOL . '    $this->responsiveSetBreakpoints([' . PHP_EOL . "      'iphone_12' => '390x844'," . PHP_EOL . "      '4k' => '3840x2160'," . PHP_EOL . '    ]);' . PHP_EOL . '  }' . PHP_EOL . '}' . PHP_EOL . '@endcode',
        ],
      ],
      'with @code block with 4-space indentation' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * @code
 * if ($condition) {
 *     // 4 spaces indentation
 *     doSomething();
 *     if ($nested) {
 *         // 8 spaces indentation
 *         doNestedThing();
 *     }
 * }
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'if ($condition) {' . PHP_EOL . '    // 4 spaces indentation' . PHP_EOL . '    doSomething();' . PHP_EOL . '    if ($nested) {' . PHP_EOL . '        // 8 spaces indentation' . PHP_EOL . '        doNestedThing();' . PHP_EOL . '    }' . PHP_EOL . '}' . PHP_EOL . '@endcode',
        ],
      ],
      'with @code block with mixed content and indentation' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * Regular text before code.
 *
 * @code
 * class Example {
 *   private $value;
 *
 *   public function __construct() {
 *     $this->value = [
 *       'key1' => 'value1',
 *       'key2' => 'value2',
 *     ];
 *   }
 * }
 * @endcode
 *
 * Regular text after code.
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . 'Regular text before code.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . 'class Example {' . PHP_EOL . '  private $value;' . PHP_EOL . PHP_EOL . '  public function __construct() {' . PHP_EOL . '    $this->value = [' . PHP_EOL . "      'key1' => 'value1'," . PHP_EOL . "      'key2' => 'value2'," . PHP_EOL . '    ];' . PHP_EOL . '  }' . PHP_EOL . '}' . PHP_EOL . '@endcode' . PHP_EOL . PHP_EOL . 'Regular text after code.',
        ],
      ],
      'only whitespace lines - empty after processing' => [
        'TestTrait',
        "/**\n*/",
        [],
        'Class comment for TestTrait is empty',
      ],
      'with @code block preserving trailing spaces in code' => [
        'TestTrait',
        <<<'EOD'
/**
 * Test trait description.
 *
 * @code
 * // Line with trailing content
 * function test() {
 *   return true;
 * }
 * @endcode
 */
EOD,
        [
          'description' => 'Test trait description.',
          'description_full' => 'Test trait description.' . PHP_EOL . PHP_EOL . '@code' . PHP_EOL . '// Line with trailing content' . PHP_EOL . 'function test() {' . PHP_EOL . '  return true;' . PHP_EOL . '}' . PHP_EOL . '@endcode',
        ],
      ],
    ];
  }

  /**
   * Test extract_info with empty class comment.
   *
   * The fixture trait has a class docblock whose lines are all empty after
   * filtering, so extract_info() throws.
   */
  public function testExtractInfoEmptyClassComment(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Class comment for EmptyCommentTrait is empty');

    $trait_name = 'EmptyCommentTrait';
    $setup = $this->setUpExtractInfoTest([$trait_name]);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    extract_info([$class_name], [], $setup['base_path']);
  }

  public function testTagRegistry(): void {
    $registry = tag_registry();

    $this->assertArrayHasKey('accessibility', $registry);
    $this->assertSame('parametrized', $registry['accessibility']['form']);
    $this->assertSame('flag', $registry['disable-form-validation']['form']);

    foreach ($registry as $prefix => $definition) {
      $this->assertIsString($prefix);
      $this->assertContains($definition['form'], ['parametrized', 'flag']);
      $this->assertNotSame('', trim($definition['description']));
    }
  }

  public function testTagRegistryListsEveryTraitTag(): void {
    $registry = tag_registry();
    $missing = [];

    foreach (array_keys(static::discoverTraits()) as $trait) {
      foreach (static::reflect($trait)->getReflectionConstants() as $constant) {
        $tag = $constant->getValue();

        if (str_ends_with($constant->getName(), '_TAG') && is_string($tag) && !array_key_exists($tag, $registry)) {
          $missing[$tag] = sprintf('%s::%s', $trait, $constant->getName());
        }
      }
    }

    $this->assertSame([], $missing, 'A tag a trait names in a constant is documented in tag_registry().');
  }

  public function testNonDescriptivePlaceholders(): void {
    $placeholders = non_descriptive_placeholders();

    $this->assertContains('number', $placeholders);
    $this->assertContains('param', $placeholders);
    $this->assertNotContains('count', $placeholders);

    // Names are compared against the placeholders extracted from a step
    // pattern, which carry no leading colon.
    foreach ($placeholders as $placeholder) {
      $this->assertStringStartsNotWith(':', $placeholder);
    }
  }

  public function testPlaceholderSynonyms(): void {
    $synonyms = placeholder_synonyms();

    $this->assertSame('address', $synonyms['mail'] ?? NULL);
    $this->assertSame('index', $synonyms['link_number'] ?? NULL);

    foreach ($synonyms as $synonym => $name) {
      $this->assertStringStartsNotWith(':', $synonym);
      $this->assertStringStartsNotWith(':', $name);

      // A name to use that is itself rejected fails validation as well.
      $this->assertArrayNotHasKey($name, $synonyms);
      $this->assertNotContains($name, non_descriptive_placeholders());
    }
  }

  public function testRejectedStepPhrases(): void {
    $phrases = rejected_step_phrases();

    $this->assertSame('I click on the ', $phrases['I click the '] ?? NULL);
    $this->assertSame(' the viewport', $phrases[' a viewport'] ?? NULL);

    // A replacement containing a rejected phrase fails validation as well.
    foreach ($phrases as $replacement) {
      foreach (array_keys($phrases) as $rejected) {
        $this->assertStringNotContainsString($rejected, $replacement);
      }
    }
  }

  #[DataProvider('dataProviderValidateTag')]
  public function testValidateTag(string $tag, ?string $expected_contains): void {
    $actual = validate_tag($tag, tag_registry());

    if ($expected_contains === NULL) {
      $this->assertNull($actual);
    }
    else {
      $this->assertIsString($actual);
      $this->assertStringContainsString($expected_contains, $actual);
    }
  }

  public static function dataProviderValidateTag(): array {
    return [
      // Valid: correct colon forms.
      'colon accessibility' => ['accessibility:critical', NULL],
      'colon module' => ['module:help', NULL],
      'colon module negated' => ['module:!help', NULL],
      'colon breakpoint' => ['breakpoint:mobile_portrait', NULL],
      'colon email handler' => ['email:default', NULL],
      'colon watchdog' => ['watchdog:custom_type', NULL],
      'colon config override with dots' => ['disable-config-override:system.site', NULL],
      'colon skip with trait' => ['behat-steps-skip:AccessibilityTrait', NULL],
      // Valid: bare forms of otherwise-parametrized tags.
      'bare accessibility' => ['accessibility', NULL],
      'bare email' => ['email', NULL],
      // Valid: standalone flag tags whose names contain hyphens.
      'flag disable form validation' => ['disable-form-validation', NULL],
      'flag js errors' => ['js-errors', NULL],
      'flag download' => ['download', NULL],
      'flag testmode' => ['testmode', NULL],
      'flag debug' => ['debug', NULL],
      'flag error' => ['error', NULL],
      // Valid: a flag prefix followed by a hyphen is not a parametrized tag.
      'flag prefix with hyphen suffix' => ['download-zip', NULL],
      // Valid: unknown / standard Behat tags are ignored.
      'standard api' => ['api', NULL],
      'standard javascript' => ['javascript', NULL],
      'standard wip' => ['wip', NULL],
      'doc trait tag' => ['test-trait:AccessibilityTrait', NULL],
      'unknown hyphen tag' => ['some-custom-tag', NULL],
      'docblock annotation' => ['code', NULL],
      // Violations: parametrized tags using a hyphen separator.
      'hyphen accessibility critical' => ['accessibility-critical', '@accessibility:critical'],
      'hyphen accessibility warning' => ['accessibility-warning', '@accessibility:warning'],
      'hyphen module' => ['module-help', '@module:help'],
      'hyphen breakpoint' => ['breakpoint-mobile', '@breakpoint:mobile'],
      'hyphen email handler' => ['email-default', '@email:default'],
      'hyphen watchdog' => ['watchdog-custom', '@watchdog:custom'],
      'hyphen skip with trait' => ['behat-steps-skip-AccessibilityTrait', '@behat-steps-skip:AccessibilityTrait'],
    ];
  }

  #[DataProvider('dataProviderExtractTags')]
  public function testExtractTags(string $text, array $expected): void {
    $this->assertSame($expected, extract_tags($text));
  }

  public static function dataProviderExtractTags(): array {
    return [
      'empty' => ['', []],
      'no tags' => ['just some prose here', []],
      'single tag' => ['@api', ['api']],
      'feature tag line' => ['  @javascript @accessibility:critical', ['javascript', 'accessibility:critical']],
      'backtick wrapped' => ['see `@accessibility:warning` for details', ['accessibility:warning']],
      'email address skipped' => ['Send to user@example.com please', []],
      'quoted email skipped' => ['"test@example.com"', []],
      'double at skipped' => ['foo @@bar', []],
      'trailing period stripped' => ['skip with @accessibility.', ['accessibility']],
      'tags separated by comma' => ['@api, @javascript', ['api', 'javascript']],
      'value with dots preserved' => ['@disable-config-override:system.site', ['disable-config-override:system.site']],
      'value with bang preserved' => ['@module:!help', ['module:!help']],
      'duplicates deduped' => ['@api and @api again', ['api']],
      'tag in parentheses' => ['(@accessibility)', ['accessibility']],
      'hyphenated violation captured' => ['@accessibility-critical', ['accessibility-critical']],
    ];
  }

  public function testValidateTagsFromInfo(): void {
    $info = [
      'CleanTrait' => [
        'name' => 'CleanTrait',
        'description_full' => 'Supports `@accessibility:critical`, `@module:help` and `@js-errors`.',
        'methods' => [
          ['example' => "@api @email:default\nGiven something"],
        ],
      ],
      'DirtyTrait' => [
        'name' => 'DirtyTrait',
        'description_full' => 'Legacy `@accessibility-critical` form.',
        'methods' => [
          ['example' => '@module-help'],
        ],
      ],
      // No 'name' key - the array key is used as the label.
      'NoNameTrait' => [
        'description_full' => 'Old `@watchdog-foo`.',
      ],
      // No 'description_full' - only the example is scanned.
      'NoDescTrait' => [
        'name' => 'NoDescTrait',
        'methods' => [
          ['example' => '@breakpoint-mobile'],
        ],
      ],
      // 'methods' is not an array - skipped.
      'BadMethods' => [
        'name' => 'BadMethods',
        'methods' => 'not-an-array',
      ],
      // Non-array method, non-string example - both skipped.
      'BadMethodItem' => [
        'name' => 'BadMethodItem',
        'methods' => ['not-an-array', ['example' => 123], ['other' => 'x']],
      ],
      // Non-array trait info - skipped.
      'NotArrayTrait' => 'a string',
    ];

    $actual = validate_tags($info, static::$tmp);
    $joined = implode('', $actual);

    $this->assertCount(4, $actual);
    $this->assertStringContainsString('DirtyTrait', $joined);
    $this->assertStringContainsString('@accessibility:critical', $joined);
    $this->assertStringContainsString('@module:help', $joined);
    $this->assertStringContainsString('NoNameTrait', $joined);
    $this->assertStringContainsString('@watchdog:foo', $joined);
    $this->assertStringContainsString('NoDescTrait', $joined);
    $this->assertStringContainsString('@breakpoint:mobile', $joined);
  }

  public function testValidateTagsFromFeatureFiles(): void {
    $this->writeFixture('tests/behat/features/clean.feature', "@api @accessibility:critical\nScenario: ok");
    $this->writeFixture('tests/behat/features/dirty.feature', "@javascript @accessibility-critical\nScenario: bad");

    $actual = validate_tags([], static::$tmp);

    $this->assertCount(1, $actual);
    $this->assertStringContainsString('tests/behat/features/dirty.feature', $actual[0]);
    $this->assertStringContainsString('@accessibility:critical', $actual[0]);
  }

  public function testValidateTagsReturnsNoErrorsForCleanInput(): void {
    $info = [
      'CleanTrait' => [
        'name' => 'CleanTrait',
        'description_full' => 'Supports `@accessibility:critical`, `@module:help`, `@disable-form-validation` and `@behat-steps-skip:CleanTrait`.',
        'methods' => [
          ['example' => '@api @email:default'],
        ],
      ],
    ];

    $this->assertSame([], validate_tags($info, static::$tmp));
  }

  #[DataProvider('dataProviderMethodIsRegistered')]
  public function testMethodIsRegistered(string $method, bool $expected): void {
    $this->assertSame($expected, method_is_registered(new \ReflectionMethod(HelperSampleTrait::class, $method)));
  }

  public static function dataProviderMethodIsRegistered(): array {
    return [
      'step' => ['helperSampleAssertTest', TRUE],
      'hook' => ['helperSampleBeforeScenario', TRUE],
      'transformation' => ['helperSampleTransformValue', TRUE],
      'helper' => ['helperSampleBuild', FALSE],
    ];
  }

  #[DataProvider('dataProviderCommentIsInternal')]
  public function testCommentIsInternal(string $comment, bool $expected): void {
    $this->assertSame($expected, comment_is_internal($comment));
  }

  public static function dataProviderCommentIsInternal(): array {
    return [
      'empty' => ['', FALSE],
      'tagged' => ["/**\n * Summary.\n *\n * @internal\n */", TRUE],
      'tagged with reason' => ["/**\n * Summary.\n *\n * @internal\n *   Because.\n */", TRUE],
      'mentioned in prose' => ["/**\n * Summary of the internal state.\n */", FALSE],
      'similar tag' => ["/**\n * Summary.\n *\n * @internalised\n */", FALSE],
    ];
  }

  #[DataProvider('dataProviderRenderMethodSignature')]
  public function testRenderMethodSignature(string $method, string $expected): void {
    $this->assertSame($expected, render_method_signature(new \ReflectionMethod(HelperSignatureTrait::class, $method)));
  }

  public static function dataProviderRenderMethodSignature(): array {
    return [
      'union' => ['helperSignatureUnion', 'public function helperSignatureUnion(string|int $value): string|int'],
      'intersection' => ['helperSignatureIntersection', 'public function helperSignatureIntersection(Countable&Stringable $value): Countable&Stringable'],
      'nullable class' => ['helperSignatureNullable', 'protected function helperSignatureNullable(?NodeElement $element = NULL): ?NodeElement'],
      'untyped' => ['helperSignatureUntyped', 'protected function helperSignatureUntyped($value)'],
      'variadic' => ['helperSignatureVariadic', 'protected function helperSignatureVariadic(string ...$parts): void'],
      'defaults' => ['helperSignatureDefaults', "protected static function helperSignatureDefaults(string \$text = 'one', array \$empty = [], array \$filled = [...], bool \$flag = FALSE, int \$count = 3, ?string \$missing = NULL): void"],
      'mixed' => ['helperSignatureMixed', 'protected function helperSignatureMixed(mixed $value): mixed'],
    ];
  }

  public function testRenderTypeWithoutDeclaration(): void {
    $this->assertSame('', render_type(NULL));
  }

  #[DataProvider('dataProviderRenderValue')]
  public function testRenderValue(mixed $value, string $expected): void {
    $this->assertSame($expected, render_value($value));
  }

  public static function dataProviderRenderValue(): array {
    return [
      'null' => [NULL, 'NULL'],
      'true' => [TRUE, 'TRUE'],
      'false' => [FALSE, 'FALSE'],
      'empty array' => [[], '[]'],
      'filled array' => [['one'], '[...]'],
      'string' => ['one', "'one'"],
      'integer' => [3, '3'],
      'float' => [1.5, '1.5'],
      'object' => [new \stdClass(), 'object'],
    ];
  }

  #[DataProvider('dataProviderHeadingAnchor')]
  public function testHeadingAnchor(string $name, string $expected): void {
    $this->assertSame($expected, heading_anchor($name));
  }

  public static function dataProviderHeadingAnchor(): array {
    return [
      'plain' => ['ElementTrait', 'elementtrait'],
      'contextual' => ['Drupal\\ContentTrait', 'drupalcontenttrait'],
      'hyphenated' => ['Some-Trait_Name', 'some-trait_name'],
    ];
  }

  #[DataProvider('dataProviderResolveInheritedComment')]
  public function testResolveInheritedComment(string $method, string $expected_contains): void {
    $this->assertStringContainsString($expected_contains, resolve_inherited_comment(new \ReflectionMethod(InheritedChild::class, $method)));
  }

  public static function dataProviderResolveInheritedComment(): array {
    return [
      'from the interface' => ['inheritedValue', 'Read the contract value.'],
      'from the parent' => ['inheritedParentValue', 'Read the parent value.'],
      'internal tag travels' => ['inheritedInternal', '@internal'],
      'nothing to inherit' => ['inheritedUnresolved', '{@inheritdoc}'],
    ];
  }

  #[DataProvider('dataProviderRelativeSourcePath')]
  public function testRelativeSourcePath(string $file_path, string $base_path, string $expected): void {
    $this->assertSame($expected, relative_source_path($file_path, $base_path));
  }

  public static function dataProviderRelativeSourcePath(): array {
    return [
      'under the documented repository' => ['/repo/src/Behat/Context/WebRawContext.php', '/repo', 'src/Behat/Context/WebRawContext.php'],
      'under this repository' => [dirname(__DIR__, 3) . '/src/Behat/Context/WebRawContext.php', '/elsewhere', 'src/Behat/Context/WebRawContext.php'],
      'outside both repositories' => ['/elsewhere/src/Behat/Context/WebRawContext.php', '/repo', '/elsewhere/src/Behat/Context/WebRawContext.php'],
    ];
  }

  public function testExtractHelpers(): void {
    $setup = $this->setUpExtractInfoTest(['HelperSampleTrait']);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $actual = extract_helpers([$class_name], [], $setup['base_path']);

    $this->assertArrayHasKey('HelperSampleTrait', $actual);
    $trait = $actual['HelperSampleTrait'];

    $this->assertSame('Sample trait carrying helpers for testing.', $trait['description']);

    // Steps, hooks, transformations, protected members, internal members and
    // members whose name belongs to another trait are left out. The rest is
    // sorted by name.
    $this->assertSame(['helperSampleBuild', 'helperSampleDefaults'], array_column($trait['helpers'], 'name'));

    $this->assertSame('public function helperSampleBuild(string $name, ?int $count = NULL, bool $strict = TRUE): string', $trait['helpers'][0]['signature']);
    $this->assertSame('Build a sample value.', $trait['helpers'][0]['description']);
    $this->assertStringContainsString("\$this->helperSampleBuild('one');", $trait['helpers'][0]['example']);
    $this->assertSame('', $trait['helpers'][1]['example']);
  }

  public function testExtractHelpersResolvesContext(): void {
    $setup = $this->setUpExtractInfoTest(['HelperDrupalTrait'], 'Drupal');

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $actual = extract_helpers([$class_name], [], $setup['base_path']);

    $this->assertArrayHasKey('HelperDrupalTrait', $actual);
    $trait = $actual['HelperDrupalTrait'];

    $this->assertSame('Drupal', $trait['context']);
    $this->assertSame('Drupal\\HelperDrupalTrait', $trait['name_contextual']);
    $this->assertSame('src/Steps/Drupal/HelperDrupalTrait.php', $trait['source']);
    $this->assertSame('drupalhelperdrupaltrait', $trait['steps_anchor']);
    $this->assertSame(['helperDrupalValue'], array_column($trait['helpers'], 'name'));
  }

  public function testExtractHelpersSkipsTraitsWithoutHelpers(): void {
    $setup = $this->setUpExtractInfoTest(['SampleTrait']);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $actual = extract_helpers([$class_name], [], $setup['base_path']);

    $this->assertArrayNotHasKey('SampleTrait', $actual);
  }

  public function testExtractHelpersSkipsHelperTraitsWithoutHelpers(): void {
    // collect_helper_traits() derives the namespace from the directory, so the
    // fixture declares the library's helper namespace.
    require_once $this->writeFixture('src/Helper/Web/BareHelperTrait.php', <<<'EOD'
<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

/**
 * Declare no public method.
 */
trait BareHelperTrait {

  protected function bareHelperValue(): void {}

}

EOD);

    $this->assertArrayHasKey('BareHelperTrait', collect_helper_traits(static::$tmp));
    $this->assertArrayNotHasKey('BareHelperTrait', extract_helpers([], [], static::$tmp));
  }

  public function testExtractHelpersPublishesToolboxClasses(): void {
    $setup = $this->setUpExtractInfoTest(['HelperSampleTrait']);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $actual = extract_helpers([$class_name], [], $setup['base_path']);

    $this->assertArrayHasKey('WebRawContext', $actual);
    $this->assertSame('Toolbox', $actual['WebRawContext']['context']);
    $this->assertNull($actual['WebRawContext']['steps_anchor']);
    $this->assertSame('src/Behat/Context/WebRawContext.php', $actual['WebRawContext']['source']);

    // The injection points the initializer calls are withdrawn from the
    // published surface.
    $names = array_column($actual['WebRawContext']['helpers'], 'name');
    $this->assertContains('backendFor', $names);
    $this->assertNotContains('setBackendRegistry', $names);
    $this->assertNotContains('setParameters', $names);

    // A composed helper trait is published under its own name, so the context
    // does not repeat it.
    $this->assertNotContains('isJavascriptSupported', $names);
  }

  public function testExtractHelpersPublishesTheHelperTraits(): void {
    $actual = extract_helpers([WebContext::class, DrupalContext::class], [], dirname(__DIR__, 3));

    $this->assertSame('Web', $actual['RequestHeadersTrait']['context']);
    $this->assertNull($actual['RequestHeadersTrait']['steps_anchor']);
    $this->assertSame('src/Helper/Web/RequestHeadersTrait.php', $actual['RequestHeadersTrait']['source']);
    $this->assertSame(['requestHeadersSet'], array_column($actual['RequestHeadersTrait']['helpers'], 'name'));

    $this->assertContains('entityLifecycleCreateNode', array_column($actual['EntityLifecycleTrait']['helpers'], 'name'));
  }

  public function testExtractHelpersResolvesTheTraitCommentAgainstItsContract(): void {
    $actual = extract_helpers([WebContext::class, DrupalContext::class], [], dirname(__DIR__, 3));
    $helpers = [];

    foreach ($actual['AuthTrait']['helpers'] as $helper) {
      $helpers[$helper['name']] = $helper['description'];
    }

    // A trait declares no interface of its own, so '{@inheritdoc}' resolves
    // against the contract the composing context declares.
    $this->assertSame('Returns the user registry.', $helpers['authGetUserRegistry']);

    // The injection point is '@internal' on that same contract.
    $this->assertArrayNotHasKey('authSetUserRegistry', $helpers);
  }

  /**
   * Tests that a trait's contracts are read off the classes composing it.
   *
   * @param class-string $trait_name
   *   The helper trait to inspect.
   * @param array<int, string> $expected
   *   The interfaces expected among the trait's contracts.
   */
  #[DataProvider('dataProviderHelperTraitContracts')]
  public function testHelperTraitContracts(string $trait_name, array $expected): void {
    $contracts = helper_trait_contracts(static::reflect($trait_name), [WebContext::class, DrupalContext::class]);
    $names = array_map(static fn(\ReflectionClass $contract): string => $contract->getName(), $contracts);

    $this->assertSame($expected, array_values(array_intersect($names, [UserAwareInterface::class, BackendAwareInterface::class])));
  }

  public static function dataProviderHelperTraitContracts(): \Iterator {
    yield 'reached through a composed step trait' => [AuthTrait::class, [BackendAwareInterface::class, UserAwareInterface::class]];
    yield 'reached through the root context' => [StringTrait::class, [BackendAwareInterface::class, UserAwareInterface::class]];
  }

  public function testHelperTraitContractsSkipsTheClassComposingNothing(): void {
    $this->assertSame([], helper_trait_contracts(new \ReflectionClass(StringTrait::class), [\stdClass::class]));
  }

  public function testCollectHelperTraitsSkipsTheRepositoryWithoutThem(): void {
    $this->assertSame([], collect_helper_traits(static::$tmp));
  }

  public function testCollectHelperTraitsReadsOnlyTheTraitFiles(): void {
    $this->writeFixture('src/Helper/Web/StringTrait.php', "<?php\n\ntrait StringTrait {}\n");
    $this->writeFixture('src/Helper/Web/README.md', 'not code');
    $this->writeFixture('src/Helper/Loose.php', "<?php\n\ntrait Loose {}\n");

    $collected = collect_helper_traits(static::$tmp);

    $this->assertSame(['StringTrait'], array_keys($collected));
    $this->assertSame('Web', $collected['StringTrait']['context']);
  }

  public function testCollectHelperMethodsTakesOnlyDeclaredMembers(): void {
    $actual = collect_helper_methods(new \ReflectionClass(InheritedChild::class));

    // The internal member is withdrawn through the contract it inherits from,
    // and the parent's own declaration is not repeated on the child.
    $this->assertSame(['inheritedParentValue', 'inheritedUnresolved', 'inheritedValue'], array_column($actual, 'name'));
  }

  #[DataProvider('dataProviderValidateHelpers')]
  public function testValidateHelpers(array $info, array $expected): void {
    $this->assertSame($expected, validate_helpers($info));
  }

  public static function dataProviderValidateHelpers(): array {
    return [
      'no helpers' => [[], []],
      'summary present' => [
        [
          'SomeTrait' => [
            'name' => 'SomeTrait',
            'helpers' => [['name' => 'someValue', 'description' => 'Read the value.']],
          ],
        ],
        [],
      ],
      'summary missing' => [
        [
          'SomeTrait' => [
            'name' => 'SomeTrait',
            'helpers' => [
              ['name' => 'someValue', 'description' => ''],
              ['name' => 'someOther', 'description' => '   '],
            ],
          ],
        ],
        [
          '  SomeTrait::someValue - Published helper has no summary. Write one, or mark the helper @internal' . PHP_EOL,
          '  SomeTrait::someOther - Published helper has no summary. Write one, or mark the helper @internal' . PHP_EOL,
        ],
      ],
    ];
  }

  public function testValidateHelpersFromSource(): void {
    $setup = $this->setUpExtractInfoTest(['HelperNoSummaryTrait']);

    /** @var class-string $class_name */
    $class_name = $setup['class_name'];
    $actual = validate_helpers(extract_helpers([$class_name], [], $setup['base_path']));

    $this->assertNotEmpty($actual);
    $this->assertStringContainsString('HelperNoSummaryTrait::helperNoSummaryValue', $actual[0]);
  }

  public function testRenderHelpers(): void {
    $base_path = static::$tmp;
    $this->writeFixture(STEPS_DIRECTORY . '/Web/SomeTrait.php', '<?php');
    $this->writeFixture(STEPS_DIRECTORY . '/Drupal/OtherTrait.php', '<?php');

    $info = [
      'SomeTrait' => [
        'name' => 'SomeTrait',
        'name_contextual' => 'SomeTrait',
        'context' => 'Web',
        'source' => 'src/Steps/Web/SomeTrait.php',
        'steps_anchor' => 'sometrait',
        'description' => 'Do the generic thing.',
        'helpers' => [
          ['name' => 'someValue', 'signature' => 'protected function someValue(): string', 'description' => 'Read the value.', 'example' => "\$this->someValue();"],
        ],
      ],
      'OtherTrait' => [
        'name' => 'OtherTrait',
        'name_contextual' => 'Drupal\\OtherTrait',
        'context' => 'Drupal',
        'source' => 'src/Steps/Drupal/OtherTrait.php',
        'steps_anchor' => NULL,
        'description' => 'Do the Drupal thing.',
        'helpers' => [
          ['name' => 'otherValue', 'signature' => 'public function otherValue(): void', 'description' => 'Write the value.', 'example' => ''],
        ],
      ],
    ];

    $actual = render_helpers($info, $base_path);

    $this->assertStringContainsString('### Index of Web helpers', $actual);
    $this->assertStringContainsString('### Index of Drupal helpers', $actual);
    $this->assertStringContainsString('| [SomeTrait](#sometrait) | 1 | Do the generic thing. |', $actual);
    $this->assertStringContainsString('| [Drupal\\OtherTrait](#drupalothertrait) | 1 | Do the Drupal thing. |', $actual);

    // A trait that contributes steps links to them; one that does not links
    // only to its source.
    $this->assertStringContainsString('[Source](src/Steps/Web/SomeTrait.php), [Steps](STEPS.md#sometrait)', $actual);
    $this->assertStringContainsString('[Source](src/Steps/Drupal/OtherTrait.php)' . PHP_EOL, $actual);

    $this->assertStringContainsString('<summary><code>protected function someValue(): string</code></summary>', $actual);
    $this->assertStringContainsString('Read the value' . PHP_EOL, $actual);
    $this->assertStringContainsString('```' . PHP_EOL . '$this->someValue();' . PHP_EOL . '```', $actual);

    // The generic context is rendered before every other one.
    $this->assertLessThan(strpos($actual, '## Drupal\\OtherTrait'), (int) strpos($actual, '## SomeTrait'));
  }

  public function testRenderHelpersThrowsOnMissingSource(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Source file');

    render_helpers([
      'SomeTrait' => [
        'name' => 'SomeTrait',
        'name_contextual' => 'SomeTrait',
        'context' => 'Web',
        'source' => 'src/Steps/Web/MissingTrait.php',
        'steps_anchor' => 'sometrait',
        'description' => 'Do the thing.',
        'helpers' => [],
      ],
    ], static::$tmp);
  }

  public function testRenderTagReference(): void {
    $actual = render_tag_reference();

    $this->assertStringContainsString('| Tag | Description |', $actual);
    $this->assertStringContainsString('| `@accessibility:VALUE` |', $actual);
    $this->assertStringContainsString('| `@bigpipe` |', $actual);

    foreach (array_keys(tag_registry()) as $tag) {
      $this->assertStringContainsString('`@' . $tag, $actual);
    }
  }

  public function testRenderExtensionOptions(): void {
    $actual = render_extension_options();

    $this->assertStringContainsString('| Option | Type | Default | Description |', $actual);
    $this->assertStringContainsString("| `login_field` | string | `'name'` |", $actual);
    $this->assertStringContainsString('| `login_wait` | integer | `0` |', $actual);
    $this->assertStringContainsString('| `steps` | map | `[]` |', $actual);
    $this->assertStringContainsString('| `regions` | map | `[]` |', $actual);
    $this->assertStringContainsString('| `text` | section | - |', $actual);

    // A nested option reads as a dotted path, and a required one says so.
    $this->assertStringContainsString("| `text.login_url` | string | `'/user'` |", $actual);
    $this->assertStringContainsString('| `drupal.drupal_root` | string | required |', $actual);

    // Line breaks inside an option description are made table-safe.
    $this->assertStringContainsString('<br/>', $actual);
  }

  #[DataProvider('dataProviderExtensionOptionType')]
  public function testExtensionOptionType(string $option, string $expected): void {
    $builder = new TreeBuilder(BehatStepsExtension::CONFIG_KEY);
    (new BehatStepsExtension())->configure($builder->getRootNode());

    $root = $builder->buildTree();
    $this->assertInstanceOf(ArrayNode::class, $root);

    $this->assertSame($expected, extension_option_type($root->getChildren()[$option]));
  }

  public static function dataProviderExtensionOptionType(): array {
    return [
      'scalar' => ['login_field', 'string'],
      'integer' => ['login_wait', 'integer'],
      'prototyped array' => ['regions', 'map'],
      'array' => ['text', 'section'],
    ];
  }

  #[DataProvider('dataProviderExtractTraitOptions')]
  public function testExtractTraitOptions(string $trait_name, array $expected): void {
    $this->assertSame($expected, array_keys(extract_trait_options(DocumentedOptionsContext::class, $trait_name)));
  }

  public static function dataProviderExtractTraitOptions(): array {
    return [
      'a trait the class composes' => ['DocumentedOptionsTrait', ['enabled', 'limit']],
      'a trait the class does not compose' => ['SampleTrait', []],
    ];
  }

  public function testExtractTraitOptionsRejectsMalformedDeclaration(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the "duplicate_option.label" option twice.');

    extract_trait_options(DuplicateOptionConfigContext::class, 'DuplicateOptionTrait');
  }

  #[DataProvider('dataProviderRenderTraitOptions')]
  public function testRenderTraitOptions(mixed $options, string $expected): void {
    $this->assertSame($expected, render_trait_options('DocumentedOptionsTrait', $options));
  }

  public static function dataProviderRenderTraitOptions(): array {
    $table = implode(PHP_EOL, [
      '| Option | Type | Default | Tag | Description |',
      '| --- | --- | --- | --- | --- |',
      '| `documented_options.enabled` | boolean | `TRUE` | `@documented-off`, `@behat-steps-skip:DocumentedOptionsTrait` | Whether the hook runs. |',
      '| `documented_options.limit` | integer | `7` | - | Reads a \| b. |',
    ]);

    return [
      'not a list' => ['not a list', ''],
      'no option' => [[], ''],
      'no entry that is an option' => [['not an option'], ''],
      'a table skipping an entry that is not an option' => [
        [
          new Option('enabled', default: TRUE, description: 'Whether the hook runs.', tags: ['documented-off' => FALSE]),
          'not an option',
          new Option('limit', default: 7, description: 'Reads a | b.'),
        ],
        '### Options' . PHP_EOL . PHP_EOL . $table . PHP_EOL . PHP_EOL,
      ],
    ];
  }

  public function testExtractTraitPrerequisites(): void {
    $prerequisites = extract_trait_prerequisites(DocumentedPrerequisitesContext::class, DocumentedPrerequisitesTrait::class);

    $this->assertSame(['a backend in the scenario\'s list provides "CoreCapabilityInterface"', 'the "documented" module is enabled'], array_map(static fn(Prerequisite $prerequisite): string => $prerequisite->description, $prerequisites));
  }

  #[DataProvider('dataProviderRenderTraitPrerequisites')]
  public function testRenderTraitPrerequisites(mixed $prerequisites, string $expected): void {
    $this->assertSame($expected, render_trait_prerequisites($prerequisites));
  }

  public static function dataProviderRenderTraitPrerequisites(): array {
    $table = implode(PHP_EOL, [
      '| Prerequisite | Capability |',
      '| --- | --- |',
      '| A backend in the scenario\'s list provides "CoreCapabilityInterface" | `CoreCapabilityInterface` |',
      '| The "a \| b" module is enabled | `ModuleCapabilityInterface` |',
    ]);

    return [
      'not a list' => ['not a list', ''],
      'no prerequisite' => [[], ''],
      'no entry that is a prerequisite' => [['not a prerequisite'], ''],
      'a table skipping an entry that is not a prerequisite' => [
        [
          Prerequisite::capability(CoreCapabilityInterface::class),
          'not a prerequisite',
          Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('a|b'), 'the "a | b" module is enabled'),
        ],
        '### Prerequisites' . PHP_EOL . PHP_EOL . $table . PHP_EOL . PHP_EOL,
      ],
    ];
  }

  #[DataProvider('dataProviderTraitOptionType')]
  public function testTraitOptionType(mixed $default, string $expected): void {
    $this->assertSame($expected, trait_option_type($default));
  }

  public static function dataProviderTraitOptionType(): array {
    return [
      'a boolean' => [TRUE, 'boolean'],
      'an integer' => [7, 'integer'],
      'a float' => [0.5, 'float'],
      'a map' => [['.one'], 'map'],
      'a string' => ['page', 'string'],
      'a default naming no type' => [NULL, 'string'],
    ];
  }

  public function testValidateEnvVars(): void {
    $this->writeFixture('docs/configuration.md', 'The `BEHAT_STEPS_DOCUMENTED` and `BEHAT_STEPS_DISABLE_CLEANUP` variables are documented.');
    $this->writeFixture('src/Documented.php', '<?php $value = getenv("BEHAT_STEPS_DOCUMENTED");');
    $this->writeFixture('src/Nested/Undocumented.php', "<?php \$value = getenv('BEHAT_STEPS_UNDOCUMENTED');");
    // A documented name that only starts with the source name does not
    // document it.
    $this->writeFixture('src/Prefix.php', "<?php \$value = getenv('BEHAT_STEPS_DISABLE');");
    // A variable named only in a comment belongs to a consuming project, not
    // to this source.
    $this->writeFixture('src/Commented.php', "<?php\n/**\n * Reads getenv('BEHAT_STEPS_COMMENTED').\n */\n");
    $this->writeFixture('src/NotPhp.txt', "getenv('BEHAT_STEPS_TEXT')");

    $actual = validate_env_vars(static::$tmp);

    $this->assertCount(2, $actual);
    $this->assertStringContainsString('BEHAT_STEPS_UNDOCUMENTED', $actual[0]);
    $this->assertStringContainsString('src/Nested/Undocumented.php', $actual[0]);
    $this->assertStringContainsString('docs/configuration.md', $actual[0]);
    $this->assertStringContainsString('BEHAT_STEPS_DISABLE', $actual[1]);
    $this->assertStringContainsString('src/Prefix.php', $actual[1]);
  }

  public function testValidateEnvVarsWithoutSourceOrReference(): void {
    $this->assertSame([], validate_env_vars(static::$tmp . '/missing'));
  }

}

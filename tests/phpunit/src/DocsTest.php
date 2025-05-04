<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for docs generation.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment
 */
#[CoversFunction('parse_method_comment')]
#[CoversFunction('camel_to_snake')]
#[CoversFunction('array_to_markdown_table')]
#[CoversFunction('render_info')]
#[CoversFunction('validate')]
#[CoversFunction('replace_content')]
class DocsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../docs.php';
  }

  #[DataProvider('dataProviderParseMethodComment')]
  public function testParseMethodComment(string $comment, ?array $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage($exception);
    }

    $actual = parse_method_comment($comment);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderParseMethodComment(): array {
    return [
      'empty' => [
        '',
        NULL,
      ],
      'no steps' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @param string $test
 */
EOD,
        NULL,
      ],
      'with steps' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'multiple steps' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
 * @When I click on the button
 * @Then I should see the text
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage', '@When I click on the button', '@Then I should see the text'],
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'with example' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
 *
 * @code
 * Given I am on the homepage
 * @endcode
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description.',
          'example' => "Given I am on the homepage\n",
        ],
      ],
      'with indented example' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
 *
 * @code
 *   Given I am on the homepage
 *   When I click "Submit"
 * @endcode
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description.',
          'example' => "Given I am on the homepage\nWhen I click \"Submit\"\n",
        ],
      ],
      'multiline description' => [
        <<<'EOD'
/**
 * This is a description
 * that spans multiple lines.
 *
 * @Given I am on the homepage
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description',
          'example' => '',
        ],
      ],
      'steps out of order' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @When I click on the button
 * @Given I am on the homepage
 * @Then I should see the text
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage', '@When I click on the button', '@Then I should see the text'],
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'complex example with empty lines' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
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
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description.',
          'example' => "Given I am on the homepage\n\nWhen I click \"Submit\"\nThen I should see \"Success\"\n",
        ],
      ],
      'comment with comment markers' => [
        <<<'EOD'
/**
 * This is a description.
 * /* nested comment start
 * */ nested comment end
 * @Given I am on the homepage
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description.',
          'example' => '',
        ],
      ],
      'unclosed example error' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @Given I am on the homepage
 *
 * @code
 * Example without closing tag
 */
EOD,
        NULL,
        'Example not closed',
      ],
      'example without steps' => [
        <<<'EOD'
/**
 * This is a description.
 *
 * @code
 * Example code
 * @endcode
 */
EOD,
        NULL,
      ],
      'trim description' => [
        <<<'EOD'
/**
 * This is a description with trailing space.
 *
 * @Given I am on the homepage
 */
EOD,
        [
          'steps' => ['@Given I am on the homepage'],
          'description' => 'This is a description with trailing space.',
          'example' => '',
        ],
      ],
    ];

  }

  #[DataProvider('dataProviderCamelToSnake')]
  public function testCamelToSnake(string $input, string $expected, string $separator = '_'): void {
    $actual = camel_to_snake($input, $separator);
    $this->assertEquals($expected, $actual);
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
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderArrayToMarkdownTable(): array {
    return [
      'basic table' => [
        ['Header 1', 'Header 2'],
        [
          'row1' => ['Cell 1', 'Cell 2'],
          'row2' => ['Cell 3', 'Cell 4'],
        ],
        "| Header 1 | Header 2 |\n| --- | --- |\n| Cell 1 | Cell 2 |\n| Cell 3 | Cell 4 |",
      ],
      'single column table' => [
        ['Header'],
        [
          'row1' => ['Cell 1'],
          'row2' => ['Cell 2'],
        ],
        "| Header |\n| --- |\n| Cell 1 |\n| Cell 2 |",
      ],
      'single row table' => [
        ['Header 1', 'Header 2'],
        [
          'row1' => ['Cell 1', 'Cell 2'],
        ],
        "| Header 1 | Header 2 |\n| --- | --- |\n| Cell 1 | Cell 2 |",
      ],
      'multi-column table' => [
        ['Header 1', 'Header 2', 'Header 3', 'Header 4'],
        [
          'row1' => ['Cell 1', 'Cell 2', 'Cell 3', 'Cell 4'],
          'row2' => ['Cell 5', 'Cell 6', 'Cell 7', 'Cell 8'],
        ],
        "| Header 1 | Header 2 | Header 3 | Header 4 |\n| --- | --- | --- | --- |\n| Cell 1 | Cell 2 | Cell 3 | Cell 4 |\n| Cell 5 | Cell 6 | Cell 7 | Cell 8 |",
      ],
      'with special characters' => [
        ['Header *1*', 'Header **2**'],
        [
          'row1' => ['Cell *1*', 'Cell **2**'],
          'row2' => ['Cell [3](link)', 'Cell `4`'],
        ],
        "| Header *1* | Header **2** |\n| --- | --- |\n| Cell *1* | Cell **2** |\n| Cell [3](link) | Cell `4` |",
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
        "| Header 1 | Header 2 | Header 3 |\n| --- | --- | --- |\n| Cell 1 |  | Cell 3 |\n|  | Cell 5 |  |",
      ],
      'with numeric values' => [
        ['ID', 'Value'],
        [
          'row1' => ['1', '100'],
          'row2' => ['2', '200'],
        ],
        "| ID | Value |\n| --- | --- |\n| 1 | 100 |\n| 2 | 200 |",
      ],
    ];
  }

  #[DataProvider('dataProviderRenderInfo')]
  public function testRenderInfo(array $info, string $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\Exception::class);
      $exception = str_replace('@tmp', static::$tmp, $exception);
      $this->expectExceptionMessage($exception);
    }

    // Create a mock that will simulate our file_exists checks in the render_info method.
    $base_path = static::$tmp;

    // Create temporary files for testing.
    $trait_dir = $base_path . DIRECTORY_SEPARATOR . 'src';
    $features_dir = $base_path . DIRECTORY_SEPARATOR . 'tests/behat/features';

    // Ensure directories exist.
    mkdir($trait_dir, 0777, TRUE);
    mkdir($features_dir, 0777, TRUE);

    // Create sample files that the function will check for existence.
    foreach ($info as $trait => $methods) {
      $src_file = sprintf('src/%s.php', $trait);
      $src_file_path = $base_path . DIRECTORY_SEPARATOR . $src_file;
      file_put_contents($src_file_path, '<?php');

      $example_name = camel_to_snake(str_replace('Trait', '', $trait));
      $example_file = sprintf('tests/behat/features/%s.feature', $example_name);
      $example_file_path = $base_path . DIRECTORY_SEPARATOR . $example_file;
      file_put_contents($example_file_path, 'Feature: Test');
    }

    // For the missing file test.
    if (isset($info['MissingTrait'])) {
      $src_file_path = $base_path . DIRECTORY_SEPARATOR . 'src/MissingTrait.php';
      @unlink($src_file_path);
    }

    $actual = render_info($info, $base_path);

    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderRenderInfo(): array {
    return [
      'single trait with single method' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'class_description' => 'Test trait description',
              'name' => 'testMethod',
              'steps' => ['@Given I am on the homepage'],
              'description' => 'Test method description',
              'example' => 'Given I am on the homepage',
            ],
          ],
        ],
        <<<'EOD'
| Class | Description |
| --- | --- |
| [TestTrait](#testtrait) | Test trait description |
## TestTrait

[Source](src/TestTrait.php), [Example](tests/behat/features/test.feature)

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
            [
              'class_name' => 'FirstTrait',
              'class_description' => 'First trait description',
              'name' => 'firstMethod',
              'steps' => ['@Given I am on the homepage'],
              'description' => 'First method description',
              'example' => 'Given I am on the homepage',
            ],
          ],
          'SecondTrait' => [
            [
              'class_name' => 'SecondTrait',
              'class_description' => 'Second trait description',
              'name' => 'secondMethod',
              'steps' => ['@When I click "Submit"'],
              'description' => 'Second method description',
              'example' => 'When I click "Submit"',
            ],
          ],
        ],
        <<<'EOD'
| Class | Description |
| --- | --- |
| [FirstTrait](#firsttrait) | First trait description |
| [SecondTrait](#secondtrait) | Second trait description |
## FirstTrait

[Source](src/FirstTrait.php), [Example](tests/behat/features/first.feature)

<details>
  <summary><code>@Given I am on the homepage</code></summary>

```gherkin
Given I am on the homepage
```
</details>

## SecondTrait

[Source](src/SecondTrait.php), [Example](tests/behat/features/second.feature)

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
            [
              'class_name' => 'MultiMethodTrait',
              'class_description' => 'Multi-method trait description',
              'name' => 'firstMethod',
              'steps' => ['@Given I am on the homepage'],
              'description' => 'First method description',
              'example' => 'Given I am on the homepage',
            ],
            [
              'class_name' => 'MultiMethodTrait',
              'class_description' => 'Multi-method trait description',
              'name' => 'secondMethod',
              'steps' => ['@When I click "Submit"'],
              'description' => 'Second method description',
              'example' => 'When I click "Submit"',
            ],
          ],
        ],
        <<<'EOD'
| Class | Description |
| --- | --- |
| [MultiMethodTrait](#multimethodtrait) | Multi-method trait description |
## MultiMethodTrait

[Source](src/MultiMethodTrait.php), [Example](tests/behat/features/multi_method.feature)

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
            [
              'class_name' => 'StepsTrait',
              'class_description' => 'Steps trait description',
              'name' => 'methodWithMultipleSteps',
              'steps' => ['@Given I am on the homepage', '@When I click "Submit"', '@Then I should see "Success"'],
              'description' => 'Method with multiple steps',
              'example' => "Given I am on the homepage\nWhen I click \"Submit\"\nThen I should see \"Success\"",
            ],
          ],
        ],
        <<<'EOD'
| Class | Description |
| --- | --- |
| [StepsTrait](#stepstrait) | Steps trait description |
## StepsTrait

[Source](src/StepsTrait.php), [Example](tests/behat/features/steps.feature)

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
        "\n",
      ],
      'with missing source file' => [
        [
          'MissingTrait' => [
            [
              'class_name' => 'MissingTrait',
              'class_description' => 'Missing trait description',
              'name' => 'testMethod',
              'steps' => ['@Given I am on the homepage'],
              'description' => 'Test method description',
              'example' => 'Given I am on the homepage',
            ],
          ],
        ],
        "",
        'Source file @tmp/src/MissingTrait.php does not exist',
      ],
    ];
  }

  #[DataProvider('dataProviderValidate')]
  public function testValidate(array $info, array $expected): void {
    $actual = validate($info);
    $this->assertEquals($expected, $actual);
  }

  #[DataProvider('dataProviderReplaceContent')]
  public function testReplaceContent(
    string $haystack,
    string $start,
    string $end,
    string $replacement,
    string $expected,
    ?string $exception = NULL,
  ): void {
    if ($exception) {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage($exception);
    }

    $actual = replace_content($haystack, $start, $end, $replacement);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderReplaceContent(): array {
    return [
      'basic replacement' => [
        'This is a test string with START some content END in it.',
        'START',
        'END',
        ' new content ',
        "This is a test string with START\n new content \nEND in it.",
      ],
      'multiline content' => [
        "Line 1\nSTART\nsome content\nmore content\nEND\nLine 3",
        "START",
        "END",
        "\nnew content\n",
        "Line 1\nSTART\n\nnew content\n\nEND\nLine 3",
      ],
      'replacement with special characters' => [
        'Content with START $pecial ch@rs END here',
        'START',
        'END',
        ' $p3c!al r3pl@cement ',
        "Content with START\n \$p3c!al r3pl@cement \nEND here",
      ],
      'start and end with regex characters' => [
        'Content with [START] regex.chars* [END] here',
        '[START]',
        '[END]',
        ' escaped content ',
        "Content with [START]\n escaped content \n[END] here",
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
        "Content with START\n replacement \nEND together",
      ],
      'nested markers' => [
        'Content with START nested START inner END markers END',
        'START',
        'END',
        ' replaced all ',
        "Content with START\n replaced all \nEND markers END",
      ],
      'empty replacement' => [
        'Content with START content to remove END here',
        'START',
        'END',
        '',
        "Content with START\n\nEND here",
      ],
    ];
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
        [],
      ],
      'multiple steps error' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@Given step one', '@Given step two'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testMethod - Multiple steps found' . PHP_EOL],
      ],
      'given without following' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@Given items:'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing "following" in the step' . PHP_EOL],
      ],
      'when without I' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@When click on button'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing "I " in the step' . PHP_EOL],
      ],
      'then without assert in method' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@Then the page should contain "text"'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing "Assert" in the method name' . PHP_EOL],
      ],
      'then with should in method' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testAssertShouldMethod',
              'steps' => ['@Then the page should contain "text"'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testAssertShouldMethod - Assert method contains "Should" but should not.' . PHP_EOL],
      ],
      'then without should in step' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testAssertMethod',
              'steps' => ['@Then the page contains "text"'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Missing "should" in the step' . PHP_EOL],
      ],
      'then without the/a/no' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testAssertMethod',
              'steps' => ['@Then page should contain "text"'],
              'description' => 'Test method description',
              'example' => 'Example text',
            ],
          ],
        ],
        ['  TestTrait::testAssertMethod - Missing "the", "a" or "no" in the step' . PHP_EOL],
      ],
      'missing example' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@Given the following items:'],
              'description' => 'Test method description',
              'example' => '',
            ],
          ],
        ],
        ['  TestTrait::testMethod - Missing example' . PHP_EOL],
      ],
      'multiple validation errors' => [
        [
          'TestTrait' => [
            [
              'class_name' => 'TestTrait',
              'name' => 'testMethod',
              'steps' => ['@Then page contains "text"'],
              'description' => 'Test method description',
              'example' => '',
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
        [],
      ],
    ];
  }

}

<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\MappingTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests for MappingTrait.
 */
#[CoversTrait(MappingTrait::class)]
class MappingTraitTest extends UnitTestCase {

  /**
   * A test implementation of MappingTrait.
   */
  protected MappingTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new MappingTraitTestImplementation();
    $this->testObject->setParameters([
      'steps' => [
        'mapping' => [
          'groups' => [
            'paths' => ['User Login' => '/user/login'],
            'pages' => ['Home' => '/'],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a token is replaced by the value it maps to.
   *
   * @param string $argument
   *   The raw step argument.
   * @param string $expected
   *   The expected result.
   */
  #[DataProvider('dataProviderTransformValue')]
  public function testTransformValue(string $argument, string $expected): void {
    $this->assertSame($expected, $this->testObject->mappingTransformValue($argument));
  }

  public static function dataProviderTransformValue(): array {
    return [
      'token alone' => ['{{ User Login }}', '/user/login'],
      'token without padding' => ['{{User Login}}', '/user/login'],
      'token within text' => ['go to {{ Home }} now', 'go to / now'],
      'two tokens' => ['{{ Home }} then {{ User Login }}', '/ then /user/login'],
      'no token' => ['/plain/path', '/plain/path'],
    ];
  }

  public function testTransformTableResolvesEveryCell(): void {
    $table = new TableNode([
      ['path', 'label'],
      ['{{ User Login }}', 'Log in'],
      ['{{ Home }}', 'Home'],
    ]);

    $expected = [
      ['path', 'label'],
      ['/user/login', 'Log in'],
      ['/', 'Home'],
    ];

    $this->assertSame($expected, $this->testObject->mappingTransformTable($table)->getRows());
  }

  public function testUnknownKeyFailsTheStep(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No such mapping: Nonexistent Key');

    $this->testObject->mappingTransformValue('{{ Nonexistent Key }}');
  }

  public function testDuplicateKeyAcrossGroupsFailsTheStep(): void {
    $this->testObject->setParameters([
      'steps' => [
        'mapping' => [
          'groups' => [
            'paths' => ['Home' => '/'],
            'pages' => ['Home' => '/front'],
          ],
        ],
      ],
    ]);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Duplicate mapping key "Home" found in groups "paths" and "pages" under "mapping.groups".');

    $this->testObject->mappingTransformValue('{{ Home }}');
  }

  /**
   * Tests that a skipped scenario passes a token through untouched.
   */
  public function testSkippedScenarioLeavesTokensUntouched(): void {
    $this->testObject->mappingBeforeScenario($this->createBeforeScenarioScope(['behat-steps-skip:MappingTrait']));

    $this->assertSame('{{ User Login }}', $this->testObject->mappingTransformValue('{{ User Login }}'));

    $table = new TableNode([['path'], ['{{ User Login }}']]);
    $this->assertSame([['path'], ['{{ User Login }}']], $this->testObject->mappingTransformTable($table)->getRows());
  }

  /**
   * Tests that an unskipped scenario resolves tokens.
   */
  public function testUnskippedScenarioResolvesTokens(): void {
    $this->testObject->mappingBeforeScenario($this->createBeforeScenarioScope());

    $this->assertSame('/user/login', $this->testObject->mappingTransformValue('{{ User Login }}'));
  }

}

/**
 * Test implementation of MappingTrait.
 */
class MappingTraitTestImplementation extends WebRawContext {

  use MappingTrait;

}

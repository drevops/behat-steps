<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\EntityTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the entity reference steps and the entity ID tokens of EntityTrait.
 */
#[CoversTrait(EntityTrait::class)]
class EntityTraitTest extends UnitTestCase {

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * Tests that a step fills the field with the newest entity's label and id.
   *
   * @param string $method
   *   The step to call.
   * @param array<int, string> $args
   *   The arguments to call it with.
   * @param string|null $bundle
   *   The bundle the lookup is expected to be restricted to.
   * @param string $label
   *   The label the lookup is expected to match.
   * @param array<int, string> $ids
   *   The ids the lookup returns.
   * @param string $expected
   *   The value the field is expected to receive.
   */
  #[DataProvider('dataProviderStepFillsTheField')]
  public function testStepFillsTheField(string $method, array $args, ?string $bundle, string $label, array $ids, string $expected): void {
    $backend = $this->createBackend(TRUE);
    $backend->expects($this->once())->method('findEntityIds')->with('taxonomy_term', ['name' => $label], $bundle)->willReturn($ids);

    $context = $this->createContext($backend);
    $this->page->expects($this->once())->method('fillField')->with('Tags', $expected);

    $context->{$method}(...$args);
  }

  public static function dataProviderStepFillsTheField(): \Iterator {
    yield 'any bundle' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'Jazz'], NULL, 'Jazz', ['3'], 'Jazz (3)'];
    yield 'a bundle' => ['entityFillReferenceFieldOfBundleWithLabel', ['Tags', 'tags', 'taxonomy_term', 'Jazz'], 'tags', 'Jazz', ['3'], 'Jazz (3)'];
    yield 'the newest of several' => ['entityFillReferenceFieldOfBundleWithLabel', ['Tags', 'tags', 'taxonomy_term', 'Jazz'], 'tags', 'Jazz', ['3', '9', '12'], 'Jazz (12)'];
    yield 'a label with a comma' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'Rock, pop'], NULL, 'Rock, pop', ['4'], '"Rock, pop (4)"'];
    yield 'a label with a double quote' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'The "best" band'], NULL, 'The "best" band', ['5'], '"The ""best"" band (5)"'];
  }

  /**
   * Tests that a lookup that cannot resolve an id fails naming what it sought.
   *
   * @param string|null $bundle
   *   The bundle to look up in.
   * @param bool $bundle_exists
   *   Whether the bundle exists.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderGetIdByLabelFails')]
  public function testGetIdByLabelFails(?string $bundle, bool $bundle_exists, string $expected_message): void {
    $backend = $this->createBackend($bundle_exists);
    $backend->method('findEntityIds')->willReturn([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createContext($backend)->entityGetIdByLabel('taxonomy_term', 'Jazz', $bundle);
  }

  public static function dataProviderGetIdByLabelFails(): \Iterator {
    yield 'an unknown bundle' => ['missing', FALSE, 'The entity type "taxonomy_term" has no bundle "missing".'];
    yield 'no match in a bundle' => ['tags', TRUE, 'The "tags" "taxonomy_term" entity with the label "Jazz" does not exist.'];
    yield 'no match in any bundle' => [NULL, TRUE, 'The "taxonomy_term" entity with the label "Jazz" does not exist.'];
  }

  /**
   * Tests that a step argument has every entity ID token replaced.
   *
   * @param string $argument
   *   The raw step argument.
   * @param array<string, array<int, string>> $lookups
   *   The ids each lookup returns, keyed by '<entity_type>|<bundle>|<label>'.
   * @param string $expected
   *   The expected argument.
   */
  #[DataProvider('dataProviderTransformValue')]
  public function testTransformValue(string $argument, array $lookups, string $expected): void {
    $backend = $this->createBackend(TRUE);
    $backend->expects($this->exactly(count($lookups)))->method('findEntityIds')->willReturnCallback(static fn(string $entity_type, array $conditions, ?string $bundle): array => $lookups[$entity_type . '|' . $bundle . '|' . $conditions['name']]);

    $this->assertSame($expected, $this->createContext($backend)->entityTransformValue($argument));
  }

  public static function dataProviderTransformValue(): \Iterator {
    yield 'token alone' => ['[id:user:jane]', ['user||jane' => ['12']], '12'];
    yield 'token within a path' => ['/print/pdf/user/[id:user:jane]/debug', ['user||jane' => ['12']], '/print/pdf/user/12/debug'];
    yield 'token with a bundle' => ['/print/pdf/taxonomy_term/[id:taxonomy_term.topics:Jazz]', ['taxonomy_term|topics|Jazz' => ['7']], '/print/pdf/taxonomy_term/7'];
    yield 'the newest of several' => ['[id:node.page:Home]', ['node|page|Home' => ['3', '9', '12']], '12'];
    yield 'a label with brackets' => ['/node/[id:node:[TEST] Page]/edit', ['node||[TEST] Page' => ['4']], '/node/4/edit'];
    yield 'a label with nested brackets' => ['[id:node:[[TEST]] Page]', ['node||[[TEST]] Page' => ['5']], '5'];
    yield 'a label with a colon and a dot' => ['[id:node:Part 1: v2.0]', ['node||Part 1: v2.0' => ['6']], '6'];
    yield 'two tokens' => ['/compare/[id:node:A]/[id:node:B]', ['node||A' => ['1'], 'node||B' => ['2']], '/compare/1/2'];
    yield 'an empty label' => ['[id:node:]', ['node||' => ['8']], '8'];
    yield 'an uppercase entity type stays literal' => ['[id:Node:Home]', [], '[id:Node:Home]'];
    yield 'unbalanced brackets stay literal' => ['[id:node:[TEST Page]', [], '[id:node:[TEST Page]'];
    yield 'no token' => ['/plain/path', [], '/plain/path'];
  }

  public function testTransformTableResolvesEveryCell(): void {
    $backend = $this->createBackend(TRUE);
    $backend->method('findEntityIds')->willReturnCallback(static fn(string $entity_type, array $conditions): array => [$conditions['name'] === 'jane' ? '12' : '13']);

    $table = new TableNode([
      ['title', 'uid'],
      ['First', '[id:user:jane]'],
      ['Second', 'user [id:user:john]'],
    ]);

    $expected = [
      ['title', 'uid'],
      ['First', '12'],
      ['Second', 'user 13'],
    ];

    $this->assertSame($expected, $this->createContext($backend)->entityTransformTable($table)->getRows());
  }

  public function testTransformTableWithoutTokensReturnsTheSameTable(): void {
    $backend = $this->createBackend(TRUE);
    $backend->expects($this->never())->method('findEntityIds');

    $table = new TableNode([['title'], ['First']]);

    $this->assertSame($table, $this->createContext($backend)->entityTransformTable($table));
  }

  /**
   * Tests that a token whose entity cannot be resolved fails the step.
   *
   * @param string $argument
   *   The raw step argument.
   * @param bool $bundle_exists
   *   Whether the bundle exists.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderTransformValueFails')]
  public function testTransformValueFails(string $argument, bool $bundle_exists, string $expected_message): void {
    $backend = $this->createBackend($bundle_exists);
    $backend->method('findEntityIds')->willReturn([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createContext($backend)->entityTransformValue($argument);
  }

  public static function dataProviderTransformValueFails(): \Iterator {
    yield 'an unknown bundle' => ['[id:taxonomy_term.missing:Jazz]', FALSE, 'The entity type "taxonomy_term" has no bundle "missing".'];
    yield 'no match in a bundle' => ['[id:taxonomy_term.tags:Jazz]', TRUE, 'The "tags" "taxonomy_term" entity with the label "Jazz" does not exist.'];
    yield 'no match in any bundle' => ['/user/[id:user:jane]', TRUE, 'The "user" entity with the label "jane" does not exist.'];
  }

  public function testSkippedScenarioLeavesTokensUntouched(): void {
    $backend = $this->createBackend(TRUE);
    $backend->expects($this->never())->method('findEntityIds');

    $context = $this->createContext($backend);
    $context->entityBeforeScenario($this->createBeforeScenarioScope(['behat-steps-skip:EntityTrait']));

    $this->assertSame('[id:user:jane]', $context->entityTransformValue('[id:user:jane]'));

    $table = new TableNode([['uid'], ['[id:user:jane]']]);
    $this->assertSame([['uid'], ['[id:user:jane]']], $context->entityTransformTable($table)->getRows());
  }

  public function testUnskippedScenarioResolvesTokens(): void {
    $backend = $this->createBackend(TRUE);
    $backend->method('findEntityIds')->willReturn(['12']);

    $context = $this->createContext($backend);
    $context->entityBeforeScenario($this->createBeforeScenarioScope());

    $this->assertSame('12', $context->entityTransformValue('[id:user:jane]'));
  }

  /**
   * Creates a backend that resolves entity definitions and lookups.
   *
   * @param bool $bundle_exists
   *   What 'bundleExists()' returns.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface&\DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend.
   */
  protected function createBackend(bool $bundle_exists): BackendInterface&EntityDefinitionCapabilityInterface&EntityLookupCapabilityInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface&\DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, EntityDefinitionCapabilityInterface::class, EntityLookupCapabilityInterface::class]);
    $backend->method('getEntityLabelKey')->willReturn('name');
    $backend->method('bundleExists')->willReturn($bundle_exists);

    return $backend;
  }

  /**
   * Creates a context backed by the given backend, with a mocked page.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the scenario lists.
   */
  protected function createContext(BackendInterface $backend): EntityTraitTestImplementation {
    $registry = new BackendRegistry(['test' => $backend]);
    $registry->setScenarioBackends(['test' => 'test']);

    $this->page = $this->createMock(DocumentElement::class);
    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new EntityTraitTestImplementation();
    $context->setBackendRegistry($registry);
    $context->setMink($mink);

    return $context;
  }

}

/**
 * Test implementation of EntityTrait.
 */
class EntityTraitTestImplementation extends WebRawContext {

  use EntityTrait;

}

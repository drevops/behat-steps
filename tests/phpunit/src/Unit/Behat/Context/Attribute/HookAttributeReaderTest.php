<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context\Attribute;

use DrevOps\BehatSteps\Behat\Context\Attribute\HookAttributeReader;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\EntityScopeInterface;
use DrevOps\BehatSteps\Behat\Hook\Scope\NodeScope;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\HookedContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests which callees the reader builds from a context method's attributes.
 */
#[CoversClass(HookAttributeReader::class)]
class HookAttributeReaderTest extends TestCase {

  public function testAnAttributedMethodYieldsItsHookCall(): void {
    $callees = $this->read('beforeNode');

    $this->assertCount(1, $callees);
    $this->assertInstanceOf(BeforeNodeCreate::class, $callees[0]);
    $this->assertSame(NodeScope::BEFORE, $callees[0]->getScopeName());
    $this->assertSame('BeforeNodeCreate', $callees[0]->getName());
  }

  public function testStaticHookIsCalledThroughItsClass(): void {
    $callees = $this->read('beforeNode');

    $this->assertSame([HookedContext::class, 'beforeNode'], $callees[0]->getCallable());
  }

  public function testAnInstanceHookResolvesToItsContextMethod(): void {
    $callees = $this->read('afterNode');

    $this->assertCount(1, $callees);
    $this->assertInstanceOf(AfterNodeCreate::class, $callees[0]);

    // Behat 3 takes the '[class, method]' pair and Behat 4 wraps an instance
    // method in a late-bound callable, so assert the method the callee
    // resolves to rather than the shape it is carried in.
    $reflection = $callees[0]->getReflection();

    $this->assertInstanceOf(\ReflectionMethod::class, $reflection);
    $this->assertSame(HookedContext::class, $reflection->getDeclaringClass()->getName());
    $this->assertSame('afterNode', $reflection->getName());
  }

  public function testTheFilterStringIsCarriedOntoTheCall(): void {
    $callees = $this->read('filtered');

    $this->assertInstanceOf(AfterEntityCreate::class, $callees[0]);
    $this->assertSame('@api', $callees[0]->getFilterString());
    $this->assertSame(EntityScopeInterface::AFTER, $callees[0]->getScopeName());
  }

  public function testMethodMayCarryMoreThanOneHook(): void {
    $callees = $this->read('both');

    $this->assertCount(2, $callees);
    $this->assertInstanceOf(BeforeNodeCreate::class, $callees[0]);
    $this->assertInstanceOf(AfterNodeCreate::class, $callees[1]);
  }

  public function testAnUnrelatedBehatHookIsIgnored(): void {
    $this->assertSame([], $this->read('unrelated'));
  }

  public function testEntityHookWithoutCallClassIsSkipped(): void {
    $this->assertSame([], $this->read('unmapped'));
  }

  public function testMethodWithoutAttributesYieldsNothing(): void {
    $this->assertSame([], $this->read('plain'));
  }

  /**
   * Reads the callees a fixture context method declares.
   *
   * @param string $method
   *   The fixture method to reflect.
   *
   * @return array<int, \Behat\Testwork\Call\RuntimeCallee>
   *   The callees the reader produced.
   */
  protected function read(string $method): array {
    return (new HookAttributeReader())->readCallees(HookedContext::class, new \ReflectionMethod(HookedContext::class, $method));
  }

}

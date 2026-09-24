<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use DrevOps\BehatSteps\Helper\RequestHeadersTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests for RequestHeadersTrait.
 */
#[CoversTrait(RequestHeadersTrait::class)]
class RequestHeadersTraitTest extends UnitTestCase {

  /**
   * A test implementation of RequestHeadersTrait.
   */
  protected RequestHeadersTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new RequestHeadersTraitTestImplementation();
  }

  public function testTheBagStartsEmpty(): void {
    $this->assertSame([], $this->testObject->callAll());
  }

  public function testHeaderIsReadBackUnderItsName(): void {
    $this->testObject->setRequestHeader('X-A', '1');
    $this->testObject->setRequestHeader('X-B', '2');

    $this->assertSame(['X-A' => '1', 'X-B' => '2'], $this->testObject->callAll());
  }

  public function testSettingTheSameNameReplacesTheValue(): void {
    $this->testObject->setRequestHeader('X-A', '1');
    $this->testObject->setRequestHeader('X-A', '2');

    $this->assertSame(['X-A' => '2'], $this->testObject->callAll());
  }

  public function testUnsetDropsOnlyTheNamedHeader(): void {
    $this->testObject->setRequestHeader('X-A', '1');
    $this->testObject->setRequestHeader('X-B', '2');

    $this->testObject->callUnset('X-A');

    $this->assertSame(['X-B' => '2'], $this->testObject->callAll());
  }

  public function testUnsettingAnAbsentHeaderLeavesTheBagAlone(): void {
    $this->testObject->setRequestHeader('X-A', '1');

    $this->testObject->callUnset('X-Missing');

    $this->assertSame(['X-A' => '1'], $this->testObject->callAll());
  }

  public function testResetEmptiesTheBag(): void {
    $this->testObject->setRequestHeader('X-A', '1');

    $this->testObject->callReset();

    $this->assertSame([], $this->testObject->callAll());
  }

}

/**
 * Test implementation of RequestHeadersTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class RequestHeadersTraitTestImplementation {

  use RequestHeadersTrait;

  /**
   * Read the accumulated headers.
   *
   * @return array<string, string>
   *   Header values keyed by header name.
   */
  public function callAll(): array {
    return $this->getRequestHeaders();
  }

  public function callUnset(string $name): void {
    $this->unsetRequestHeader($name);
  }

  public function callReset(): void {
    $this->resetRequestHeaders();
  }

}

<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Config;

use DrevOps\BehatSteps\Extension\Config\ParametersTrait;
use DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures\ParametersAwareObject;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests how configured text and selectors are read back.
 */
#[CoversTrait(ParametersTrait::class)]
class ParametersTraitTest extends UnitTestCase {

  /**
   * Parameters the host is seeded with.
   */
  protected const PARAMETERS = [
    'login_field' => 'mail',
    'text' => ['logout' => 'Sign out'],
    'selectors' => ['logged_in_selector' => 'body.logged-in'],
  ];

  public function testAnUnsetParameterIsNull(): void {
    $this->assertNull($this->createHost()->getParameter('missing'));
  }

  public function testSetParameterIsReturned(): void {
    $this->assertSame('mail', $this->createHost()->getParameter('login_field'));
  }

  public function testParametersDefaultToAnEmptyMap(): void {
    $this->assertNull((new ParametersAwareObject())->getParameter('login_field'));
  }

  public function testConfiguredTextIsReturned(): void {
    $this->assertSame('Sign out', $this->createHost()->getText('logout'));
  }

  public function testConfiguredSelectorIsReturned(): void {
    $this->assertSame('body.logged-in', $this->createHost()->getSelector('logged_in_selector'));
  }

  /**
   * Tests that a name absent from the configuration is rejected.
   *
   * @param string $method
   *   The accessor to call.
   * @param string $name
   *   The name to look up.
   * @param string $expected_message
   *   The message the accessor is expected to throw with.
   */
  #[DataProvider('dataProviderUnknownNameThrows')]
  public function testUnknownNameThrows(string $method, string $name, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createHost()->{$method}($name);
  }

  public static function dataProviderUnknownNameThrows(): \Iterator {
    yield 'text' => ['getText', 'login', 'No such text: login.'];
    yield 'selector' => ['getSelector', 'login_form_selector', 'No such selector: login_form_selector.'];
  }

  /**
   * Builds a host seeded with the fixture parameters.
   */
  protected function createHost(): ParametersAwareObject {
    $host = new ParametersAwareObject();
    $host->setParameters(static::PARAMETERS);

    return $host;
  }

}

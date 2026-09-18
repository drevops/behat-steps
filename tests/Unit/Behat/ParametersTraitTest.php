<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use DrevOps\BehatSteps\Behat\ParametersTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ParametersAwareObject;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests how configured text, selectors and mappings are read back.
 */
#[CoversTrait(ParametersTrait::class)]
class ParametersTraitTest extends TestCase {

  /**
   * Parameters the host is seeded with.
   */
  protected const PARAMETERS = [
    'login_field' => 'mail',
    'text' => ['log_out' => 'Sign out'],
    'selectors' => ['logged_in_selector' => 'body.logged-in'],
    'mappings' => ['User Login' => '/user/login'],
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
    $this->assertSame('Sign out', $this->createHost()->getDrupalText('log_out'));
  }

  public function testConfiguredSelectorIsReturned(): void {
    $this->assertSame('body.logged-in', $this->createHost()->getDrupalSelector('logged_in_selector'));
  }

  public function testConfiguredMappingIsReturned(): void {
    $this->assertSame('/user/login', $this->createHost()->getMapping('User Login'));
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

    $this->createHost()->$method($name);
  }

  public static function dataProviderUnknownNameThrows(): \Iterator {
    yield 'text' => ['getDrupalText', 'log_in', 'No such Drupal string: log_in'];
    yield 'selector' => ['getDrupalSelector', 'login_form_selector', 'No such selector configured: login_form_selector'];
    yield 'mapping' => ['getMapping', 'User Registration', 'No such mapping: User Registration'];
  }

  /**
   * Builds a host seeded with the fixture parameters.
   */
  protected function createHost(): ParametersAwareObject {
    $host = new ParametersAwareObject();
    $host->setParameters(self::PARAMETERS);

    return $host;
  }

}

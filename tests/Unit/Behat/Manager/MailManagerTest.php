<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use DrevOps\BehatSteps\Behat\Manager\MailManager;
use DrevOps\BehatSteps\Driver\Capability\MailCapabilityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that mail collection delegates to the driver.
 */
#[CoversClass(MailManager::class)]
class MailManagerTest extends TestCase {

  /**
   * Tests that each manager method calls its driver counterpart.
   *
   * @param string $method
   *   The manager method to invoke.
   * @param string $driver_method
   *   The driver method expected to be called.
   * @param array<int, string> $extra_driver_methods
   *   Additional driver methods that should be called.
   */
  #[DataProvider('dataProviderDriverDelegation')]
  public function testDriverDelegation(string $method, string $driver_method, array $extra_driver_methods = []): void {
    $driver = $this->createMock(MailCapabilityInterface::class);
    $driver->expects($this->once())->method($driver_method);

    foreach ($extra_driver_methods as $extra_driver_method) {
      $driver->expects($this->once())->method($extra_driver_method);
    }

    $manager = new MailManager($driver);
    $manager->$method();
  }

  public static function dataProviderDriverDelegation(): \Iterator {
    yield 'startCollectingMail calls driver and clears' => ['startCollectingMail', 'mailStartCollecting', ['mailClear']];
    yield 'stopCollectingMail delegates to driver' => ['stopCollectingMail', 'mailStopCollecting'];
    yield 'disableMail starts collecting' => ['disableMail', 'mailStartCollecting', ['mailClear']];
    yield 'enableMail stops collecting' => ['enableMail', 'mailStopCollecting'];
    yield 'clearMail delegates to driver' => ['clearMail', 'mailClear'];
  }

  public function testGetMailDelegatesToDriver(): void {
    $expected = [['to' => 'a@b.com', 'subject' => 'test', 'body' => 'hello']];
    $driver = $this->createMock(MailCapabilityInterface::class);
    $driver->expects($this->once())->method('mailGet')->willReturn($expected);

    $manager = new MailManager($driver);

    $this->assertSame($expected, $manager->getMail());
  }

}

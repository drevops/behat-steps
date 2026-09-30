<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface as MinkDriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException as MinkUnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Http\HttpIdentity;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\DrushDriverInterface;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\PrerequisiteContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\SamplePrerequisiteTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\StepPrerequisiteTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests the plumbing every shipped context inherits.
 */
#[CoversClass(WebRawContext::class)]
class WebRawContextTest extends UnitTestCase {

  public function testImplementsDriverAwareInterface(): void {
    $this->assertInstanceOf(DriverAwareInterface::class, new WebRawContext());
  }

  /**
   * Tests that an uninitialized context reports what it is missing.
   *
   * @param string $method
   *   The accessor to call on an uninitialized context.
   * @param string $expected_message
   *   The message the accessor is expected to throw with.
   */
  #[DataProvider('dataProviderUninitializedContextNamesMissingCollaborator')]
  public function testUninitializedContextNamesMissingCollaborator(string $method, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    (new WebRawContext())->$method();
  }

  public static function dataProviderUninitializedContextNamesMissingCollaborator(): \Iterator {
    yield 'driver registry' => ['getDriverRegistry', 'The driver registry is available only after Behat has initialized the context.'];
    yield 'basic authenticator' => ['getBasicAuthenticator', 'The basic authenticator is available only after Behat has initialized the context.'];
  }

  public function testTheDriverComesFromTheManager(): void {
    $driver = $this->createMock(DriverInterface::class);
    $context = $this->createContext($driver);

    $this->assertSame($driver, $context->getDriver('test'));
    $this->assertSame($driver, $context->driverFor(DriverInterface::class));
  }

  public function testTheRandomGeneratorComesFromTheDriver(): void {
    $random = new Random();
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('getRandom')->willReturn($random);

    $this->assertSame($random, $this->createContext($driver)->getRandom());
  }

  public function testDriverForNamesTheCapabilityWhenNoDriverProvidesIt(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No driver provides "%s".', CoreCapabilityInterface::class));

    $context->driverFor(CoreCapabilityInterface::class);
  }

  public function testDriverForBootstrapsOnceAndReturnsTheDriver(): void {
    $driver = $this->createMock(DrupalDriverInterface::class);
    $driver->method('isBootstrapped')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $driver->expects($this->once())->method('bootstrap');

    $context = $this->createContext($driver);

    $first = $context->driverFor(CoreCapabilityInterface::class);
    $second = $context->driverFor(CoreCapabilityInterface::class);

    $this->assertSame($driver, $first);
    $this->assertSame($driver, $second);
  }

  public function testHttpClientFactoryIsTheInjectedOne(): void {
    $factory = $this->createMock(HttpClientFactoryInterface::class);
    $context = new WebRawContext();

    $context->setHttpClientFactory($factory);

    $this->assertSame($factory, $context->getHttpClientFactory());
  }

  public function testHttpClientFactoryDefaultsToStandaloneOne(): void {
    $context = new WebRawContext();

    $factory = $context->getHttpClientFactory();

    $this->assertInstanceOf(HttpClientFactory::class, $factory);
    $this->assertSame($factory, $context->getHttpClientFactory());
  }

  public function testHttpPageClientIsTheSessionBrowser(): void {
    $browser = new HttpBrowser(new MockHttpClient());

    $this->assertSame($browser, $this->createBrowserKitContext($browser)->httpPageClient());
  }

  public function testHttpPageClientIsUnsupportedWithoutPhpBrowser(): void {
    $mink = new Mink(['default' => new Session($this->createMock(MinkDriverInterface::class))]);
    $mink->setDefaultSessionName('default');
    $context = new WebRawContext();
    $context->setMink($mink);

    $this->expectException(MinkUnsupportedDriverActionException::class);

    $context->httpPageClient();
  }

  public function testHttpBareClientComesFromTheFactory(): void {
    $browser = new HttpBrowser(new MockHttpClient());
    $factory = $this->createMock(HttpClientFactoryInterface::class);
    $factory->expects($this->once())->method('createBare')->with(['timeout' => 5])->willReturn($browser);
    $context = new WebRawContext();
    $context->setHttpClientFactory($factory);

    $this->assertSame($browser, $context->httpBareClient(['timeout' => 5]));
  }

  public function testHttpDetachedClientCarriesTheSessionIdentity(): void {
    $page = new MockResponse('<html><body>Page</body></html>', ['response_headers' => ['Set-Cookie: SESS=abc; path=/']]);
    $context = $this->createBrowserKitContext(new HttpBrowser(new MockHttpClient($page)));
    $context->getSession()->visit('http://example.com/page');
    $context->requestHeadersSet('X-Token', 't1');

    $basic_authenticator = $this->createMock(BasicAuthenticatorInterface::class);
    $basic_authenticator->method('findCredentials')->willReturn(['username' => 'bob', 'password' => 'pw']);
    $context->setBasicAuthenticator($basic_authenticator);

    $identity = $this->captureDetachedIdentity($context);

    $this->assertSame(['SESS' => 'abc'], $identity->cookies);
    $this->assertSame('http://example.com/page', $identity->cookieUrl);
    $this->assertSame(['X-Token' => 't1'], $identity->headers);
    $this->assertSame(['username' => 'bob', 'password' => 'pw'], $identity->credentials);
  }

  public function testHttpDetachedClientBeforeAnyPageCarriesNoCookies(): void {
    $context = $this->createBrowserKitContext(new HttpBrowser(new MockHttpClient()));

    $identity = $this->captureDetachedIdentity($context);

    $this->assertSame([], $identity->cookies);
    $this->assertSame('', $identity->cookieUrl);
    $this->assertNull($identity->credentials);
  }

  public function testPrerequisitesHoldWhenEveryDeclarationIsMet(): void {
    $drupal = $this->createStub(DrupalDriverInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);

    $context = $this->createPrerequisiteContext(['drupal' => $drupal]);
    $context->callAssertPrerequisites(SamplePrerequisiteTrait::class);

    $this->assertTrue($context->callPrerequisitesMet(SamplePrerequisiteTrait::class));
  }

  /**
   * Tests that the first unmet prerequisite fails with its message.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Driver\DriverInterface>> $drivers
   *   Driver interfaces to stub, keyed by the name the scenario lists each
   *   one under, in order.
   * @param bool $enabled
   *   What each stub reports for any module.
   * @param string $trait
   *   The trait whose prerequisites to assert.
   * @param class-string<\RuntimeException> $exception
   *   The exception the assertion throws.
   * @param string $message
   *   The message it throws with.
   */
  #[DataProvider('dataProviderUnmetPrerequisiteFails')]
  public function testUnmetPrerequisiteFails(array $drivers, bool $enabled, string $trait, string $exception, string $message): void {
    $stubs = [];

    foreach ($drivers as $name => $interface) {
      $stub = $this->createStub($interface);
      $stub->method('moduleIsEnabled')->willReturn($enabled);
      $stubs[$name] = $stub;
    }

    $context = $this->createPrerequisiteContext($stubs);

    $this->assertFalse($context->callPrerequisitesMet($trait));

    $this->expectException($exception);
    $this->expectExceptionMessage($message);

    $context->callAssertPrerequisites($trait);
  }

  public static function dataProviderUnmetPrerequisiteFails(): \Iterator {
    $switch_off = ' Meet the prerequisite, or switch SamplePrerequisiteTrait off with the "sample_prerequisite.enabled" option or the "@behat-steps-skip:SamplePrerequisiteTrait" tag.';

    yield 'no driver provides the capability' => [
      ['drush' => DrushDriverInterface::class],
      TRUE,
      SamplePrerequisiteTrait::class,
      UnsupportedDriverActionException::class,
      'SamplePrerequisiteTrait requires that a driver in the scenario\'s list provides "CoreCapabilityInterface", which does not hold. Drivers available to this scenario, in order: drush.' . $switch_off,
    ];
    yield 'no driver listed' => [
      [],
      TRUE,
      SamplePrerequisiteTrait::class,
      UnsupportedDriverActionException::class,
      'SamplePrerequisiteTrait requires that a driver in the scenario\'s list provides "CoreCapabilityInterface", which does not hold. Drivers available to this scenario, in order: none.' . $switch_off,
    ];
    yield 'a check fails for a trait that switches off' => [
      ['drupal' => DrupalDriverInterface::class],
      FALSE,
      SamplePrerequisiteTrait::class,
      \RuntimeException::class,
      'SamplePrerequisiteTrait requires that the "sample" module is enabled, which does not hold.' . $switch_off,
    ];
  }

  public function testUnmetPrerequisiteOfTraitWithoutSwitchNamesNoSwitch(): void {
    $drupal = $this->createStub(DrupalDriverInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    try {
      $this->createPrerequisiteContext(['drupal' => $drupal])->callAssertPrerequisites(StepPrerequisiteTrait::class);
      $this->fail('An unmet prerequisite did not fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('StepPrerequisiteTrait requires that the "step" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public function testReachedDriverAnswersBeforeFirstInList(): void {
    $drush = $this->createMock(DrushDriverInterface::class);
    $drush->expects($this->never())->method('moduleIsEnabled');

    $drupal = $this->createStub(DrupalDriverInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);

    $this->assertTrue($this->createPrerequisiteContext(['drush' => $drush, 'drupal' => $drupal])->callPrerequisitesMet(SamplePrerequisiteTrait::class));
  }

  #[DataProvider('dataProviderAnyDriverForReusesReachedDriver')]
  public function testAnyDriverForReusesReachedDriver(bool $reach_drupal, string $expected): void {
    $drivers = ['drush' => $this->createStub(DrushDriverInterface::class), 'drupal' => $this->createStub(DrupalDriverInterface::class)];
    $context = $this->createPrerequisiteContext($drivers);

    if ($reach_drupal) {
      $context->driverFor(CoreCapabilityInterface::class);
    }

    $this->assertSame($drivers[$expected], $context->callAnyDriverFor(ModuleCapabilityInterface::class));
  }

  public static function dataProviderAnyDriverForReusesReachedDriver(): \Iterator {
    yield 'a reached driver answers before the first listed' => [TRUE, 'drupal'];
    yield 'the first listed answers when none was reached' => [FALSE, 'drush'];
  }

  /**
   * Builds a context whose Mink session runs on the given BrowserKit browser.
   */
  protected function createBrowserKitContext(HttpBrowser $browser): WebRawContext {
    $mink = new Mink(['default' => new Session(new BrowserKitDriver($browser, 'http://example.com'))]);
    $mink->setDefaultSessionName('default');

    $context = new WebRawContext();
    $context->setMink($mink);

    return $context;
  }

  /**
   * Returns the identity the context hands the factory for a detached browser.
   */
  protected function captureDetachedIdentity(WebRawContext $context): HttpIdentity {
    $captured = NULL;

    $factory = $this->createMock(HttpClientFactoryInterface::class);
    $factory->expects($this->once())->method('createDetached')->willReturnCallback(static function (HttpIdentity $identity) use (&$captured): HttpBrowser {
      $captured = $identity;

      return new HttpBrowser(new MockHttpClient());
    });
    $context->setHttpClientFactory($factory);

    $context->httpDetachedClient();

    $this->assertInstanceOf(HttpIdentity::class, $captured);

    return $captured;
  }

  /**
   * Builds an initialized context over the given driver.
   *
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver the registry hands out.
   */
  protected function createContext(DriverInterface $driver): WebRawContext {
    $driver_registry = new DriverRegistry(['test' => $driver]);
    $driver_registry->setScenarioDrivers(['test' => 'test']);
    $driver_registry->setEnvironment($this->createMock(Environment::class));

    $context = new WebRawContext();
    $context->setDriverRegistry($driver_registry);
    $context->setDispatcher($this->createHookDispatcher());
    $context->setBasicAuthenticator($this->createMock(BasicAuthenticatorInterface::class));

    return $context;
  }

  /**
   * Builds a context composing traits with prerequisites over drivers.
   *
   * @param array<string, \DrevOps\BehatSteps\Driver\DriverInterface> $drivers
   *   The drivers the scenario lists, in order, keyed by name.
   */
  protected function createPrerequisiteContext(array $drivers): PrerequisiteContext {
    $driver_registry = new DriverRegistry($drivers);
    $names = array_keys($drivers);
    $driver_registry->setScenarioDrivers(array_combine($names, $names));

    $context = new PrerequisiteContext();
    $context->setDriverRegistry($driver_registry);

    return $context;
  }

}

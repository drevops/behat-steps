<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Behat\Auth\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Context\BackendAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Http\HttpIdentity;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
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

  public function testImplementsBackendAwareInterface(): void {
    $this->assertInstanceOf(BackendAwareInterface::class, new WebRawContext());
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
    yield 'backend registry' => ['getBackendRegistry', 'The backend registry is available only after Behat has initialized the context.'];
    yield 'basic authenticator' => ['getBasicAuthenticator', 'The basic authenticator is available only after Behat has initialized the context.'];
  }

  public function testTheBackendComesFromTheRegistry(): void {
    $backend = $this->createMock(BackendInterface::class);
    $context = $this->createContext($backend);

    $this->assertSame($backend, $context->getBackend('test'));
    $this->assertSame($backend, $context->backendFor(BackendInterface::class));
  }

  public function testTheRandomGeneratorComesFromTheBackend(): void {
    $random = new Random();
    $backend = $this->createMock(BackendInterface::class);
    $backend->method('getRandom')->willReturn($random);

    $this->assertSame($random, $this->createContext($backend)->getRandom());
  }

  public function testBackendForNamesTheCapabilityWhenNoBackendProvidesIt(): void {
    $context = $this->createContext($this->createMock(BackendInterface::class));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessage(sprintf('No backend provides "%s".', CoreCapabilityInterface::class));

    $context->backendFor(CoreCapabilityInterface::class);
  }

  public function testBackendForBootstrapsOnceAndReturnsTheBackend(): void {
    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->method('isBootstrapped')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $backend->expects($this->once())->method('bootstrap');

    $context = $this->createContext($backend);

    $first = $context->backendFor(CoreCapabilityInterface::class);
    $second = $context->backendFor(CoreCapabilityInterface::class);

    $this->assertSame($backend, $first);
    $this->assertSame($backend, $second);
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
    $mink = new Mink(['default' => new Session($this->createMock(DriverInterface::class))]);
    $mink->setDefaultSessionName('default');
    $context = new WebRawContext();
    $context->setMink($mink);

    $this->expectException(UnsupportedDriverActionException::class);

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
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);

    $context = $this->createPrerequisiteContext(['drupal' => $drupal]);
    $context->callAssertPrerequisites(SamplePrerequisiteTrait::class);

    $this->assertTrue($context->callPrerequisitesMet(SamplePrerequisiteTrait::class));
  }

  /**
   * Tests that the first unmet prerequisite fails with its message.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Backend\BackendInterface>> $backends
   *   Backend interfaces to stub, keyed by the name the scenario lists each
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
  public function testUnmetPrerequisiteFails(array $backends, bool $enabled, string $trait, string $exception, string $message): void {
    $stubs = [];

    foreach ($backends as $name => $interface) {
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

    yield 'no backend provides the capability' => [
      ['drush' => DrushBackendInterface::class],
      TRUE,
      SamplePrerequisiteTrait::class,
      UnsupportedBackendActionException::class,
      'SamplePrerequisiteTrait requires that a backend in the scenario\'s list provides "CoreCapabilityInterface", which does not hold. Backends available to this scenario, in order: drush.' . $switch_off,
    ];
    yield 'no backend listed' => [
      [],
      TRUE,
      SamplePrerequisiteTrait::class,
      UnsupportedBackendActionException::class,
      'SamplePrerequisiteTrait requires that a backend in the scenario\'s list provides "CoreCapabilityInterface", which does not hold. Backends available to this scenario, in order: none.' . $switch_off,
    ];
    yield 'a check fails for a trait that switches off' => [
      ['drupal' => DrupalBackendInterface::class],
      FALSE,
      SamplePrerequisiteTrait::class,
      \RuntimeException::class,
      'SamplePrerequisiteTrait requires that the "sample" module is enabled, which does not hold.' . $switch_off,
    ];
  }

  public function testUnmetPrerequisiteOfTraitWithoutSwitchNamesNoSwitch(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    try {
      $this->createPrerequisiteContext(['drupal' => $drupal])->callAssertPrerequisites(StepPrerequisiteTrait::class);
      $this->fail('An unmet prerequisite did not fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('StepPrerequisiteTrait requires that the "step" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public function testReachedBackendAnswersBeforeFirstInList(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->never())->method('moduleIsEnabled');

    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);

    $this->assertTrue($this->createPrerequisiteContext(['drush' => $drush, 'drupal' => $drupal])->callPrerequisitesMet(SamplePrerequisiteTrait::class));
  }

  #[DataProvider('dataProviderAnyBackendForReusesReachedBackend')]
  public function testAnyBackendForReusesReachedBackend(bool $reach_drupal, string $expected): void {
    $backends = ['drush' => $this->createStub(DrushBackendInterface::class), 'drupal' => $this->createStub(DrupalBackendInterface::class)];
    $context = $this->createPrerequisiteContext($backends);

    if ($reach_drupal) {
      $context->backendFor(CoreCapabilityInterface::class);
    }

    $this->assertSame($backends[$expected], $context->callAnyBackendFor(ModuleCapabilityInterface::class));
  }

  public static function dataProviderAnyBackendForReusesReachedBackend(): \Iterator {
    yield 'a reached backend answers before the first listed' => [TRUE, 'drupal'];
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
   * Builds an initialized context over the given backend.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the registry hands out.
   */
  protected function createContext(BackendInterface $backend): WebRawContext {
    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);
    $backend_registry->setEnvironment($this->createMock(Environment::class));

    $context = new WebRawContext();
    $context->setBackendRegistry($backend_registry);
    $context->setHookDispatcher($this->createHookDispatcher());
    $context->setBasicAuthenticator($this->createMock(BasicAuthenticatorInterface::class));

    return $context;
  }

  /**
   * Builds a context composing traits with prerequisites over backends.
   *
   * @param array<string, \DrevOps\BehatSteps\Backend\BackendInterface> $backends
   *   The backends the scenario lists, in order, keyed by name.
   */
  protected function createPrerequisiteContext(array $backends): PrerequisiteContext {
    $backend_registry = new BackendRegistry($backends);
    $names = array_keys($backends);
    $backend_registry->setScenarioBackends(array_combine($names, $names));

    $context = new PrerequisiteContext();
    $context->setBackendRegistry($backend_registry);

    return $context;
  }

}

<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ForeignMinkExtension;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Tests the config schema and the services the extension puts in the container.
 */
#[CoversClass(BehatStepsExtension::class)]
class BehatStepsExtensionTest extends UnitTestCase {

  /**
   * Working directory to restore after a test that changed it.
   */
  protected string $originalCwd;

  protected function setUp(): void {
    parent::setUp();

    $this->originalCwd = (string) getcwd();
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);

    parent::tearDown();
  }

  public function testConfigKeyNamesTheExtension(): void {
    $this->assertSame('behat_steps', (new BehatStepsExtension())->getConfigKey());
  }

  /**
   * Tests that the first-party factory displaces Mink's own.
   *
   * @param array<int, class-string<\Behat\Testwork\ServiceContainer\Extension>> $locators
   *   The extensions to activate, in activation order.
   */
  #[DataProvider('dataProviderInitializeRegistersTheFirstPartyBrowserKitFactory')]
  public function testInitializeRegistersTheFirstPartyBrowserKitFactory(array $locators): void {
    $manager = new ExtensionManager([]);

    foreach ($locators as $locator) {
      $manager->activateExtension($locator);
    }

    $mink = $manager->getExtension('mink');
    $this->assertInstanceOf(MinkExtension::class, $mink);
    $before = $this->readBrowserDriverFactories($mink);

    $manager->initializeExtensions();

    $after = $this->readBrowserDriverFactories($mink);
    $this->assertNotInstanceOf(BrowserKitFactory::class, $before['browserkit_http']);
    $this->assertInstanceOf(BrowserKitFactory::class, $after['browserkit_http']);
    $this->assertSame(array_keys($before), array_keys($after));
  }

  public static function dataProviderInitializeRegistersTheFirstPartyBrowserKitFactory(): \Iterator {
    yield 'Mink activated first' => [[MinkExtension::class, BehatStepsExtension::class]];
    yield 'Mink activated last' => [[BehatStepsExtension::class, MinkExtension::class]];
  }

  public function testInitializeBuildsTheBrowserKitSessionOnItsOwnClient(): void {
    $mink = $this->initializeMink(new BehatStepsExtension());
    $container = new ContainerBuilder();

    $mink->load($container, $this->processMinkConfig($mink, ['base_url' => 'http://example.com', 'sessions' => ['default' => ['browserkit_http' => ['http_client_parameters' => ['verify_peer' => FALSE]]]]]));

    $session = $container->getDefinition('mink')->getMethodCalls()[0][1][1];
    $this->assertInstanceOf(Definition::class, $session);
    $driver = $session->getArgument(0);
    $this->assertInstanceOf(Definition::class, $driver);
    $browser = $driver->getArgument(0);
    $this->assertInstanceOf(Definition::class, $browser);
    $client = $browser->getArgument(0);
    $this->assertInstanceOf(Definition::class, $client);

    $this->assertSame(BrowserKitDriver::class, $driver->getClass());
    $this->assertSame(HttpBrowser::class, $browser->getClass());
    $this->assertSame([HttpClient::class, 'create'], $client->getFactory());
    $this->assertSame([['verify_peer' => FALSE]], $client->getArguments());
  }

  public function testInitializeSkipsSuiteWithoutMink(): void {
    $manager = new ExtensionManager([]);

    (new BehatStepsExtension())->initialize($manager);

    $this->assertSame([], $manager->getExtensions());
  }

  public function testInitializeLeavesForeignExtensionUnderMinkKey(): void {
    $foreign = new ForeignMinkExtension();

    (new BehatStepsExtension())->initialize(new ExtensionManager([$foreign]));

    $this->assertSame([], $foreign->driverFactories);
  }

  public function testInitializeAddsNoAjaxTimeoutToMinkConfiguration(): void {
    $mink = $this->initializeMink(new BehatStepsExtension());

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Unrecognized option "ajax_timeout" under "mink"');

    $this->processMinkConfig($mink, ['ajax_timeout' => 5, 'sessions' => ['default' => ['browserkit_http' => NULL]]]);
  }

  /**
   * Tests that the transport carries the options the sessions declare.
   *
   * @param array<string, mixed> $sessions
   *   The Mink sessions the suite declares.
   * @param array<string, mixed> $expected
   *   The options the transport is expected to carry.
   */
  #[DataProvider('dataProviderProcessDefinesTheTransportFromTheSessions')]
  public function testProcessDefinesTheTransportFromTheSessions(array $sessions, array $expected): void {
    $extension = new BehatStepsExtension();
    $mink = $this->initializeMink($extension);
    $container = $this->load([], $extension);

    $mink->load($container, $this->processMinkConfig($mink, ['base_url' => 'http://example.com', 'sessions' => $sessions]));
    $extension->process($container);

    $transport = $container->getDefinition(BehatStepsExtension::TRANSPORT_SERVICE);
    $this->assertSame([HttpClientFactory::class, 'createTransport'], $transport->getFactory());
    $this->assertSame([$expected, 'http://example.com'], $transport->getArguments());
  }

  public static function dataProviderProcessDefinesTheTransportFromTheSessions(): \Iterator {
    yield 'a session without options' => [['default' => ['browserkit_http' => NULL]], []];
    yield 'a session with options' => [['default' => ['browserkit_http' => ['http_client_parameters' => ['verify_peer' => FALSE]]]], ['verify_peer' => FALSE]];
    yield '2 sessions with the same options' => [
      [
        'default' => ['browserkit_http' => ['http_client_parameters' => ['timeout' => 30]]],
        'other' => ['browserkit_http' => ['http_client_parameters' => ['timeout' => 30]]],
      ],
      ['timeout' => 30],
    ];
  }

  public function testProcessRejectsSessionsWithDifferentOptions(): void {
    $extension = new BehatStepsExtension();
    $mink = $this->initializeMink($extension);
    $container = $this->load([], $extension);

    $mink->load($container, $this->processMinkConfig($mink, [
      'base_url' => 'http://example.com',
      'sessions' => [
        'default' => ['browserkit_http' => ['http_client_parameters' => ['timeout' => 30]]],
        'slow' => ['browserkit_http' => ['http_client_parameters' => ['timeout' => 60]]],
      ],
    ]));

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The 2 "browserkit_http" sessions declare different "http_client_parameters".');

    $extension->process($container);
  }

  public function testProcessDefinesTheTransportWithoutMink(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load([], $extension);

    $extension->process($container);

    $this->assertSame([[], NULL], $container->getDefinition(BehatStepsExtension::TRANSPORT_SERVICE)->getArguments());
  }

  public function testBlackboxBackendIsAlwaysRegistered(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.backend.blackbox'));
    $this->assertFalse($container->hasDefinition('behat_steps.backend.drupal'));
    $this->assertFalse($container->hasDefinition('behat_steps.backend.drush'));
  }

  public function testServicesFileIsLoaded(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.backend_registry'));
    $this->assertTrue($container->hasDefinition('behat_steps.authenticator'));
    $this->assertTrue($container->hasDefinition('behat_steps.user_registry'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.initializer'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.attribute_reader'));
    $this->assertTrue($container->hasDefinition('behat_steps.listener.backend'));
    $this->assertTrue($container->hasDefinition('behat_steps.listener.skip_tag'));
    $this->assertTrue($container->hasDefinition('behat_steps.region_selector'));
    $this->assertTrue($container->hasDefinition('behat_steps.http_client_factory'));
  }

  public function testDrupalBackendIsRegisteredWithItsRoot(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web']]);

    $this->assertTrue($container->hasDefinition('behat_steps.backend.drupal'));
    $this->assertTrue($container->hasDefinition('behat_steps.backend.core'));
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drupal.drupal_root'));
  }

  public function testDrushBackendIsRegisteredWithItsRoot(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertTrue($container->hasDefinition('behat_steps.backend.drush'));
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drush.root'));
    $this->assertFalse($container->getParameter('behat_steps.backend.drush.alias'));
  }

  public function testDrushBackendAcceptsAliasInsteadOfRoot(): void {
    $container = $this->load(['drush' => ['alias' => '@self']]);

    $this->assertSame('@self', $container->getParameter('behat_steps.backend.drush.alias'));
    $this->assertFalse($container->getParameter('behat_steps.backend.drush.root'));
  }

  public function testDrupalBackendRequiresItsRoot(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The child config "drupal_root" under "behat_steps.drupal" must be configured');

    $this->load(['drupal' => []]);
  }

  public function testDrushBackendRequiresAliasOrRoot(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush `alias` or `root` path is required for the Drush backend.');

    $this->load(['drush' => []]);
  }

  public function testDrushGlobalOptionsReachTheBackend(): void {
    $container = $this->load(['drush' => ['root' => 'web', 'global_options' => '--yes']]);

    $this->assertSame([['setArguments', ['--yes']]], $container->getDefinition('behat_steps.backend.drush')->getMethodCalls());
  }

  public function testDrushGlobalOptionsAreOptional(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.drush')->getMethodCalls());
  }

  /**
   * Tests that the configured region map reaches the container.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param array<string, string> $expected
   *   The region map expected on the container parameter.
   */
  #[DataProvider('dataProviderRegionsReachTheContainer')]
  public function testRegionsReachTheContainer(array $config, array $expected): void {
    $container = $this->load($config);

    $this->assertSame($expected, $container->getParameter('behat_steps.regions'));

    // The same map is surfaced through 'behat_steps.parameters', so a context
    // using ParametersTrait resolves the value the 'region' selector uses.
    $parameters = $container->getParameter('behat_steps.parameters');
    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters['regions']);
  }

  public static function dataProviderRegionsReachTheContainer(): \Iterator {
    yield 'configured map is exposed' => [
      ['regions' => ['Header' => '#header', 'Content' => '#main']],
      ['Header' => '#header', 'Content' => '#main'],
    ];

    yield 'no regions yields an empty map' => [
      [],
      [],
    ];
  }

  /**
   * Tests that the steps section reaches the parameters untouched.
   *
   * A group there may name a trait only one of the registered contexts
   * composes, so the extension validates nothing about its contents.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param array<string, mixed> $expected
   *   The expected steps section.
   */
  #[DataProvider('dataProviderStepsSectionIsPassedThrough')]
  public function testStepsSectionIsPassedThrough(array $config, array $expected): void {
    $parameters = $this->load($config)->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters['steps']);
  }

  public static function dataProviderStepsSectionIsPassedThrough(): \Iterator {
    yield 'one group' => [
      ['steps' => ['javascript' => ['enabled' => FALSE]]],
      ['javascript' => ['enabled' => FALSE]],
    ];

    yield 'nested values are kept as written' => [
      ['steps' => ['mapping' => ['groups' => ['paths' => ['Home' => '/']]]]],
      ['mapping' => ['groups' => ['paths' => ['Home' => '/']]]],
    ];

    yield 'a group no context composes is kept' => [
      ['steps' => ['nonexistent' => ['enabled' => FALSE]]],
      ['nonexistent' => ['enabled' => FALSE]],
    ];

    yield 'the AJAX timeout in the wait group' => [
      ['steps' => ['wait' => ['ajax_timeout' => 10]]],
      ['wait' => ['ajax_timeout' => 10]],
    ];

    yield 'no steps section yields an empty map' => [
      [],
      [],
    ];
  }

  /**
   * Tests the values the schema falls back to.
   *
   * @param string $name
   *   The configuration key to read.
   * @param mixed $expected
   *   The value the schema is expected to default to.
   */
  #[DataProvider('dataProviderSchemaDefaults')]
  public function testSchemaDefaults(string $name, mixed $expected): void {
    $parameters = $this->load([])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters[$name]);
  }

  public static function dataProviderSchemaDefaults(): \Iterator {
    yield 'login_field' => ['login_field', 'name'];
    yield 'login_wait' => ['login_wait', 0];
    yield 'steps' => ['steps', []];
    yield 'text' => [
      'text',
      [
        'login_url' => '/user',
        'logout_url' => '/user/logout',
        'logout_confirm_url' => '/user/logout/confirm',
        'login' => 'Log in',
        'logout' => 'Log out',
        'password_field' => 'Password',
        'username_field' => 'Username',
      ],
    ];
    yield 'selectors' => [
      'selectors',
      [
        'login_form_selector' => 'form#user-login,form#user-login-form',
        'logged_in_selector' => 'body.logged-in,body.user-logged-in',
      ],
    ];
  }

  public function testMessageSelectorsAtTheirFormerPathAreRejected(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "selectors: messages:" setting under "behat_steps" moved to "steps: message: selectors:". Move each severity selector across.');

    $this->load(['selectors' => ['messages' => ['error' => '.messages--error']]]);
  }

  /**
   * Tests that a 'log_in' or 'log_out' text key fails, naming its replacement.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   * @param string $expected_message
   *   The message the configuration is expected to fail with.
   */
  #[DataProvider('dataProviderRenamedTextKeysAreRejected')]
  public function testRenamedTextKeysAreRejected(array $config, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $this->load($config);
  }

  public static function dataProviderRenamedTextKeysAreRejected(): \Iterator {
    $log_in = 'The "text: log_in:" setting under "behat_steps" moved to "text: login:". Rename the key; its value is unchanged.';
    $log_out = 'The "text: log_out:" setting under "behat_steps" moved to "text: logout:". Rename the key; its value is unchanged.';

    yield 'log_in' => [['text' => ['log_in' => 'Sign in']], $log_in];
    yield 'log_out' => [['text' => ['log_out' => 'Sign out']], $log_out];
    yield 'log_in with no value' => [['text' => ['log_in' => NULL]], $log_in];
    yield 'log_out with no value' => [['text' => ['log_out' => NULL]], $log_out];
    yield 'log_in beside login' => [['text' => ['login' => 'Sign in', 'log_in' => 'Sign in']], $log_in];
    yield 'log_out beside logout' => [['text' => ['logout' => 'Sign out', 'log_out' => 'Sign out']], $log_out];
    yield 'log-in spelled with a dash' => [['text' => ['log-in' => 'Sign in']], $log_in];
  }

  public function testTextTheTreeDoesNotDeclareIsKept(): void {
    $parameters = $this->load(['text' => ['acme_greeting' => 'Hello']])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame('Hello', $parameters['text']['acme_greeting']);
  }

  /**
   * Tests that a 'drivers' key fails, naming 'backends' in its place.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   */
  #[DataProvider('dataProviderDriversKeyIsRejected')]
  public function testDriversKeyIsRejected(array $config): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drivers" setting under "behat_steps" moved to "backends". Rename the key; its entries are unchanged.');

    $this->load($config);
  }

  public static function dataProviderDriversKeyIsRejected(): \Iterator {
    yield 'a list' => [['drivers' => ['drupal', 'blackbox']]];
    yield 'a keyed list' => [['drivers' => ['api' => 'drupal']]];
    yield 'a scalar' => [['drivers' => 'drupal']];
    yield 'no value' => [['drivers' => NULL]];
    yield 'beside a backends list' => [['backends' => ['blackbox'], 'drivers' => ['blackbox']]];
  }

  public function testSelectorTheTreeDoesNotDeclareIsKept(): void {
    $parameters = $this->load(['selectors' => ['acme_banner' => '.acme-banner']])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame('.acme-banner', $parameters['selectors']['acme_banner']);
  }

  public function testProcessSwapsInTheContextClassGenerator(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load([], $extension);

    $extension->process($container);

    $this->assertSame(ClassGenerator::class, $container->getDefinition(ContextExtension::CLASS_GENERATOR_TAG . '.simple')->getClass());
  }

  public function testProcessRegistersTheTaggedBackends(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $calls = $container->getDefinition('behat_steps.backend_registry')->getMethodCalls();
    $names = array_map(static fn(array $call): string => $call[0], $calls);

    $this->assertSame(['registerBackend', 'registerBackend'], $names);
  }

  public function testTheConfiguredBackendListReachesTheContainer(): void {
    $container = $this->load(['backends' => ['blackbox', 'api' => 'drupal'], 'drupal' => ['drupal_root' => 'web']]);

    $this->assertSame(['blackbox', 'api' => 'drupal'], $container->getParameter(BehatStepsExtension::BACKENDS_PARAMETER));
  }

  public function testAnOmittedBackendListReachesTheContainerAsEmpty(): void {
    $this->assertSame([], $this->load([])->getParameter(BehatStepsExtension::BACKENDS_PARAMETER));
  }

  /**
   * Tests the backend lists the configuration may declare.
   *
   * @param array<array-key, mixed> $backends
   *   The 'backends' list the configuration declares.
   */
  #[DataProvider('dataProviderProcessAcceptsValidBackendList')]
  public function testProcessAcceptsValidBackendList(array $backends): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['backends' => $backends, 'drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.backend_registry'));
  }

  public static function dataProviderProcessAcceptsValidBackendList(): \Iterator {
    yield 'bare entries' => [['drupal', 'blackbox']];
    yield 'aliased entries' => [['api' => 'drupal']];
    yield 'bare and aliased mixed' => [['blackbox', 'api' => 'drupal']];
    yield 'a name carrying a hyphen, an underscore and a digit' => [['api-2_b' => 'drupal']];
    yield 'a name matched without regard to case' => [['Drupal']];
    yield 'an omitted list' => [[]];
  }

  /**
   * Tests the backend lists the configuration may not declare.
   *
   * @param array<array-key, mixed> $backends
   *   The 'backends' list the configuration declares.
   * @param string $expected_message
   *   Part of the message the build is expected to fail with.
   */
  #[DataProvider('dataProviderProcessRejectsInvalidBackendList')]
  public function testProcessRejectsInvalidBackendList(array $backends, string $expected_message): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter(BehatStepsExtension::BACKENDS_PARAMETER, $backends);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $extension->process($container);
  }

  public static function dataProviderProcessRejectsInvalidBackendList(): \Iterator {
    yield 'an unregistered backend' => [['ghost'], 'The "backends" list under "behat_steps" names the backend "ghost", which is not registered. Registered backends: blackbox, drupal.'];
    yield 'an unregistered backend behind an alias' => [['api' => 'ghost'], 'which is not registered'];
    yield 'a tag name carrying a space' => [['my backend' => 'drupal'], 'so that "@backend:my backend" is a valid tag'];
    yield 'a tag name carrying a colon' => [['my:backend' => 'drupal'], 'so that "@backend:my:backend" is a valid tag'];
    yield 'a tag name carrying a trailing newline' => [["api\n" => 'drupal'], 'A backend name may hold only letters, digits'];
    yield 'an entry that is not a name' => [[['drupal']], 'holds an entry that is not a backend name'];
    yield 'an empty entry' => [[''], 'holds an entry that is not a backend name'];
    yield 'the same name twice' => [['drupal', 'drupal'], 'names "drupal" twice'];
    yield 'two names differing only by case' => [['drupal', 'Drupal'], 'names "drupal" twice'];
    yield 'two aliases differing only by case' => [['api' => 'drupal', 'API' => 'blackbox'], 'names "api" twice'];
  }

  public function testTheSameBackendMayCarryTwoDistinctNames(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['backends' => ['api' => 'drupal', 'web' => 'drupal'], 'drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.backend_registry'));
  }

  public function testProcessSkipsValidationWithoutTheBackendsParameter(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->getParameterBag()->remove(BehatStepsExtension::BACKENDS_PARAMETER);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.backend_registry'));
  }

  public function testProcessSkipsValidationWhenTheParameterIsNotList(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter(BehatStepsExtension::BACKENDS_PARAMETER, 'drupal');

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.backend_registry'));
  }

  public function testTheSchemaRefusesBackendListThatIsNotList(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('behat_steps.backends');

    $this->load(['backends' => 'drupal']);
  }

  public function testAbsoluteBinaryPathIsReturnedAsIs(): void {
    $this->assertSame('/usr/local/bin/drush', BehatStepsExtension::resolveBinaryPath('/usr/local/bin/drush'));
  }

  public function testBareBinaryCommandIsReturnedAsIs(): void {
    $this->assertSame('drush', BehatStepsExtension::resolveBinaryPath('drush'));
  }

  public function testBinaryPathResolvesFromWorkingDirectory(): void {
    $project = $this->createDrushProject();
    chdir($project);

    $this->assertSame($project . '/vendor/bin/drush', BehatStepsExtension::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testBinaryPathResolvesFromParentDirectory(): void {
    $project = $this->createDrushProject();
    chdir($project . '/web');

    $this->assertSame($project . '/vendor/bin/drush', BehatStepsExtension::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testUnresolvableBinaryPathIsReturnedAsIs(): void {
    chdir(static::$tmp);

    $this->assertSame('some/nonexistent/binary', BehatStepsExtension::resolveBinaryPath('some/nonexistent/binary'));
  }

  /**
   * Runs a raw configuration array through the schema and into a container.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param \DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension|null $extension
   *   The extension to load with, when the test needs it afterwards.
   */
  protected function load(array $config, ?BehatStepsExtension $extension = NULL): ContainerBuilder {
    $extension ??= new BehatStepsExtension();

    $builder = new ArrayNodeDefinition(BehatStepsExtension::CONFIG_KEY);
    $extension->configure($builder);
    $tree = $builder->getNode(TRUE);

    $container = new ContainerBuilder();
    $extension->load($container, $tree->finalize($tree->normalize($config)));

    return $container;
  }

  /**
   * Returns Mink's extension after the given extension initialized against it.
   *
   * @param \DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension $extension
   *   The extension to initialize.
   */
  protected function initializeMink(BehatStepsExtension $extension): MinkExtension {
    $mink = new MinkExtension();

    $extension->initialize(new ExtensionManager([$mink]));

    return $mink;
  }

  /**
   * Runs a raw Mink configuration array through Mink's schema.
   *
   * @param \Behat\MinkExtension\ServiceContainer\MinkExtension $mink
   *   The Mink extension whose schema to apply.
   * @param array<string, mixed> $config
   *   The Mink configuration, before schema normalisation.
   *
   * @return array<string, mixed>
   *   The processed configuration.
   */
  protected function processMinkConfig(MinkExtension $mink, array $config): array {
    $builder = new ArrayNodeDefinition('mink');
    $mink->configure($builder);
    $tree = $builder->getNode(TRUE);

    $processed = $tree->finalize($tree->normalize($config));
    $this->assertIsArray($processed);

    return $processed;
  }

  /**
   * Returns the driver factories Mink's extension holds, keyed by driver name.
   *
   * @param \Behat\MinkExtension\ServiceContainer\MinkExtension $mink
   *   The Mink extension to read.
   *
   * @return array<string, \Behat\MinkExtension\ServiceContainer\Driver\DriverFactory>
   *   The registered factories.
   */
  protected function readBrowserDriverFactories(MinkExtension $mink): array {
    $factories = (new \ReflectionProperty(MinkExtension::class, 'driverFactories'))->getValue($mink);
    $this->assertIsArray($factories);

    return $factories;
  }

  /**
   * Writes a project with a Drush binary and a web root below it.
   *
   * @return string
   *   The project directory.
   */
  protected function createDrushProject(): string {
    $this->writeFixture('project/vendor/bin/drush', '');
    mkdir(static::$tmp . '/project/web');

    return static::$tmp . '/project';
  }

}

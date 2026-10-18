# Migrating from 3.x to 4.0

This guide takes a project from behat-steps 3.14 to 4.0. Every name in a `3.14` column is one a 3.14 project had, either from this package or from the Drupal Extension 6 and Drupal Driver 3 that its Drupal traits built on. 4.0 uses neither of them. A project on an earlier 3.x release updates to 3.14 first.

## How to read this guide

The sections follow the order of an upgrade: Composer requirements, Behat configuration, context classes, feature files, then the PHP your project writes against this package. [Failure messages](#failure-messages) comes last, because only a test that asserts on message text needs it.

Every table follows the same conventions, so a tool can apply it as written:

- A mapping table reads left to right. The `3.14` column holds what a 3.14 project used, and the `4.0` column holds what replaces it. A `Notes` column, where a table has one, says what else changes.
- `Removed` alone in a `4.0` cell means nothing replaces the name, and the notes say what to do instead.
- Step text is written the way Behat registers it, keyword included. `:name` is a placeholder, a word in parentheses such as `(s)` or `( region)` is optional, `a/an` lists alternatives, and a step ending in `...` stands for every step that starts with the text before it. A `4.0` cell listing several steps on separate lines replaces 1 step with that sequence.
- A placeholder carries its value from the `3.14` step to the `4.0` one. A name in both cells is the same value, and a renamed placeholder keeps its role, as `:rowText` does when it becomes `:partial_text`, so values pair by meaning, never by position. A placeholder the `4.0` step doesn't name drops its value, and the new step no longer checks it.
- A method is written `Owner::method()`, where the owner is the short name of the trait, class or interface that declares it. Tables of classes give fully qualified names, and a part in angle brackets, such as `<Name>` or `<N>`, stands for the same part on both sides of a row.
- In a failure message, `...` stands for a value and `N` for a number.

## Requirements

4.0 needs:

- PHP 8.3 or newer. 3.14 ran on PHP 8.2.
- Drupal 11 or 12 on a Drupal site. 4.0 requires no Drupal package of its own, and its Drupal backend throws a `BootstrapException` on Drupal 10.
- Behat 3 or Behat 4. 3.14 refused Behat 4. On Behat 4, it also needs `friends-of-behat/mink-extension` 3, which only has an alpha release so far, so the installation section of the [README](README.md) shows how to require both.
- Drush 13 or newer for the Drush backend, as [Backend contracts](#backend-contracts) explains.

4.0 replaces `drupal/drupal-extension` and `drupal/drupal-driver`, and it conflicts with every version of both, so Composer won't install it while your project requires either one. Remove the extension before updating, and add `drupal/drupal-driver` to the same command if your `composer.json` requires it directly:

```bash
composer remove --dev drupal/drupal-extension
```

Removing the extension removes the packages it required. Any other package that requires either one blocks the update too, and Composer's error names it.

| Package | 3.14 | 4.0 |
| --- | --- | --- |
| `drupal/drupal-extension` | Required by the Drupal traits | Removed; 4.0 conflicts with every version |
| `drupal/drupal-driver` | Installed with the Drupal Extension | Removed; 4.0 conflicts with every version |
| `lullabot/mink-selenium2-driver` | Installed with the Drupal Extension | Required by your project when it runs `@javascript` scenarios through Selenium |
| `dmore/behat-chrome-extension` | Optional | Optional; 1.5.0 or newer on Behat 4 |
| `webflo/drupal-finder` | Installed with the Drupal Extension | Required by your project when your code calls it |
| `softcreatr/jsonpath` | Optional | Optional |
| `justinrainbow/json-schema` | Optional | Optional |
| `symfony/browser-kit`, `symfony/http-client`, `symfony/mime` | Not required by this package | Required by this package |

A suite that runs `@javascript` scenarios installs 1 of the 2 browser drivers: `lullabot/mink-selenium2-driver` drives a Selenium server, and `dmore/behat-chrome-extension` drives headless Chrome with no Selenium server. A Drupal project that got the Selenium driver from the Drupal Extension now requires it directly:

```bash
composer require --dev lullabot/mink-selenium2-driver
```

## Behat configuration

### Extensions

Mink's own extension replaces the Drupal Extension's Mink extension, and the extension this package supplies replaces the Drupal Extension:

| 3.14 | 4.0 |
| --- | --- |
| `Drupal\MinkExtension` | `Behat\MinkExtension` |
| `Drupal\DrupalExtension` | `DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension` |

In `behat.yml`:

```yaml
# Before.
extensions:
  Drupal\MinkExtension:
    base_url: http://your-site.local
    sessions:
      browserkit_http:
        browserkit_http: ~
  Drupal\DrupalExtension:
    drupal:
      drupal_root: web

# After.
extensions:
  Behat\MinkExtension:
    base_url: http://your-site.local
    sessions:
      browserkit_http:
        browserkit_http: ~
  DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension:
    drupal:
      drupal_root: web
```

In `behat.php`, which Behat 4 requires, `Behat\MinkExtension` is the `Behat\MinkExtension\ServiceContainer\MinkExtension` class:

```php
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

$profile
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
  ]));
```

### Extension settings

The Mink options, such as `base_url`, `files_path`, `javascript_session`, `selenium2` and `browserkit_http`, carry over under `Behat\MinkExtension`. These `Drupal\DrupalExtension` settings carry over under `BehatStepsExtension` with the same name and value: `blackbox`, `drupal`, `drush`, `regions`, `login_field`, `login_wait`, the `text` keys other than `log_in` and `log_out`, `selectors.login_form_selector` and `selectors.logged_in_selector`. The `regions` map keeps its shape, so the region steps resolve the same names.

The other `Drupal\DrupalExtension` settings move or go. A dotted path names a nested key, so `steps.wait.ajax_timeout` is `ajax_timeout` under `wait` under `steps`:

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `default_driver` | `backends` | See [Backends](#backends) |
| `api_driver` | `backends` | See [Backends](#backends) |
| `drush_driver` | `backends` | See [Backends](#backends) |
| `ajax_timeout` | `steps.wait.ajax_timeout` | |
| `selectors.messages` | `steps.message.selectors` | |
| `mappings` | `steps.mapping.groups` | |
| `text.log_in` | `text.login` | |
| `text.log_out` | `text.logout` | |
| `region_map` | `regions` | The spelling Drupal Extension 6.0 still accepted |
| `suppress_deprecations` | Removed | Delete the key |

A key left under its old name fails the container build. `ajax_timeout`, `mappings`, `region_map`, `suppress_deprecations` and the `*_driver` keys fail with an `Unrecognized option` error, such as `Unrecognized option "ajax_timeout" under "behat_steps"`, and `selectors.messages`, `text.log_in` and `text.log_out` fail with a message naming the new key.

```yaml
# Before.
default:
  extensions:
    Drupal\DrupalExtension:
      ajax_timeout: 5
      selectors:
        messages:
          default: '.messages'
          error: '.messages--error'
        logged_in_selector: 'body.user-logged-in'
      mappings:
        paths:
          User Login: /user/login

# After.
default:
  extensions:
    DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension:
      selectors:
        logged_in_selector: 'body.user-logged-in'
      steps:
        wait:
          ajax_timeout: 5
        message:
          selectors:
            default: '.messages'
            error: '.messages--error'
        mapping:
          groups:
            paths:
              User Login: /user/login
```

The same settings in `behat.php`:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'selectors' => ['logged_in_selector' => 'body.user-logged-in'],
  'steps' => [
    'wait' => ['ajax_timeout' => 5],
    'message' => ['selectors' => [
      'default' => '.messages',
      'error' => '.messages--error',
    ]],
    'mapping' => ['groups' => ['paths' => ['User Login' => '/user/login']]],
  ],
]));
```

### HTTP client options

4.0 builds every `browserkit_http` session on Mink's own `HttpBrowser`, and the requests steps send from PHP go through Symfony HttpClient. Drupal's `DrupalTestBrowser` and Guzzle are no longer used, so the `guzzle_request_options` of a `browserkit_http` session under `Drupal\MinkExtension` give way to Mink's own `http_client_parameters`, which take [Symfony HttpClient options](https://symfony.com/doc/current/http_client.html):

```yaml
# Before.
extensions:
  Drupal\MinkExtension:
    sessions:
      browserkit_http:
        browserkit_http:
          guzzle_request_options:
            verify: false

# After.
extensions:
  Behat\MinkExtension:
    sessions:
      browserkit_http:
        browserkit_http:
          http_client_parameters:
            verify_peer: false
            verify_host: false
```

| 3.14 Guzzle option | 4.0 Symfony HttpClient option |
| --- | --- |
| `verify: false` | `verify_peer: false` and `verify_host: false` |
| `verify: /path/to/ca.pem` | `cafile: /path/to/ca.pem` |
| `cert: /path/to/client.pem` | `local_cert: /path/to/client.pem` |
| `auth: [user, pass]` | `auth_basic: [user, pass]` |
| `timeout: 30` | `max_duration: 30` |
| `proxy` | `proxy` |
| `headers` | `headers` |

The page applies the options to every host, as it did with Guzzle. The requests steps send from PHP apply them to `base_url` only, and every `browserkit_http` session has to declare the same ones; [HTTP clients](docs/content/guides/http-clients.md) explains both rules.

### Backends

The Drupal Extension picked a driver for a scenario from the `@api` tag and the `default_driver`, `api_driver` and `drush_driver` settings. 4.0 replaces them with 1 `backends` list under `behat_steps`, which names the backends a scenario may reach, in precedence order. A step resolves a backend by the capability it needs, such as `ContentCapabilityInterface`. `@api` and `@drush` no longer select anything, so [Tags](#tags) lists them as removed.

| 3.14 | 4.0 |
| --- | --- |
| `'default_driver' => 'blackbox'` | No `backends` list: every registered backend, in registration order |
| `'api_driver' => 'drupal'` | `'backends' => ['drupal', 'blackbox']` |
| `'drush_driver' => 'drush'` | `'drush'` in the `backends` list |

```php
// Before.
use Drupal\DrupalExtension\ServiceContainer\DrupalExtension;

$profile->withExtension(new Extension(DrupalExtension::class, [
  'default_driver' => 'blackbox',
  'api_driver' => 'drupal',
  'drush_driver' => 'drush',
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));

// After.
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['drupal', 'drush', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));
```

- Every name in the list has to be a backend the extension registers, so each one needs the settings block that registers it: `drupal` needs `drupal:`, `drush` needs `drush:`, and `blackbox` is always registered. A name without its block fails the container build.
- An entry may be keyed, so a profile can swap the implementation behind a name without editing any Gherkin: `'backends' => ['api' => 'drupal', 'blackbox']` in one profile and `'backends' => ['api' => 'acme-jsonapi', 'blackbox']` in another both answer `@backend:api`.
- `@backend:NAME` on a scenario moves that backend to the front of the scenario's order. It never adds a backend the list doesn't hold, and a name outside the list fails at scenario start.
- Restricting a run to fewer backends is a profile's job. The package reads nothing from a Behat suite's settings.
- An empty `alias` or `root` under `behat_steps: drush:` fails the container build and names the setting, where 3.14 read it as missing. Leave the setting out, or set it to `NULL`.

```gherkin
@backend:drush
Scenario: The cache is cleared over the command line
  Given the cache has been cleared
```

Removing `@api` changes 3 things to check in an existing project:

- A step that used to fail for a missing `@api` now runs, because the tag no longer gates anything. Where that gate mattered, run those scenarios under a profile whose `backends` list excludes the Drupal backend.
- Hooks that skipped a scenario without `@api` now run in it. 3.14 ran the scenario hooks of `ConfigTrait`, `ConfigOverrideTrait`, `EmailTrait`, `FileTrait`, `ModuleTrait`, `StateTrait`, `TestmodeTrait`, `TimeTrait` and `WatchdogTrait` only in `@api` scenarios, and so did its entity cleanup and the Drupal Extension's static cache reset. They now run in every scenario of a context that composes their trait. The watchdog check, for one, reads the log after a scenario that never carried `@api`, and fails the scenario at its start when the profile lists no backend that can read the log.
- A suite or profile that filters on `@api`, such as `filters: { tags: '@api' }` or `'~@api'`, filters on a tag no scenario carries, so it runs none of its scenarios or all of them. Select those scenarios with a profile and its own `backends` list instead.

### Trait options

Trait options are new in 4.0. 3.14 tuned a trait by overriding a `Get` method in your `FeatureContext`, such as `modalGetSelectors()`, `tableGetHeaderSelector()` or `queueGetProcessLimit()`. Those methods still exist and return the matching option, so an override still wins once it's declared `public`, as [Visibility changes](#visibility-changes) explains. The option itself can be set without code:

- for a profile, under the extension's `steps` section;
- for 1 context, through its `config` argument, as [Constructor](#constructor) shows;
- for 1 feature or scenario, through the tag the option declares, where it declares one.

An option resolves through the declaration default, then `behat_steps: steps:`, then the context's `config` argument, then the feature tag, then the scenario tag. Each group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe` and `FileDownloadTrait` reads `file_download`. [STEPS.md](STEPS.md) lists each trait's options beside its steps, and [Trait options](docs/content/extending/steps.md#declare-options-for-a-trait-of-your-own) shows how a trait of your own declares one.

A `config` argument that names a group or an option its context can't serve fails while Behat builds the context, and the error names what the context accepts. `WebContext` accepts the groups its web traits declare, such as `javascript`, `modal`, `wait`, `message`, `mapping` and `diagnostics`, and `DrupalContext` adds the groups of the Drupal traits, such as `watchdog`, `big_pipe`, `cache`, `queue` and `email`. The extension's `steps` section is read permissively: a group there may name a trait that only 1 of the registered contexts composes.

### Contexts to register

A 3.14 suite registered the Drupal Extension's contexts, such as `DrupalContext`, `MinkContext`, `MessageContext` and `DrushContext`, beside a context of its own that composed this package's traits. 4.0's `DrevOps\BehatSteps\DrupalContext` composes all 57 step traits, which carry the Drupal Extension's vocabulary, so `$suite->addContext(DrupalContext::class)` alone gives a Drupal suite every step this package ships.

Register Mink's own `Behat\MinkExtension\Context\MinkContext` where the suite registered the Drupal Extension's `MinkContext`, which extended it. It brings the base browser steps, such as `I am on`, `I go to`, `I should see`, `I fill in`, `I press`, `I follow`, `I check`, `I select`, `I attach the file` and `the response status code should be`, with their text unchanged. Don't register the Drupal Extension's `RandomContext` or `MappingContext` either: `RandomTrait` and `MappingTrait` carry the `[?name:type]` and `{{ Key }}` tokens unchanged, and `WebContext` composes both.

- Registering `WebContext` beside `DrupalContext` is fatal, because the 28 web traits would register their steps twice. `WebContext::assertOneContext()` runs on `BeforeSuite` and names the mistake.
- 2 registered contexts can't compose the same trait: Behat throws a `RedundantStepException`. Drop the trait from the context you composed by hand, or register the shipped context instead of it.
- An inherited step can't be removed, so a Drupal project can't take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step extends `WebRawContext` and composes the traits it wants.
- `DrupalContext` composes `WatchdogTrait`, so a suite that registers it fails any scenario that logs a PHP error, even when your 3.14 context never composed the trait. The check reads the `watchdog` table, which only the core `dblog` module creates, and it reads it in the Behat process, so it needs a backend such as `drupal`. On a site without `dblog`, or under a profile that lists no such backend, such as `'backends' => ['drush', 'blackbox']`, every scenario fails at its start until you meet the prerequisite or switch the check off for the profile with `'steps' => ['watchdog' => ['enabled' => FALSE]]`. Setting `fail_on_errors` to `FALSE`, or tagging a scenario `@error`, doesn't cover an unmet prerequisite, because both only apply to errors that were read.

### Environment variables

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `BEHAT_DRUPALEXTENSION_DISABLE_CLEANUP=1` | `BEHAT_STEPS_DISABLE_CLEANUP=1` | Switches the user, role and entity cleanup off for the whole run. `@behat-steps-skip:AuthTrait` and `@behat-steps-skip:EntityLifecycleTrait` switch the same cleanup off for 1 scenario or feature, which no 3.14 tag could do |

## Context classes

### Base classes

A 3.14 context extended the Drupal Extension's `DrupalContext` or `RawDrupalContext`, or Mink's `RawMinkContext` on a site that isn't Drupal. 4.0 ships a chain of its own, with `WebRawContext` at the root and each class below it adding 1 half of the vocabulary:

```
        Behat\MinkExtension\Context\RawMinkContext
                          |
                    WebRawContext
                          |
                     WebContext
                          |
                   DrupalContext
```

| Extend | When |
| --- | --- |
| `DrupalContext` | The suite tests a Drupal site and wants all 57 step traits |
| `WebContext` | The suite tests a web page and wants the 28 web step traits |
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

| 3.14 parent | 4.0 parent |
| --- | --- |
| `Drupal\DrupalExtension\Context\RawDrupalContext` | `DrevOps\BehatSteps\WebRawContext` |
| `Behat\MinkExtension\Context\RawMinkContext`, composing this package's traits | `DrevOps\BehatSteps\WebRawContext` |
| `Drupal\DrupalExtension\Context\DrupalContext` | `DrevOps\BehatSteps\DrupalContext`, without the `use` lines for the traits it already composes |

```php
// Before.
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\JavascriptTrait;
use DrevOps\BehatSteps\WaitTrait;

class UiContext extends RawMinkContext {

  use JavascriptTrait;
  use WaitTrait;

}

// After.
use DrevOps\BehatSteps\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;

class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

Of the web traits 3.14 shipped, `JsonTrait`, `LinkTrait`, `PathTrait`, `ResponseTrait`, `ResponsiveTrait` and `XmlTrait` still compose onto Mink's `RawMinkContext`. Every other trait needs a context that extends `WebRawContext`, because it reads a trait option, a backend, a browser capability, an HTTP client or a skip tag through members only `WebRawContext` declares. A context that composes one onto `RawMinkContext` or the Drupal Extension's `RawDrupalContext` fails at run time once the trait calls such a member, such as the `skipTag()` its hooks open with. Each trait names the class it needs in a `@phpstan-require-extends` annotation, so PHPStan reports the mismatch before the run does.

### Constructor

`WebRawContext::__construct(array $config = [])` takes the trait options that differ between 2 contexts of 1 profile, and every shipped and consuming context inherits it. The Drupal Extension's `RawDrupalContext` declared no constructor, so a 3.14 context that declares its own never called a parent one. Once it extends `WebRawContext`, `WebContext` or `DrupalContext`, it adds the parameter and forwards it:

```php
// Before.
use Drupal\DrupalExtension\Context\RawDrupalContext;

class UiContext extends RawDrupalContext {

  public function __construct(protected string $fixtures_path) {
  }

}

// After.
use DrevOps\BehatSteps\WebRawContext;

class UiContext extends WebRawContext {

  public function __construct(
    protected string $fixtures_path,
    array $config = [],
  ) {
    parent::__construct($config);
  }

}
```

A constructor that doesn't forward it fails as soon as a hook or a step reads an option:

```
UiContext declares a constructor that does not call parent::__construct(), so its "config" argument was never set. Add "array $config = []" to the constructor and forward it.
```

[Context constructor arguments](docs/content/guides/configuration.md#context-constructor-arguments) shows how a suite passes `config`.

### Interfaces

The Drupal Extension's `DrupalAwareInterface`, which `RawDrupalContext` implemented, is split in 2:

- `BackendAwareInterface`, which `WebRawContext` implements. It declares the accessors for the backend registry, the hook dispatcher, the HTTP client factory, option resolution and basic authentication. Basic authentication is a service of its own, reached through `setBasicAuthenticator()` and `getBasicAuthenticator()`.
- `UserAwareInterface`, which declares the 4 accessors `AuthTrait` implements: `authSetUserRegistry()`, `authGetUserRegistry()`, `authSetAuthenticator()` and `authGetAuthenticator()`. A context that composes `AuthTrait`, as `UserTrait` does, declares it, so the context initializer injects the user registry and the authenticator. A context that creates no users declares nothing, and neither service is injected.

A context that extends a shipped context inherits both. One that implemented `DrupalAwareInterface` by hand implements the 2 new interfaces instead, with the new types in its accessor signatures, or PHP refuses to load it. [Drupal Extension methods](#drupal-extension-methods) maps the accessors.

### Trait namespaces

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `DrevOps\BehatSteps\<Name>Trait` | `DrevOps\BehatSteps\Steps\Web\<Name>Trait` | Every web trait; the trait name is unchanged |
| `DrevOps\BehatSteps\Drupal\<Name>Trait` | `DrevOps\BehatSteps\Steps\Drupal\<Name>Trait` | Every Drupal trait; the trait name is unchanged |
| `DrevOps\BehatSteps\HelperTrait` | Removed | Its members moved to traits under `DrevOps\BehatSteps\Steps\Helper\Web`; see [HelperTrait methods](#helpertrait-methods) |
| `DrevOps\BehatSteps\Drupal\HelperTrait` | Removed | Its members moved to traits under `DrevOps\BehatSteps\Steps\Helper\Drupal`; see [HelperTrait methods](#helpertrait-methods) |
| `DrevOps\BehatSteps\Drupal\OverrideTrait` | Removed | [OverrideTrait methods](#overridetrait-methods) lists what replaces each of its overrides |

```php
// Before.
use DrevOps\BehatSteps\CookieTrait;
use DrevOps\BehatSteps\Drupal\ContentTrait;
use DrevOps\BehatSteps\HelperTrait;

// After.
use DrevOps\BehatSteps\Steps\Web\CookieTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;
```

### Trait composition

A step trait composes the helper traits it calls. Composing a step trait needs no `use` statement for the Drupal helpers, and extending `WebRawContext` needs none for `LastStepTrait`, `RequestHeadersTrait` or `StringTrait`, which it composes. A context that called a 3.14 `HelperTrait` member composes the trait that holds it now, as [HelperTrait methods](#helpertrait-methods) lists.

- `FieldTrait` no longer composes `KeyboardTrait`, so a context that composed only `FieldTrait` loses the keyboard steps. Compose `KeyboardTrait` as well:

  ```php
  use DrevOps\BehatSteps\Steps\Web\KeyboardTrait;

  class FeatureContext extends WebRawContext {

    use FieldTrait;
    use KeyboardTrait;

  }
  ```

- `SearchApiTrait` no longer composes `ContentTrait`, so a context that relied on it for the content steps composes `ContentTrait` itself.
- `ConfigOverrideTrait` sets its `X-Config-No-Override` header in the request header bag of `RequestHeadersTrait`, where 3.14 set it on `RestTrait`'s property. The bag belongs to 1 context, so a suite that wants the header on `RestTrait`'s own requests composes both traits into 1 context. The browser header reaches the site either way.
- `ConfigOverrideTrait` hands the header to every backend that provides `RequestEnvironmentCapabilityInterface`, where 3.14 wrote the `$_SERVER` entry and the environment variable into the Behat process itself. The in-process backend writes the `$_SERVER` entry, and the Drush backend passes the variable to each command it runs, so a command a step starts some other way, such as through `CommandTrait`, no longer inherits it.
- The entity teardown comes with the traits that create entities. A context that composes no entity-creating trait runs no entity teardown, where `RawDrupalContext` ran its `clean*()` hooks in every context that extended it. 4.0's `DrupalContext` composes every entity-creating trait, and a context that wants the teardown without the Drupal vocabulary composes the helper alone:

  ```php
  class SpecContext extends WebRawContext {

    use EntityLifecycleTrait;

  }
  ```

## Step text

### Steps of this package

Each step this package registered in 3.14 that 4.0 doesn't register verbatim has a row below, under the trait that registers it in both versions. A step that isn't listed kept its text. The new wording follows the step text rules in [CONTRIBUTING.md](CONTRIBUTING.md#steps-format).

A placeholder name never appears in a feature file, so a row that only renames a placeholder needs no edit there. Behat binds a step argument to the method parameter of the same name, though, so the parameter behind a renamed placeholder is renamed too, and [Methods](#methods) lists each one.

#### AccessibilityTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the current page should pass accessibility checks` | `Then the page should pass accessibility checks` |
| `Then the current page should pass accessibility checks for tags :rules` | `Then the page should pass accessibility checks for the tags :tags` |

#### BlockTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the block :label has the :condition condition removed` | `Given the block :label has the condition :condition removed` |
| `Given the block :label has the following :condition condition configuration:` | `Given the block :label has the condition :condition with the following configuration:` |
| `Given the instance of :admin_label block exists with the following configuration:` | `Given the instance of the block :admin_label with the following configuration exists:` |
| `Then the block :label should exist in the :region region` | `Then the block :label in the region :region should exist` |
| `Then the block :label should not exist in the :region region` | `Then the block :label in the region :region should not exist` |

#### CommandTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the command error output should contain :text` | `Then the command error output should contain :value` |
| `Then the command output should be :text` | `Then the command output should be :value` |
| `Then the command output should contain :text` | `Then the command output should contain :value` |
| `Then the command output should not contain :text` | `Then the command output should not contain :value` |

#### ConfigTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the config :name key :key has the value :value` | `Given the config :name with the key :key has the value :value` |
| `Given the following config values:` | `Given the following config values exist:` |
| `Then the config :name key :key should contain the effective value :value` | `Then the config :name with the key :key should contain the effective value :value` |
| `Then the config :name key :key should contain the value :value` | `Then the config :name with the key :key should contain :value` |
| `Then the config :name key :key should have the effective value :value` | `Then the config :name with the key :key should have the effective value :value` |
| `Then the config :name key :key should have the value :value` | `Then the config :name with the key :key should have the value :value` |
| `Then the config :name key :key should not contain the effective value :value` | `Then the config :name with the key :key should not contain the effective value :value` |
| `Then the config :name key :key should not contain the value :value` | `Then the config :name with the key :key should not contain :value` |
| `Then the config :name key :key should not have the effective value :value` | `Then the config :name with the key :key should not have the effective value :value` |
| `Then the config :name key :key should not have the value :value` | `Then the config :name with the key :key should not have the value :value` |

#### ContentBlockTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following :type content blocks do not exist:` | `Given the following :content_block_type content blocks do not exist:` |
| `Given the following :type content blocks exist:` | `Given the following :content_block_type content blocks exist:` |
| `Given the following :type content blocks with fields:` | `Given the following :content_block_type content blocks with fields exist:` |
| `When I edit the :type content block with the description :description` | `When I visit the :content_block_type content block edit page with the description :description` |
| `Then the content block type :type should exist` | `Then the content block type :content_block_type should exist` |

#### ContentTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following :type content with fields:` | `Given the following :content_type content with fields exists:` |
| `When I change the moderation state of the :content_type content with the title :title to the :new_state state` | `When the moderation state of the :content_type content with the title :title changes to the state :new_state` |
| `When I rebuild the access grants for all content` | `When the access grants for all content are rebuilt` |
| `When I rebuild the access grants for the :content_type content with the title :title` | `When the access grants for the :content_type content with the title :title are rebuilt` |
| `When I set the path alias of the :content_type content with the title :title to :alias` | `When the path alias of the :content_type content with the title :title is set to :alias` |
| `Then :content_type content with the title :title should be published` | `Then the :content_type content with the title :title should be published` |
| `Then :content_type content with the title :title should not be published` | `Then the :content_type content with the title :title should not be published` |
| `Then :content_type content with the title :title should not exist` | `Then the :content_type content with the title :title should not exist` |

#### CookieTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then a cookie with the name :name and a value containing :partial_value should exist` | `Then the cookie :name with a value containing :partial_value should exist` |
| `Then a cookie with the name :name and a value containing :partial_value should not exist` | `Then the cookie :name with a value containing :partial_value should not exist` |
| `Then a cookie with the name :name and the value :value should exist` | `Then the cookie :name with the value :value should exist` |
| `Then a cookie with the name :name and the value :value should not exist` | `Then the cookie :name with the value :value should not exist` |
| `Then a cookie with the name :name should exist` | `Then the cookie :name should exist` |
| `Then a cookie with the name :name should not exist` | `Then the cookie :name should not exist` |

#### DraggableviewsTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I save the draggable views items of the view :view_id and the display :view_display_id for the :bundle content in the following order:` | `When the draggable views items of the view :view_id and the display :view_display_id for the :content_type content are saved in the following order:` |

#### DropzoneTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I drop the file :path on the :selector dropzone` | `When I drop the file :filename on the dropzone :selector` |
| `When I drop the following files on the :selector dropzone:` | `When I drop the following files on the dropzone :selector:` |

#### EckTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following eck :bundle :entity_type entities do not exist:` | `Given the following ECK :bundle :entity_type entities do not exist:` |
| `Given the following eck :bundle :entity_type entities exist:` | `Given the following ECK :bundle :entity_type entities exist:` |
| `When I edit eck :bundle :entity_type entity with the title :title` | `When I visit the ECK :bundle :entity_type entity edit page with the title :title` |
| `When I visit eck :bundle :entity_type entity with the title :title` | `When I visit the ECK :bundle :entity_type entity page with the title :title` |

#### ElementTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given I do not accept any confirmation dialogs` | `Given I decline all confirmation dialogs` |
| `When I follow the link :text with the index :index` | `When I follow the link :link with the index :index` |
| `When I press the button :label with the index :index` | `When I press the button :button with the index :index` |
| `When I trigger the JS event :event on the element :selector` | `When the JS event :event is triggered on the element :selector` |
| `Then the element :selector should be displayed within a viewport` | `Then the element :selector should be displayed within the viewport` |
| `Then the element :selector should be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should be displayed within the viewport with a top offset of :offset pixels` |
| `Then the element :selector should have the CSS property :property with the value containing :value` | `Then the element :selector should have the CSS property :property with a value containing :partial_value` |
| `Then the element :selector should not be displayed within a viewport` | `Then the element :selector should not be displayed within the viewport` |
| `Then the element :selector should not be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should not be displayed within the viewport with a top offset of :offset pixels` |
| `Then the element :selector should not have the CSS property :property with the value containing :value` | `Then the element :selector should not have the CSS property :property with a value containing :partial_value` |
| `Then the element :selector with the attribute :attribute and the value containing :value should exist` | `Then the element :selector with the attribute :attribute and a value containing :partial_value should exist` |
| `Then the element :selector with the attribute :attribute and the value containing :value should not exist` | `Then the element :selector with the attribute :attribute and a value containing :partial_value should not exist` |
| `Then the text :text1 should appear after the text :text2` | `Then I should see the text :text1 after the text :text2` |

#### EmailTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I clear the test email system queue` | `When the test email system queue is cleared` |
| `When I disable the test email system` | `When the test email system is disabled` |
| `When I enable the test email system` | `When the test email system is enabled` |
| `When I follow link number :link_number in the email with the subject :subject` | `When I follow the link with the index :index in the email with the subject :subject` |
| `When I follow link number :link_number in the email with the subject containing :subject` | `When I follow the link with the index :index in the email with a subject containing :partial_subject` |
| `Then an email should be sent to the :address` | `Then an email should have been sent to the address :address` |
| `Then an email should be sent to the address :address with the content containing:` | `Then an email should have been sent to the address :address with the content containing:` |
| `Then an email should be sent to the address :address with the content not containing:` | `Then an email should have been sent to the address :address with the content not containing:` |
| `Then an email should be sent to the address :address with the content:` | `Then an email should have been sent to the address :address with the content:` |
| `Then an email should not be sent to the address :address with the content containing:` | `Then no emails should have been sent to the address :address with the content containing:` |
| `Then an email should not be sent to the address :address with the content:` | `Then no emails should have been sent to the address :address with the content:` |
| `Then no emails should have been sent to the :address` | `Then no emails should have been sent to the address :address` |
| `Then the email header :header should exactly be:` | `Then the email header :header should be:` |
| `Then the file :file_name should be attached to the email with the subject :subject` | `Then the file :filename should be attached to the email with the subject :subject` |
| `Then the file :file_name should be attached to the email with the subject containing :subject` | `Then the file :filename should be attached to the email with a subject containing :partial_subject` |

#### FieldTrait

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Given browser validation for the form :selector is disabled` | `Given the browser validation for the form :selector is disabled` | |
| `When I fill in the WYSIWYG field :field with the :value` | `When I fill in the WYSIWYG field :field with :value` | |
| `When I fill in the color field :field with the value :value` | `When I fill in the color field :field with :value` | |
| `When I fill in the datetime field :label with date :date and time :time` | `When I fill in the datetime field :label with the date :date and the time :time` | |
| `When I fill in the end datetime field :label with date :date and time :time` | `When I fill in the end datetime field :label with the date :date and the time :time` | |
| `When I fill in the start datetime field :label with date :date and time :time` | `When I fill in the start datetime field :label with the date :date and the time :time` | |
| `When I unselect :option from :selector` | `When I unselect the option :option from the select :selector` | |
| `Then the field :name should exist` | `Then the field :field should exist` | |
| `Then the field :name should have :enabled_or_disabled state` | `Then the field :field should be enabled` | For the `disabled` state, the step is `Then the field :field should be disabled`. Any other value used to assert an enabled field |
| `Then the field :name should not exist` | `Then the field :field should not exist` | |
| `Then the option :option should be selected within the select element :selector` | `Then the option :option within the select :selector should be selected` | |
| `Then the option :option should exist within the select element :selector` | `Then the option :option within the select :selector should exist` | |
| `Then the option :option should not be selected within the select element :selector` | `Then the option :option within the select :selector should not be selected` | |
| `Then the option :option should not exist within the select element :selector` | `Then the option :option within the select :selector should not exist` | |

#### FileDownloadTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the downloaded file name should contain :file_name_part` | `Then the downloaded file name should contain :partial_name` |
| `Then the downloaded file should be a zip archive containing the following files named:` | `Then the downloaded file should be a zip archive containing files with the following names:` |
| `Then the downloaded file should be a zip archive containing the following files partially named:` | `Then the downloaded file should be a zip archive containing files with the following partial names:` |
| `Then the downloaded file should be a zip archive not containing the following files partially named:` | `Then the downloaded file should be a zip archive not containing files with the following partial names:` |

#### FileTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following managed files:` | `Given the following managed files exist:` |
| `Given the unmanaged file at the URI :uri exists with :content` | `Given the unmanaged file at the URI :uri exists with the content :content` |
| `Then an unmanaged file at the URI :uri should contain :content` | `Then the unmanaged file at the URI :uri should contain :content` |
| `Then an unmanaged file at the URI :uri should exist` | `Then the unmanaged file at the URI :uri should exist` |
| `Then an unmanaged file at the URI :uri should not contain :content` | `Then the unmanaged file at the URI :uri should not contain :content` |
| `Then an unmanaged file at the URI :uri should not exist` | `Then the unmanaged file at the URI :uri should not exist` |

#### IframeTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I switch to iframe with locator :locator` | `When I switch to the iframe :selector` |

#### JsonTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the response JSON content is the following:` | `Given the response JSON is the following:` |
| `Given the response JSON from the file :filename` | `Given the response JSON is loaded from the file :filename` |
| `When I print last JSON response` | `When I print the last JSON response` |
| `Then the JSON path :path should be equal to :value` | `Then the JSON path :path should have the value :value` |
| `Then the JSON path :path should not be equal to :value` | `Then the JSON path :path should not have the value :value` |

#### LinkTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I click on the link with the title :title` | `When I follow the link with the title :title` |

#### MediaTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given :media_type media type does not exist` | `Given the media type :media_type does not exist` |
| `Given the following :bundle media with fields:` | `Given the following :media_type media with fields exist:` |
| `Given the following media :media_type do not exist:` | `Given the following :media_type media do not exist:` |
| `Given the following media :media_type exist:` | `Given the following :media_type media exist:` |
| `When I edit the media :media_type with the name :name` | `When I visit the :media_type media edit page with the name :name` |
| `When I visit the media :media_type delete page with the name :name` | `When I visit the :media_type media delete page with the name :name` |
| `When I visit the media :media_type revisions page with the name :name` | `When I visit the :media_type media revisions page with the name :name` |
| `When I visit the media :media_type with the name :name` | `When I visit the :media_type media page with the name :name` |
| `Then the :media_type media type should exist` | `Then the media type :media_type should exist` |
| `Then the :media_type media type should not exist` | `Then the media type :media_type should not exist` |

#### MenuTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following menus:` | `Given the following menus exist:` |

#### MetatagTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the :metaName meta tag should not contain any HTML tags` | `Then the meta tag :name should not contain any HTML tags` |
| `Then the meta robots should include :directive` | `Then the robots meta tag should contain :directive` |
| `Then the meta robots should not include :directive` | `Then the robots meta tag should not contain :directive` |

#### ModalTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I click on :selector in the modal` | `When I click on the element :selector in the modal` |
| `Then the modal should contain :text` | `Then I should see the text :text in the modal` |
| `Then the modal should not contain :text` | `Then I should not see the text :text in the modal` |

#### ModuleTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the :module module is disabled` | `Given the module :module is disabled` |
| `Given the :module module is enabled` | `Given the module :module is enabled` |
| `Then the :module module should be disabled` | `Then the module :module should be disabled` |
| `Then the :module module should be enabled` | `Then the module :module should be enabled` |

#### ParagraphsTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:` | `Given the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:` |

#### PathTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the basic authentication with the username :username and the password :password` | `Given I am using basic authentication with the username :username and the password :password` |
| `Then current url should have the :param parameter` | `Then the URL should have the query parameter :name` |
| `Then current url should have the :param parameter with the :value value` | `Then the URL should have the query parameter :name with the value :value` |
| `Then current url should not have the :param parameter` | `Then the URL should not have the query parameter :name` |
| `Then current url should not have the :param parameter with the :value value` | `Then the URL should not have the query parameter :name with the value :value` |

#### QueueTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the :queue queue is empty` | `Given the queue :queue is empty` |
| `When I process :count item(s) from the :queue queue` | `When the queue :queue processes :count item(s)` |
| `When I process the :queue queue` | `When the queue :queue is processed` |
| `Then the :queue queue should be empty` | `Then the queue :queue should be empty` |
| `Then the :queue queue should have :count item(s)` | `Then the queue :queue should have :count item(s)` |

#### ResponseTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the response header :header_name should contain the value :header_value` | `Then the response header :name should contain :value` |
| `Then the response header :header_name should not contain the value :header_value` | `Then the response header :name should not contain :value` |
| `Then the response should contain the header :header_name` | `Then the response should contain the header :name` |
| `Then the response should not contain the header :header_name` | `Then the response should not contain the header :name` |

#### ResponsiveTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following responsive breakpoints:` | `Given the following responsive breakpoints exist:` |
| `When I set the viewport to the :breakpoint breakpoint` | `When I set the viewport to the breakpoint :breakpoint` |

#### RestTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given a REST header :name with value :value` | `Given the REST header :name has the value :value` |
| `When I send a REST :method request to :url with body:` | `When I send a REST :method request to :url with the body:` |
| `Then the REST response should contain :text` | `Then the REST response should contain :value` |

#### SearchApiTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I add the :content_type content with the title :title to the search index` | `When the :content_type content with the title :title is indexed` |
| `When I run search indexing for :count item(s)` | `When search indexing runs for :count item(s)` |
| `When I run the Search API Solr cron` | `When the Search API Solr cron runs` |
| `When I run the Search API cron` | `When the Search API cron runs` |

#### StateTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following state values:` | `Given the following state values exist:` |

#### TableTrait

| 3.14 | 4.0 |
| --- | --- |
| `Then the :rowText row should contain the following:` | `Then I should see the following text in the row containing :partial_text:` |

#### TaxonomyTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following :vocabulary terms with fields:` | `Given the following :vocabulary terms with fields exist:` |
| `Given the following :vocabulary_machine_name vocabulary terms do not exist:` | `Given the following :vocabulary terms do not exist:` |
| `When I visit the :vocabulary_machine_name term delete page with the name :term_name` | `When I visit the :vocabulary term delete page with the name :name` |
| `When I visit the :vocabulary_machine_name term edit page with the name :term_name` | `When I visit the :vocabulary term edit page with the name :name` |
| `When I visit the :vocabulary_machine_name term page with the name :term_name` | `When I visit the :vocabulary term page with the name :name` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist` | `Then the :vocabulary term with the name :name should exist` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist` | `Then the :vocabulary term with the name :name should not exist` |
| `Then the vocabulary :machine_name should not exist` | `Then the vocabulary :vocabulary should not exist` |
| `Then the vocabulary :machine_name with the name :name should exist` | `Then the vocabulary :vocabulary with the name :name should exist` |

#### TimeTrait

| 3.14 | 4.0 |
| --- | --- |
| `When I reset system time` | `When the system time is reset` |
| `When I set system time to :value` | `When the system time is set to :value` |

#### UserTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the following roles:` | `Given the following roles exist:` |
| `Given the following users with fields:` | `Given the following users with fields exist:` |
| `Given the role :role_name with the permissions :permissions` | `Given the role :role has the permission(s) :permissions` |
| `When I visit :name user profile delete page` | `When I visit the profile delete page of the user :name` |
| `When I visit :name user profile edit page` | `When I visit the profile edit page of the user :name` |
| `When I visit :name user profile page` | `When I visit the profile page of the user :name` |
| `When I visit my own user profile delete page` | `When I visit my own profile delete page` |
| `When I visit my own user profile edit page` | `When I visit my own profile edit page` |
| `When I visit my own user profile page` | `When I visit my own profile page` |
| `When I visit the password reset link for :name` | `When I visit the password reset link for the user :name` |
| `Then the user with the email :mail should exist` | `Then the user with the email :address should exist` |
| `Then the user with the email :mail should not exist` | `Then the user with the email :address should not exist` |

#### WebformTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given a webform :title from template :template` | `Given the webform :title has been created from the template :template` |

#### XmlTrait

| 3.14 | 4.0 |
| --- | --- |
| `Given the response content from the file :filename` | `Given the response XML is loaded from the file :filename` |
| `Given the response content is the following:` | `Given the response XML is the following:` |
| `When I print last XML response` | `When I print the last XML response` |
| `Then the XML attribute :attribute on element :element should be equal to :text` | `Then the XML attribute :attribute on the element :element should have the value :value` |
| `Then the XML attribute :attribute on element :element should exist` | `Then the XML attribute :attribute on the element :element should exist` |
| `Then the XML attribute :attribute on element :element should not be equal to :text` | `Then the XML attribute :attribute on the element :element should not have the value :value` |
| `Then the XML attribute :attribute on element :element should not exist` | `Then the XML attribute :attribute on the element :element should not exist` |
| `Then the XML attribute :attribute_name on element :element should contain :text` | `Then the XML attribute :attribute on the element :element should contain :value` |
| `Then the XML attribute :attribute_name on element :element should not contain :text` | `Then the XML attribute :attribute on the element :element should not contain :value` |
| `Then the XML element :element should be equal to :text` | `Then the XML element :element should have the value :value` |
| `Then the XML element :element should contain :text` | `Then the XML element :element should contain :value` |
| `Then the XML element :element should not be equal to :text` | `Then the XML element :element should not have the value :value` |
| `Then the XML element :element should not contain :text` | `Then the XML element :element should not contain :value` |

### Drupal Extension steps

The Drupal Extension's contexts are gone, and the step traits carry their vocabulary. A Drupal Extension step that 4.0 registers with the same text isn't listed. The steps Mink's `MinkContext` registers keep their text and aren't listed either, as [Contexts to register](#contexts-to-register) describes. A row noted "Mink's step" maps to one of them. Mink registers its steps as regular expressions, so each value in one goes in double quotes, apart from a status code, which is a bare number: `Then the response status code should be 200`.

#### Session and users

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Given I am not logged in` | `Given I am an anonymous user` | |
| `Given I am logged in as a user with the :role role(s)` | `Given I am logged in as a user with the role(s) :roles` | For the `anonymous` or `anonymous user` role, which 3.14's `OverrideTrait` turned into a logout, write `Given I am an anonymous user` |
| `Given I am logged in as a/an :role` | `Given I am logged in as a user with the role(s) :roles` | |
| `Given I am logged in as a user with the :role role(s) and I have the following fields:` | `Given I am logged in as a user with the role(s) :roles and I have the following fields:` | |
| `Given I am logged in as a user with the :permissions permission(s)` | `Given I am logged in as a user with the permission(s) :permissions` | |
| `Given I am logged in as :name` | `Given I am logged in as the user :name` | |
| `Given the following users:` | `Given the following users exist:` | 3.14's `OverrideTrait` deleted the users the table named before creating them. The 4.0 step doesn't, so a scenario that relied on it adds `Given the following users do not exist:` first |

#### Content, terms, entities and languages

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Given the following :type content:` | `Given the following :content_type content exists:` | 3.14's `OverrideTrait` deleted the nodes the table named before creating them. The 4.0 step doesn't, so a scenario that relied on it adds `Given the following :content_type content does not exist:` first |
| `Given the following :vocabulary terms:` | `Given the following :vocabulary terms exist:` | |
| `Given the following :type entities:` | `Given the following :entity_type entities exist:` | |
| `Given the/these (following )languages are available:` | `Given the following languages exist:` | |
| `Given a/an :type with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title`, and pass the same title to the second step |
| `Given a/an :type content with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title`, and pass the same title to the second step |
| `Given I am viewing a/an :type with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title`, and pass the same title to the second step |
| `Given I am viewing a/an :type content with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title`, and pass the same title to the second step |
| `Given I am viewing a/an :type with the following fields:` | `Given the following :content_type content with fields exists:`<br>`When I visit the :content_type content page with the title :title` | The first step takes the field table as it is, 1 field per row; pass its `title` to the second step |
| `Given I am viewing a/an :type content with the following fields:` | `Given the following :content_type content with fields exists:`<br>`When I visit the :content_type content page with the title :title` | The first step takes the field table as it is, 1 field per row; pass its `title` to the second step |
| `Given I am viewing my :type with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title` and an `author` column naming the logged-in user, and pass the same title to the second step |
| `Given I am viewing my :type content with the title :title` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content page with the title :title` | Give the table a `title` column holding `:title` and an `author` column naming the logged-in user, and pass the same title to the second step |
| `Given a/an :vocabulary term with the name :name` | `Given the following :vocabulary terms exist:`<br>`When I visit the :vocabulary term page with the name :name` | Give the table a `name` column holding `:name`, and pass the same name to the second step |
| `Given I am viewing a/an :vocabulary term with the name :name` | `Given the following :vocabulary terms exist:`<br>`When I visit the :vocabulary term page with the name :name` | Give the table a `name` column holding `:name`, and pass the same name to the second step |
| `Then I should be able to edit the :type` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content edit page with the title :title`<br>`Then the response status code should be 200` | Give the table a `title` column, and pass the same title to the second step |
| `Then I should be able to edit the :type content` | `Given the following :content_type content exists:`<br>`When I visit the :content_type content edit page with the title :title`<br>`Then the response status code should be 200` | Give the table a `title` column, and pass the same title to the second step |

#### Cache, cron, batch and queues

| 3.14 | 4.0 |
| --- | --- |
| `Given I run cron` | `When cron runs` |
| `Given I wait for the batch job to finish` | `When I wait for the batch job to finish` |
| `Given the following item is in the system queue:` | `Given the following item is in the queue :queue:` |

#### Navigation, buttons, headings and fields

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Given I am at :path` | `When I visit :path`<br>`Then the response status code should be 200` | |
| `When I click :link` | `When I follow :link` | Mink's step |
| `Given for :field I enter :value` | `When I fill in :field with :value` | Mink's step |
| `Given I enter :value for :field` | `When I fill in :value for :field` | Mink's step |
| `When I press the :button button` | `When I press :button` | Mink's step |
| `Given I check the box :checkbox` | `When I check :checkbox` | Mink's step |
| `Given I uncheck the box :checkbox` | `When I uncheck :checkbox` | Mink's step |
| `When I select the radio button :label` | `When I choose the radio button :selector` | |
| `When I select the radio button :label with the id :id` | `When I choose the radio button :selector` | Pass the id as the selector |
| `Given I press the :char key in the :field field` | `When I press the key :key on the element :selector` | |
| `When I :action details labelled :summary` | `When I click on the element :selector` | Target the `summary` element |
| `When I drag element :source onto element :target` | Removed | Write a JavaScript step in your own context |
| `Then I should get a :code HTTP response` | `Then the response status code should be :code` | Mink's step |
| `Then I should not get a :code HTTP response` | `Then the response status code should not be :code` | Mink's step |
| `Then I should see the text :text` | `Then I should see :text` | Mink's step |
| `Then I should not see the text :text` | `Then I should not see :text` | Mink's step |
| `Then I should not visibly see the link :link` | `Then the element :selector should not be displayed` | |
| `Then I should see the :button button` | `Then I should see the button :button` | |
| `Then I should not see the :button button` | `Then I should not see the button :button` | |

#### Regions

The region steps name the region after the word `region`, as in `in the region "header"`, where the Drupal Extension wrote `in the "header" region`. An action names what it acts on (`the link`, `the button`, `the field`, `the checkbox`), and a link is followed rather than clicked. A text step keeps `the text`, as in `I should see the text "Welcome" in the region "header"`, so Mink's own `I should see :text` never matches it. A step about an element named by a selector starts with the element and names the region with it, before `should`.

| 3.14 | 4.0 |
| --- | --- |
| `When I follow/click :link in the :region( region)` | `When I follow the link :link in the region :region` |
| `Given I press :button in the :region( region)` | `When I press the button :button in the region :region` |
| `Given I fill in :field with :value in the :region( region)` | `When I fill in the field :field with :value in the region :region` |
| `Given I fill in :value for :field in the :region( region)` | `When I fill in the field :field with :value in the region :region` |
| `Given I check :locator in the :region( region)` | `When I check the checkbox :checkbox in the region :region` |
| `Given I uncheck :checkbox in the :region( region)` | `When I uncheck the checkbox :checkbox in the region :region` |
| `Then I should see( the text) :text in the :region( region)` | `Then I should see the text :text in the region :region` |
| `Then I should not see( the text) :text in the :region( region)` | `Then I should not see the text :text in the region :region` |
| `Then I should see the heading :heading in the :region( region)` | `Then I should see the heading :heading in the region :region` |
| `Then I should see the :heading heading in the :region( region)` | `Then I should see the heading :heading in the region :region` |
| `Then I should see the link :link in the :region( region)` | `Then I should see the link :link in the region :region` |
| `Then I should not see the link :link in the :region( region)` | `Then I should not see the link :link in the region :region` |
| `Then I should see the button :button in the :region( region)` | `Then I should see the button :button in the region :region` |
| `Then I should see the :button button in the :region( region)` | `Then I should see the button :button in the region :region` |
| `Then I should not see the button :button in the :region( region)` | `Then I should not see the button :button in the region :region` |
| `Then I should not see the :button button in the :region( region)` | `Then I should not see the button :button in the region :region` |
| `Then I should see the :tag element in the :region( region)` | `Then the element :selector in the region :region should exist` |
| `Then I should not see the :tag element in the :region( region)` | `Then the element :selector in the region :region should not exist` |
| `Then I should see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should have the value :value` |
| `Then I should not see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should not have the value :value` |
| `Then I should see the :tag element with the :attribute attribute set to :value in the :region( region)` | `Then the element :selector in the region :region should have the attribute :attribute with the value :value` |
| `Then I should see :text in the :tag element with the :attribute attribute set to :value in the :region( region)` | `Then the element :selector with the text :text in the region :region should have the attribute :attribute with the value :value` |
| `Then I should see :text in the :tag element with the :property CSS property set to :value in the :region( region)` | `Then the element :selector with the text :text in the region :region should have the CSS property :property with the value :value` |

#### Messages

The `( containing)` variants are gone: every message step matches on a substring, which is what both forms always did. The message tables lose their header row, so each row is a message, with no `error messages` heading cell.

| 3.14 | 4.0 |
| --- | --- |
| `Then I should see the message( containing) :message` | `Then I should see the message :message` |
| `Then I should not see the message( containing) :message` | `Then I should not see the message :message` |
| `Then I should see the error message( containing) :message` | `Then I should see the error message :message` |
| `Then I should not see the error message( containing) :message` | `Then I should not see the error message :message` |
| `Then I should see the success message( containing) :message` | `Then I should see the success message :message` |
| `Then I should not see the success message( containing) :message` | `Then I should not see the success message :message` |
| `Then I should see the warning message( containing) :message` | `Then I should see the warning message :message` |
| `Then I should not see the warning message( containing) :message` | `Then I should not see the warning message :message` |
| `Then I should see the following error message(s):` | `Then I should see the following error messages:` |
| `Then I should see the following warning message(s):` | `Then I should see the following warning messages:` |

#### Table rows

| 3.14 | 4.0 |
| --- | --- |
| `Given I click :link in the :rowText row` | `When I follow the link :link in the row containing :partial_text` |
| `Given I press :button in the :rowText row` | `When I press the button :button in the row containing :partial_text` |
| `Then I should see the text :text in the :rowText row` | `Then I should see the text :text in the row containing :partial_text` |
| `Then I should not see the text :text in the :rowText row` | `Then I should not see the text :text in the row containing :partial_text` |
| `Then I should see the :link in the :rowText row` | `Then I should see the link :link in the row containing :partial_text` |
| `Then I should not see the :link in the :rowText row` | `Then I should not see the link :link in the row containing :partial_text` |

#### Mail

`EmailTrait` collects mail through Drupal's test mail collector. A scenario enables collection with the `@email` tag or with `When the test email system is enabled`, and the assertions read the collected messages. The Drupal Extension's `new` steps tracked the messages sent since the previous assertion. `When the test email system queue is cleared` replaces them: run it before the action that sends the mail, and the collector holds only what that action sends.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `When I send the following mail:` | Removed | Trigger the site behavior that sends the mail |
| `When I send the following email:` | Removed | Trigger the site behavior that sends the mail |
| `Then the following (e)mail(s) should have been sent:` | `Then the email field :field should contain:` | |
| `Then the following (e)mail(s) should have been sent to :to:` | `Then an email should have been sent to the address :address with the content:` | |
| `Then the following (e)mail(s) should have been sent with the subject :subject:` | `Then the email field :field should be:` | Name `subject` as the field |
| `Then the following (e)mail(s) should have been sent to :to with the subject :subject:` | `Then an email should have been sent to the address :address with the content:`<br>`Then the email field :field should be:` | Name `subject` as the field of the second step |
| `Then the following new (e)mail(s) should have been sent...` | Removed | Clear the queue before the action, then use the matching step without `new` |
| `Then there should be a total of :count (e)mail(s) sent` | `Then the number of emails sent should be :count` | |
| `Then there should be a total of :count (e)mail(s) sent to :to` | `Then the number of emails sent to the address :address should be :count` | |
| `Then there should be a total of :count (e)mail(s) sent with the subject :subject` | `Then the number of emails sent with the subject :subject should be :count` | |
| `Then there should be a total of :count (e)mail(s) sent to :to with the subject :subject` | `Then the number of emails sent to the address :address should be :count`<br>`Then the number of emails sent with the subject :subject should be :count` | |
| `Then there should be a total of :count new (e)mail(s) sent...` | Removed | Clear the queue before the action, then use the matching step without `new` |
| `Then (a )(an )(e)mail(s) should have been sent with the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` | |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` | |
| `Then (a )(an )(e)mail(s) should have been sent with the subject :subject and the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` | 1 step per attachment |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the subject :subject and the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` | 1 step per attachment |
| `When I follow the link to :urlFragment from the email` | `When I follow the link with a URL containing :partial_url in the email` | |
| `When I follow the link to :urlFragment from the mail` | `When I follow the link with a URL containing :partial_url in the email` | |
| `When I follow the link to :urlFragment from the email to :to` | `When I follow the link with a URL containing :partial_url in the email` | |
| `When I follow the link to :urlFragment from the mail to :to` | `When I follow the link with a URL containing :partial_url in the email` | |
| `When I follow the link to :urlFragment from the email with the subject :subject` | `When I follow the link with a URL containing :partial_url in the email` | To keep the subject, use `When I follow the link with the index :index in the email with the subject :subject` |
| `When I follow the link to :urlFragment from the mail with the subject :subject` | `When I follow the link with a URL containing :partial_url in the email` | To keep the subject, use `When I follow the link with the index :index in the email with the subject :subject` |
| `When I follow the link to :urlFragment from the email to :to with the subject :subject` | `When I follow the link with a URL containing :partial_url in the email` | To keep the subject, use `When I follow the link with the index :index in the email with the subject :subject` |
| `When I follow the link to :urlFragment from the mail to :to with the subject :subject` | `When I follow the link with a URL containing :partial_url in the email` | To keep the subject, use `When I follow the link with the index :index in the email with the subject :subject` |

Several of these steps held 2 conditions to the same email, such as a recipient and a subject, or a subject and a link URL. No 4.0 step does, so each row keeps 1 of the conditions or checks each with its own step, and 2 steps can pass on 2 different emails. Clearing the queue right before the action that sends the mail usually leaves just that email. Where it doesn't, a step of your own holds both conditions to 1 email through the public helpers in [HELPERS.md](HELPERS.md). `emailGetMessagesToAddress()` and `emailGetMessagesWithSubject()` key each message as collected, so the emails that match both are:

```php
$messages = array_intersect_key(
  $this->emailGetMessagesToAddress($address),
  $this->emailGetMessagesWithSubject($subject),
);
```

For a link, `emailFindMessageBySubject()` finds the email and `emailExtractLinks()` lists the links in its body.

The Drupal Extension matched a subject in part and in any case. `with the subject :subject` compares the whole subject, so a scenario that relied on a partial match moves to `with a subject containing :partial_subject` and writes the subject's own case, as [Email](#email) describes.

#### Config

| 3.14 | 4.0 |
| --- | --- |
| `Given I set the configuration item :name with key :key to :value` | `Given the config :name with the key :key has the value :value` |
| `Given I set the configuration item :name with key :key with the following values:` | `Given the following config values exist:` |

#### Drush

| 3.14 | 4.0 |
| --- | --- |
| `Given I run drush :command` | `When I run the drush command :command` |
| `Given I run drush :command :arguments` | `When I run the drush command :command with the arguments :arguments` |
| `Given I run the failing drush command :command` | `When I run the failing drush command :command` |
| `Given I run the failing drush command :command :arguments` | `When I run the failing drush command :command with the arguments :arguments` |
| `Then the drush output should contain :output` | `Then the drush output should contain :value` |
| `Then the drush output should not contain :output` | `Then the drush output should not contain :value` |

#### AJAX and debugging

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Given I wait for AJAX to finish` | `When I wait for AJAX to finish` | |
| `When (I )break` | Removed | Use a debugger, or Mink's `Then print last response` |

## Tags

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `@api` | Removed | A step resolves the backend it needs from the `backends` list, so a configuration that lists a Drupal backend reaches Drupal without the tag; see [Backends](#backends) |
| `@drush` | Removed | `DrushTrait` resolves `DrushCapabilityInterface` from the `backends` list |
| `@queue` | Removed | `QueueTrait` deletes the queues a scenario used after every scenario; see [Queues](#queues) |
| `@no-file-cleanup` | Removed | 4.0 doesn't delete the files a scenario uploads with Mink's `I attach the file` step, so Drupal renames a later upload of the same name, as `image_0.png` |
| `@error` | `@error` | Means `fail_on_errors` is `FALSE` for `WatchdogTrait`: the errors the scenario logged are still read and cleared from the `watchdog` table, and the scenario doesn't fail. 3.14 left the start time unset, which switched the check off and left the rows behind. A scenario that relied on those rows for a later assertion reads them before the scenario ends, or uses `@behat-steps-skip:WatchdogTrait` |
| `@email:TYPE` | `@email:TYPE` | Enables the test email system by itself, with the handler types it names, where 3.14 read it only beside a bare `@email`. The bare tag beside a typed one can go. A scenario tagged only `@email:TYPE` now captures its mail in the test collector instead of sending it through the site's mail system, so drop the tag where the mail has to leave the site |
| `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` | `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` | Now also keeps `node`, `taxonomy_term` and `language` entities |
| `@behat-steps-skip:configAfterScenario` | `@behat-steps-skip:ConfigTrait` | |
| `@behat-steps-skip:configOverrideBeforeScenario` | `@behat-steps-skip:ConfigOverrideTrait` | |
| `@behat-steps-skip:configOverrideBeforeStep` | `@behat-steps-skip:ConfigOverrideTrait` | |
| `@behat-steps-skip:emailAfterScenario` | `@behat-steps-skip:EmailTrait` | Also skips the setup; see [Skip tags](#skip-tags) |
| `@behat-steps-skip:emailBeforeScenario` | `@behat-steps-skip:EmailTrait` | |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:EntityLifecycleTrait` | Also keeps the nodes, terms and languages a scenario created, which 3.14 left to the Drupal Extension's cleanup |
| `@behat-steps-skip:fileAfterScenario` | `@behat-steps-skip:FileTrait` | Also skips creating the private and temporary directories. `@behat-steps-entity-cleanup-skip:file` still keeps the managed files |
| `@behat-steps-skip:fileBeforeScenario` | `@behat-steps-skip:FileTrait` | |
| `@behat-steps-skip:fileDownloadAfterScenario` | `@behat-steps-skip:FileDownloadTrait` | Also skips preparing the download directory |
| `@behat-steps-skip:fileDownloadBeforeScenario` | `@behat-steps-skip:FileDownloadTrait` | |
| `@behat-steps-skip:moduleAfterScenario` | `@behat-steps-skip:ModuleTrait` | Also skips the `@module:` tags |
| `@behat-steps-skip:moduleBeforeScenario` | `@behat-steps-skip:ModuleTrait` | |
| `@behat-steps-skip:overrideBootstrapDrupal` | Removed | `OverrideTrait` is gone, and a step bootstraps Drupal through the backend it resolves |
| `@behat-steps-skip:queueAfterScenario` | `@behat-steps-skip:QueueTrait` | |
| `@behat-steps-skip:restBeforeScenario` | `@behat-steps-skip:RestTrait` | |
| `@behat-steps-skip:stateAfterScenario` | `@behat-steps-skip:StateTrait` | |
| `@behat-steps-skip:testmodeAfterScenario` | `@behat-steps-skip:TestmodeTrait` | Also skips the `@testmode` tag |
| `@behat-steps-skip:testmodeBeforeScenario` | `@behat-steps-skip:TestmodeTrait` | |
| `@behat-steps-skip:timeCleanup` | `@behat-steps-skip:TimeTrait` | |
| `@behat-steps-skip:watchdogAfterStep` | `@behat-steps-skip:WatchdogTrait` | |
| `@behat-steps-skip:watchdogSetScenario` | `@behat-steps-skip:WatchdogTrait` | |
| `@behat-steps-skip:mediaAfterScenario` | `@behat-steps-entity-cleanup-skip:media` | A per-type cleanup tag from before 3.12, which 3.14 already ignored and 4.0 fails on |

### Error checks

`WatchdogTrait` and `JavascriptTrait` each read 2 independent options, `enabled` and `fail_on_errors`, and their tags map onto them. `JavascriptTrait`'s tags mean what they meant in 3.14, while `@error` changed as the table above says.

| Switch | Extension or context | Scenario tag |
| --- | --- | --- |
| Do not collect at all | `'watchdog' => ['enabled' => FALSE]` | `@behat-steps-skip:WatchdogTrait` |
| Collect, do not fail | `'watchdog' => ['fail_on_errors' => FALSE]` | `@error` |
| Do not collect at all | `'javascript' => ['enabled' => FALSE]` | `@behat-steps-skip:JavascriptTrait` |
| Collect, do not fail | `'javascript' => ['fail_on_errors' => FALSE]` | `@js-errors` |

### Skip tags

`@behat-steps-skip:<TraitName>` is the only form of a skip tag. It names the trait whose hooks it switches off, it switches off every hook that trait registers, and it's the same switch the trait's `enabled` option sets for a profile or a context.

A skip tag that carries anything but a trait name fails the run at scenario start, naming the tag, so a feature file that still carries a hook-method tag can't quietly lose its effect:

```
The "@behat-steps-skip:emailAfterScenario" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@behat-steps-skip:JavascriptTrait".
```

A skip tag naming a trait that no context of the suite composes fails the same way, so a misspelled trait name can't leave the trait's hooks running:

```
The "@behat-steps-skip:EmialTrait" tag names no trait a context of the "default" suite composes, so it would switch nothing off. Check the trait name for a typo, or remove the tag.
```

A trait counts as composed when a context uses it directly, through a parent class or through another trait, so `@behat-steps-skip:EntityLifecycleTrait` works in a suite that registers `DrupalContext`. The name has to match the trait's own, case included. A feature that 2 suites share, carrying a skip tag for a trait only 1 suite's contexts compose, fails in the other suite: drop the tag and switch the trait off with its `enabled` option instead, in the `config` argument of the context that composes it.

The trait's tag reaches every hook of its trait. For `ConfigTrait`, `ConfigOverrideTrait`, `QueueTrait`, `RestTrait`, `StateTrait`, `TimeTrait` and `WatchdogTrait`, it does exactly what the hook-method tag did, because the trait has 1 hook to switch off or both of its tags had the same effect. For the others, a tag that skipped the teardown alone now skips the setup as well. `@behat-steps-skip:EmailTrait`, for one, keeps an `@email` scenario from enabling the test email system, not only from disabling it afterwards, and a scenario that wants the collector without the teardown enables it with `When the test email system is enabled`.

### Tags on the `Feature:` line

A tag on the `Feature:` line switched some traits on and did nothing for others. `@javascript` there turned on `JavascriptTrait`'s error collection, because Behat's own hook filter reads both lines, while `@email` there left `EmailTrait`'s collector off. Every hook now reads the scenario's tags together with its feature's, skip tags included, so a skip tag on the `Feature:` line switches its trait off for every scenario below it.

| Trait | Tags now read on the `Feature:` line |
| --- | --- |
| `EmailTrait` | `@email`, `@email:TYPE`, `@debug` |
| `FieldTrait` | `@disable-form-validation` |
| `FileDownloadTrait` | `@download` |
| `JavascriptTrait` | `@js-errors` |
| `ModuleTrait` | `@module:NAME`, `@module:!NAME` |
| `ResponsiveTrait` | `@breakpoint:NAME`, and the `@javascript` it requires |
| `TestmodeTrait` | `@testmode` |
| `WatchdogTrait` | `@watchdog:TYPE`, `@error` |
| `EntityLifecycleTrait` | `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` |

Where both lines carry the same kind of tag:

- A flag such as `@email` or `@download` switches the behavior on from either line.
- `@email:TYPE` and `@watchdog:TYPE` add up, so the scenario uses every handler type and tracks every message type named on either line.
- `@module:` and `@breakpoint:` take the scenario's value over the feature's. A feature tagged `@module:help` holding a scenario tagged `@module:!help` leaves `help` disabled for that scenario, and doesn't install it first.
- Each line takes 1 `@breakpoint:` tag at most. 2 on the `Feature:` line fail every scenario below it with `Only one @breakpoint tag is allowed per feature`.
- A parametrized tag with nothing after the colon, such as `@module:`, `@email:` or `@breakpoint:`, names nothing and is ignored. It used to reach some traits as an empty module name, handler type or breakpoint.

A tag on the `Feature:` line that used to do nothing now acts on every scenario in the feature. `grep -rn -B3 'Feature:' <your features directory>` prints the lines above each `Feature:` keyword, which is where those tags sit. Look for:

- `@email`: every scenario captures mail in the test collector, so a scenario that relied on mail leaving the site no longer sends it.
- `@testmode`: every scenario runs in test mode, and fails on a site without the Testmode module enabled.
- `@module:NAME`: the module is installed or uninstalled around every scenario, which is slow. If every scenario needs it, enable it in the fixture site instead.
- `@watchdog:TYPE`: every scenario that logs a warning or worse of that type fails.
- `@breakpoint:NAME`: every scenario is resized, and a scenario with `@javascript` on neither line fails.
- `@disable-form-validation`: every JavaScript scenario submits forms the browser would otherwise block.
- `@download` and `@debug`: the download directory is prepared around every scenario, and every `@email` scenario prints the messages it reads.

A tag that was only meant for some of the scenarios in a feature moves down onto those scenarios.

## Step behavior

These steps keep their text, or take the 4.0 text from [Step text](#step-text), and behave differently. A scenario changes only where it leaned on the old behavior, and each part says what to look for.

### Numbers in step arguments

A step that takes a number parses it with `StringTrait::stringParseInteger()` or `StringTrait::stringParseNumber()`, which throw `\RuntimeException` naming the argument:

```
The count must be an integer, but "abc" was given.
The count must be 0 or greater, but "-1" was given.
```

| Steps | `abc` in 3.14 | `abc` in 4.0 |
| --- | --- | --- |
| Every step whose method took an `int`: the queue, table and element counts, the element index, tolerance and offset steps, and `the REST response status code should be :code` | Behat's `Type error: ... must be of type int, string given` | `\RuntimeException` |
| `I wait for :seconds second(s)`, `I wait for :seconds second(s) for AJAX to finish` and `search indexing runs for :count item(s)` | Read as `0` | `\RuntimeException` |
| `the number of emails sent should be :count` and the 2 other email count steps | Read as `0`, and `no`, `a` and `an` read as `0`, `1` and `1` | `\RuntimeException`; write the number |
| `the XML element :element should have :count element(s)` and `the system time is set to :value` | Read as `0` | `\RuntimeException` |
| `I set the viewport width to :width`, `I set the viewport height to :height` and `I set the viewport to :width by :height` | Read as `0`, and the resize failed without a word | `\RuntimeException` |

PHP's coercion was looser than the `int` type suggested: `1e3` read as 1000, and `3.5` read as 3 with a deprecation notice, which only failed the step where PHP reports deprecations. Both now fail with `\RuntimeException`. A value below what a step accepts fails the same way: a count below 0, a link index below 1, a viewport width or height below 1, a wait below 0 seconds and a command duration below 0.

### A value of `0`

A few checks read a string with `empty()`, which treats the string `0` as absent. They compare against the empty string now, so `0` is a value like any other:

- `Given the password for the user :name is "0"` sets the password instead of failing with `Password must not be empty.`
- An attribute whose value is `0` counts as present for the `the element :selector with the attribute :attribute ...` steps.
- A WYSIWYG field with the id `0` is filled through its id.
- `fileCreateEntity()` honors a destination URI of `0`.
- The Drush backend reads an alias or root path of `0` as configured, where the Drupal Driver's `DrushDriver` read it as missing.

### Lookups by title, label or name

When several entities share the title, label, name or description a step names, the step acts on the newest of them. The traits used to pick 4 different ways, so a scenario changes only where it has duplicates:

| Trait | 3.14 | 4.0 |
| --- | --- | --- |
| `ContentTrait`, `SearchApiTrait`, `MediaTrait`, `TaxonomyTrait`, `ContentBlockTrait` | The entity saved most recently, by revision ID | The entity created last, by ID |
| `MenuTrait` (a menu by label, a link by title), `DraggableviewsTrait`, `EckTrait` | The first match the database returned | The newest match |
| `ParagraphsTrait` (the parent entity) | The last match the database returned | The newest match |
| `BlockTrait` | The last machine name in string order, so `block_9` won over `block_10` | The block the scenario placed last, otherwise the last machine name in natural order |
| `WebformTrait` (`the webform :title has been created from the template :template`) | The first template sorted by title, then by machine name | The template the scenario created last, otherwise the one with the last machine name in natural order |

An entity saved again after a newer one was created no longer wins: content entities are compared by ID, which only grows when an entity is created. Blocks and menus have machine names instead of numeric IDs, so the one the scenario created last wins over any the site already held.

`the menu :menu_name does not exist` and `the following menu links do not exist in the menu :menu_name:` now remove every menu and every link that matches, as every other `... does not exist` step already did. They used to remove 1.

### Relative dates

`DateTrait` expands `[relative:...]` tokens in `:partial_value`, `:text` and `:content` arguments as well as in `:value`, `:datetime` and `:expected_value` ones. A token in the text a region, a row, the modal, the REST response, the command output or the drush output is checked for, or in an unmanaged file's content, is now expanded rather than compared as written.

`DateTrait` registers its transform as `#[Transform(':expected_value')]`, where 3.14 registered `#[Transform(':expectedValue')]`. No shipped step declares `:expectedValue`, so only a step of your own that declares it and relies on the expansion changes: rename its placeholder to `:expected_value`.

### Query parameters

`PathTrait` tested for a query parameter with `empty()`, which reads a parameter carrying `0` or an empty string as absent. It checks presence with `array_key_exists()` now, so `?page=0` and `?debug=` are parameters in the URL, and their value is compared separately.

| Step | `?filter=0` in 3.14 | `?filter=0` in 4.0 |
| --- | --- | --- |
| `Then the URL should have the query parameter :name` | Fails | Passes |
| `Then the URL should have the query parameter :name with the value :value` | Fails | Passes for the value `0` |
| `Then the URL should not have the query parameter :name` | Passes | Fails |
| `Then the URL should not have the query parameter :name with the value :value` | Passes | Fails for the value `0` |

A scenario that asserted a falsy parameter away with `Then current url should not have the "filter" parameter` names the value it excludes:

```gherkin
Then the URL should not have the query parameter "filter" with the value "recent"
```

### Email

4 `EmailTrait` steps pick an email by its subject. `with the subject :subject` now names the whole subject, as `the number of emails sent with the subject :subject should be :count` does, and `with a subject containing :partial_subject` matches part of it, case-sensitively, like every other `containing` step.

| Step | 3.14 | 4.0 |
| --- | --- | --- |
| `When I follow the link with the index :index in the email with the subject :subject` | The subject contains `:subject`, whitespace collapsed | The subject is exactly `:subject` |
| `Then the file :filename should be attached to the email with the subject :subject` | The subject contains `:subject`, whitespace collapsed | The subject is exactly `:subject` |
| `When I follow the link with the index :index in the email with a subject containing :partial_subject` | The subject contains `:partial_subject` in any case | The subject contains `:partial_subject` in the same case |
| `Then the file :filename should be attached to the email with a subject containing :partial_subject` | The subject contains `:partial_subject` in any case | The subject contains `:partial_subject` in the same case |

When several emails match, each step still uses the first one collected. A scenario that named only part of a subject switches to the `containing` step, and one that leaned on case-insensitive matching writes the subject's own case:

```gherkin
# Before.
When I follow link number "1" in the email with the subject "Verification"
Then the file "report.xlsx" should be attached to the email with the subject containing "monthly report"

# After.
When I follow the link with the index "1" in the email with a subject containing "Verification"
Then the file "report.xlsx" should be attached to the email with a subject containing "Monthly Report"
```

`When I enable the test email system`, which 4.0 reads `When the test email system is enabled`, enabled nothing in a scenario without an `@email` tag, because only the hook reading that tag named a handler. It now falls back to the `default` handler, as a bare `@email` does, and the collector it enables is disabled once the scenario finishes, unless `@behat-steps-skip:EmailTrait` switches the hooks off.

### Page cache

`Given the page cache for the path :path has been cleared` named 1 path but cleared every page: it invalidated the `http_response` cache tag, which Drupal puts on every cacheable response, so it emptied the whole internal page cache and the whole dynamic page cache. `Given the page cache for the paths matching :path_pattern has been cleared` matched its pattern anywhere in the cached URL, so `/news*` also cleared `/archive/news`, and a pattern with no `*` cleared every path that contained it.

Both steps now delete the internal page cache entries whose path matches. The match ignores the host, the query string and the request format, so clearing `/about` clears `/about?page=2` too. A pattern matches the whole path, from its first character, and `*` matches any run of characters, `/` included, so a site served under a base path includes it, as in `/subdir/news*`. That holds on the database backend, which holds the page cache by default and is the only cache backend that can list its entries. A page cache bin on Redis, Memcache or any other backend is emptied whole instead, so the steps never leave a stale entry behind.

| Step | 3.14 | 4.0 |
| --- | --- | --- |
| `Given the page cache for the path "/about" has been cleared` | Empties the internal and dynamic page caches for every path | Deletes the internal page cache entries for `/about` |
| `Given the page cache for the paths matching "/news*" has been cleared` | Deletes every entry whose URL contains `/news` | Deletes the entries whose path starts with `/news` |
| `Given the page cache for the paths matching "/news" has been cleared` | Deletes every entry whose URL contains `/news` | Deletes the entries for `/news` |

A scenario that leaned on the old reach, to refresh a path it didn't name or a page the dynamic page cache serves to a logged-in user, switches to a step that says what it clears:

```gherkin
# Before.
Given the page cache for the path "/about" has been cleared

# After: every internal page cache entry.
Given the page cache for the paths matching "/*" has been cleared

# After: every cache bin, the dynamic page cache included.
Given the cache has been cleared
```

A path or a pattern carrying a query string or a fragment fails with `The path "..." must not contain a query string or a fragment.`, or `The path pattern "..."` for a pattern. A cached path never holds either, so the argument could only match nothing; drop the query string, since the step covers every query string of the path already. A missing cache table only means nothing is cached yet, so both steps pass where the pattern step used to fail. They fail when the cache bin itself doesn't exist, which is the case when the `page_cache` module isn't enabled.

### Viewport and scrolling

- `ResponsiveTrait` swallowed every browser driver exception. On a browser driver that can't resize the window, its 4 viewport steps passed without doing anything, and `responsiveGetCurrentDimensions()` reported 1280 by 800 whatever the browser held. The steps now fail with Mink's `UnsupportedDriverActionException`, which names the browser driver, and `responsiveGetCurrentDimensions()` throws `\RuntimeException` when the browser doesn't report a positive integer. Tag a scenario that sets the viewport `@javascript`, as `@breakpoint:NAME` already required.
- `the element :selector should be displayed within the viewport with a top offset of :offset pixels` and its negative scrolled `:offset` pixels past the top of the element. They now scroll so the top of the element is `:offset` pixels below the top of the viewport, the space a fixed header of that height covers, and a negative offset scrolls the top of the element above the viewport. To keep what a scenario checked, negate its offset, as the example below shows.
- The scroll targets the element's position in the document. It used to read `offsetTop`, which is measured from the nearest positioned ancestor, so an element inside a `position: relative` wrapper further down the page was scrolled to the wrong place. `the element :selector should be displayed within the viewport` and its negative scroll the same way with no offset, and now find such an element where it is.
- `the element :selector should not be displayed within the viewport` and its `with a top offset of :offset pixels` form read a selector that matched nothing as displayed, so they failed on it. Nothing matching now means nothing is displayed, and both pass. `ElementTrait::elementIsVisuallyVisible()` returns a falsy value for such a selector, and the positive steps fail on it with `ElementNotFoundException`, as they did before.
- `the element :selector should be at the top of the viewport` passed for any element whose top edge sat somewhere in the viewport. It now passes only when the element's top edge is within 2 pixels of the viewport top, the same tolerance `the element :selector should be pinned to the top of the viewport` uses, and an element that isn't rendered fails it. A selector that matches nothing fails with `ElementNotFoundException`, where it used to fail with a JavaScript error from the browser driver. `I scroll to the element :selector` centers the element by default, so a scenario that scrolled and then checked "at the top" only passed because the check was loose. Assert the position the scroll leaves, or assert `the element :selector should be displayed within the viewport` where the element only has to be on screen.

```gherkin
# Before.
Then the element ".sticky-header" should be displayed within a viewport with a top offset of 200 pixels
When I scroll to the element "#main-content"
Then the element "#main-content" should be at the top of the viewport

# After.
Then the element ".sticky-header" should be displayed within the viewport with a top offset of -200 pixels
When I scroll to the element "#main-content"
Then the element "#main-content" should be centered in the viewport
```

### Steps that need a browser capability

`I wait for the modal to appear`, `I drop the following files on the dropzone :selector:` and `I switch to the iframe :selector` did work only a browser can do without checking for one first. Each now raises `UnsupportedDriverActionException` naming the capability it needs. The modal step used to spend its whole timeout, 3 seconds by default, and then report that the modal hadn't appeared. `I press the key ...` names the capability where it named "Selenium2 or Chrome", and `I wait for :seconds second(s) for AJAX to finish` names it where it named the browser driver's class.

### Blocks

- `the block :label has the condition :condition removed` reset the condition to its defaults instead of removing it, and added the condition to a block that didn't carry it. Drupal drops a condition left at its defaults when the block saves, so for the conditions core ships, the saved block came out the same, but a condition whose configuration doesn't return to its defaults stayed on the block. The step now removes the condition and leaves a block without it unchanged. A condition ID that names no condition plugin fails with `\RuntimeException` (`The condition "..." does not exist.`), where Drupal's `PluginNotFoundException` used to surface.
- `the instance of the block :admin_label exists with the following configuration:` looked the block up again by its label after creating it, so an existing block with the same label could receive the configuration. It now configures the block it created, and registers it for removal before configuring it.
- The steps that configure, enable, disable or change the conditions of a block start from its stored configuration, so a value a module or `settings.php` overrides is no longer written into the block when it saves.

### Search API

`I add the :content_type content with the title :title to the search index`, which 4.0 reads `the :content_type content with the title :title is indexed`, tracked the node, then indexed whichever item came next in each index, which was the named node only when nothing else was waiting. It now indexes the named node's own items on every enabled index that includes the node, and leaves everything else queued. It fails with `\RuntimeException` when no enabled index includes the node (`No active search index includes the "..." content with the title "...".`), where it used to fail only when no index was enabled at all. An index that fails to index the node throws Search API's `SearchApiException` rather than logging it.

A scenario that relied on the step to push another queued item into the index runs `search indexing runs for :count item(s)` as well. A PHP call to `SearchApiTrait::searchApiIndexContent()` changes the same way.

`search indexing runs for :count item(s)` goes through `SearchIndexCapabilityInterface`, which the Drush backend serves with `search-api:index`. The other 3 search steps go through `SearchApiCapabilityInterface`, which only the in-process backend provides, so they reach it whatever the order lists first and fail with `UnsupportedBackendActionException` only when no backend in the scenario's list provides it.

### Queues

`QueueTrait` deleted the queues a scenario used only when the scenario was tagged `@queue`, a tag nothing documented, so an untagged scenario left its queue items behind for the next one. The teardown now runs after every scenario, and `@queue` does nothing. A queue counts as used once any queue step names it, the assertions included, so a scenario that only checks a queue the site filled deletes it as well. To keep the items for a later scenario, tag the scenario that leaves them `@behat-steps-skip:QueueTrait`, or set `queue.enabled` to `FALSE` for the suite.

The queue steps go through `QueueCapabilityInterface`, which the Drush backend serves with `queue:list`, `queue:run` and `queue:delete`, apart from `the following item is in the queue :queue:`, which goes through `QueueItemCapabilityInterface`. Only the in-process backend provides that one, so the step reaches it whatever the order lists first, and fails with `UnsupportedBackendActionException` only when no backend in the scenario's list provides it. The helpers behind the steps, `queueCountItems()`, `queueClear()` and `queueProcess()`, act on a queue by name and return no Drupal object.

### Log entries

`WatchdogTrait` reads the log through `LogCapabilityInterface`, which the Drush backend serves with `watchdog:show` and `watchdog:delete`, so a profile reaching Drupal only through Drush runs the check. The entries a scenario logged are the ones newer than the id the log held when the scenario started, where they used to be the ones with a later timestamp. `watchdogClearErrors()` renders each entry from the array the capability returns, so an error report reads `Array (` where it read `stdClass Object (`.

### Test mode

`TestmodeTrait` writes the mode through `StateCapabilityInterface` and the cache tag through `CacheCapabilityInterface` rather than through the Testmode module's `Testmode` class, so `@testmode` works under `@backend:drush` too. `testmodeEnableTestMode()` and `testmodeDisableTestMode()` are instance methods, as [Signature changes](#signature-changes) lists.

### Files

Every file step goes through `FileCapabilityInterface`, which only the in-process backend provides. `the unmanaged file at the URI :uri should exist` and its negative read `is_file()` rather than `file_exists()`, so a directory at the URI no longer counts as a file.

### Modules

`the module :module is enabled`, `the module :module is disabled` and their table forms no longer rebuild every cache after the install or the uninstall. Drupal's module installer rebuilds the container and the router itself, so the steps call `moduleInstall()` or `moduleUninstall()` and nothing else, and under `@backend:drush` no `cache:rebuild` follows the `pm:install` or `pm:uninstall`.

### Redirects

The redirect steps go through `RedirectCapabilityInterface`, which only the in-process backend provides. A source path and a destination are stored as the module's own `Redirect::setSource()` and `Redirect::setRedirect()` store them, so `/old` and `old` name the same redirect, and `/about`, `about` and `internal:/about` the same destination, in the creation step and the assertions alike.

### Entity cleanup

3.14 tore a scenario's entities down in 2 hooks. The Drupal Extension's `RawDrupalContext::cleanEntities()` deleted the nodes, terms and languages its creation methods made, and this package's `entityCleanupAfterScenario()` deleted the entities its traits registered, leaving out the types in `ENTITY_CLEANUP_EXCLUDED_TYPES` so the 2 never deleted the same one. 4.0 has 1 hook, `EntityLifecycleTrait::entityLifecycleAfterScenario()`, with no second registry and no exclusion list:

- Every entity a creation step or the backend creates, except a user or a role, is registered and deleted in reverse creation order, so a node, a term and a media item created in 1 scenario come down in an order that respects the references between them.
- The hook runs after every scenario, where `entityCleanupAfterScenario()` ran only after an `@api` one.
- An entity your own step creates outside the creation steps joins the teardown only when the step registers it with `$this->entityLifecycleRegister($stub, $id_key)`, passing a stub that carries its ID and the entity type's id key. Without that call, the entity survives the scenario.
- `AuthTrait::authAfterScenario()` deletes the users and roles a scenario created, users first. It still runs the role cleanup when removing the users fails, and when both fail, it throws 1 `\RuntimeException` that names both.

### Fields, files and stored values

- A `supported_image` field resolves its value the way `file` and `image` fields do: a value naming a managed file that's already on the site references that file, and only a path to a file on disk is uploaded. `public://hero.jpg`, the URI of a managed file, references the file where 3.14 uploaded a copy as a new managed file, and `hero.jpg`, the basename of a managed file in `public://` or `private://`, references the file where 3.14 failed with `Error reading file hero.jpg.` `SupportedImageHandler` extends `ImageHandler` now, and the 3 file handlers share the `doExpand()` in `FileHandler`.
- A `path` in `the following managed files exist:` that climbs out of `files_path` with `..` no longer resolves against the fixture directory, so it's read relative to the working directory instead.
- The other columns of `the following managed files exist:`, such as `status` or `uid`, go through the field handlers, as the columns of every other creation step do, where 3.14 set each value on the file as written. A `uid` cell may name the user, and a value a handler can't resolve, such as a user that doesn't exist, fails the step.
- `the response XML is loaded from the file :filename`, `the response JSON is loaded from the file :filename` and the 4 `the response should match the ... in the file :filename` steps fail for a path that leaves `files_path`, with `The fixture file "..." is outside the configured "files_path".`, where 3.14 read the file outside it.
- A state value holding a JSON object is stored as an array. `StateTrait` decoded JSON to a `stdClass` while `ConfigTrait` decoded it to an array, and both now share `stringNormalizeValue()`, so `the state :name has the value :value` with `{"a":1}` stores `['a' => 1]`.
- `the draggable views items ... are saved in the following order:` resolves every title before it writes a weight. It used to write each row's weight as it went, so a missing title left the order half-saved. A missing title now fails before anything is written. A view that doesn't exist fails with `\RuntimeException` (`No view with the id "..." exists.`), where the weights used to be written under any name, and the saved order invalidates the list tags of the view's base entity type and the view's and the display's own tags, as the module's own form does, where `node_list` alone was invalidated.
- A textual `created`, `changed`, `revision_timestamp` or `timestamp` value, such as `-1 hour` or `2014-10-17 8:00am`, is read by the field handlers, so it works on every entity type with such a field where 3.14 converted it for a node alone. A value `strtotime()` can't read fails with `Timestamp field value "..." is not parseable.`
- A field handler of your own is registered through the `drupal.field_handlers` option, a map of field type to handler class, or from a context through `FieldHandlerCapabilityInterface::registerFieldHandler()`, as [Field handlers](docs/content/extending/backend.md#field-handlers) describes. 3.14 registered one with `registerFieldHandler()` on the driver's `Core`, or through a `Core` subclass that overrode `registerDefaultFieldHandlers()`. `FieldHandlerInterface` now declares the constructor every handler takes, `__construct(EntityStubInterface $stub, string $entity_type, string $field_name)`, so a handler that implements the interface directly declares it too. A handler extending `AbstractHandler` inherits it.
- A creation alias of your own is registered through the `drupal.creation_aliases` and `drush.creation_aliases` options, 1 list of classes per backend, or from a context through `CreationAliasCapabilityInterface::registerCreationAlias()`, as [Creation aliases](docs/content/extending/backend.md#creation-aliases) describes. 3.14 registered one with `registerCreationAlias()` on the driver's `Core` or `DrushDriver`, or through a subclass that overrode `registerDefaultCreationAliases()`. The `drupal` backend also runs aliases for the media, paragraphs, block content, menu links, ECK entities and files a step creates, and for the entities `the following :entity_type entities exist:` creates, where 3.14 ran them for nodes, terms and users only, and its field parser leaves a registered alias's column as written. The Drush backend runs the `PreCreateAliasInterface` aliases for a user before `drush user:create`, where 3.14 ran only the `PostCreateAliasInterface` ones. An alias that implements neither interface fails to register, where 3.14 registered it and never ran it.
- A `date_recur` field's `value` and `end_value` are read in the record's `timezone` and stored in UTC, as the module's own form stores them. `2025-01-01T09:00:00` with `Australia/Sydney` starts the first occurrence at 09:00 in Sydney, where 3.14 stored the value as written and the module read it as UTC, 20:00 in Sydney. A value with its own offset keeps it, so `2025-01-01T09:00:00Z` stores what 3.14 stored. A record with a date but no `timezone` takes the site timezone, where 3.14 failed to save it with `Missing time zone`, and an invalid `timezone` fails with `Date recur field record has an invalid timezone "...".`

### Prerequisites

A trait checks what it needs before a step acts, and fails with a message naming what's missing, as [Failure messages](#failure-messages) lists. A hook checks them at scenario start, and when the trait has an `enabled` option, its message also names the option and the skip tag that switch the trait off.

| Trait | 3.14 | 4.0 |
| --- | --- | --- |
| `ContentTrait`, `DraggableviewsTrait`, `MenuTrait`, `RedirectTrait`, `SearchApiTrait` | Checked its module, with a message of its own | Checks the same module, with the prerequisite message |
| `EckTrait`, `ParagraphsTrait`, `WebformTrait` | Checked nothing | Fails without the `eck`, `paragraphs` or `webform` module |
| `MediaTrait`, `TaxonomyTrait` | Checked nothing, and failed with whatever Drupal threw first | Fails without the core `media` or `taxonomy` module. `TaxonomyTrait`'s term creation goes through the term capability and checks nothing |
| `FileTrait` | Checked nothing | Its managed file steps fail without the core `file` module; the unmanaged file steps keep working without it |
| `BlockTrait`, `ContentBlockTrait` | Checked nothing, and failed with whatever Drupal threw first | Fails without the core `block` or `block_content` module |
| `CacheTrait`, `EmailTrait`, `QueueTrait`, `TimeTrait`, `UserTrait` | Checked nothing | Names the capability it needs: `CacheCapabilityInterface` for the page and render cache steps, `QueueCapabilityInterface` for the queue steps, `MailCapabilityInterface` for the email steps, `StateCapabilityInterface` for the time steps and `UserCapabilityInterface` for the user creation and deletion steps |
| `TestmodeTrait` | Called the module's `Testmode` class without checking | Checks the `testmode` module when a `@testmode` scenario starts, switches the mode through `StateCapabilityInterface` and `CacheCapabilityInterface`, and its teardown disables test mode only if the scenario enabled it |
| `WatchdogTrait` | Ran only in `@api` scenarios | Needs the core `dblog` module and a backend providing `LogCapabilityInterface`, the Drush backend included, and fails the scenario at its start without them, as [Contexts to register](#contexts-to-register) explains |

## Classes and interfaces

Everything the Drupal Driver kept under `Drupal\Driver` moved under `DrevOps\BehatSteps\Backend`: what any backend may provide sits at its root, and the Drupal and Drush backends, `Core`, the field handlers, the Drupal creation aliases and the capabilities only a Drupal backend provides sit under `DrevOps\BehatSteps\Backend\Drupal`. A Drupal Driver type the table doesn't name keeps its short name and changes only its namespace. The Drupal Extension's types moved under `DrevOps\BehatSteps\Extension`, apart from its field parser, which joined the field handlers under `DrevOps\BehatSteps\Backend`. "Driver" now only ever means Mink's browser driver: the Drupal, Drush and Blackbox drivers are backends. [Base classes](#base-classes) maps the Drupal Extension's `RawDrupalContext` and `DrupalContext`.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `Drupal\Driver\<Name>` | `DrevOps\BehatSteps\Backend\<Name>` | `EntityStub`, the creation alias contracts, the exceptions and the capabilities any backend may provide: `Authentication`, `Cache`, `Content`, `Cron`, `CreationAlias`, `Language`, `Mail`, `Role` and `User` |
| `Drupal\Driver\<Name>` | `DrevOps\BehatSteps\Backend\Drupal\<Name>` | `Core`, the field handlers and the capabilities only a Drupal backend provides: `Batch`, `Block`, `Config`, `Core`, `Drush`, `Module`, `Node`, `State` and `Term` |
| `Drupal\Driver\DriverInterface` | `DrevOps\BehatSteps\Backend\BackendInterface` | |
| `Drupal\Driver\DrupalDriver` | `DrevOps\BehatSteps\Backend\Drupal\DrupalBackend` | Builds its Core when it's constructed, from 2 more constructor arguments: the class the `drupal.core` option names and the random generator. A subclass that overrides the constructor passes both on, or the backend ignores the `drupal.core` option and the Core makes its own random generator |
| `Drupal\Driver\DrupalDriverInterface` | `DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface` | Declares only `setCore()`; `getCore()` and `getDrupalVersion()` moved to `CoreCapabilityInterface` |
| `Drupal\Driver\DrushDriver` | `DrevOps\BehatSteps\Backend\Drupal\DrushBackend` | |
| `Drupal\Driver\DrushDriverInterface` | `DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface` | |
| `Drupal\Driver\BlackboxDriver` | `DrevOps\BehatSteps\Backend\BlackboxBackend` | |
| `Drupal\Driver\BlackboxDriverInterface` | `DrevOps\BehatSteps\Backend\BlackboxBackendInterface` | |
| `Drupal\Driver\Core<N>\Core` | `DrevOps\BehatSteps\Backend\Drupal\Core<N>\Core` | The namespace a version core of this package sits in. The `drupal` backend builds the newest one at or below the Drupal major it detects. A Core of your own is named by the `drupal.core` option, as [Core](docs/content/extending/backend.md#core) describes, and a field handler of your own is registered through `drupal.field_handlers`; see [Fields, files and stored values](#fields-files-and-stored-values) |
| `Drupal\Driver\Alias\RolesAlias` | `DrevOps\BehatSteps\Backend\Alias\RolesAlias` | Final |
| `Drupal\Driver\Core\Alias\AuthorAlias` | `DrevOps\BehatSteps\Backend\Drupal\Core\Alias\AuthorAlias` | Final |
| `Drupal\Driver\Core\Alias\ParentTermAlias` | `DrevOps\BehatSteps\Backend\Drupal\Core\Alias\ParentTermAlias` | Final |
| `Drupal\Driver\Core\Alias\VocabularyMachineNameAlias` | `DrevOps\BehatSteps\Backend\Drupal\Core\Alias\VocabularyMachineNameAlias` | Final |
| `Drupal\Driver\Core\Field\FieldClassifier` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldClassifier` | Final |
| `Drupal\Driver\Core\Field\FieldShapeClassifier` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldShapeClassifier` | Final |
| `Drupal\Driver\Exception\BootstrapException` | `DrevOps\BehatSteps\Backend\Exception\BootstrapException` | Final |
| `Drupal\Driver\Exception\CreationAliasResolutionException` | `DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException` | Final; see [Exceptions](#exceptions) |
| `Drupal\Driver\Exception\UnsupportedDriverActionException` | `DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException` | Final. Its short name no longer matches Mink's `UnsupportedDriverActionException`, so a file that imported Mink's under an alias can drop the alias |
| `Drupal\Driver\SubDriverFinderInterface` | Removed | It served the Drupal Extension's subcontext discovery; see [Backend contracts](#backend-contracts) |
| `Drupal\Driver\Capability\WatchdogCapabilityInterface` | Removed | Neither this package nor the Drupal Extension called its `watchdogFetch()` |
| `Drupal\Driver\Core\Field\ColorFieldTypeHandler` | Removed | Its field type falls back to `DefaultHandler` |
| `Drupal\Driver\Core\Field\TextHandler` | Removed | Its field type falls back to `DefaultHandler` |
| `Drupal\Driver\Core\Field\TextLongHandler` | Removed | Its field type falls back to `DefaultHandler` |
| `Drupal\Driver\Core\Field\TextWithSummaryHandler` | Removed | Its field type falls back to `DefaultHandler` |
| `Drupal\DrupalExtension\ServiceContainer\DrupalExtension` | `DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension` | Final |
| `Drupal\DrupalExtension\Compiler\DriverPass` | `DrevOps\BehatSteps\Extension\ServiceContainer\BackendPass` | Final |
| `Drupal\DrupalExtension\Context\MinkContext` | `Behat\MinkExtension\Context\MinkContext` | See [Contexts to register](#contexts-to-register) |
| `Drupal\DrupalExtension\Context\ConfigContext` | Removed | `DrupalContext` carries its steps; see [Drupal Extension steps](#drupal-extension-steps) |
| `Drupal\DrupalExtension\Context\DrushContext` | Removed | `DrupalContext` carries its steps; see [Drupal Extension steps](#drupal-extension-steps) |
| `Drupal\DrupalExtension\Context\MailContext` | Removed | `DrupalContext` carries its steps; see [Drupal Extension steps](#drupal-extension-steps) |
| `Drupal\DrupalExtension\Context\MarkupContext` | Removed | `DrupalContext` carries its steps; see [Drupal Extension steps](#drupal-extension-steps) |
| `Drupal\DrupalExtension\Context\MessageContext` | Removed | `DrupalContext` carries its steps; see [Drupal Extension steps](#drupal-extension-steps) |
| `Drupal\DrupalExtension\Context\RawMailContext` | Removed | See [Backend contracts](#backend-contracts) |
| `Drupal\DrupalExtension\Context\RandomContext` | Removed | See [Contexts to register](#contexts-to-register) |
| `Drupal\DrupalExtension\Context\MappingContext` | Removed | See [Contexts to register](#contexts-to-register) |
| `Drupal\DrupalExtension\Context\DrupalAwareInterface` | `DrevOps\BehatSteps\Extension\Context\BackendAwareInterface` and `DrevOps\BehatSteps\Extension\Context\UserAwareInterface` | See [Interfaces](#interfaces) |
| `Drupal\DrupalExtension\Context\Initializer\DrupalAwareInitializer` | `DrevOps\BehatSteps\Extension\Context\Initializer\BackendAwareInitializer` | Final |
| `Drupal\DrupalExtension\Context\Attribute\HookAttributeReader` | `DrevOps\BehatSteps\Extension\Context\Attribute\HookAttributeReader` | Final |
| `Drupal\DrupalExtension\ParametersAwareInterface` | `DrevOps\BehatSteps\Extension\Context\ParametersAwareInterface` | |
| `Drupal\DrupalExtension\ParametersTrait` | `DrevOps\BehatSteps\Extension\Config\ParametersTrait` | No longer carries `getMapping()`, and `getDrupalText()` and `getDrupalSelector()` are `getText()` and `getSelector()`; see [Drupal Extension methods](#drupal-extension-methods) |
| `Drupal\DrupalExtension\MinkAwareTrait` | `DrevOps\BehatSteps\Extension\Browser\MinkAwareTrait` | No longer carries `saveScreenshot()`, which a context still inherits from Mink's `RawMinkContext` |
| `Drupal\DrupalExtension\Listener\DriverListener` | `DrevOps\BehatSteps\Extension\Listener\BackendListener` | Final |
| `Drupal\DrupalExtension\Manager\DriverManager` | `DrevOps\BehatSteps\Extension\Registry\BackendRegistry` | Final. Registers the backends, resolves 1 by capability and tracks the scenario's order |
| `Drupal\DrupalExtension\Manager\DriverManagerInterface` | `DrevOps\BehatSteps\Extension\Registry\BackendRegistryInterface` | |
| `Drupal\DrupalExtension\Manager\DrupalUserManager` | `DrevOps\BehatSteps\Extension\Registry\UserRegistry` | Final. Stores the users a scenario created and tracks the current one |
| `Drupal\DrupalExtension\Manager\DrupalUserManagerInterface` | `DrevOps\BehatSteps\Extension\Registry\UserRegistryInterface` | |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManager` | `DrevOps\BehatSteps\Extension\Auth\Authenticator` and `DrevOps\BehatSteps\Extension\Auth\BasicAuthenticator` | Both final. `Authenticator` logs a user in and out, holds the Drupal session and implements `AuthenticatorInterface` and `FastLogoutInterface`. `BasicAuthenticator` derives the credentials from `base_url`, applies them to Mink and implements `BasicAuthenticatorInterface`; `Authenticator` takes it as a constructor argument to reapply the credentials after a fast logout |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManagerInterface` | `DrevOps\BehatSteps\Extension\Auth\AuthenticatorInterface` | |
| `Drupal\DrupalExtension\Manager\BasicAuthInterface` | `DrevOps\BehatSteps\Extension\Auth\BasicAuthenticatorInterface` | Adds `findCredentials()` |
| `Drupal\DrupalExtension\Manager\FastLogoutInterface` | `DrevOps\BehatSteps\Extension\Auth\FastLogoutInterface` | |
| `Drupal\DrupalExtension\Manager\DrupalMailManager` | Removed | See [Backend contracts](#backend-contracts) |
| `Drupal\DrupalExtension\Manager\DrupalMailManagerInterface` | Removed | |
| `Drupal\DrupalExtension\Selector\RegionSelector` | `DrevOps\BehatSteps\Extension\Selector\RegionSelector` | Final |
| `Drupal\DrupalExtension\Generator\ClassGenerator` | `DrevOps\BehatSteps\Extension\Generator\ClassGenerator` | Final |
| `Drupal\DrupalExtension\Hook\Attribute\<Name>` | `DrevOps\BehatSteps\Extension\Hook\Attribute\<Name>` | The entity and user creation hook attributes, `BeforeEntityCreate`, `AfterEntityCreate`, `BeforeUserCreate` and `AfterUserCreate`, which take no argument now; see [Hooks](#hooks) |
| `Drupal\DrupalExtension\Hook\Attribute\<Name>` | `DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\<Name>` | The node and term creation hook attributes, `BeforeNodeCreate`, `AfterNodeCreate`, `BeforeTermCreate` and `AfterTermCreate`, which take no argument now; see [Hooks](#hooks) |
| `Drupal\DrupalExtension\Hook\Attribute\DrupalHook` | `DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface` | Declares `getCallClass()` in place of `getFilterString()` |
| `Drupal\DrupalExtension\Hook\Scope\<Name>` | `DrevOps\BehatSteps\Extension\Hook\Scope\<Name>` | The entity and user creation hook scopes, such as `BeforeEntityCreateScope` |
| `Drupal\DrupalExtension\Hook\Scope\<Name>` | `DrevOps\BehatSteps\Extension\Drupal\Hook\Scope\<Name>` | The node, term and language creation hook scopes, such as `BeforeNodeCreateScope` |
| `Drupal\DrupalExtension\Hook\Scope\EntityScope` | `DrevOps\BehatSteps\Extension\Hook\Scope\EntityScopeInterface` | |
| `Drupal\DrupalExtension\Hook\Call\EntityHook` | `DrevOps\BehatSteps\Extension\Hook\Call\EntityHook` | See [Hooks](#hooks) |
| `Drupal\DrupalExtension\Hook\Call\AfterEntityCreate` and the 3 other entity and user `Before*Create` and `After*Create` calls | `DrevOps\BehatSteps\Extension\Hook\Call\AfterEntityCreate` and the same 3 | Final; see [Hooks](#hooks) |
| `Drupal\DrupalExtension\Hook\Call\AfterNodeCreate` and the 3 other node and term `Before*Create` and `After*Create` calls | `DrevOps\BehatSteps\Extension\Drupal\Hook\Call\AfterNodeCreate` and the same 3 | Final; see [Hooks](#hooks) |
| `Drupal\DrupalExtension\Element\DocumentElement` | `DrevOps\BehatSteps\Extension\Browser\Element\DocumentElement` | |
| `Drupal\DrupalExtension\Parser\EntityFieldParser` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\EntityFieldParser` | Final, as it was |
| `Drupal\DrupalExtension\Parser\EntityFieldParserInterface` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\EntityFieldParserInterface` | |
| `Drupal\DrupalExtension\Parser\Exception\ParseException` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\ParseException` | |
| `Drupal\DrupalExtension\Parser\Exception\MultipleParseException` | `DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\MultipleParseException` | Final |
| `Drupal\MinkExtension\ServiceContainer\Driver\BrowserKitFactory` | `DrevOps\BehatSteps\Extension\ServiceContainer\Driver\BrowserKitFactory` | Final |

`grep -rnE 'Drupal\\+(Driver|DrupalExtension|MinkExtension)' <your project>` lists every import, docblock type and configuration key that still names the Drupal Driver, the Drupal Extension or its Mink extension.

### Final classes

A class a project isn't meant to extend is `final`. The classes left open are the 3 contexts, the backends, `Core`, the field handlers, the browser adapters, `HttpClientFactory` and `DocumentElement`, and [Final classes](CONTRIBUTING.md#final-classes) says why each one stays open. A class of your own that extended a class the table marks final can't follow it into 4.0 by changing its parent:

```
PHP Fatal error:  Class Acme\AcmeAuthenticator cannot extend final class DrevOps\BehatSteps\Extension\Auth\Authenticator
```

Implement its interface instead. A suite that swaps in its own registry or authenticator through a `*.class` parameter implements `BackendRegistryInterface`, `ScenarioTagRegistryInterface`, `UserRegistryInterface`, `AuthenticatorInterface` with `FastLogoutInterface`, or `BasicAuthenticatorInterface`. A creation alias of your own implements `PreCreateAliasInterface` or `PostCreateAliasInterface`, as [Creation aliases](docs/content/extending/backend.md#creation-aliases) describes, and option resolution is replaced through `TraitOptionResolverFactoryInterface`, as [Trait options](docs/content/extending/extension.md#replace-option-resolution) describes. PHPUnit can't double a final class either, so a test that built a mock of one fails with `Class "..." is declared "final" and cannot be doubled`; double its interface instead.

## Methods

Each method a 3.14 project could call, override or implement that 4.0 renamed, moved or removed has 1 row below, keyed by its 3.14 name. The notes carry every other change to the same method: a renamed parameter, a new visibility, what it returns, what it throws. A row without notes is a rename and nothing else. A method that kept its name but changed its signature or visibility is in [Signature changes](#signature-changes) or [Visibility changes](#visibility-changes).

Rename every call and every override. An override left under a 3.14 name is a method of your own that nothing calls any more, and nothing reports it, as [Override points](#override-points) explains. Apply the renames in 1 pass, matching each name against the `3.14` column only: some 4.0 names are 3.14 names of other methods, so renaming in sequence would rename `mediaCreateSingle()` to `mediaCreate()` and then on to `mediaCreateMultiple()`. [Names that now belong to another method](#names-that-now-belong-to-another-method) lists every such name.

### Renamed, moved and removed methods

#### Web step trait methods

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `AccessibilityTrait::accessibilityAggregateRender()` | `AccessibilityTrait::accessibilityAfterSuite()` | Hook; takes `AfterSuiteScope $scope` |
| `AccessibilityTrait::accessibilityAssertCurrentPage()` | `AccessibilityTrait::accessibilityAssertCurrentPagePasses()` | |
| `AccessibilityTrait::accessibilityAssertCurrentPageForTags()` | `AccessibilityTrait::accessibilityAssertCurrentPagePassesForTags()` | `$rules` is `$tags` |
| `AccessibilityTrait::accessibilityAutoAssess()` | `AccessibilityTrait::accessibilityAfterStep()` | Hook |
| `AccessibilityTrait::accessibilityBlankUrls()` | `AccessibilityTrait::accessibilityGetBlankUrls()` | Override point; now `public static`. It stays `static` because the suite report calls it from a static hook |
| `AccessibilityTrait::accessibilityFinalizeScenario()` | `AccessibilityTrait::accessibilityAfterScenario()` | Hook |
| `AccessibilityTrait::accessibilitySetupScenario()` | `AccessibilityTrait::accessibilityBeforeScenario()` | Hook |
| `CommandTrait::commandAssertExitCode()` | `CommandTrait::commandAssertExitCodeEquals()` | |
| `CommandTrait::commandAssertHasRun()` | `CommandTrait::commandRequireRun()` | Protected |
| `CommandTrait::commandAssertInteger()` | Removed | Was protected. Parse the number with `StringTrait::stringParseInteger()` |
| `CommandTrait::commandAssertNumeric()` | Removed | Was protected. Parse the number with `StringTrait::stringParseNumber()` |
| `CommandTrait::commandTimeout()` | `CommandTrait::commandGetTimeout()` | Override point; now `public` |
| `CookieTrait::cookieAssertWithNameExists()` | `CookieTrait::cookieAssertExistsWithName()` | |
| `CookieTrait::cookieAssertWithNameNotExists()` | `CookieTrait::cookieAssertNotExistsWithName()` | |
| `CookieTrait::cookieAssertWithNamePartialValueExists()` | `CookieTrait::cookieAssertExistsWithNamePartialValue()` | |
| `CookieTrait::cookieAssertWithNamePartialValueNotExists()` | `CookieTrait::cookieAssertNotExistsWithNamePartialValue()` | |
| `CookieTrait::cookieAssertWithNameValueExists()` | `CookieTrait::cookieAssertExistsWithNameValue()` | |
| `CookieTrait::cookieAssertWithNameValueNotExists()` | `CookieTrait::cookieAssertNotExistsWithNameValue()` | |
| `CookieTrait::cookieAssertWithPartialNameExists()` | `CookieTrait::cookieAssertExistsWithPartialName()` | |
| `CookieTrait::cookieAssertWithPartialNameNotExists()` | `CookieTrait::cookieAssertNotExistsWithPartialName()` | |
| `CookieTrait::cookieAssertWithPartialNamePartialValueExists()` | `CookieTrait::cookieAssertExistsWithPartialNamePartialValue()` | |
| `CookieTrait::cookieAssertWithPartialNamePartialValueNotExists()` | `CookieTrait::cookieAssertNotExistsWithPartialNamePartialValue()` | |
| `CookieTrait::cookieAssertWithPartialNameValueExists()` | `CookieTrait::cookieAssertExistsWithPartialNameValue()` | |
| `CookieTrait::cookieAssertWithPartialNameValueNotExists()` | `CookieTrait::cookieAssertNotExistsWithPartialNameValue()` | |
| `CookieTrait::cookieExists()` | `CookieTrait::cookieAssertExists()` | Now `public` |
| `CookieTrait::cookieGetByName()` | `CookieTrait::cookieFindByName()` | Returns `NULL` when nothing matches; now `public` |
| `CookieTrait::cookieNotExists()` | `CookieTrait::cookieAssertNotExists()` | Now `public` |
| `DateTrait::dateNow()` | `DateTrait::dateGetNow()` | Override point, and the seam for pinning the clock; now `public static` |
| `DiagnosticsTrait::diagnosticsGetDriverName()` | `DiagnosticsTrait::diagnosticsFindBrowserDriverName()` | Returns `NULL` when nothing matches; now `public` |
| `DiagnosticsTrait::diagnosticsGetRerunCommand()` | `DiagnosticsTrait::diagnosticsFindRerunCommand()` | Returns `NULL` when nothing matches; now `public` |
| `DiagnosticsTrait::diagnosticsGetStatusCode()` | `DiagnosticsTrait::diagnosticsFindStatusCode()` | Returns `NULL` when nothing matches; now `public` |
| `DiagnosticsTrait::diagnosticsGetUrl()` | `DiagnosticsTrait::diagnosticsFindUrl()` | Returns `NULL` when nothing matches; now `public` |
| `DiagnosticsTrait::diagnosticsHeader()` | `DiagnosticsTrait::diagnosticsGetHeader()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsRerunBinary()` | `DiagnosticsTrait::diagnosticsGetRerunBinary()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsShowDriver()` | `DiagnosticsTrait::diagnosticsGetShowBrowserDriver()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsShowJsErrors()` | `DiagnosticsTrait::diagnosticsGetShowJsErrors()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsShowRerun()` | `DiagnosticsTrait::diagnosticsGetShowRerun()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsShowStatusCode()` | `DiagnosticsTrait::diagnosticsGetShowStatusCode()` | Override point; now `public` |
| `DiagnosticsTrait::diagnosticsShowUrl()` | `DiagnosticsTrait::diagnosticsGetShowUrl()` | Override point; now `public` |
| `DropzoneTrait::dropzoneResolvePath()` | `FixtureDirectoryTrait::fixtureDirectoryGetFile()` | Now `public` |
| `ElementTrait::elementAssertAttributeContainingValueExists()` | `ElementTrait::elementAssertExistsWithAttributeContainingValue()` | `mixed $value` is `string $partial_value` |
| `ElementTrait::elementAssertAttributeContainingValueNotExists()` | `ElementTrait::elementAssertNotExistsWithAttributeContainingValue()` | `mixed $value` is `string $partial_value` |
| `ElementTrait::elementAssertAttributeWithValueExists()` | `ElementTrait::elementAssertExistsWithAttributeValue()` | `mixed $value` is `string $value` |
| `ElementTrait::elementAssertAttributeWithValueNotExists()` | `ElementTrait::elementAssertNotExistsWithAttributeValue()` | `mixed $value` is `string $value` |
| `ElementTrait::elementAssertHasCssPropertyContainingValue()` | `ElementTrait::elementAssertCssPropertyContains()` | `$value` is `$partial_value` |
| `ElementTrait::elementAssertHasCssPropertyWithValue()` | `ElementTrait::elementAssertCssPropertyEquals()` | |
| `ElementTrait::elementAssertIsNotPinnedToTop()` | `ElementTrait::elementAssertNotPinnedToTop()` | |
| `ElementTrait::elementAssertIsNotVisible()` | `ElementTrait::elementAssertNotVisible()` | |
| `ElementTrait::elementAssertIsNotVisuallyVisibleWithOffset()` | `ElementTrait::elementAssertNotVisuallyVisibleWithOffset()` | `int $number` is `string $offset` |
| `ElementTrait::elementAssertIsPinnedToTop()` | `ElementTrait::elementAssertPinnedToTop()` | |
| `ElementTrait::elementAssertIsPinnedToTopWithTolerance()` | `ElementTrait::elementAssertPinnedToTopWithTolerance()` | `int $tolerance` is `string $tolerance` |
| `ElementTrait::elementAssertIsVisible()` | `ElementTrait::elementAssertVisible()` | |
| `ElementTrait::elementAssertIsVisuallyHidden()` | `ElementTrait::elementAssertNotVisuallyVisible()` | No `$offset` parameter; call `elementAssertNotVisuallyVisibleWithOffset()` for an offset. A call that still passes 1 checks an offset of 0 |
| `ElementTrait::elementAssertIsVisuallyVisible()` | `ElementTrait::elementAssertVisuallyVisible()` | |
| `ElementTrait::elementAssertIsVisuallyVisibleWithOffset()` | `ElementTrait::elementAssertVisuallyVisibleWithOffset()` | `int $number` is `string $offset` |
| `ElementTrait::elementAssertNotHasCssPropertyContainingValue()` | `ElementTrait::elementAssertCssPropertyNotContains()` | `$value` is `$partial_value` |
| `ElementTrait::elementAssertNotHasCssPropertyWithValue()` | `ElementTrait::elementAssertCssPropertyNotEquals()` | |
| `ElementTrait::elementAssertPinnedToTop()` | `ElementTrait::elementAssertPinnedToTopWithin()` | Protected. The 3.14 name now belongs to a step; see [Names that now belong to another method](#names-that-now-belong-to-another-method) |
| `ElementTrait::elementClickByIndex()` | `ElementTrait::elementClickWithIndex()` | `int $index` is `string $index` |
| `ElementTrait::elementFindNthOrFail()` | `ElementTrait::elementGetNth()` | Throws `ElementNotFoundException` when nothing matches and `ExpectationException` past the last match. Throws `\RuntimeException` for an index below 1, where it threw `ExpectationException`. Now `public` |
| `ElementTrait::elementFollowLinkByIndex()` | `ElementTrait::elementFollowLinkWithIndex()` | `string $text, int $index` is `string $link, string $index` |
| `ElementTrait::elementNormaliseCssProperty()` | `ElementTrait::elementNormalizeCssProperty()` | Protected |
| `ElementTrait::elementPressButtonByIndex()` | `ElementTrait::elementPressButtonWithIndex()` | `string $label, int $index` is `string $button, string $index` |
| `ElementTrait::elementScrollIntoViewCenter()` | `ElementTrait::elementGetScrollIntoViewCenter()` | Override point; now `public` |
| `FieldTrait::fieldAssertColorFieldHasValue()` | `FieldTrait::fieldAssertColorFieldEquals()` | |
| `FieldTrait::fieldAssertState()` | `FieldTrait::fieldAssertEnabled()` | Takes only `string $field`. For the `disabled` state, call `FieldTrait::fieldAssertDisabled()` |
| `FieldTrait::fieldCheckboxCheck()` | `FieldTrait::fieldCheckCheckbox()` | |
| `FieldTrait::fieldCheckboxUncheck()` | `FieldTrait::fieldUncheckCheckbox()` | |
| `FieldTrait::fieldFillDatetimeHelper()` | `FieldTrait::fieldFillDatetimeInput()` | Protected |
| `FieldTrait::fieldRadioSelect()` | `FieldTrait::fieldChooseRadioButton()` | |
| `FileDownloadTrait::fileDownloadAssertFileName()` | `FileDownloadTrait::fileDownloadAssertFileNameEquals()` | |
| `FileDownloadTrait::fileDownloadAssertLinkPresent()` | `FileDownloadTrait::fileDownloadGetLink()` | Throws `ElementNotFoundException` when nothing matches; now `protected` |
| `FileDownloadTrait::fileDownloadAssertNoZipContainsPartial()` | `FileDownloadTrait::fileDownloadAssertZipNotContainsPartial()` | |
| `JavascriptTrait::javascriptAssertNoErrors()` | `JavascriptTrait::javascriptAssertErrorsNotExist()` | Now `public` |
| `JavascriptTrait::javascriptClearRegistry()` | `JavascriptErrorTrait::javascriptErrorClear()` | Protected |
| `JsonTrait::jsonAssertResponseIsJson()` | `JsonTrait::jsonAssertResponseJson()` | |
| `JsonTrait::jsonAssertResponseIsNotJson()` | `JsonTrait::jsonAssertResponseNotJson()` | |
| `JsonTrait::jsonReadFile()` | `FixtureDirectoryTrait::fixtureDirectoryReadFile()` | Now `public` |
| `JsonTrait::jsonResolveContent()` | `JsonTrait::jsonGetContent()` | Now `public` |
| `JsonTrait::jsonResolveScalar()` | `JsonTrait::jsonGetScalar()` | Throws `ExpectationException` when nothing matches; now `public` |
| `JsonTrait::jsonResolveSingle()` | `JsonTrait::jsonGetValue()` | Throws `ExpectationException` when the path matches nothing or more than 1 value, and returns `NULL` for a JSON `null`; now `public` |
| `JsonTrait::jsonValidateSchema()` | `JsonTrait::jsonAssertResponseMatchesSchema()` | Now `public` |
| `KeyboardTrait::keyboardTriggerKey()` | `KeyboardCapabilityInterface::keyboardTriggerKey()` | Was protected. Call it on the browser driver's adapter: `$this->browserDriverFor(KeyboardCapabilityInterface::class)->keyboardTriggerKey($xpath, $key)` |
| `LinkTrait::linkAssertLinkIsAbsolute()` | `LinkTrait::linkAssertAbsolute()` | |
| `LinkTrait::linkAssertLinkIsNotAbsolute()` | `LinkTrait::linkAssertNotAbsolute()` | |
| `LinkTrait::linkAssertTextWithHrefExists()` | `LinkTrait::linkAssertExistsWithHref()` | |
| `LinkTrait::linkAssertTextWithHrefNotExists()` | `LinkTrait::linkAssertNotExistsWithHref()` | |
| `LinkTrait::linkAssertTextWithHrefWithinElementExists()` | `LinkTrait::linkAssertExistsWithHrefWithinElement()` | `?string $selector` is `string $selector`; for the whole page, call `linkAssertExistsWithHref()` |
| `LinkTrait::linkAssertTextWithHrefWithinElementNotExists()` | `LinkTrait::linkAssertNotExistsWithHrefWithinElement()` | `?string $selector` is `string $selector`; for the whole page, call `linkAssertNotExistsWithHref()` |
| `LinkTrait::linkAssertWithTitleExists()` | `LinkTrait::linkAssertExistsWithTitle()` | |
| `LinkTrait::linkAssertWithTitleNotExists()` | `LinkTrait::linkAssertNotExistsWithTitle()` | |
| `MetatagTrait::metatagAssertMetaSetPresent()` | `MetatagTrait::metatagAssertMetaSetExists()` | Now `public` |
| `MetatagTrait::metatagAssertNoHtml()` | `MetatagTrait::metatagAssertNotContainsHtml()` | `$meta_name` is `$name` |
| `MetatagTrait::metatagAssertOpenGraphTags()` | `MetatagTrait::metatagAssertOpenGraphTagsExist()` | |
| `MetatagTrait::metatagAssertRobotsIncludes()` | `MetatagTrait::metatagAssertRobotsContains()` | |
| `MetatagTrait::metatagAssertRobotsNotIncludes()` | `MetatagTrait::metatagAssertRobotsNotContains()` | |
| `MetatagTrait::metatagAssertTwitterCardTags()` | `MetatagTrait::metatagAssertTwitterCardTagsExist()` | |
| `MetatagTrait::metatagAssertWithAttributesExists()` | `MetatagTrait::metatagAssertExistsWithAttributes()` | |
| `MetatagTrait::metatagAssertWithAttributesNotExists()` | `MetatagTrait::metatagAssertNotExistsWithAttributes()` | |
| `MetatagTrait::metatagGetCanonicalHref()` | `MetatagTrait::metatagFindCanonicalHref()` | Returns `NULL` when nothing matches; now `public` |
| `MetatagTrait::metatagGetMetaContent()` | `MetatagTrait::metatagFindMetaContent()` | Returns `NULL` when nothing matches. `$name` is `$meta_name`; now `public` |
| `MetatagTrait::metatagOpenGraphRequired()` | `MetatagTrait::metatagGetRequiredOpenGraphTags()` | Override point; now `public` |
| `MetatagTrait::metatagTwitterCardRequired()` | `MetatagTrait::metatagGetRequiredTwitterCardTags()` | Override point; now `public` |
| `ModalTrait::modalFindVisible()` | `ModalTrait::modalGetVisible()` | Throws `ExpectationException` when no modal is visible; now `public` |
| `PathTrait::pathAssertUrlHasNoParameter()` | `PathTrait::pathAssertUrlParameterNotExists()` | `$param` is `$name` |
| `PathTrait::pathAssertUrlHasNoParameterWithValue()` | `PathTrait::pathAssertUrlParameterNotEquals()` | `$param` is `$name` |
| `PathTrait::pathAssertUrlHasParameter()` | `PathTrait::pathAssertUrlParameterExists()` | `$param` is `$name` |
| `PathTrait::pathAssertUrlHasParameterWithValue()` | `PathTrait::pathAssertUrlParameterEquals()` | `$param` is `$name` |
| `ResponseTrait::responseAssertContainsHeader()` | `ResponseTrait::responseAssertHeaderExists()` | `$header_name` is `$name` |
| `ResponseTrait::responseAssertNotContainsHeader()` | `ResponseTrait::responseAssertHeaderNotExists()` | `$header_name` is `$name` |
| `ResponsiveTrait::responsiveSetBreakpointsFromTable()` | `ResponsiveTrait::responsiveSetBreakpointMultiple()` | |
| `RestTrait::restAssertResponseStatusCode()` | `RestTrait::restAssertResponseStatusCodeEquals()` | `int $code` is `string $code` |
| `TableTrait::tableAssertMultipleTextsInRow()` | `TableTrait::tableAssertRowContainsMultiple()` | `$row_text` is `$partial_text` |
| `TableTrait::tableFind()` | `TableTrait::tableGet()` | Throws `ElementNotFoundException` when nothing matches, where it threw `ExpectationException`; now `public` |
| `WaitTrait::waitForAjaxToFinish()` | `WaitTrait::waitForAjax()` | `string\|int $seconds` is `string $seconds`. PHP holding an `int` calls `waitForAjaxWithin(int $seconds)` instead |
| `WaitTrait::waitWaitForSeconds()` | `WaitTrait::waitSeconds()` | `string\|int $seconds` is `string $seconds` |
| `XmlTrait::xmlAssertResponseIsNotXml()` | `XmlTrait::xmlAssertResponseNotXml()` | |
| `XmlTrait::xmlAssertResponseIsXml()` | `XmlTrait::xmlAssertResponseXml()` | |
| `XmlTrait::xmlAssertValidAtomFeed()` | `XmlTrait::xmlAssertAtomFeedValid()` | |
| `XmlTrait::xmlAssertValidRssFeed()` | `XmlTrait::xmlAssertRssFeedValid()` | |
| `XmlTrait::xmlReadFile()` | `FixtureDirectoryTrait::fixtureDirectoryReadFile()` | Now `public` |
| `XmlTrait::xmlSetResponseContentDirect()` | `XmlTrait::xmlSetContent()` | |
| `XmlTrait::xmlSetResponseContentFromFile()` | `XmlTrait::xmlSetContentFromFile()` | |
| `XmlTrait::xmlValidateAtomFeed()` | `XmlTrait::xmlAssertAtomFeedValid()` | Was protected; folded into the step method, which takes no argument |
| `XmlTrait::xmlValidateDtd()` | `XmlTrait::xmlAssertResponseMatchesDtd()` | Now `public` |
| `XmlTrait::xmlValidateRelaxNg()` | `XmlTrait::xmlAssertResponseMatchesRelaxNg()` | Now `public` |
| `XmlTrait::xmlValidateRssFeed()` | `XmlTrait::xmlAssertRssFeedValid()` | Was protected; folded into the step method, which takes no argument |
| `XmlTrait::xmlValidateXsd()` | `XmlTrait::xmlAssertResponseMatchesXsd()` | Now `public` |

#### Drupal step trait methods

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `BigPipeTrait::bigPipeWaitBeforeStep()` | `BigPipeTrait::bigPipeBeforeStep()` | Hook; takes `BeforeStepScope $scope` |
| `BlockTrait::blockLoadByLabel()` | `BlockTrait::blockFindByLabel()` | Returns `NULL` when nothing matches; now `public` |
| `BlockTrait::blockRemove()` | `BlockTrait::blockDelete()` | |
| `ConfigOverrideTrait::configOverrideClearDriverHeader()` | `ConfigOverrideTrait::configOverrideClearBrowserDriverHeader()` | Protected |
| `ConfigTrait::configCastValue()` | `StringTrait::stringNormalizeValue()` | Protected. `StateTrait` shares it |
| `ConfigTrait::configCompareContains()` | `ConfigTrait::configAssertContains()` | Protected |
| `ConfigTrait::configCompareEquals()` | `ConfigTrait::configAssertEquals()` | Protected |
| `ConfigTrait::configReadEffective()` | `ConfigTrait::configFindEffectiveValue()` | Returns `NULL` when nothing matches; now `public` |
| `ConfigTrait::configReadStored()` | `ConfigTrait::configFindStoredValue()` | Returns `NULL` when nothing matches; now `public` |
| `ConfigTrait::configSnapshot()` | `ConfigTrait::configStoreOriginalData()` | Protected |
| `ConfigTrait::configStringifyValue()` | `StringTrait::stringFormatValue()` | Protected. `StateTrait` shares it |
| `ContentBlockTrait::contentBlockCreate()` | `ContentBlockTrait::contentBlockCreateMultiple()` | `$type` is `$content_block_type`. The 3.14 name now belongs to another method |
| `ContentBlockTrait::contentBlockCreateSingle()` | `ContentBlockTrait::contentBlockCreate()` | `$type` is `$content_block_type`; now `public` |
| `ContentBlockTrait::contentBlockCreateWithFields()` | `ContentBlockTrait::contentBlockCreateMultipleWithFields()` | `$type` is `$content_block_type` |
| `ContentBlockTrait::contentBlockDelete()` | `ContentBlockTrait::contentBlockDeleteMultiple()` | `$type` is `$content_block_type`. The 3.14 name now belongs to another method |
| `ContentBlockTrait::contentBlockEditBlockContentWithDescription()` | `ContentBlockTrait::contentBlockVisitEditPageWithDescription()` | `$type` is `$content_block_type` |
| `ContentTrait::contentAssertPathModuleEnabled()` | Removed | Was protected. `contentPrerequisites()` declares the `path` module |
| `ContentTrait::contentCreateWithFields()` | `ContentTrait::contentCreateMultipleWithFields()` | `$type` is `$content_type` |
| `ContentTrait::contentDelete()` | `ContentTrait::contentDeleteMultiple()` | The 3.14 name now belongs to another method |
| `ContentTrait::contentLoadMultiple()` | `QueryTrait::queryNodeIds()` | `$type` is `$content_type`; now `public` |
| `ContentTrait::contentLoadNodeByTitle()` | `ContentTrait::contentGetNodeByTitle()` | Throws `\RuntimeException` when nothing matches; now `public` |
| `ContentTrait::contentRebuildAccessGrantsByTitle()` | `ContentTrait::contentRebuildAccessGrantsWithTitle()` | |
| `ContentTrait::contentRemoveContentType()` | `ContentTrait::contentDeleteType()` | |
| `ContentTrait::contentResolveNidByTitle()` | `ContentTrait::contentGetNidByTitle()` | Throws `\RuntimeException` when nothing matches; now `public` |
| `ContentTrait::contentVisitViewWithTitle()` | `ContentTrait::contentVisitPageWithTitle()` | |
| `DraggableviewsTrait::draggableViewsFindNode()` | `DraggableviewsTrait::draggableviewsFindNode()` | Case only; now `public` |
| `DraggableviewsTrait::draggableViewsSaveBundleOrder()` | `DraggableviewsTrait::draggableviewsSaveBundleOrder()` | Case only. `$bundle` is `$content_type` |
| `EckTrait::eckCreateEntities()` | `EckTrait::eckCreateMultiple()` | Was protected; folded into the step method, which takes `$bundle, $entity_type, $table` where it took `$entity_type, $bundle, $table` |
| `EckTrait::eckCreateEntity()` | `EckTrait::eckCreate()` | Now `public` |
| `EckTrait::eckDeleteEntities()` | `EckTrait::eckDeleteMultiple()` | |
| `EckTrait::eckEditEntityWithTitle()` | `EckTrait::eckVisitEntityEditPageWithTitle()` | |
| `EckTrait::eckEntitiesCreate()` | `EckTrait::eckCreateMultiple()` | |
| `EmailTrait::emailAssertLinkNumber()` | Removed | Was protected. Parse the number with `StringTrait::stringParseInteger()` |
| `EmailTrait::emailAssertMessageContainsAttachmentWithName()` | `EmailTrait::emailAssertMessageContainsAttachmentWithSubject()` | `$file_name` is `$filename` |
| `EmailTrait::emailAssertMessageField()` | `EmailTrait::emailAssertMessageFieldEquals()` | |
| `EmailTrait::emailAssertMessageFieldNotExact()` | `EmailTrait::emailAssertMessageFieldNotEquals()` | |
| `EmailTrait::emailAssertMessageHeader()` | `EmailTrait::emailAssertMessageHeaderEquals()` | |
| `EmailTrait::emailAssertMessageSentTo()` | `EmailTrait::emailAssertMessageSentToAddress()` | |
| `EmailTrait::emailAssertMessageSentToAddressWithContentNotContaining()` | `EmailTrait::emailAssertMessageSentToAddressNotContains()` | Still asserts that an email went to the address and that no collected email's body contains the text |
| `EmailTrait::emailAssertNoMessagesSent()` | `EmailTrait::emailAssertMessageNotSent()` | |
| `EmailTrait::emailAssertNoMessagesSentToAddress()` | `EmailTrait::emailAssertMessageNotSentToAddress()` | |
| `EmailTrait::emailDeleteMailSystemOriginal()` | Removed | `MailCapabilityInterface::mailStopCollecting()` deletes the record of the handlers the collector replaced; see [Backend contracts](#backend-contracts) |
| `EmailTrait::emailFollowLinkNumber()` | `EmailTrait::emailFollowLinkWithIndexWithSubject()` | `$link_number` is `$index` |
| `EmailTrait::emailFollowLinkNumberWithSubjectContaining()` | `EmailTrait::emailFollowLinkWithIndexWithSubjectContaining()` | `$link_number, $subject` is `$index, $partial_subject` |
| `EmailTrait::emailGetMailSystemDefault()` | Removed | `MailCapabilityInterface::mailStartCollecting()` records the handler each type held; see [Backend contracts](#backend-contracts) |
| `EmailTrait::emailGetMailSystemOriginal()` | `EmailTrait::emailCollectorIsEnabled()` | Answers whether the scenario's collector has replaced the handler type, from the handler types the scenario enabled, where it returned the handler recorded on the site; now `public`, no longer `static` |
| `EmailTrait::emailSetMailSystemDefault()` | Removed | `MailCapabilityInterface::mailStartCollecting()` and `mailStopCollecting()` switch and restore the handlers; see [Backend contracts](#backend-contracts) |
| `EmailTrait::emailSetMailSystemOriginal()` | Removed | `MailCapabilityInterface::mailStartCollecting()` records the handler each type held; see [Backend contracts](#backend-contracts) |
| `FileTrait::fileAssertUnmanagedHasContent()` | `FileTrait::fileAssertUnmanagedContains()` | |
| `FileTrait::fileAssertUnmanagedHasNoContent()` | `FileTrait::fileAssertUnmanagedNotContains()` | |
| `FileTrait::fileCreateManaged()` | `FileTrait::fileCreateManagedMultiple()` | The 3.14 name now belongs to another method |
| `FileTrait::fileCreateManagedSingle()` | `FileTrait::fileCreateManaged()` | `EntityStub $stub` is `EntityStubInterface $stub`; now `public` |
| `FileTrait::fileDeleteManagedFiles()` | `FileTrait::fileDeleteManagedMultiple()` | |
| `MediaTrait::mediaCreate()` | `MediaTrait::mediaCreateMultiple()` | The 3.14 name now belongs to another method |
| `MediaTrait::mediaCreateSingle()` | `MediaTrait::mediaCreate()` | `EntityStub $stub` is `EntityStubInterface $stub`; now `public` |
| `MediaTrait::mediaCreateWithFields()` | `MediaTrait::mediaCreateMultipleWithFields()` | `$bundle` is `$media_type` |
| `MediaTrait::mediaDelete()` | `MediaTrait::mediaDeleteMultiple()` | The 3.14 name now belongs to another method |
| `MediaTrait::mediaEditWithName()` | `MediaTrait::mediaVisitEditPageWithName()` | |
| `MediaTrait::mediaExpandEntityFields()` | Removed | Was protected. `mediaCreateEntity()` creates through `ContentCapabilityInterface::createEntity()`, which checks the bundle and expands the fields itself |
| `MediaTrait::mediaRemoveType()` | `MediaTrait::mediaDeleteType()` | `$type` is `$media_type` |
| `MediaTrait::mediaVisitDeleteWithName()` | `MediaTrait::mediaVisitDeletePageWithName()` | |
| `MediaTrait::mediaVisitRevisionsWithName()` | `MediaTrait::mediaVisitRevisionsPageWithName()` | |
| `MediaTrait::mediaVisitViewWithName()` | `MediaTrait::mediaVisitPageWithName()` | |
| `MenuTrait::loadMenuByLabel()` | `MenuTrait::menuFindByLabel()` | Returns `NULL` when nothing matches; now `public` |
| `MenuTrait::loadMenuLinkByTitle()` | `MenuTrait::menuFindLinkByTitle()` | Returns `NULL` when nothing matches; now `public` |
| `MenuTrait::menuCreate()` | `MenuTrait::menuCreateMultiple()` | The 3.14 name now belongs to another method |
| `MenuTrait::menuDeleteSingle()` | `MenuTrait::menuDelete()` | |
| `MenuTrait::menuLinksCreate()` | `MenuTrait::menuCreateLinkMultiple()` | |
| `MenuTrait::menuLinksDelete()` | `MenuTrait::menuDeleteLinkMultiple()` | |
| `ParagraphsTrait::paragraphsExpandEntityFields()` | Removed | Was protected. `paragraphsAttachFromStubToEntity()` creates through `ContentCapabilityInterface::createEntity()`, which expands the fields itself |
| `RedirectTrait::redirectCreate()` | `RedirectTrait::redirectCreateMultiple()` | The 3.14 name now belongs to another method |
| `RedirectTrait::redirectDelete()` | `RedirectTrait::redirectDeleteMultiple()` | The 3.14 name now belongs to another method |
| `SearchApiTrait::searchApiDoIndex()` | `SearchApiTrait::searchApiRunIndexing()` | `string\|int $limit` is `string $count` |
| `StateTrait::stateAssertHasValue()` | `StateTrait::stateAssertValueEquals()` | |
| `StateTrait::stateNormaliseValue()` | `StringTrait::stringNormalizeValue()` | Protected. `ConfigTrait` shares it |
| `StateTrait::stateReadValue()` | `StateTrait::stateExists()` and `StateTrait::stateFindValue()` | Was protected and returned `['exists' => ..., 'value' => ...]`; both are `public`, as the example below shows |
| `StateTrait::stateStringifyValue()` | `StringTrait::stringFormatValue()` | Protected. `ConfigTrait` shares it |
| `TaxonomyTrait::taxonomyAssertTermExistsByName()` | `TaxonomyTrait::taxonomyAssertTermExistsWithName()` | `$term_name, $vocabulary_machine_name` is `$name, $vocabulary` |
| `TaxonomyTrait::taxonomyAssertTermNotExistsByName()` | `TaxonomyTrait::taxonomyAssertTermNotExistsWithName()` | `$term_name, $vocabulary_machine_name` is `$name, $vocabulary` |
| `TaxonomyTrait::taxonomyCreateWithFields()` | `TaxonomyTrait::taxonomyCreateMultipleWithFields()` | |
| `TaxonomyTrait::taxonomyDeleteTerms()` | `TaxonomyTrait::taxonomyDeleteMultiple()` | `$vocabulary_machine_name` is `$vocabulary` |
| `TimeTrait::timeCleanup()` | `TimeTrait::timeAfterScenario()` | Hook |
| `UserTrait::userAssertExistsByMail()` | `UserTrait::userAssertExistsWithMail()` | `$mail` is `$address` |
| `UserTrait::userAssertHasNoRoles()` | `UserTrait::userAssertNotHasRoles()` | |
| `UserTrait::userAssertIsBlocked()` | `UserTrait::userAssertBlocked()` | |
| `UserTrait::userAssertIsNotBlocked()` | `UserTrait::userAssertNotBlocked()` | |
| `UserTrait::userAssertNotExistsByMail()` | `UserTrait::userAssertNotExistsWithMail()` | `$mail` is `$address` |
| `UserTrait::userCreateRoles()` | `UserTrait::userCreateRoleMultiple()` | |
| `UserTrait::userCreateWithFields()` | `UserTrait::userCreateMultipleWithFields()` | |
| `UserTrait::userDelete()` | `UserTrait::userDeleteMultiple()` | The 3.14 name now belongs to another method |
| `UserTrait::userDeleteOwnProfile()` | `UserTrait::userVisitOwnProfileDeletePage()` | |
| `UserTrait::userDeleteProfile()` | `UserTrait::userVisitProfileDeletePage()` | |
| `UserTrait::userEditOwnProfile()` | `UserTrait::userVisitOwnProfileEditPage()` | |
| `UserTrait::userEditProfile()` | `UserTrait::userVisitProfileEditPage()` | |
| `UserTrait::userLoadByName()` | `UserTrait::userGetByName()` | Throws `\RuntimeException` when nothing matches, where it returned `NULL`; now `public` |
| `UserTrait::userVisitOwnProfile()` | `UserTrait::userVisitOwnProfilePage()` | |
| `UserTrait::userVisitProfile()` | `UserTrait::userVisitProfilePage()` | |
| `WatchdogTrait::watchdogAssertNoErrors()` | `WatchdogTrait::watchdogAssertErrorsNotExist()` | Now `public` |
| `WatchdogTrait::watchdogParseMessageTypes()` | Removed | Was protected. `watchdogBeforeScenario()` reads the `@watchdog:TYPE` tags through `Tag::values()` |
| `WatchdogTrait::watchdogSetScenario()` | `WatchdogTrait::watchdogBeforeScenario()` | Hook |
| `WebformTrait::webformLoadAll()` | `WebformTrait::webformLoadMultiple()` | Now `public` |
| `WebformTrait::webformTemplates()` | `WebformTrait::webformLoadTemplateMultiple()` | Returns an empty array when nothing matches; now `public` |

`stateReadValue()` answered 2 questions in 1 array, so read each half from its own helper:

```php
// Before.
$state = $this->stateReadValue('my_module.launched');
if (!$state['exists']) {
  return;
}
$value = $state['value'];

// After.
if (!$this->stateExists('my_module.launched')) {
  return;
}
$value = $this->stateFindValue('my_module.launched');
```

#### HelperTrait methods

`HelperTrait` is 3.14's `DrevOps\BehatSteps\HelperTrait`, and `Drupal\HelperTrait` is `DrevOps\BehatSteps\Drupal\HelperTrait`. Their members moved to traits under `DrevOps\BehatSteps\Steps\Helper\Web` and `DrevOps\BehatSteps\Steps\Helper\Drupal`, each named for 1 concern, and each method carries its own trait's prefix in place of `helper`.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `HelperTrait::helperBuildHorizontalTable()` | `TableTransposeTrait::tableTransposeHorizontal()` | Now `public` |
| `HelperTrait::helperFixStepArgument()` | `StringTrait::stringFixStepArgument()` | |
| `HelperTrait::helperIsJavascriptSupported()` | `WebRawContext::browserDriverHas(JavascriptCapabilityInterface::class)` | See [Capabilities of the browser driver](#capabilities-of-the-browser-driver) |
| `HelperTrait::helperIsLastStep()` | `LastStepTrait::lastStepReached()` | |
| `HelperTrait::helperNormalizeWhitespace()` | `StringTrait::stringNormalizeWhitespace()` | |
| `HelperTrait::helperSetLastStepLine()` | `LastStepTrait::lastStepSetLine()` | |
| `HelperTrait::helperSlug()` | `StringTrait::stringSlug()` | |
| `HelperTrait::helperSplitCommaSeparated()` | `StringTrait::stringSplitCommaSeparated()` | |
| `HelperTrait::helperTransposeVerticalTable()` | `TableTransposeTrait::tableTransposeVertical()` | Now `public` |
| `Drupal\HelperTrait::entityCleanupAfterScenario()` | `EntityLifecycleTrait::entityLifecycleAfterScenario()` | Hook; see [Entity cleanup](#entity-cleanup) |
| `Drupal\HelperTrait::entityCleanupDelete()` | `EntityLifecycleTrait::entityLifecycleDeleteStub()` | Protected. Takes the entity stub where it took the entity type ID and the entity ID |
| `Drupal\HelperTrait::entityCleanupRun()` | `EntityLifecycleTrait::entityLifecycleAfterScenario()` | Was protected; folded into the hook |
| `Drupal\HelperTrait::entityCleanupSkippedTypes()` | `EntityLifecycleTrait::entityLifecycleSkippedCleanupTypes()` | Protected. Takes the `ScenarioScope` where it took the tags |
| `Drupal\HelperTrait::entityRegister()` | `EntityLifecycleTrait::entityLifecycleRegister()` | Takes the stub a create returned and the entity type's id key, as in `entityLifecycleRegister($created, 'mid')`, where it took the entity; now `public` |
| `Drupal\HelperTrait::entityRegisterId()` | `EntityLifecycleTrait::entityLifecycleRegister()` | Takes a stub carrying the ID and the entity type's id key, where it took the entity type and the ID; now `public` |
| `Drupal\HelperTrait::helperAssertModuleEnabled()` | Removed | Declare the module in `<prefix>Prerequisites()` and check it with `assertPrerequisites(__TRAIT__)`; see [Traits of your own](#traits-of-your-own) |
| `Drupal\HelperTrait::helperExpandCompoundCellFixtures()` | `FixtureFileTrait::fixtureFileExpandCompoundCell()` | No `$fixture_path` parameter: it reads the directory through `FixtureDirectoryTrait` |
| `Drupal\HelperTrait::helperExpandEntityFieldsFixtures()` | `FixtureFileTrait::fixtureFileExpandEntityFields()` | Now `public` |
| `Drupal\HelperTrait::helperLooksLikeCompoundCell()` | `FixtureFileTrait::fixtureFileLooksLikeCompoundCell()` | |
| `Drupal\HelperTrait::helperManagedFileExists()` | `FixtureFileTrait::fixtureFileManagedExists()` | |
| `Drupal\HelperTrait::helperResolveFixtureFile()` | `FixtureFileTrait::fixtureFileResolve()` | No `$fixture_path` parameter: it reads the directory through `FixtureDirectoryTrait` |

#### OverrideTrait methods

3.14's `DrevOps\BehatSteps\Drupal\OverrideTrait` overrode 3 of the Drupal Extension's `DrupalContext` methods and bootstrapped Drupal ahead of every `@api` scenario.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `OverrideTrait::createNodes()` | `ContentTrait::contentCreateMultiple()` | Doesn't delete the nodes the table names before creating them |
| `OverrideTrait::createUsers()` | `UserTrait::userCreateMultiple()` | Doesn't delete the users the table names before creating them |
| `OverrideTrait::iAmLoggedInAsUserWithRole()` | `UserTrait::userLoginWithRoles()` | Doesn't turn the `anonymous` and `anonymous user` roles into a logout; `userLogoutSession()` logs out |
| `OverrideTrait::overrideBootstrapDrupal()` | Removed | A step bootstraps Drupal through the backend it resolves |

#### Drupal Extension methods

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `DrupalContext::createAndLoginUserWithRole()` | `UserTrait::userCreateAndLogin()` | |
| `DrupalContext::createEntities()` | `EntityTrait::entityCreateMultiple()` | |
| `DrupalContext::createLanguages()` | `LanguageTrait::languageCreateMultiple()` | |
| `DrupalContext::createNodes()` | `ContentTrait::contentCreateMultiple()` | |
| `DrupalContext::createTerms()` | `TaxonomyTrait::taxonomyCreateMultiple()` | 3.14's `TaxonomyTrait` overrode it |
| `DrupalContext::createUsers()` | `UserTrait::userCreateMultiple()` | |
| `DrupalContext::iAmAnonymous()` | `UserTrait::userLogoutSession()` | |
| `DrupalContext::iAmLoggedInAs()` | `UserTrait::userLoginAs()` | |
| `DrupalContext::iAmLoggedInAsRole()` | `UserTrait::userLoginWithRoles()` | |
| `DrupalContext::iAmLoggedInAsUserWithPermissions()` | `UserTrait::userLoginWithPermissions()` | |
| `DrupalContext::iAmLoggedInAsUserWithRole()` | `UserTrait::userLoginWithRoles()` | |
| `DrupalContext::iAmLoggedInAsUserWithRoleAndFields()` | `UserTrait::userLoginWithRolesAndFields()` | |
| `DrupalContext::iAmNotLoggedIn()` | `UserTrait::userLogoutSession()` | |
| `DrupalContext::iLogOut()` | `UserTrait::userLogout()` | |
| `DrushContext::readDrushOutput()` | `DrushTrait::drushGetOutput()` | Throws `\RuntimeException` when no Drush command has run in the scenario |
| `ParametersTrait::getDrupalSelector()` | `ParametersTrait::getSelector()` | |
| `ParametersTrait::getDrupalText()` | `ParametersTrait::getText()` | |
| `ParametersTrait::getMapping()` | `MappingTrait::mappingGetValue()` | Compose `MappingTrait`, or extend `WebContext` or `DrupalContext`. A context that only uses the `{{ Key }}` token changes nothing |
| `RawDrupalContext::alterNodeParameters()` | Removed | The backend's `TimestampHandler` reads a textual `created`, `changed` or `revision_timestamp` value, so nothing alters the node parameters before creation; see [Fields, files and stored values](#fields-files-and-stored-values) |
| `RawDrupalContext::cleanEntities()` | `EntityLifecycleTrait::entityLifecycleAfterScenario()` | Hook; see [Entity cleanup](#entity-cleanup) |
| `RawDrupalContext::cleanRoles()` | `AuthTrait::authCleanRoles()` | Protected, takes no parameters and isn't a hook, because `authAfterScenario()` is. An override drops its `@AfterScenario` annotation or `#[AfterScenario]` attribute, or it runs twice |
| `RawDrupalContext::cleanUsers()` | `AuthTrait::authCleanUsers()` | Protected, takes no parameters and isn't a hook, because `authAfterScenario()` is. An override drops its `@AfterScenario` annotation or `#[AfterScenario]` attribute, or it runs twice |
| `RawDrupalContext::clearStaticCaches()` | `StaticCacheTrait::staticCacheAfterScenario()` | Hook |
| `RawDrupalContext::entityCreate()` | `EntityLifecycleTrait::entityLifecycleCreate()` | |
| `RawDrupalContext::getAuthenticationManager()` | `AuthTrait::authGetAuthenticator()` | |
| `RawDrupalContext::getContentDriver()` | `WebRawContext::backendFor()` | Names the capability the call needs: `NodeCapabilityInterface` for `createNode()`, `TermCapabilityInterface` for `createTerm()` and `ContentCapabilityInterface` for `createEntity()`; see [Backend contracts](#backend-contracts) |
| `RawDrupalContext::getDriver()` | `WebRawContext::getBackend()` or `WebRawContext::backendFor()` | A call that names a backend moves to `getBackend()`, which has no default. A call with no argument, which returned the driver `@api` selected, moves to `backendFor()` naming the capability the step calls, such as `$this->backendFor(ContentCapabilityInterface::class)` for a step that creates an entity |
| `RawDrupalContext::getFieldParser()` | `Core::createEntityFieldParser()` | Protected, so semantic versioning doesn't cover it. The backend parses the values through `EntityDefinitionCapabilityInterface::parseEntityFields()`, so a context that changes how it parses overrides `EntityLifecycleTrait::entityLifecycleParseFields()`, and a field type that needs its own parsing gets a handler through `drupal.field_handlers` |
| `RawDrupalContext::getUserManager()` | `AuthTrait::authGetUserRegistry()` | |
| `RawDrupalContext::languageCreate()` | `EntityLifecycleTrait::entityLifecycleCreateLanguage()` | Returns the stub where it returned `FALSE` for a language that already exists; only a stub the backend saved joins the teardown |
| `RawDrupalContext::loggedIn()` | `AuthTrait::authIsLoggedIn()` | |
| `RawDrupalContext::login()` | `AuthTrait::authLogin()` | |
| `RawDrupalContext::logout()` | `AuthTrait::authLogout()` | `$fast` is `$is_fast` |
| `RawDrupalContext::nodeCreate()` | `EntityLifecycleTrait::entityLifecycleCreateNode()` | |
| `RawDrupalContext::parseEntityFields()` | `EntityLifecycleTrait::entityLifecycleParseFields()` | |
| `RawDrupalContext::setAuthenticationManager()` | `AuthTrait::authSetAuthenticator()` | |
| `RawDrupalContext::setUserManager()` | `AuthTrait::authSetUserRegistry()` | |
| `RawDrupalContext::termCreate()` | `EntityLifecycleTrait::entityLifecycleCreateTerm()` | |
| `RawDrupalContext::userCreate()` | `AuthTrait::authCreateUser()` | The 3.14 name now belongs to another method |
| `RawMailContext::getMailManager()` | Removed | Call `MailCapabilityInterface` through `backendFor()`; see [Backend contracts](#backend-contracts) |
| `DrupalMailManager::clearMail()` | `MailCapabilityInterface::mailClear()` | |
| `DrupalMailManager::disableMail()` | `MailCapabilityInterface::mailStartCollecting()`, then `MailCapabilityInterface::mailClear()` | |
| `DrupalMailManager::enableMail()` | `MailCapabilityInterface::mailStopCollecting()` | |
| `DrupalMailManager::getMail()` | `MailCapabilityInterface::mailGet()` | |
| `DrupalMailManager::startCollectingMail()` | `MailCapabilityInterface::mailStartCollecting()`, then `MailCapabilityInterface::mailClear()` | |
| `DrupalMailManager::stopCollectingMail()` | `MailCapabilityInterface::mailStopCollecting()` | |
| `DrupalAwareInterface::getAuthenticationManager()` | `UserAwareInterface::authGetAuthenticator()` | |
| `DrupalAwareInterface::getDrupal()` | `BackendAwareInterface::getBackendRegistry()` | |
| `DrupalAwareInterface::setAuthenticationManager()` | `UserAwareInterface::authSetAuthenticator()` | |
| `DrupalAwareInterface::setDispatcher()` | `BackendAwareInterface::setHookDispatcher()` | |
| `DrupalAwareInterface::setDrupal()` | `BackendAwareInterface::setBackendRegistry()` | |
| `DrupalAuthenticationManagerInterface::loggedIn()` | `AuthenticatorInterface::isLoggedIn()` | A custom authenticator that declares `loggedIn()` fails to load until it declares `isLoggedIn()` |
| `DrupalAuthenticationManagerInterface::logIn()` | `AuthenticatorInterface::login()` | Case only |
| `DrupalAuthenticationManagerInterface::logOut()` | `AuthenticatorInterface::logout()` | Case only |
| `DrupalAuthenticationManager::getLogoutElement()` | `Authenticator::getLogoutElement()` | Now `protected`; call `isLoggedIn()`, the question it served |
| `DrupalAuthenticationManager::resolveBasicAuth()` | `BasicAuthenticatorInterface::findCredentials()` | Was protected. Returns the credentials `base_url` carries, so a class implementing the interface adds it |
| `DriverManagerInterface::getDriver()` | `BackendRegistryInterface::getBackend()` | Takes a backend's name; `getBackendFor()` resolves 1 by capability |
| `DriverManagerInterface::getDrivers()` | `BackendRegistryInterface::getBackends()` | |
| `DriverManagerInterface::registerDriver()` | `BackendRegistryInterface::registerBackend()` | |
| `DriverManagerInterface::setDefaultDriverName()` | `BackendRegistryInterface::setScenarioBackends()` | Takes the scenario's ordered list |
| `DriverListener::prepareDefaultDrupalDriver()` | `BackendListener::prepareScenarioBackends()` | |

#### Drupal Driver methods

A backend of your own that implements one of these interfaces renames the methods it implements. The shipped `DrupalBackend`, `DrushBackend` and `Core` use the new names. Every create now returns a stub, and every delete returns `void` and does nothing for a target that doesn't exist, as [Backend contracts](#backend-contracts) explains.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `BlockCapabilityInterface::blockContentCreate()` | `BlockCapabilityInterface::createBlockContent()` | |
| `BlockCapabilityInterface::blockContentDelete()` | `BlockCapabilityInterface::deleteBlockContent()` | |
| `BlockCapabilityInterface::blockDelete()` | `BlockCapabilityInterface::deleteBlock()` | Throws `\RuntimeException` for a stub without an `id`, where it threw `\InvalidArgumentException` |
| `BlockCapabilityInterface::blockPlace()` | `BlockCapabilityInterface::placeBlock()` | Takes the block plugin id and the label, and places the block in the default theme, where it took a stub of the placement's values. Set the region, the status and the label display with `configureBlock()` |
| `ContentCapabilityInterface::entityCreate()` | `ContentCapabilityInterface::createEntity()` | |
| `ContentCapabilityInterface::entityDelete()` | `ContentCapabilityInterface::deleteEntity()` | |
| `ContentCapabilityInterface::nodeCreate()` | `NodeCapabilityInterface::createNode()` | Under `DrevOps\BehatSteps\Backend\Drupal\Capability`; see [Backend contracts](#backend-contracts) |
| `ContentCapabilityInterface::nodeDelete()` | `NodeCapabilityInterface::deleteNode()` | Under `DrevOps\BehatSteps\Backend\Drupal\Capability`. Throws `\RuntimeException` for a stub that's neither saved nor carries a `nid`, where it did nothing |
| `ContentCapabilityInterface::termCreate()` | `TermCapabilityInterface::createTerm()` | Under `DrevOps\BehatSteps\Backend\Drupal\Capability`; see [Backend contracts](#backend-contracts) |
| `ContentCapabilityInterface::termDelete()` | `TermCapabilityInterface::deleteTerm()` | Under `DrevOps\BehatSteps\Backend\Drupal\Capability`. Returns `void` where it returned `bool`. Throws `\RuntimeException` for a stub that's neither saved nor carries a `tid`, where it did nothing |
| `LanguageCapabilityInterface::languageCreate()` | `LanguageCapabilityInterface::createLanguage()` | Returns the stub, left unsaved for a language that already exists, where it returned `FALSE` for one |
| `LanguageCapabilityInterface::languageDelete()` | `LanguageCapabilityInterface::deleteLanguage()` | Does nothing for a language that doesn't exist, where it threw |
| `RoleCapabilityInterface::roleCreate()` | `RoleCapabilityInterface::createRole()` | Returns a `user_role` stub carrying `id` and `label` where it returned the role's machine name. Takes permission machine names, because `drush role:perm:add` rejects labels; the in-process backend still converts a label |
| `RoleCapabilityInterface::roleDelete()` | `RoleCapabilityInterface::deleteRole()` | The Drush backend no longer fails for a role that's already gone |
| `UserCapabilityInterface::userAddRole()` | `UserCapabilityInterface::addUserRole()` | The Drush backend resolves a role label as well, through `drush role:list` |
| `UserCapabilityInterface::userCreate()` | `UserCapabilityInterface::createUser()` | Returns the stub where it returned `void` |
| `UserCapabilityInterface::userDelete()` | `UserCapabilityInterface::deleteUser()` | No longer fails for a user that's already gone. The in-process backend throws `\RuntimeException` for a stub that's neither saved nor carries a `uid`, where it passed an empty ID to `user_cancel()` |
| `DrupalDriverInterface::getCore()` | `CoreCapabilityInterface::getCore()` | |
| `DrupalDriverInterface::getDrupalVersion()` | `CoreCapabilityInterface::getDrupalVersion()` | |
| `DrupalDriver::getSubDriverPaths()` | Removed | See [Backend contracts](#backend-contracts) |
| `DrupalDriver::setCoreFromVersion()` | Removed | `DrupalBackend` builds its Core when it's constructed: the class the `drupal.core` option names, or the package's Core for the Drupal major it detects |
| `Exception::getDriver()` | `Exception::getBackend()` | On `DrevOps\BehatSteps\Backend\Exception\Exception` |
| `AbstractHandler::normalise()` | `AbstractHandler::normalize()` | A field handler of your own that overrode it renames the override. It's protected, so semantic versioning doesn't cover it |

### Names that now belong to another method

12 names a 3.14 project used now belong to a different method. 10 of them were steps over a table, which took a `Multiple` name, and the name each one freed went to a helper that acts on 1 entity. This table isn't a mapping: the middle column is the unrelated method a call left on the 3.14 name reaches, and the tables above give each method's real 4.0 name.

| 3.14 method | 4.0 method with the same name | A call left on the 3.14 name |
| --- | --- | --- |
| `ContentTrait::contentDelete(string $content_type, TableNode $table)` | `ContentTrait::contentDelete(string $content_type, array $conditions)` | Fails with a `TypeError` |
| `ContentBlockTrait::contentBlockCreate(string $type, TableNode $content_block_table)` | `ContentBlockTrait::contentBlockCreate(string $content_block_type, array $values)` | Fails with a `TypeError` |
| `ContentBlockTrait::contentBlockDelete(string $type, TableNode $content_block_table)` | `ContentBlockTrait::contentBlockDelete(string $content_block_type, array $conditions)` | Fails with a `TypeError` |
| `FileTrait::fileCreateManaged(TableNode $table)` | `FileTrait::fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL)` | Fails with an `ArgumentCountError` |
| `MediaTrait::mediaCreate(string $media_type, TableNode $table)` | `MediaTrait::mediaCreate(EntityStubInterface $stub)` | Fails with a `TypeError` |
| `MediaTrait::mediaDelete(string $media_type, TableNode $table)` | `MediaTrait::mediaDelete(string $media_type, array $conditions)` | Fails with a `TypeError` |
| `MenuTrait::menuCreate(TableNode $table)` | `MenuTrait::menuCreate(array $values)` | Fails with a `TypeError` |
| `RedirectTrait::redirectCreate(TableNode $table)` | `RedirectTrait::redirectCreate(string $from, string $to, int $status_code = 301)` | Fails with an `ArgumentCountError` |
| `RedirectTrait::redirectDelete(TableNode $table)` | `RedirectTrait::redirectDelete(string $from)` | Runs without an error and deletes nothing |
| `UserTrait::userDelete(TableNode $table)` | `UserTrait::userDelete(array $values)` | Fails with a `TypeError` |
| `ElementTrait::elementAssertPinnedToTop(string $selector, int $tolerance, bool $is_inverted)` | `ElementTrait::elementAssertPinnedToTop(string $selector)` | Runs without an error: PHP drops the 2 extra arguments, so it asserts that the element is pinned within 2 pixels, whatever tolerance and direction it passed |
| `RawDrupalContext::userCreate(EntityStubInterface $stub)` | `UserTrait::userCreate(array $values)`, on the shipped `DrupalContext` | Fails with a `TypeError` |

`TableNode` implements `Stringable`, so where the 4.0 method takes a `string` first, PHP's default coercive mode turns the table into its text instead of rejecting it. `fileCreateManaged()` and `redirectCreate()` then fail on the argument that's missing, and `redirectDelete()` looks for a redirect whose source is the table's text, finds none, and returns. In a file that declares `strict_types=1`, all 3 fail with a `TypeError` instead. 3.14's `OverrideTrait` called `contentDelete()` and `userDelete()` with a table, so a context that copied its overrides carries 2 of these calls.

An override that keeps a 3.14 signature fails too. On a context that extends a shipped one, PHP rejects the declaration as incompatible with the 4.0 method when the class loads. On a context that composes the trait itself, an override of one of these names replaces the 4.0 helper, so the `Multiple` step that calls the helper fails with a `TypeError`. Search a project for each name above before upgrading.

### Methods that return entity stubs

A helper that returned a Drupal entity or a set of entity IDs returns an `EntityStubInterface` now, and a helper that took an entity takes its stub, so a step of your own never holds a Drupal object. A `LoadMultiple()` returns a stub per entity keyed by entity ID, or an empty array when nothing matches. A stub isn't a loaded entity: it carries the entity type, the bundle when the helper knows it, and the entity ID under the entity type's id key, such as `nid` for a node or `id` for a block. `contentLoadMultiple()` is `QueryTrait::queryNodeIds()` now, and returns the IDs it did.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `BlockTrait::blockLoadByLabel()` | `BlockTrait::blockFindByLabel()` | Returned `?Block` |
| `ContentBlockTrait::contentBlockCreateSingle()` | `ContentBlockTrait::contentBlockCreate()` | Returned `BlockContent` |
| `ContentBlockTrait::contentBlockLoadMultiple()` | `ContentBlockTrait::contentBlockLoadMultiple()` | Returned block content IDs |
| `ContentTrait::contentLoadNodeByTitle()` | `ContentTrait::contentGetNodeByTitle()` | Returned `NodeInterface`. `contentGetNidByTitle()` still returns the node ID |
| `DraggableviewsTrait::draggableViewsFindNode()` | `DraggableviewsTrait::draggableviewsFindNode()` | Returned `?NodeInterface` |
| `EckTrait::eckLoadMultiple()` | `EckTrait::eckLoadMultiple()` | Returned entity IDs |
| `FileTrait::fileCreateEntity()` | `FileTrait::fileCreateEntity()` | Returned `FileInterface`. The values of the stub it takes go through the field handlers, as on any other create |
| `FileTrait::fileCreateManagedSingle()` | `FileTrait::fileCreateManaged()` | Returned `FileInterface` |
| `FileTrait::fileLoadMultiple()` | `FileTrait::fileLoadMultiple()` | Returned file IDs |
| `MediaTrait::mediaCreateEntity()` | `MediaTrait::mediaCreateEntity()` | Returned `MediaInterface` |
| `MediaTrait::mediaCreateSingle()` | `MediaTrait::mediaCreate()` | Returned `MediaInterface` |
| `MediaTrait::mediaLoadMultiple()` | `MediaTrait::mediaLoadMultiple()` | Returned media IDs |
| `MenuTrait::loadMenuByLabel()` | `MenuTrait::menuFindByLabel()` | Returned `?MenuInterface` |
| `MenuTrait::loadMenuLinkByTitle()` | `MenuTrait::menuFindLinkByTitle()` | Returned `?MenuLinkContent` |
| `ParagraphsTrait::paragraphsAttachFromStubToEntity()` | `ParagraphsTrait::paragraphsAttachFromStubToEntity()` | Took `ContentEntityInterface` and returned `ParagraphInterface`; see [Signature changes](#signature-changes) |
| `ParagraphsTrait::paragraphsFindEntity()` | `ParagraphsTrait::paragraphsFindEntity()` | Returned `?ContentEntityInterface` |
| `TaxonomyTrait::taxonomyLoadMultiple()` | `TaxonomyTrait::taxonomyLoadMultiple()` | Returned term IDs |
| `UserTrait::userLoadByName()` | `UserTrait::userGetByName()` | Returned `?UserInterface` |
| `UserTrait::userLoadMultiple()` | `UserTrait::userLoadMultiple()` | Returned `UserInterface` entities |
| `UserTrait::userVisitPasswordResetLinkForUser()` | `UserTrait::userVisitPasswordResetLinkForUser()` | Took `UserInterface` |
| `WebformTrait::webformLoadAll()` | `WebformTrait::webformLoadMultiple()` | Returned `WebformInterface` entities |
| `WebformTrait::webformTemplates()` | `WebformTrait::webformLoadTemplateMultiple()` | Returned `WebformInterface` entities |

The native return type of a `LoadMultiple()` is still `array`, so the call itself doesn't fail. Code that treats a value as an ID or an entity fails where it uses it instead, for example with `Object of class ... could not be converted to string` when it builds a path, or `Call to undefined method ...::getRoles()`. Read the ID from the stub, a field through `ContentCapabilityInterface::getEntityValues()` and a config entity's property through `ConfigCapabilityInterface::configGet()`, and change the entity through `updateEntity()` or `ConfigEntityCapabilityInterface::updateConfigEntity()`, as [Backend contracts](#backend-contracts) describes. A user's roles and password have methods of their own on `UserCapabilityInterface`, which the Drush backend serves as well. When only the IDs are needed, call `QueryTrait::queryEntityIds()`, which every one of these traits composes.

```php
// Before.
$user = $this->userLoadByName('jane');
$roles = $user->getRoles(TRUE);
$user->setPassword('secret')->save();

// After.
$user = $this->userGetByName('jane');
$backend = $this->backendFor(UserCapabilityInterface::class);
$roles = array_diff($backend->getUserRoles($user), ['authenticated']);
$backend->setUserPassword($user, 'secret');

// After, when only the IDs are needed.
$ids = $this->queryEntityIds('media', ['name' => 'Logo'], 'image');
```

The keys changed as well. An entity query keys a revisionable entity type by revision ID, so media, terms and content blocks came back keyed by revision ID. Every set is keyed by entity ID now.

### Signature changes

These methods kept their names. A renamed parameter only matters to a call that passes it by name; a changed type, a new parameter or a removed one matters to every call and override. An override in your `FeatureContext` takes the new signature, or PHP reports it as incompatible with the trait's. Throughout the backend layer, a parameter named `$driver` that held a Drupal Driver driver is `$backend` now.

| Method | 3.14 | 4.0 |
| --- | --- | --- |
| `AccessibilityTrait::accessibilityAggregateCapture()` | `string $dir` | `string $directory` |
| `AccessibilityTrait::accessibilityAggregateRollup()` | `array $pages` | `array $pages, array $impacts = []` |
| `AccessibilityTrait::accessibilityResolveTags()` | `array $tags` | `BeforeScenarioScope $scope` |
| `CommandTrait::commandAfterScenario()` | No parameters | `AfterScenarioScope $scope` |
| `CommandTrait::commandAssertErrorOutputContains()` | `string $text` | `string $value` |
| `CommandTrait::commandAssertOutputContains()` | `string $text` | `string $value` |
| `CommandTrait::commandAssertOutputEquals()` | `string $text` | `string $value` |
| `CommandTrait::commandAssertOutputNotContains()` | `string $text` | `string $value` |
| `CommandTrait::commandBeforeScenario()` | No parameters | `BeforeScenarioScope $scope` |
| `ContentBlockTrait::contentBlockAssertTypeExists()` | `string $type` | `string $content_block_type` |
| `ContentBlockTrait::contentBlockLoadMultiple()` | `string $type, array $conditions = []` | `string $content_block_type, array $conditions = []` |
| `ContentTrait::contentVisitActionPageWithTitle()` | `string $action_subpath = ''` | `string $rel = 'canonical'`, the link relationship of the page: `edit-form` where 3.14 took `/edit`, `delete-form` for `/delete`, `version-history` for `/revisions` and `scheduled_transitions` for `/scheduled-transitions` |
| `DropzoneTrait::dropzoneDropFile()` | `string $path, string $selector` | `string $filename, string $selector` |
| `ElementTrait::elementAssertAttributeWithValue()` | `mixed $value` | `string $value` |
| `ElementTrait::elementAssertChildElementCount()` | `int $count` | `string $count` |
| `EmailTrait::emailAssertMessageContainsAttachmentWithSubjectContaining()` | `string $file_name, string $subject` | `string $filename, string $partial_subject` |
| `EmailTrait::emailAssertMessageFieldContains()` | `bool $exact = FALSE` | No `$exact`; for `TRUE`, call `emailAssertMessageFieldEquals()` |
| `EmailTrait::emailAssertMessageFieldNotContains()` | `bool $exact = FALSE` | No `$exact`; for `TRUE`, call `emailAssertMessageFieldNotEquals()` |
| `EmailTrait::emailAssertMessageHeaderContains()` | `bool $exact = FALSE` | No `$exact`; for `TRUE`, call `emailAssertMessageHeaderEquals()` |
| `EmailTrait::emailClearTestQueue()` | `bool $force = FALSE` | No `$force`; `emailClearCollectedMessages()` clears the collected messages without the check |
| `EmailTrait::emailFindMessage()` | `bool $exact = FALSE` | `bool $is_exact = FALSE` |
| `FieldTrait::fieldAssertExists()` | `string $name`, returns `NodeElement` | `string $field`, returns nothing; `fieldGet()` returns the field, as the example below shows |
| `FieldTrait::fieldAssertNotExists()` | `string $name` | `string $field` |
| `FieldTrait::fieldFillColor()` | `?string $value = NULL`, returns `mixed` | `string $value`, returns nothing |
| `FileDownloadTrait::fileDownloadAssertFileNameContains()` | `string $file_name_part` | `string $partial_name` |
| `FileDownloadTrait::fileDownloadProcess()` | `CURLOPT_*` options | Symfony HttpClient options; see [HTTP clients](#http-clients) |
| `FileTrait::fileCreateEntity()` | `EntityStub $stub` | `EntityStubInterface $stub` |
| `FileTrait::fileCreateUnmanaged()` | `string $content = 'test'` | No `$content`; call `fileCreateUnmanagedWithContent()` |
| `IframeTrait::iframeSwitchTo()` | `string $locator` | `string $selector` |
| `JsonTrait::jsonAfterScenario()` | No parameters | `AfterScenarioScope $scope` |
| `JsonTrait::jsonBeforeScenario()` | No parameters | `BeforeScenarioScope $scope` |
| `KeyboardTrait::keyboardPressKeyOnElement()` | `?string $selector` | `string $selector`; for the focused element, call `keyboardPressKey()` |
| `KeyboardTrait::keyboardPressKeyOnElementSingle()` | `?string $selector` | `?string $selector = NULL` |
| `KeyboardTrait::keyboardPressKeysOnElement()` | `?string $selector` | `string $selector`; for the focused element, call `keyboardPressKeys()` |
| `MediaTrait::mediaCreateEntity()` | `EntityStub $stub` | `EntityStubInterface $stub` |
| `MediaTrait::mediaExpandEntityFieldsFixtures()` | `EntityStub $stub` | `EntityStubInterface $stub` |
| `MediaTrait::mediaLoadMultiple()` | `string $type, array $conditions = []` | `string $media_type, array $conditions = []` |
| `MediaTrait::mediaVisitActionPageWithName()` | `string $action_subpath = ''` | `string $rel = 'canonical'`, the link relationship of the page: `edit-form` where 3.14 took `/edit`, `delete-form` for `/delete` and `version-history` for `/revisions` |
| `MetatagTrait::metatagFindMeta()` | `string $name` | `string $meta_name` |
| `ParagraphsTrait::paragraphsAttachFromStubToEntity()` | `ContentEntityInterface $parent_entity, string $parent_field_name, string $paragraph_bundle, EntityStub $stub, bool $save_entity = TRUE`, returns `ParagraphInterface` | `EntityStubInterface $parent_entity, string $parent_field, string $paragraph_type, EntityStubInterface $stub`, returns `EntityStubInterface`. No `$save_entity`: the parent is saved every time |
| `PathTrait::pathAssertNotCurrent()` | Returns `bool` | Returns nothing |
| `QueueTrait::queueAssertItemCount()` | `int $count` | `string $count` |
| `QueueTrait::queueProcessItems()` | `int $count` | `string $count` |
| `QueueTrait::queueTrackName()` | `string $queue_name` | `string $queue` |
| `ResponseTrait::responseAssertHeaderContains()` | `string $header_name, string $header_value` | `string $name, string $value` |
| `ResponseTrait::responseAssertHeaderNotContains()` | `string $header_name, string $header_value` | `string $name, string $value` |
| `ResponsiveTrait::responsiveExtractDimensions()` | `?string $name = NULL` | `?string $breakpoint = NULL` |
| `ResponsiveTrait::responsiveGetBreakpoint()` | `string $name` | `string $breakpoint` |
| `RestTrait::restAssertResponseContains()` | `string $text` | `string $value` |
| `SearchApiTrait::searchApiIndexContent()` | `string $type` | `string $content_type` |
| `TableTrait::tableAssertColumnCount()` | `int $count` | `string $count` |
| `TableTrait::tableAssertRowCount()` | `int $count` | `string $count` |
| `TableTrait::tableFindRowByText()` | `string $text` | `string $row_text` |
| `TaxonomyTrait::taxonomyAssertVocabularyExists()` | `string $machine_name` | `string $vocabulary` |
| `TaxonomyTrait::taxonomyAssertVocabularyNotExists()` | `string $machine_name` | `string $vocabulary` |
| `TaxonomyTrait::taxonomyLoadMultiple()` | `string $vocabulary_machine_name` | `string $vocabulary` |
| `TaxonomyTrait::taxonomyVisitActionPageWithName()` | `string $vocabulary_machine_name, string $term_name, string $action_subpath = ''` | `string $vocabulary, string $name, string $rel = 'canonical'`, the link relationship of the page: `edit-form` where 3.14 took `/edit` and `delete-form` for `/delete` |
| `TaxonomyTrait::taxonomyVisitTermDeletePageWithName()` | `string $vocabulary_machine_name, string $term_name` | `string $vocabulary, string $name` |
| `TaxonomyTrait::taxonomyVisitTermEditPageWithName()` | `string $vocabulary_machine_name, string $term_name` | `string $vocabulary, string $name` |
| `TaxonomyTrait::taxonomyVisitTermPageWithName()` | `string $vocabulary_machine_name, string $term_name` | `string $vocabulary, string $name` |
| `TestmodeTrait::testmodeDisableTestMode()` | `static` | An instance method, called as `$this->testmodeDisableTestMode()` |
| `TestmodeTrait::testmodeEnableTestMode()` | `static` | An instance method, called as `$this->testmodeEnableTestMode()` |
| `UserTrait::userCreateRole()` | `string $role_name` | `string $role` |
| `UserTrait::userCreateRoleWithPermissions()` | Returns `RoleInterface` | Returns `EntityStubInterface`, the `user_role` stub `RoleCapabilityInterface::createRole()` returns; its machine name is `getValue('id')`. A permission the site doesn't define fails the step, where it was granted as written |
| `UserTrait::userVisitActionPage()` | `string $action_subpath = ''` | `string $rel = 'canonical'`, the link relationship of the page: `edit-form` where 3.14 took `/edit` and `cancel-form` for `/cancel` |
| `XmlTrait::xmlAfterScenario()` | No parameters | `AfterScenarioScope $scope` |
| `XmlTrait::xmlAssertAttributeContains()` | `string $attribute_name, string $element, string $text` | `string $attribute, string $element, string $value` |
| `XmlTrait::xmlAssertAttributeEquals()` | `string $text` | `string $value` |
| `XmlTrait::xmlAssertAttributeNotContains()` | `string $attribute_name, string $element, string $text` | `string $attribute, string $element, string $value` |
| `XmlTrait::xmlAssertAttributeNotEquals()` | `string $text` | `string $value` |
| `XmlTrait::xmlAssertElementContains()` | `string $text` | `string $value` |
| `XmlTrait::xmlAssertElementEquals()` | `string $text` | `string $value` |
| `XmlTrait::xmlAssertElementNotContains()` | `string $text` | `string $value` |
| `XmlTrait::xmlAssertElementNotEquals()` | `string $text` | `string $value` |
| `XmlTrait::xmlBeforeScenario()` | No parameters | `BeforeScenarioScope $scope` |
| `WebRawContext::getRandom()` | Returns `Drupal\Component\Utility\Random` | Returns `RandomGeneratorInterface`; see [Backend contracts](#backend-contracts) |
| `BackendInterface::getRandom()` | Returns `Drupal\Component\Utility\Random` | Returns `RandomGeneratorInterface`; see [Backend contracts](#backend-contracts) |
| `BlackboxBackend::__construct()` | `?Random $random = NULL` | `?RandomGeneratorInterface $random = NULL` |
| `CacheCapabilityInterface::cacheClear()` | A cache bin | No parameters; see [Backend contracts](#backend-contracts) |
| `ConfigCapabilityInterface::configGet()` | `string $key = ''` | `?string $key = NULL` |
| `ConfigCapabilityInterface::configGetOriginal()` | `string $key = ''` | `?string $key = NULL` |
| `Core::__construct()` | `?Random $random = NULL` | `?RandomGeneratorInterface $random = NULL` |
| `DrushBackend::__construct()` | An empty string for an unset alias or root; `?Random $random = NULL` | `NULL` for an unset alias or root; an empty string throws `BootstrapException`. `?RandomGeneratorInterface $random = NULL` |
| `MailCapabilityInterface::mailStartCollecting()` | No parameters | `array $handler_types = ['default']`; see [Backend contracts](#backend-contracts) |
| `RolesAlias::__construct()` | `$driver` | `$userCapability` |
| `UnsupportedBackendActionException::__construct()` | `driver:` named argument | `backend:` named argument |

A call that still passes a removed argument doesn't fail, because PHP drops an extra argument without a word: `fileCreateUnmanaged($uri, 'Hello')` writes `test`, `emailAssertMessageFieldContains($field, $string, TRUE)` compares with whitespace collapsed, `elementAssertNotVisuallyVisible($selector, 10)` checks an offset of 0, and `paragraphsAttachFromStubToEntity($parent, $field, $type, $stub, FALSE)` saves the parent anyway. Move each one to the method the table names. PHPStan reports every such call as a method invoked with more parameters than it takes.

```php
// Before.
$field = $this->fieldAssertExists('Title');

// After.
$field = $this->fieldGet('Title');
```

### Visibility changes

PHP refuses to narrow an inherited method, so an override of a method that's public in 4.0 is declared `public` as well, or the class doesn't load:

```
Fatal error: Access level to FeatureContext::bigPipeGetWaitTimeout() must be public (as in class ...)
```

Change the keyword on the override and leave its body as it is. [HELPERS.md](HELPERS.md) lists every public helper. These methods kept their names and are `public` now; the renamed ones are in the tables above.

| Trait | Methods now `public` |
| --- | --- |
| `AccessibilityTrait` | `accessibilityAssess()`, `accessibilityFetchJs()`, `accessibilityFormatUrl()`, `accessibilityGetAutoTag()`, `accessibilityGetCdnUrl()`, `accessibilityGetDefaultRules()`, `accessibilityGetFailOnIncomplete()`, `accessibilityGetFailureThreshold()`, `accessibilityGetFetchAttempts()`, `accessibilityGetFetchTimeout()`, `accessibilityGetImpacts()`, `accessibilityGetJs()`, `accessibilityGetPrintCli()`, `accessibilityGetReportDir()`, `accessibilityNormalizeResults()`, `accessibilityRenderAggregate()`, `accessibilityRenderHtmlPage()`, `accessibilityRenderHtmlSections()`, `accessibilityRunEngine()` |
| `BigPipeTrait` | `bigPipeGetWaitTimeout()`, `bigPipeWaitForPlaceholders()` |
| `CacheTrait` | `cacheGetPageCacheBin()` |
| `ContentBlockTrait` | `contentBlockLoadMultiple()` |
| `ContentTrait` | `contentVisitActionPageWithTitle()` |
| `CookieTrait` | `cookieGetAll()` |
| `DateTrait` | `dateRelativeStringHasToken()` |
| `DiagnosticsTrait` | `diagnosticsGetJsErrors()` |
| `EckTrait` | `eckLoadMultiple()` |
| `ElementTrait` | `elementExecuteJs()`, `elementIsVisuallyVisible()` |
| `EmailTrait` | `emailExtractLinks()`, `emailFindMessage()`, `emailGetCollectedMessages()` |
| `FieldTrait` | `fieldDisableFormValidation()`, `fieldGetAddMoreButtonSelectors()`, `fieldGetRequiredMarkerSelectors()`, `fieldIsMarkedRequired()` |
| `FileDownloadTrait` | `fileDownloadGetTempDir()`, `fileDownloadOpenZip()`, `fileDownloadProcess()` |
| `FileTrait` | `fileCreateEntity()`, `fileLoadMultiple()` |
| `JsonTrait` | `jsonDecode()`, `jsonDecodeLoose()`, `jsonQuery()` |
| `MediaTrait` | `mediaCreateEntity()`, `mediaLoadMultiple()`, `mediaVisitActionPageWithName()` |
| `MetatagTrait` | `metatagFindMeta()`, `metatagGetHreflangAlternates()`, `metatagGetRobotsDirectives()`, `metatagIsIndexable()`, `metatagIsValidHreflang()`, `metatagResponseHasNoindexHeader()` |
| `ModalTrait` | `modalFind()`, `modalGetCloseSelectors()`, `modalGetContentSelectors()`, `modalGetSelectors()`, `modalGetWaitTimeout()` |
| `ModuleTrait` | `moduleDisable()`, `moduleEnable()`, `moduleIsEnabled()`, `moduleIsPresent()` |
| `ParagraphsTrait` | `paragraphsAttachFromStubToEntity()`, `paragraphsFindEntity()` |
| `PathTrait` | `pathGetCurrentUrlQuery()` |
| `QueueTrait` | `queueGetLeaseTime()`, `queueGetProcessLimit()` |
| `ResponsiveTrait` | `responsiveGetAllBreakpoints()`, `responsiveGetBreakpoint()`, `responsiveGetCurrentDimensions()`, `responsiveResize()`, `responsiveResizeToBreakpoint()` |
| `RestTrait` | `restGetClient()`, `restResolveUrl()` |
| `TableTrait` | `tableFindRowByText()`, `tableGetBodyRowSelector()`, `tableGetColumnIndex()`, `tableGetHeaderSelector()`, `tableGetHeaders()`, `tableGetRows()` |
| `TaxonomyTrait` | `taxonomyLoadMultiple()`, `taxonomyVisitActionPageWithName()` |
| `TestmodeTrait` | `testmodeDisableTestMode()`, `testmodeEnableTestMode()` |
| `UserTrait` | `userExistsByMail()`, `userLoadMultiple()`, `userVisitActionPage()`, `userVisitPasswordResetLinkForUser()` |

A call from outside the context object to a method that's `protected` now no longer works:

| Trait | Methods now `protected` | Notes |
| --- | --- | --- |
| `AccessibilityTrait` | `accessibilityAggregateReset()`, `accessibilityCaptureBaseDir()` | No longer hooks. `accessibilityBeforeSuite()` is the hook, and it runs `accessibilityCaptureBaseDir()` and then `accessibilityAggregateReset()`, so an override drops the `#[BeforeSuite]` attribute it copied from 3.14, or it runs twice |
| `KeyboardTrait` | `keyboardPressKeyOnElementSingle()` | |

### Override points

An override point is a method a trait calls for a value, such as `dateGetNow()` or `elementGetScrollIntoViewCenter()`, and the tables above mark the renamed ones. An override left under its 3.14 name is never called, and nothing reports it: a `dateNow()` that pinned the clock stops pinning it, and a `FeatureContext` that switched centered scrolling off the way the 3.14 `elementScrollIntoViewCenter()` docblock showed scrolls to the center again. Rename each override and declare it `public`.

## Properties and constants

5 properties that relied on a `@var` docblock declare a native type now, which narrows what a subclass may assign to them.

| 3.14 | 4.0 | Notes |
| --- | --- | --- |
| `AccessibilityTrait::IMPACT_CRITICAL` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_CRITICAL` | |
| `AccessibilityTrait::IMPACT_MINOR` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MINOR` | |
| `AccessibilityTrait::IMPACT_MODERATE` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MODERATE` | |
| `AccessibilityTrait::IMPACT_SERIOUS` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_SERIOUS` | |
| `BigPipeTrait::DEFAULT_WAIT_TIMEOUT` | Removed | Set the `big_pipe.wait_timeout` option, as in `'steps' => ['big_pipe' => ['wait_timeout' => 10000]]` |
| `BigPipeTrait::$bigPipeWaitTimeout` | `BigPipeTrait::$bigPipeWaitTimeout` | `?int`, defaulting to `NULL`, which means "take the configured option". Assigning it still overrides the wait for 1 scenario |
| `ConfigOverrideTrait::$configOverrideSkipBeforeStep` | Removed | A skipped scenario leaves `$configOverrideDisabledNames` empty instead |
| `ConfigTrait::$configOriginalData` | `ConfigTrait::$configOriginalData` | Each entry carries `exists` and `value` where it carried `existed` and `data`, matching `StateTrait::$stateOriginalValues` |
| `Drupal\HelperTrait::ENTITY_CLEANUP_EXCLUDED_TYPES` | Removed | The entity teardown has no exclusion list; see [Entity cleanup](#entity-cleanup) |
| `Drupal\HelperTrait::$entityRegistry` | `EntityLifecycleTrait::$entityLifecycleCreatedStubs` | Holds entity stubs |
| `FileDownloadTrait::$fileDownloadDownloadedFileInfo` | `FileDownloadTrait::$fileDownloadDownloadedFileInfo` | Typed `array` |
| `FileTrait::$filesUnmanagedUris` | `FileTrait::$fileUnmanagedUris` | Typed `array` |
| `HelperTrait::$helperLastStepLine` | `LastStepTrait::$lastStepLine` | |
| `JavascriptTrait::$javascriptBypassErrors` | Removed | `javascriptGetFailOnErrors()` returns `FALSE` where the property was `TRUE` |
| `JavascriptTrait::$javascriptErrorRegistry` | `JavascriptErrorTrait::$javascriptErrorRegistry` | |
| `RawDrupalContext::$createdStubs` | `EntityLifecycleTrait::$entityLifecycleCreatedStubs` | Holds entity stubs |
| `RawDrupalContext::$roles` | `AuthTrait::$authRoles` | Records the roles a scenario creates, which `authCleanRoles()` deletes |
| `RedirectTrait::$redirectAllowedStatusCodes` | `RedirectTrait::$redirectAllowedStatusCodes` | Typed `array` |
| `RestTrait::$restHeaders` | `RequestHeadersTrait::$requestHeaders` | Read and written through `requestHeadersSet()`, `requestHeadersUnset()`, `requestHeadersAll()` and `requestHeadersReset()` |
| `WatchdogTrait::$watchdogMessageTypes` | `WatchdogTrait::$watchdogMessageTypes` | Typed `array` |
| `WatchdogTrait::$watchdogScenarioStartTime` | `WatchdogTrait::$watchdogLastId` | Holds the id of the newest log entry when the scenario started, typed `?int` |
| `DriverManager::$drivers` | `BackendRegistry::$backends` | |

Every constant this package declares has a native type, as `public const string CONFIG_KEY = 'behat_steps';` does. PHP holds a redeclared constant to the type it inherits, so a constant of your own that shares its name with one your context now inherits, such as a `BATCH_WAIT_TIMEOUT` in a `FeatureContext` that extends `DrupalContext`, declares the same type:

```
PHP Fatal error:  Type of FeatureContext::BATCH_WAIT_TIMEOUT must be compatible with DrupalContext::BATCH_WAIT_TIMEOUT of type int
```

## Hooks

Each hook method is named for its event and declares its scope parameter, used or not. [Methods](#methods) lists the renamed hooks and [Signature changes](#signature-changes) the ones that gained the parameter. The entity creation hooks, such as `BeforeNodeCreate` and `AfterTermCreate`, keep their names.

The entity creation hook attributes, such as `#[BeforeNodeCreate]` and `#[AfterEntityCreate]`, accepted an argument, and a hook declared with one never ran: it matched no entity at all. The attributes take no argument now, and a hook declared with one fails the run while Behat reads the context:

```
The "#[BeforeNodeCreate]" attribute on "FeatureContext::alterArticle()" takes no argument. The hook runs for every entity created in its scope, so read the entity from "$scope->getStub()" and return early for one it does not handle.
```

A hook meant for some entities only checks the stub itself:

```php
// Before.
#[BeforeNodeCreate('article')]
public function alterArticle(BeforeNodeCreateScope $scope): void {
  // ...
}

// After.
#[BeforeNodeCreate]
public function alterArticle(BeforeNodeCreateScope $scope): void {
  if ($scope->getStub()->getBundle() !== 'article') {
    return;
  }

  // ...
}
```

Once the argument is gone, the hook runs for the first time, so check that its body still does what it was written to do.

`EntityHook` extends `RuntimeHook` rather than `RuntimeFilterableHook`, so a hook call carries no filter string, and each call under `DrevOps\BehatSteps\Extension\Hook\Call` takes the callable as its first constructor argument:

| 3.14 | 4.0 |
| --- | --- |
| `new BeforeNodeCreate(NULL, $callable)` | `new BeforeNodeCreate($callable)` |
| `new BeforeNodeCreate(NULL, $callable, $description)` | `new BeforeNodeCreate($callable, $description)` |

## Traits of your own

A trait of your own that guarded a hook on a skip tag, as the 3.14 traits did, calls `skipTag()` with `__TRAIT__`, which resolves to the trait the code is written in. `skipTag()` is on `WebRawContext`, so the context that composes the trait extends it.

```php
// Before.
if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
  return;
}

// After.
if ($this->skipTag(__TRAIT__, $scope)) {
  return;
}
```

The skip tags in your feature files then name the trait instead of the hook method, as `@behat-steps-skip:AcmeTrait` does. A tag that still names a hook method fails the run, as [Skip tags](#skip-tags) shows.

A trait states what it needs from the site in a `<prefix>Prerequisites()` method, named like its `<prefix>ConfigSchema()`, and each prerequisite goes through a backend capability rather than a query of its own. A step checks them with `$this->assertPrerequisites(__TRAIT__)`, a setup hook with `$this->assertPrerequisites(__TRAIT__, $scope)`, and a teardown asks `$this->prerequisitesMet(__TRAIT__)` instead, so it never replaces a failure the scenario already recorded.

```php
protected function acmePrerequisites(): array {
  return [
    Prerequisite::capability(ContentCapabilityInterface::class),
    Prerequisite::check(
      static fn(ModuleCapabilityInterface $backend): bool
        => $backend->moduleIsEnabled('acme'),
      'the "acme" module from the "drupal/acme" package is enabled',
    ),
  ];
}
```

| 3.14 | 4.0 |
| --- | --- |
| `$this->helperAssertModuleEnabled('acme', 'drupal/acme')` in a step | Declare the module in `<prefix>Prerequisites()` and call `$this->assertPrerequisites(__TRAIT__)` |
| `\Drupal::moduleHandler()->moduleExists('acme')` to adapt to an optional module | `$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('acme')` |

An entity your own step saves through Drupal's API joins the teardown only when the step registers it with `$this->entityLifecycleRegister($entity)`.

## Exceptions

Which exception a step throws follows 1 rule now, because a project catches on the type:

| Failure | Exception |
| --- | --- |
| An assertion fails and the step can reach the page | `Behat\Mink\Exception\ExpectationException` |
| An assertion fails and the step has no Mink session | `DrevOps\BehatSteps\Steps\Exception\AssertionException` |
| An element the step locates is missing, on the page or in an XML response: a field, link, button, select, table or row | `Behat\Mink\Exception\ElementNotFoundException`, a subclass of `ExpectationException` |
| An attribute, a JSON path or a table column is missing; none of them is an element | `Behat\Mink\Exception\ExpectationException` |
| Anything that isn't an assertion: an invalid step argument, an unmet prerequisite, an infrastructure error | `\RuntimeException` |
| A step needs a capability the current browser driver lacks | `Behat\Mink\Exception\UnsupportedDriverActionException` |
| No backend the scenario lists provides a capability the step needs | `DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException`, a `\RuntimeException` |

`ExpectationException` needs a browser driver as its second constructor argument, so a trait that never touches the browser throws `AssertionException`, which carries the same meaning without the dependency. Behat reports each of these as a failed step either way, so a scenario that runs to a failure behaves the same. Only code that catches a specific type, or asserts on the message text, needs changing:

| Trait | Steps | 3.14 | 4.0 |
| --- | --- | --- | --- |
| `BlockTrait` | The `Given the block :label ...` steps that change a block, when the block doesn't exist | `ExpectationException` | `\RuntimeException` |
| `CacheTrait` | `the page cache for the path(s) ... is empty` | `\InvalidArgumentException` | `\RuntimeException` |
| `CommandTrait` | Every `Then` step | `\Exception` | `AssertionException` |
| `ConfigTrait` | Every `Then` step | `\Exception` | `AssertionException` |
| `ElementTrait` | `... with the index ...` with an index below 1, and `... pinned to the top of the viewport within ... pixels` with a negative tolerance | `ExpectationException` | `\RuntimeException` |
| `EmailTrait` | `I follow the link with the index ...`, with an index that isn't a positive integer | `ExpectationException` | `\RuntimeException` |
| `FieldTrait` | `the option ... within the select ... should (not) exist` | `\InvalidArgumentException` | `ElementNotFoundException` for a missing select or a missing option, `ExpectationException` for an option that exists but should not |
| `FieldTrait` | `I unselect the option ... from the select ...` and `the option ... within the select ... should not be selected` when the option is missing, and `I fill in the multi-value field ...` when an input row is missing | `ExpectationException` | `ElementNotFoundException` |
| `FieldTrait` | `I fill in the WYSIWYG field ...`, when the field has no `id` attribute | `ExpectationException` | `\RuntimeException` |
| `FieldTrait` | `I fill in the multi-value field ...`, without a JavaScript browser driver | `\RuntimeException` | `UnsupportedDriverActionException` |
| `FileTrait` | `an unmanaged file at the URI ... should (not) contain the value ...`, when the file can't be read | `\Exception` | `\RuntimeException` |
| `JsonTrait` | An invalid JSONPath expression, an invalid regular expression, a count that isn't an integer, a schema that isn't JSON | `ExpectationException` | `\RuntimeException` |
| `KeyboardTrait` | `I press the key(s) ...` | `\InvalidArgumentException` | `\RuntimeException` |
| `KeyboardTrait` | `I press the key(s) ...` without an element, when nothing has focus | `ExpectationException` | `\RuntimeException` |
| `MediaTrait` | The steps that create media, when the bundle is missing or doesn't exist | `\Exception` | `\RuntimeException` |
| `MetatagTrait` | Every `Then` step | `\Exception` | `ExpectationException`; `ElementNotFoundException` when the meta tag itself is missing; `\RuntimeException` when an hreflang alternate page returns an HTTP error |
| `ModalTrait` | `I close the modal`, `I click on the element ... in the modal` and `the modal should (not) contain ...`, when the close button, the content element or the target element is missing | `ExpectationException` | `ElementNotFoundException` |
| `ModuleTrait` | Every `Then` step | `\Exception` | `AssertionException` |
| `QueueTrait` | Every `Then` step | `ExpectationException` | `AssertionException` |
| `RedirectTrait` | `the following redirects should (not) exist:` | `\Exception` | `AssertionException` |
| `StateTrait` | Every `Then` step | `\Exception` | `AssertionException` |
| `TableTrait` | Any table step, when the table or the row is missing | `ExpectationException` | `ElementNotFoundException` |
| `TableTrait` | `the table ... should be sorted by ... in ... order`, with a direction other than `ascending` or `descending` | `ExpectationException` | `\RuntimeException` |
| `WaitTrait` | `I wait for ... second(s) for AJAX to finish`, without a JavaScript browser driver | `\RuntimeException` | `UnsupportedDriverActionException` |
| `WatchdogTrait` | The check for PHP errors logged during a scenario | `ExpectationException` | `AssertionException` |
| `XmlTrait` | `the response should be in XML format` | `\RuntimeException` | `ExpectationException` |
| `XmlTrait` | Every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing | `ExpectationException` | `ElementNotFoundException` |
| `XmlTrait` | `I print the last XML response`, when the document can't be serialized | `ExpectationException` | `\RuntimeException` |
| `XmlTrait` | `the response should match the following DTD:` and `the response should match the DTD in the file ...`, when the response has no root element or can't be serialized | `ExpectationException` | `\RuntimeException` |

The same rule covers the backend layer and the Behat services, which 4.0 carries in place of the Drupal Driver and the Drupal Extension. A project that calls one of these classes directly and catches on the type updates it. [Classes and interfaces](#classes-and-interfaces) maps their 3.14 names.

| Class | Case | 3.14 | 4.0 |
| --- | --- | --- | --- |
| `DrevOps\BehatSteps\Backend\Drupal\Core\Core` | An unknown entity type, bundle, vocabulary, language or handler class | `\InvalidArgumentException` or `\Exception` | `\RuntimeException` |
| `DrevOps\BehatSteps\Backend\Drupal\Core\Field\*Handler` | A malformed field value, an unreadable file, a missing referenced entity | `\InvalidArgumentException` or `\Exception` | `\RuntimeException` |
| `DrevOps\BehatSteps\Extension\Registry\BackendRegistry` | `getBackend()` and `setScenarioBackends()` | `\InvalidArgumentException` | `\RuntimeException` |
| `DrevOps\BehatSteps\Extension\Registry\UserRegistry` | `getUser()` | `\InvalidArgumentException` | `\RuntimeException` |
| `DrevOps\BehatSteps\Extension\Selector\RegionSelector` | `translateToXPath()` | `\InvalidArgumentException` | `\RuntimeException` |
| `DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException` | Its parent class | `\InvalidArgumentException` | `DrevOps\BehatSteps\Backend\Exception\Exception` |

`CreationAliasResolutionException` is no longer a `\LogicException`, so a `catch (\InvalidArgumentException)` or `catch (\LogicException)` doesn't catch it; catch the class itself.

## Backend contracts

This section matters to a project with a backend of its own, or with code that calls a backend directly. [Drupal Driver methods](#drupal-driver-methods) lists each renamed capability method and its contract change.

Every capability create now returns a stub, and every delete returns `void` and does nothing for a target that doesn't exist, so teardown code can delete whatever a scenario created without checking first. A backend of your own updates those signatures, and every delete it implements does nothing for a missing target rather than throwing. A stub that names no entity at all is a malformed argument rather than a miss, so a delete throws `\RuntimeException` for it, while a stub whose ID names nothing is still a no-op. A caller of `createRole()` reads the machine name from the stub:

```php
// Before.
$role = $backend->roleCreate(['access content']);

// After.
$role = $backend->createRole(['access content'])->getValue('id');
```

A create flags the stub as saved only when the backend holds the created entity. The Drush backend runs outside the test process, so its `createUser()` writes the new `uid` onto the stub and its `createRole()` stub carries the role's `id`, but neither stub is saved. Read the ID from the stub's values rather than from `getSavedEntity()` when the backend may be Drush.

In 3.14, the config, module, state and time steps called `\Drupal::configFactory()`, `\Drupal::moduleHandler()` and `\Drupal::state()` directly, so they ran only where the Drupal API driver had bootstrapped Drupal in the Behat process. They name the capability they need now, and run on any backend that provides it, Drush included. The lookups behind the delete, visit and assert steps ran `\Drupal::entityQuery()` the same way; they go through `QueryTrait::queryEntityIds()`, which resolves `EntityLookupCapabilityInterface`, and a delete step hands the id it found to the capability that deletes that entity type. The steps that changed an entity loaded it and saved it themselves; they hand a stub to `updateEntity()` now, and the menu, webform and block steps create, copy and change config entities through `ConfigEntityCapabilityInterface`. The queue, log, cache bin, file, search index, node access, workflow, moderation state, block, path alias, menu link, redirect, entity URL, one-time login, user role, password and user activity operations, and the reads of entity type, bundle and field definitions, each sit on a capability the same way, and the config override header reaches a backend through one, so the 3 raw database queries the cache, draggable views and watchdog steps ran live in the in-process backend. A backend of your own that implements these capabilities adds the methods below; a project that uses only the shipped backends changes nothing.

| Interface | Methods added in 4.0 |
| --- | --- |
| `BlockCapabilityInterface` | `findBlockPluginId()`, `hasConditionPlugin()`, `configureBlock()`, `setBlockVisibility()`, `unsetBlockVisibility()`, `getBlockRegion()`, `findBlockIdsByLabel()`. Each write loads the block without overrides, so no override is written into the stored configuration, and `findBlockIdsByLabel()` looks in the default theme |
| `CacheCapabilityInterface` | `cacheClearBin()`, `cacheInvalidateTags()`, `cacheDeletePagePath()`. The Drush backend empties the bin whole where `cacheDeletePagePath()` names a path, because it cannot list the bin's entries |
| `ConfigCapabilityInterface` | `configExists()`, `configGetData()`, `configSetData()`, `configDelete()` |
| `ConfigEntityCapabilityInterface` | `createConfigEntity()`, `updateConfigEntity()`, `deleteConfigEntity()`, `duplicateConfigEntity()`; the interface is new. Each saves through the entity storage, so the save hooks run, and an update or a copy loads the entity without overrides, so no override is written into the stored configuration. A create, an update and a copy return a stub, and a delete returns `void`. The Drush backend doesn't provide it |
| `ContentCapabilityInterface` | `updateEntity()`, `getEntityValues()`, `getEntityBundle()`, `getEntityUrl()`. An update sets every value of the stub through the field handlers, as a create does, and returns the stub; a read returns each field in its storage shape, or an empty array for an entity that doesn't exist. The in-process backend's `getEntityUrl()` returns the route's own path, without a path alias or the base path of a site in a subdirectory |
| `CreationAliasCapabilityInterface` | `registerCreationAlias()`. A registration replaces the alias registered under the same name for the same entity type, and an alias that implements neither `PreCreateAliasInterface` nor `PostCreateAliasInterface` fails with `\RuntimeException`. The in-process backend hands the alias to `Core`, and throws `UnsupportedBackendActionException` when its `Core` keeps no aliases; the Drush backend keeps it in its own registry |
| `DraggableviewsCapabilityInterface` | `draggableviewsSetOrder()`; the interface is new. It invalidates what the module's own form invalidates, the list tags of the view's base entity type and the view's and the display's own tags, and rejects a view that doesn't exist. The Drush backend doesn't provide it |
| `EntityDefinitionCapabilityInterface` | `getEntityIdKey()`, `getEntityFieldTypes()`, `parseEntityFields()`, `bundleExists()`, `getBundleLabel()`; the interface is new. `getEntityFieldTypes()` moved here from `CoreInterface`, which extends the interface, so `Core` keeps the method. The Drush backend doesn't provide it |
| `EntityLookupCapabilityInterface` | `findEntityIds()`; the interface is new. A condition is a value, matched with `=`, or an `[operator, value]` pair over `=`, `IN`, `CONTAINS` and `LIKE`, and the ids come back in natural order. The Drush backend doesn't provide it |
| `FieldHandlerCapabilityInterface` | `registerFieldHandler()`; the interface is new. A registration replaces the handler already registered for the field type, and the in-process backend hands it to `Core`. The Drush backend doesn't provide it |
| `FileCapabilityInterface` | `fileWriteUnmanaged()`, `fileReadUnmanaged()`, `fileExistsUnmanaged()`, `fileDeleteUnmanaged()`, `filePrepareDirectory()`, `fileWriteManaged()`; the interface is new. The Drush backend doesn't provide it |
| `LogCapabilityInterface` | `logGetLastId()`, `logRead()`, `logDelete()`; the interface is new, and its `SEVERITY_*` constants hold the RFC 5424 levels. The Drush backend serves it with `watchdog:show` and `watchdog:delete`, and returns each message rendered with its placeholders and no `variables` |
| `MenuLinkCapabilityInterface` | `getMenuLinkPluginId()`; the interface is new. The Drush backend doesn't provide it |
| `ModuleCapabilityInterface` | `moduleIsEnabled()`, `moduleIsPresent()` |
| `NodeCapabilityInterface` | `rebuildNodeAccess()` |
| `PathAliasCapabilityInterface` | `setEntityPathAlias()`; the interface is new. It changes the alias an entity has rather than adding a second one, and tells pathauto to leave the new one alone. The Drush backend doesn't provide it |
| `QueueCapabilityInterface` | `queueCountItems()`, `queueDelete()`, `queueProcess()`; the interface is new. The Drush backend serves it with `queue:list`, `queue:run` and `queue:delete` |
| `QueueItemCapabilityInterface` | `queueAddItem()`; the interface is new. The Drush backend doesn't provide it, because no Drush command adds an item |
| `RedirectCapabilityInterface` | `createRedirect()`, `deleteRedirect()`, `findRedirectIds()`; the interface is new. A source path and a destination are stored as `Redirect::setSource()` and `Redirect::setRedirect()` store them, so a caller passes them as a scenario writes them, with or without the leading slash and the `internal:` scheme. The Drush backend doesn't provide it |
| `RequestEnvironmentCapabilityInterface` | `requestHeaderSet()`, `requestHeaderUnset()`; the interface is new, under `DrevOps\BehatSteps\Backend\Capability`. The in-process backend writes the header to `$_SERVER` and the Drush backend passes it to each command in its environment, both under the name PHP gives a request header, such as `HTTP_X_CONFIG_NO_OVERRIDE`, and neither needs to be bootstrapped first |
| `SearchApiCapabilityInterface` | `searchIndexEntity()`, `searchRunCron()`; the interface is new. The Drush backend doesn't provide it |
| `SearchIndexCapabilityInterface` | `searchIndexItems()`; the interface is new. The Drush backend serves it with `search-api:index` |
| `StateCapabilityInterface` | `stateGet()`, `stateSet()`, `stateDelete()`, `stateExists()`; the interface is new |
| `UserActivityCapabilityInterface` | `setUserLastAccessTime()`, `setUserLastLoginTime()`; the interface is new. The Drush backend doesn't provide it |
| `UserCapabilityInterface` | `getOneTimeLoginUrl()`, `getUserRoles()`, `setUserPassword()`. The Drush backend serves them with `user:login`, `user:information` and `user:password` |
| `WorkflowCapabilityInterface` | `hasWorkflowState()`, `setModerationState()`; the interface is new. The Drush backend doesn't provide it |

`CacheCapabilityInterface::cacheClear()` took a cache bin to clear, which no backend honored. It takes no argument and clears every cache, so a call that passed a type drops it:

```php
// Before.
$backend->cacheClear('all');

// After.
$backend->cacheClear();
```

The same goes for `cacheClear('drush')`, which made the Drupal Driver's `DrushDriver` clear only Drush's own cache: it now rebuilds every cache, like any other call. A backend of your own that implements `cacheClear(?string $type = NULL)` keeps loading, because an extra optional parameter is compatible with the interface, so drop the parameter whenever it suits.

The Drupal Extension's `DrupalMailManager` only forwarded to `MailCapabilityInterface`, and only `RawMailContext::getMailManager()` built it, for the Drupal Extension's mail steps. `EmailTrait` swaps the mail system itself, so nothing in 4.0 needs the class. A project that built a `DrupalMailManager`, or called `getMailManager()`, calls the capability instead:

```php
// Before.
$mail = new DrupalMailManager($this->getDriver());
$mail->startCollectingMail();
$messages = $mail->getMail();

// After.
$backend = $this->backendFor(MailCapabilityInterface::class);
$backend->mailStartCollecting();
$backend->mailClear();
$messages = $backend->mailGet();
```

`mailStartCollecting()` takes the handler types to collect from, `['default']` when omitted, and switches only those, as `EmailTrait` did in 3.14 when it swapped the mail system itself. It records the handler each type held in `system.mail_original`, so a run that stops early leaves the record for the next one, and `mailStopCollecting()` restores every recorded handler and deletes the record. `mailGet()` returns every collected message, one the site cancelled included, so a caller that counted only sent mail filters on the `send` flag. A backend of your own adds the parameter to its `mailStartCollecting()`.

`DrupalDriver::getSubDriverPaths()` served the Drupal Extension's subcontext discovery, which this package doesn't do. Code that read the extension paths names the in-process backend through `getBackend()`, as a call naming a driver does in the `RawDrupalContext::getDriver()` row above, and asks it for its core:

```php
// Before.
$paths = $this->getDriver('drupal')->getSubDriverPaths();

// After.
$backend = $this->getBackend('drupal');
$paths = $backend instanceof DrupalBackendInterface
  ? $backend->getCore()->getExtensionPathList()
  : [];
```

`createNode()`, `deleteNode()`, `createTerm()` and `deleteTerm()` sit on `NodeCapabilityInterface` and `TermCapabilityInterface` under `DrevOps\BehatSteps\Backend\Drupal\Capability`, beside the other capabilities only a Drupal backend provides, and `ContentCapabilityInterface` keeps `createEntity()` and `deleteEntity()`, which apply to any backend, beside the `updateEntity()` and `getEntityValues()` the table above adds. A backend of your own that creates nodes or terms declares the 2 interfaces, and a step of your own names the capability that holds the method it calls:

```php
// A node.
$this->backendFor(NodeCapabilityInterface::class)->createNode($stub);

// A term.
$this->backendFor(TermCapabilityInterface::class)->createTerm($stub);

// Any other entity.
$this->backendFor(ContentCapabilityInterface::class)->createEntity($stub);
```

`BackendInterface::getRandom()` returns the package's `DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface` rather than `Drupal\Component\Utility\Random`, and `WebRawContext::getRandom()` passes it through. It carries the 4 string generators, `string()`, `name()`, `machineName()` and `word()`, with the same arguments and the same per-instance uniqueness, so a step that called one of them changes nothing. `sentences()`, `paragraphs()`, `object()` and `image()` aren't on it: a step that used one builds Drupal's `Random` itself, which Drupal core ships. A backend of your own returns the shipped `RandomGenerator`, or a class of its own implementing the interface:

```php
// Before.
use Drupal\Component\Utility\Random;

public function getRandom(): Random {
  return $this->random ??= new Random();
}

// After.
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

public function getRandom(): RandomGeneratorInterface {
  return $this->random ??= new RandomGenerator();
}
```

### The Drush backend

The Drush backend supports Drush 13, the first release that runs Drupal 11, and newer. It runs every command by its canonical colon-separated name rather than by a legacy alias, and each of those names exists from Drush 13 on. A project that extended the Drupal Driver's `DrushDriver` and matched the command names it issues, in an override of `drush()` or `drushResult()`, extends `DrushBackend` and matches the names in the right-hand column:

| Method | 3.14 command | 4.0 command |
| --- | --- | --- |
| `cronRun()` | `cron` | `core:cron` |
| `moduleInstall()` | `pm-enable` | `pm:install` |
| `moduleUninstall()` | `pm-uninstall` | `pm:uninstall` |
| `createUser()` | `user-create` | `user:create` |
| `deleteUser()` | `user-cancel` | `user:cancel` |
| `addUserRole()` | `user-add-role` | `user:role:add` |

- `cacheClear()` runs `cache:rebuild` alone, where it ran `cache-clear drush` first. That command cleared nothing on Drush 13, which keeps no cache of its own, and Drush 14 rejects the `drush` cache type, so every cache clear over Drush failed there.
- The capabilities 4.0 adds run `cache:clear bin <bin>`, `cache:tags`, `queue:list`, `queue:run`, `queue:delete`, `watchdog:show`, `watchdog:delete`, `search-api:index` and `user:login`, as [Backend contracts](#backend-contracts) lists per method.
- `configSet()` passes `--input-format=yaml`. The Drupal Driver asked for `--input-format=json`, which `drush config:set` doesn't parse, so every value it wrote was stored as its own JSON encoding: a string landed with its quotes around it, and an array landed as a JSON string. A project that worked around the mangled values drops the workaround.
- A keyed `configGet()` returns the value, where it returned Drush's `{"<name>:<key>": value}` envelope.
- `configGetOriginal()` reads the stored value and `configGet()` the effective one, which passes `--include-overridden`. They were the same call, so the stored and effective reads the config steps distinguish collapsed into 1.
- `NULL` is the only value that means the backend wasn't given an alias or a root path. The Drupal Extension passed `FALSE` for an unset `alias` or `root`, PHP turned it into an empty string, and the Drupal Driver's `DrushDriver` read an empty string as missing too. `BehatStepsExtension` passes `NULL`, so the `behat_steps.backend.drush.alias` and `behat_steps.backend.drush.root` container parameters, the Drupal Extension's `drupal.driver.drush.alias` and `drupal.driver.drush.root`, hold `NULL` when unset, and the `DrushBackend` constructor throws a `BootstrapException` for an empty alias or root path:

  ```php
  // Before.
  $driver = new DrushDriver('', 'web');

  // After.
  $backend = new DrushBackend(NULL, 'web');
  ```

## Service container

A project with a Behat extension of its own, or one that swaps an implementation through a container parameter, renames the Drupal Extension's container names:

| 3.14 | 4.0 |
| --- | --- |
| The `drupal.drupal` service | The `behat_steps.backend_registry` service |
| The `drupal.user_manager` service | The `behat_steps.user_registry` service |
| The `drupal.authentication_manager` service | The `behat_steps.authenticator` service, and `behat_steps.basic_authenticator` for basic authentication |
| The `drupal.driver.drupal` service | The `behat_steps.backend.drupal` service |
| The `drupal.driver.drush` service | The `behat_steps.backend.drush` service |
| The `drupal.driver.blackbox` service | The `behat_steps.backend.blackbox` service |
| The `drupal.driver.core` service | Removed; the `drupal` backend builds its Core, and the `drupal.core` option names a class of your own |
| The `drupal.driver.random` service | The `behat_steps.backend.random` service |
| The `drupal.listener.driver` service | The `behat_steps.listener.backend` service |
| The `drupal.context.initializer` service | The `behat_steps.context.initializer` service |
| The `drupal.context.loader.attribute` service | The `behat_steps.context.attribute_reader` service |
| The `drupal.region_selector` service | The `behat_steps.region_selector` service |
| The `drupal.random` service | Removed; nothing in the Drupal Extension 6.1 used it |
| The `drupal.context.environment.reader` service | Removed; it added no contexts |
| The `drupal.parameters` parameter | The `behat_steps.parameters` parameter |
| The `drupal.regions` parameter | The `behat_steps.regions` parameter |
| The `drupal.driver` service tag | The `behat_steps.backend` service tag |
| The `drupal.core` service tag | Removed; the `drupal.core` option names a Core of your own |

The parameters follow their service: `drupal.drupal.class` becomes `behat_steps.backend_registry.class`, `drupal.driver.drupal.class` becomes `behat_steps.backend.drupal.class`, `drupal.driver.drush.binary` becomes `behat_steps.backend.drush.binary`, and so on for every `.class`, `drupal_root`, `alias`, `binary` and `root` parameter. These 5 don't follow the pattern:

| 3.14 | 4.0 |
| --- | --- |
| `drupal.listener.drivers.class` | `behat_steps.listener.backend.class` |
| `drupal.random.class` | `behat_steps.random.class` |
| `drupal.context.attribute.reader.class` | `behat_steps.context.attribute_reader.class` |
| `drupal.context.region_selector.class` | `behat_steps.region_selector.class` |
| `drupal.driver.core.class` | The `drupal.core` option under `behat_steps` |

A class set through `behat_steps.random.class` implements `DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface`. The default is the package's `RandomGenerator`, where it was `Drupal\Component\Utility\Random`; [Backend contracts](#backend-contracts) lists what the generator carries.

A suite that swapped `drupal.authentication_manager.class` for a class that also applied basic authentication registers 2 classes now, through `behat_steps.authenticator.class` and `behat_steps.basic_authenticator.class`. Each one implements its interface, because the shipped classes are final.

A parameter under its old name isn't rejected, it's just never read, so check every parameter a suite sets. An old service ID is only loud where something requires it: a `@drupal.driver.drush` argument or a `getDefinition()` call fails the container build on the missing service, though Symfony's message doesn't mention the rename, while a `hasDefinition()` check or a service defined under an old ID is as quiet as a parameter.

A backend of your own registers by tagging its service `behat_steps.backend`, with the name it answers to as the alias:

```yaml
# Before.
tags:
  - { name: drupal.driver, alias: acme-jsonapi }

# After.
tags:
  - { name: behat_steps.backend, alias: acme-jsonapi }
```

A service still tagged `drupal.driver` isn't registered at all. When the `backends` list names it, the container build fails with `The "backends" list under "behat_steps" names the backend "acme-jsonapi", which is not registered. Registered backends: ...`. Without a `backends` list, it's left out of the scenario's order, so each step resolves the first remaining backend that provides its capability, or fails with `No backend provides "..."` when none does.

`grep -rnE 'drupal\.(drupal|driver|core|listener|context|region_selector|parameters|regions|user_manager|authentication_manager|random)' <your extension directory>` lists every Drupal Extension container name your extension still uses: parameters, service IDs and tags.

## Capabilities of the browser driver

A step names the browser capability it needs, never a browser driver, so a project that registers its own browser driver gets the shipped steps working as soon as it registers an adapter declaring that capability. `DrevOps\BehatSteps\Extension\Browser\Capability` holds the 5 capability interfaces, and `DrevOps\BehatSteps\Extension\Browser\Adapter` holds 1 adapter per shipped browser driver family, which declares the capabilities on the browser driver's behalf.

| Capability | BrowserKit | Selenium2 | Chrome (CDP) |
| --- | --- | --- | --- |
| `CookieCapabilityInterface` | Yes | Yes | Yes |
| `HttpClientCapabilityInterface` | Yes | No | No |
| `JavascriptCapabilityInterface` | No | Yes | Yes |
| `KeyboardCapabilityInterface` | No | Yes | Yes |
| `RequestHeaderCapabilityInterface` | Yes | No | Yes |

`WebRawContext::browserDriverFor()` returns the adapter or raises `UnsupportedDriverActionException` naming the capability, and `WebRawContext::browserDriverHas()` answers the same question as a boolean, for a step that degrades gracefully instead of failing. 3.14's `helperIsJavascriptSupported()` probed the browser driver by evaluating `true` in the browser, so every call paid a round trip to answer a question that can't change during a scenario. A step of your own that called it asks the capability instead:

```php
// Before.
if (!$this->helperIsJavascriptSupported()) {
  return;
}

// After.
if (!$this->browserDriverHas(JavascriptCapabilityInterface::class)) {
  return;
}
```

Where the step can't go on without JavaScript, resolve the capability and let it raise:

```php
$this->browserDriverFor(JavascriptCapabilityInterface::class);
```

### Registering an adapter for another browser driver

```php
$this->getBrowserCapabilityResolver()
  ->registerAdapter(AcmeDriverAdapter::class);
```

An adapter extends `BrowserAdapterBase`, implements the capability interfaces its browser driver can honor, and answers `supports()` for the browser driver it speaks for. A registered adapter is offered each browser driver ahead of the shipped ones.

## HTTP clients

Every request a step sends from PHP goes through 1 of 3 clients on `WebRawContext`. `httpPageClient()` sends a request whose response becomes the page, `httpDetachedClient()` sends one as the scenario's visitor, with its cookies, headers and credentials, and leaves the page alone, and `httpBareClient()` sends one that carries nothing of the scenario. All 3 return BrowserKit's `AbstractBrowser`, and [HTTP clients](docs/content/guides/http-clients.md) covers them in full.

`FileDownloadTrait` downloads through the detached client instead of cURL, so `fileDownloadProcess()` takes Symfony HttpClient options in place of `CURLOPT_*` constants:

```php
// Before.
$this->fileDownloadProcess($url, [CURLOPT_USERAGENT => 'acme']);

// After.
$this->fileDownloadProcess($url, ['headers' => ['User-Agent' => 'acme']]);
```

The download timeout moves from a hardcoded 120 seconds to the `file_download.timeout` option. The download no longer passes the session's cookies itself, because the detached client carries them, along with the headers steps set and the basic authentication credentials. A download from a route behind basic authentication now works after `I am using basic authentication with the username :username and the password :password`.

`MetatagTrait` fetches the hreflang alternates through the detached client, so the return-link check works on a site behind basic authentication or a login. `AccessibilityTrait` fetches its engine through the bare client, which takes the site's connection settings only for requests to `base_url`.

## Failure messages

A test that asserts on a failure message's text needs the 4.0 text. Each message now names its subject the way its step does, with the article, quotes every value it names, the page URL included, ends with a period, and closes a broken expectation with `, but it should not` or `, but it should be`. A message new in 4.0, with nothing in 3.14 to replace, isn't listed. Where a message changed along with its exception type, [Exceptions](#exceptions) lists the type.

| Trait or class | 3.14 | 4.0 | Notes |
| --- | --- | --- | --- |
| `BackendRegistry` | `The active Drupal driver "..." does not support ...` | `No backend provides "...". Backends available to this scenario, in order: ...` | |
| `BackendRegistry` | `Driver "..." is not registered` | `Backend "..." is not registered. Registered backends: ...` | |
| `BlockTrait` | `The block "..." exists but should not.` | `The block "..." exists, but it should not.` | |
| `BlockTrait` | `Block "..." is in region "..." but should not be.` | `The block "..." is in the region "...", but it should not be.` | |
| `BlockTrait` | `Block "..." is in region "..." but should be in "...".` | `The block "..." is in the region "...", but it should be in the region "...".` | |
| `CacheTrait` | `The page cache table "..." does not exist. Ensure the "..." cache bin is configured.` | `The cache bin "..." does not exist. Enable the page_cache module or set the "cache.page_cache_bin" option.` | |
| `CommandTrait` | `The expected exit code must be an integer, but got "...".` | `The exit code must be an integer, but "..." was given.` | `the command exit code should be :code` |
| `CommandTrait` | `The expected duration must be numeric, but got "...".` | `The duration must be a number, but "..." was given.` | `the command should complete in less than :seconds second(s)` and `... more than :seconds second(s)` |
| `ConfigTrait` | `The config "..." key "..." has the ... "...", which contains "..." but should not.` | `The config "..." with the key "..." has the ... "...", which contains "...", but it should not.` | |
| `ConfigTrait` | `The config "..." key "..." is not set, but it should have the ... "...".` | `The config "..." with the key "..." is not set, but it should have the ... "...".` | |
| `ConfigTrait` | `The config "..." key "..." has the ... "...", but it should have the ... "...".` | `The config "..." with the key "..." has the ... "...", but it should have the ... "...".` | |
| `ConfigTrait` | `The config "..." key "..." has the ... "...", but it should not have the ... "...".` | `The config "..." with the key "..." has the ... "...", but it should not have the ... "...".` | |
| `ConfigTrait` | `The config "..." key "..." is not set, but its ... should contain "...".` | `The config "..." with the key "..." is not set, but its ... should contain "...".` | |
| `ConfigTrait` | `The config "..." key "..." has the ... "...", which does not contain "...".` | `The config "..." with the key "..." has the ... "...", which does not contain "...".` | |
| `ContentBlockTrait` | `Content block type "..." does not exist.` | `The content block type "..." does not exist.` | |
| `ContentTrait` | `Content type "..." does not exist.` | `The content type "..." does not exist.` | |
| `ContentTrait` | `"..." content with the title "..." should ...` | `The "..." content with the title "..." should ...` | The 3 existence and publishing messages |
| `ContentTrait` | `The "path" module is not enabled. Enable it to manage content path aliases.` | `ContentTrait requires that the core "path" module is enabled, for the path alias step, which does not hold.` | |
| `CookieTrait` | `The cookie with name "..." was not set.` | `The cookie "..." was not set.` | |
| `CookieTrait` | `The cookie with name containing "..." was not set.` | `No cookie with a name containing "..." was set.` | |
| `CookieTrait` | `The cookie with name "..." was set with value "...", but it should contain "...".` | `The cookie "..." was set with the value "...", but it should contain "...".` | |
| `CookieTrait` | `The cookie with name containing "..." was set with value "...", but it should contain "...".` | `A cookie with a name containing "..." was set with the value "...", but it should contain "...".` | |
| `CookieTrait` | `The cookie with name "..." was set with value "...", but it should be "...".` | `The cookie "..." was set with the value "...", but it should be "...".` | |
| `CookieTrait` | `The cookie with name containing "..." was set with value "...", but it should be "...".` | `A cookie with a name containing "..." was set with the value "...", but it should be "...".` | |
| `CookieTrait` | `The cookie with name "..." was set with value containing "...", but it should not contain "...".` | `The cookie "..." was set with the value "...", but it should not contain "...".` | |
| `CookieTrait` | `The cookie with name containing "..." was set with value containing "...", but it should not contain "...".` | `A cookie with a name containing "..." was set with the value "...", but it should not contain "...".` | |
| `CookieTrait` | `The cookie with name "..." was set with value "...", but it should not be "...".` | `The cookie "..." was set with the value "...", but it should not be "...".` | |
| `CookieTrait` | `The cookie with name containing "..." was set with value "...", but it should not be "...".` | `A cookie with a name containing "..." was set with the value "...", but it should not be "...".` | |
| `CookieTrait` | `The cookie with name "..." was set but it should not be.` | `The cookie "..." was set, but it should not be.` | |
| `CookieTrait` | `The cookie with name containing "..." was set but it should not be.` | `A cookie with a name containing "..." was set, but it should not be.` | |
| `Core` | `No dedicated handler is registered for field "..." (type "...") on entity type "..." bundle "...", and DefaultHandler cannot marshal it: .... Register a dedicated handler via Core::registerFieldHandler().` | `No dedicated handler is registered for field "..." (type "...") on entity type "..." bundle "...", and DefaultHandler cannot marshal it: .... Map its field type to a handler class under the "drupal.field_handlers" option.` | |
| `DraggableviewsTrait` | `The "draggableviews" module is not enabled. Add "drupal/draggableviews" to the consumer project's composer.json and enable the module as part of the site setup.` | `DraggableviewsTrait requires that the "draggableviews" module from the "drupal/draggableviews" package is enabled, which does not hold.` | |
| `ElementTrait` | `Element(s) defined by "..." selector is not displayed within a viewport.` | `The element "..." is not displayed within the viewport.` | |
| `ElementTrait` | `Element(s) defined by "..." selector is not displayed within a viewport with a top offset of N pixels.` | `The element "..." is not displayed within the viewport with a top offset of N pixels.` | |
| `ElementTrait` | `Element(s) defined by "..." selector is displayed within a viewport, but should not be.` | `The element "..." is displayed within the viewport, but it should not be.` | |
| `ElementTrait` | `Element(s) defined by "..." selector is displayed within a viewport with a top offset of N pixels, but should not be.` | `The element "..." is displayed within the viewport with a top offset of N pixels, but it should not be.` | |
| `ElementTrait` | `Element defined by "..." selector is visible on the page, but should not be.` | `The element "..." is visible on the page, but it should not be.` | |
| `ElementTrait` | `None of the elements defined by "..." selector are visible on the page.` | `The element "..." is not visible on the page.` | |
| `ElementTrait` | `The "..." attribute does not exist on the element "...".` | `The attribute "..." does not exist on the element "...".` | |
| `ElementTrait` | `The "..." attribute exists on the element "..." with a value "...", but it should not.` | `The attribute "..." exists on the element "..." with a value "...", but it should not.` | |
| `ElementTrait` | `The "..." attribute exists on the element "..." with a value containing "...", but it should not.` | `The attribute "..." exists on the element "..." with a value containing "...", but it should not.` | |
| `ElementTrait` | `The "..." attribute exists on the element "..." with a value "...", but it does not have a value "...".` | `The attribute "..." exists on the element "..." with a value "...", but it does not have a value "...".` | |
| `ElementTrait` | `The "..." attribute exists on the element "..." with a value "...", but it does not contain a value "...".` | `The attribute "..." exists on the element "..." with a value "...", but it does not contain a value "...".` | |
| `ElementTrait` | `Element "..." appears before "...".` | `The element "..." appears before the element "...".` | |
| `ElementTrait` | `Text was not found: "...".` | `The text "..." was not found.` | |
| `ElementTrait` | `Text "..." appears before "...".` | `The text "..." appears before the text "...".` | |
| `ElementTrait` | `Element with selector "..." is not at the top of the viewport.` | `The element "..." is not at the top of the viewport.` | |
| `ElementTrait` | `Element with selector "..." is not centered in the viewport.` | `The element "..." is not centered in the viewport.` | |
| `ElementTrait` | `Expected element "..." to ...` | `Expected the element "..." to ...` | The stacking, pinned, keyboard focus and focus outline messages |
| `EmailTrait` | `The link with number N was not found among N links.` | `The link with the index N was not found among N links.` | |
| `EmailTrait` | `The link number must be a positive integer, but "..." was provided.` | `The link index must be an integer, but "..." was given.` | `I follow the link with the index :index in the email ...`, for a value that isn't an integer |
| `EmailTrait` | `The link number must be a positive integer, but "..." was provided.` | `The link index must be 1 or greater, but "..." was given.` | `I follow the link with the index :index in the email ...`, for an integer below 1 |
| `EmailTrait` | `No attachments were found in the email with subject ....` | `The file "..." is not attached to the email with subject "...".` | |
| `EmailTrait` | `No attachments were found in the email with subject containing "...".` | `The file "..." is not attached to the email with subject containing "...".` | |
| `EmailTrait` | `Invalid email field ... was specified for assertion.` | `Invalid email field "..." was specified for assertion.` | |
| `EmailTrait` | `No emails should have been sent, but some were found: ...` | `An email was sent, but it should not have been: ...` | |
| `EmailTrait` | `Unable to find email that should be sent to "..." retrieved from test email collector.` | `Unable to find an email that should be sent to "..." retrieved from test email collector.` | |
| `EmailTrait` | `Unable to find email with subject "..." retrieved from test email collector.` | `Unable to find an email with the subject "..." retrieved from test email collector.` | The same for `with subject containing`, which reads `with the subject containing` |
| `FieldTrait` | `Element "..." is not found.` | `Select with id\|name\|label "..." not found.` | `the option :option within the select :selector should (not) exist`, when the select is missing |
| `FieldTrait` | `Option "..." is not found in select "...".` | `Option in the select "..." with value\|text "..." not found.` | `the option :option within the select :selector should exist` |
| `FieldTrait` | `Option "..." is found in select "...", but should not.` | `The option "..." was found in the select "..." on the page "...", but it should not exist.` | `the option :option within the select :selector should not exist` |
| `FieldTrait` | `The option "..." was not found in the select "...".` | `Option in the select "..." with value\|text "..." not found.` | `I unselect the option :option from the select :selector` |
| `FieldTrait` | `The option "..." was not found in the select "..." on the page ....` | `Option in the select "..." with value\|text "..." not found.` | `the option :option within the select :selector should not be selected` |
| `FieldTrait` | `The option "..." was selected in the select "..." on the page ..., but should not be.` | `The option "..." was selected in the select "..." on the page "...", but it should not be.` | |
| `FieldTrait` | `The option "..." was not selected on the page ....` | `The option "..." was not selected on the page "...".` | |
| `FieldTrait` | `Could not locate input row N for multi-value field "...".` | `Input row of the multi-value field "..." with index "N" not found.` | `I fill in the multi-value field :field with the following values:` |
| `FieldTrait` | `The field "..." is empty, but should not be.` | `The field "..." is empty, but it should not be.` | |
| `FieldTrait` | `The field "..." is not empty, but should be.` | `The field "..." is not empty, but it should be.` | |
| `FieldTrait` | `The field "..." is marked as required, but should not be.` | `The field "..." is marked as required, but it should not be.` | |
| `FieldTrait` | `The field "..." is not marked as required, but should be.` | `The field "..." is not marked as required, but it should be.` | |
| `FieldTrait` | `The radio button "..." is selected, but should not be.` | `The radio button "..." is selected, but it should not be.` | |
| `FieldTrait` | `The radio button "..." is not selected, but should be.` | `The radio button "..." is not selected, but it should be.` | |
| `FieldTrait` | `A field "..." appears on this page, but it should not.` | `The field "..." appears on this page, but it should not.` | |
| `FieldTrait` | `A field "..." should be disabled, but it is not.` | `The field "..." should be disabled, but it is not.` | |
| `FieldTrait` | `A field "..." should not be disabled, but it is.` | `The field "..." should not be disabled, but it is.` | |
| `FieldTrait` | `Color field "..." expected a value "..." but has a value "...".` | `The color field "..." expected a value "..." but has a value "...".` | |
| `FileDownloadTrait` | `Downloaded file content has no data.` | `No file has been downloaded. Download a file before asserting on it.` | A downloaded-file assertion run before any download |
| `FileDownloadTrait` | `Downloaded file name content has no data.` | `No file has been downloaded. Download a file before asserting on it.` | A downloaded-file assertion run before any download |
| `FileDownloadTrait` | `Downloaded file path data is not available.` | `No file has been downloaded. Download a file before asserting on it.` | A downloaded-file assertion run before any download |
| `FileDownloadTrait` | `Downloaded file "...", but expected "...".` | `The downloaded file name is "...", but expected "...".` | |
| `FileDownloadTrait` | `Downloaded file name "..." does not contain "...".` | `The downloaded file name "..." does not contain "...".` | |
| `FileDownloadTrait` | `Downloaded file does not have correct headers set for ZIP.` | `The downloaded file does not have correct headers set for ZIP.` | |
| `FileDownloadTrait` | `Downloaded file is not a valid ZIP file.` | `The downloaded file is not a valid ZIP file.` | |
| `FileDownloadTrait` | `Found file partially named "..." in archive but should not.` | `Found file partially named "..." in archive, but it should not.` | |
| `FileDownloadTrait` | `Unable to download file from URL ....` | `Unable to download file from URL "...".` | |
| `FileDownloadTrait` | `The URL ... returned HTTP status N.` | `The URL "..." returned HTTP status N.` | |
| `FileDownloadTrait` | `Unable to save temp file from URL ....` | `Unable to save temp file from URL "...".` | |
| `FileDownloadTrait` | `Unable to write downloaded content into file ....` | `Unable to write downloaded content into file "...".` | |
| `FileTrait` | `File contents "..." contains "...", but should not.` | `The file content "..." contains "...", but it should not.` | |
| `FileTrait` | `File contents "..." does not contain "...".` | `The file content "..." does not contain "...".` | |
| `FileTrait` | `The file "..." exists but it should not.` | `The file "..." exists, but it should not.` | |
| `JsonTrait` | `The JSON response must decode to an array or object, but got integer.` | `The JSON response must decode to an array or object, but got int.` | The same for `boolean`, `double` and `NULL`, which read `bool`, `float` and `null` |
| `JsonTrait` | `The expected element count "..." is not a valid non-negative integer.` | `The count must be an integer, but "..." was given.` | `the JSON path :path should have :count element(s)`, for a value that isn't an integer |
| `JsonTrait` | `The expected element count "..." is not a valid non-negative integer.` | `The count must be 0 or greater, but "..." was given.` | `the JSON path :path should have :count element(s)`, for an integer below 0 |
| `JsonTrait`, `XmlTrait` | `The file "..." does not exist.` | `The fixture file "..." does not exist.` | `the response XML is loaded from the file :filename`, `the response JSON is loaded from the file :filename` and the 4 `the response should match the ... in the file :filename` steps, for a missing file |
| `JsonTrait`, `XmlTrait` | `The file "..." does not exist.` | `The Mink "files_path" parameter is not configured.` | The same steps, with no `files_path` configured |
| `LinkTrait` | `The link href "..." matches the specified href "..." but should not.` | `The link href "..." matches the specified href "...", but it should not.` | |
| `LinkTrait` | `The link with the title "..." exists, but should not.` | `The link with the title "..." exists, but it should not.` | |
| `MediaTrait` | `Cannot create media because provided bundle '...' does not exist.` | `Cannot create media because provided bundle "..." does not exist.` | |
| `MenuTrait` | `The "menu_link_content" module is not enabled. Enable it as part of the site setup; it ships with Drupal core.` | `MenuTrait requires that the core "menu_link_content" module is enabled, for the menu link steps, which does not hold.` | |
| `MetatagTrait` | `Meta tag with specified attributes was not found: {...}.` | `Meta tag with attributes "{...}" not found.` | `the meta tag with the following attributes should exist:` |
| `MetatagTrait` | `Meta tag with specified attributes should not exist: ....` | `The meta tag with the attributes "..." exists, but it should not.` | |
| `MetatagTrait` | `Meta tag with name or property "..." not found.` | `Meta tag with name\|property "..." not found.` | `the meta tag :name should not contain any HTML tags` |
| `MetatagTrait` | `The "..." meta tag contains HTML tags: ....` | `The meta tag "..." contains HTML tags: ....` | |
| `MetatagTrait` | `The robots meta tag does not include the "..." directive. Found: ....` | `The robots meta tag does not contain the "..." directive. Found: ....` | |
| `MetatagTrait` | `The robots meta tag includes the "..." directive, but it should not.` | `The robots meta tag contains the "..." directive, but it should not.` | |
| `MetatagTrait` | `Failed to fetch the hreflang alternate page "...".` | `Failed to fetch the hreflang alternate page "...": ....` | |
| `ModalTrait` | `The modal close button was not found.` | `Modal close button matching css "..." not found.` | `I close the modal` |
| `ModalTrait` | `The element "..." was not found in the modal.` | `Element in the modal with css\|id\|name\|title\|alt\|value\|text "..." not found.` | `I click on the element :selector in the modal` |
| `ModalTrait` | `The modal content element was not found.` | `Modal content element matching css "..." not found.` | `the modal should (not) contain :text` |
| `PathTrait` | `The parameter "..." is in the URL but should not be.` | `The parameter "..." is in the URL, but it should not be.` | |
| `PathTrait` | `The parameter "..." with value "..." is in the URL but should not be.` | `The parameter "..." with value "..." is in the URL, but it should not be.` | |
| `PathTrait` | `Current path is "...", but expected is "...".` | `The current path is "...", but it should be "...".` | |
| `PathTrait` | `Current path should not be "...".` | `The current path should not be "...", but it is.` | |
| `QueueTrait` | `Expected queue "..." to have N items, but it has N.` | `Expected the queue "..." to have N items, but it has N.` | |
| `QueueTrait` | `Expected queue "..." to be empty, but it has N items.` | `Expected the queue "..." to be empty, but it has N items.` | |
| `RedirectTrait` | `The "redirect" module is not enabled. Add "drupal/redirect" to the consumer project's composer.json and enable the module as part of the site setup.` | `RedirectTrait requires that the "redirect" module from the "drupal/redirect" package is enabled, which does not hold.` | |
| `ResponseTrait` | `The response contains the header "...", but should not.` | `The response contains the header "...", but it should not.` | |
| `ResponsiveTrait` | `Breakpoint '...' not found. Available breakpoints: ...` | `Breakpoint "..." not found. Available breakpoints: ....` | |
| `ResponsiveTrait` | `Invalid breakpoint format for '...': '...'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)` | `Invalid breakpoint format for "...": "...". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).` | |
| `ResponsiveTrait` | `Invalid breakpoint format: '...'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)` | `Invalid breakpoint format: "...". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).` | |
| `RestTrait` | `Expected response status code N, but got N.` | `Expected the REST response status code to be N, but got N.` | |
| `SearchApiTrait` | `The "search_api" module is not enabled. Add "drupal/search_api" to the consumer project's composer.json and enable the module as part of the site setup.` | `SearchApiTrait requires that the "search_api" module from the "drupal/search_api" package is enabled, which does not hold.` | |
| `TableTrait` | `Table with selector "..." not found.` | `Table matching css "..." not found.` | Every `the table ...` step, when the table is missing |
| `TableTrait` | `Table row containing text "..." not found.` | `Table row with text "..." not found.` | Every `... the row ...` step, when the row is missing |
| `TableTrait` | `Expected table "..." to ...` | `Expected the table "..." to ...` | The row count, column count, empty and not empty messages |
| `TableTrait` | `Expected table "..." to be sorted by "..." in ... order. Actual values: ....` | `Expected the table "..." to be sorted by the column "..." in ... order. Actual values: ....` | |
| `TableTrait` | `Column "..." not found in table "...". Available columns: ....` | `The column "..." was not found in the table "...". Available columns: ....` | |
| `TableTrait` | `Row N with values [...] not found in table "...".` | `The table "..." does not contain the row N with the values [...].` | |
| `TableTrait` | `Row containing "..." does not contain expected text "...".` | `The row containing "..." does not contain the text "...".` | |
| `UserTrait` | `User with name "..." does not exist.` | `The user "..." does not exist.` | |
| `UserTrait` | `User "..." does not have role(s) "...", but has roles "...".` | `The user "..." does not have role(s) "...", but has roles "...".` | |
| `UserTrait` | `User "..." should not have role(s) "...", but has "...".` | `The user "..." should not have role(s) "...", but has "...".` | |
| `UserTrait` | `User with email "..." is expected to exist, but they do not.` | `The user with the email "..." is expected to exist, but they do not.` | |
| `UserTrait` | `User with email "..." is expected to not exist, but they do.` | `The user with the email "..." is expected to not exist, but they do.` | |
| `UserTrait` | `User "..." is expected to be blocked, but they are not.` | `The user "..." is expected to be blocked, but they are not.` | |
| `UserTrait` | `User "..." is expected to not be blocked, but they are.` | `The user "..." is expected to not be blocked, but they are.` | |
| `XmlTrait` | `Failed to load XML. Errors: ...` | `The response is not valid XML: ...` | `the response should be in XML format` |
| `XmlTrait` | `The XML element "..." was not found.` | `XML element matching xpath "..." not found.` | Every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing |
| `XmlTrait` | `Failed to serialise the response for DTD validation.` | `Failed to serialize the response for DTD validation.` | |

# Configuration

Everything this package reads arrives through one of 4 channels. Each channel answers a different question, and none of them is a substitute for another.

| Channel | Answers | Scope |
| --- | --- | --- |
| [Suites and context arguments](#1-suites-and-context-arguments) | Which scenarios run against which surface, with which vocabulary | Per suite |
| [The `behat_steps` extension key](#2-the-behat_steps-extension-key) | How the package talks to the site | Per profile |
| [Tags](#3-tags) | What a single scenario opts into | Per scenario or feature |
| [Environment variables](#4-environment-variables) | What differs between one machine and another | Per run |

The tables in sections 2 and 3 are generated from the source by [docs.php](../docs.php). Run `ahoy update-docs` after changing an option or a tag.

## 1. Suites and context arguments

### Suite per surface

A suite is the unit that binds a set of scenarios to a set of contexts. The recommended layout is one suite per surface under test, because each surface is driven differently and runs at a different speed.

A suite selects its scenarios either by path or by tag. Both forms are shown below; which one fits depends on whether a feature file belongs to a single surface.

#### Selecting by tag

This is the layout [behat.php](../behat.php) in this repository uses, and it is the one to reach for when a feature file mixes surfaces. A trait's coverage usually does: `ElementTrait` is exercised against static fixtures, against a Drupal page and in a real browser, and splitting that into 3 files would repeat the `Feature:` header and the `Background` in each.

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

$surfaces = [
  // No Drupal: static pages, HTTP responses and payloads.
  'blackbox' => '~@api&&~@javascript',
  // The Drupal site, reached through the API driver.
  'api' => '@api&&~@javascript',
  // A real browser session, over either of the other 2 surfaces.
  'javascript' => '@javascript',
];

$profile = (new Profile('default'))
  ->withExtension(new Extension(BehatStepsExtension::class, ['api_driver' => 'drupal', 'drupal' => ['drupal_root' => 'web']]));

foreach ($surfaces as $name => $tags) {
  $profile->withSuite((new Suite($name))
    ->withPaths('%paths.base%/tests/behat/features')
    ->withFilter(new TagFilter($tags))
    ->addContext(FeatureContext::class));
}

return (new Config())->withProfile($profile);
```

Write the tag expressions so they partition the scenarios: a scenario that matches 2 suites runs twice, and one that matches none is skipped without a warning. The 3 above are mutually exclusive and cover every scenario, whatever its tags.

A suite filter stacks on top of the profile's own Gherkin filters rather than replacing them, so a profile-wide `~@skipped` still applies inside each suite.

#### Selecting by path

Where a feature file does belong to one surface, give each suite its own directory and its own context. The suite then carries only the vocabulary that works on it.

```php
// The browser surface: what an editor or a visitor can do in a page.
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class);

// The API surface: responses, headers and payloads, with no browser.
$api = (new Suite('api'))
  ->withPaths('%paths.base%/tests/behat/features/api')
  ->addContext(ApiContext::class);

// The specification surface: the few domain-language scenarios a stakeholder reads.
$spec = (new Suite('spec'))
  ->withPaths('%paths.base%/tests/behat/features/spec')
  ->addContext(SpecContext::class);
```

Run one surface with `vendor/bin/behat --suite=api`, or all of them with a bare `vendor/bin/behat`.

Three properties follow from the layout:

- **A slow surface can be excluded without touching the others.** Accessibility, visual and `@javascript` scenarios sit in their own suite, so the fast suites stay fast. This holds under either form of selection.
- **A path split lets each suite carry only the vocabulary its surface needs.** `ApiContext` mixes in `RestTrait`, `JsonTrait` and `ResponseTrait`; it never loads a trait that expects a rendered page. A step that cannot work on a surface is not defined on it, so a mistake is a "step not found" at parse time instead of a confusing failure mid-run. A tag split cannot offer this, because the suites share a features directory and therefore a context.
- **The spec suite and the regression suites do different jobs.** The spec suite holds domain-language scenarios written over your own step definitions; the regression suites hold broad scenarios written in the shipped vocabulary. See [Scenario styles](scenario-styles.md) for why both exist.

### Context constructor arguments

The extension key in section 2 is read once per profile. A value that has to differ between suites travels as a context constructor argument instead:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class, ['fixtures_path' => '%paths.base%/tests/behat/fixtures']);
```

```php
class UiContext extends RawContext {

  public function __construct(protected string $fixtures_path) {
  }

}
```

Behat matches the arguments to the constructor by name, so the array keys are the parameter names. `RawContext` and `DrupalContext` declare no constructor, which leaves the whole signature to the consuming context.

### Behat 3 and the YAML equivalent

Behat 4 reads PHP configuration only, from `behat.php` or, when there is no `behat.php`, from `behat.dist.php`. Behat 3 reads the same settings from `behat.yml`. Write `behat.php` first: it is the format both majors accept, and the only format Behat 4 accepts.

While a project is still on Behat 3, the YAML form of the suites above is:

```yaml
default:
  suites:
    blackbox:
      paths: ['%paths.base%/tests/behat/features']
      filters: { tags: '~@api&&~@javascript' }
      contexts: [FeatureContext]
    ui:
      paths: ['%paths.base%/tests/behat/features/ui']
      contexts:
        - UiContext:
            fixtures_path: '%paths.base%/tests/behat/fixtures'
    api:
      paths: ['%paths.base%/tests/behat/features/api']
      contexts: [ApiContext]
  extensions:
    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      api_driver: drupal
      drupal:
        drupal_root: web
```

[behat.dist.php](../behat.dist.php) in this repository sets every option the package accepts, as a reference.

## 2. The `behat_steps` extension key

Settings under this key configure how the package reaches the site: which driver bootstraps Drupal, what the login form looks like, which CSS selector each named region resolves to. They are read once per profile.

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'api_driver' => 'drupal',
  'drupal' => ['drupal_root' => 'web'],
  'regions' => ['content' => '#content'],
]));
```

A nested option is written as a section in the configuration and reads as a dotted path below.

[//]: # (START_EXTENSION_OPTIONS)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `default_driver` | string | `'blackbox'` | Use "blackbox" to test remote site. See "api_driver" for easier integration. |
| `api_driver` | string | `'drush'` | Bootstraps drupal through "drupal" or "drush". |
| `drush_driver` | string | `'drush'` | Driver that runs Drush commands for the steps that shell out, independently of "api_driver". |
| `login_field` | string | `'name'` | User entity property submitted as the login value. Defaults to "name". Set to "mail" for sites that authenticate by email, or any other user property. |
| `regions` | map | `[]` | Map of named regions to CSS selectors. Region steps such as 'I press :button in the :region region' resolve against this map.<br>My region: "#css-selector"<br>Content: "#main .region-content"<br>Right sidebar: "#sidebar-second" |
| `text` | section | - | Text strings, such as Log out or the Username field can be altered in the Behat configuration if they vary from the default values.<br>login_url: "/user"<br>logout_url: "/user/logout"<br>logout_confirm_url: "/user/logout/confirm"<br>log_out: "Sign out"<br>log_in: "Sign in"<br>password_field: "Enter your password"<br>username_field: "Nickname" |
| `text.login_url` | string | `'/user'` | Path the login steps submit the login form on. |
| `text.logout_url` | string | `'/user/logout'` | Path the logout steps request. |
| `text.logout_confirm_url` | string | `'/user/logout/confirm'` | Path of the logout confirmation form, submitted when the site asks to confirm. |
| `text.log_in` | string | `'Log in'` | Text of the login submit button. |
| `text.log_out` | string | `'Log out'` | Text of the logout link. |
| `text.password_field` | string | `'Password'` | Label of the password field on the login form. |
| `text.username_field` | string | `'Username'` | Label of the username field on the login form. |
| `login_wait` | integer | `0` | Maximum seconds to wait for post-login DOM signals (URL change, body render, logged-in selector, logout link). Set to 0 to disable waiting. |
| `ajax_timeout` | integer | `5` | Maximum time (in seconds) to wait for AJAX calls to complete. |
| `selectors` | section | - | CSS selectors the steps resolve page structures against. |
| `selectors.messages` | section | - | Selectors of the message regions asserted by the message steps, one per severity. |
| `selectors.messages.default` | string | - | Selector matching a message of any severity. |
| `selectors.messages.error` | string | - | Selector matching an error message. |
| `selectors.messages.success` | string | - | Selector matching a success message. |
| `selectors.messages.warning` | string | - | Selector matching a warning message. |
| `selectors.login_form_selector` | string | `'form#user-login,form#user-login-form'` | Selector of the login form, used to tell a login page from a page that merely holds a login block. |
| `selectors.logged_in_selector` | string | `'body.logged-in,body.user-logged-in'` | Selector present only while a user is authenticated, used to confirm a login took effect. |
| `mappings` | map | `[]` | Named value mappings grouped for organisation. A "{{ Key }}" token in any step argument or table cell is replaced with the mapped value before the step runs; whitespace inside the braces is ignored, so "{{ Key }}" and "{{Key}}" are equivalent. Group names are organisational only - a key must be unique across all groups.<br>paths:<br>User Registration: "/user/register"<br>User Login: "/user/login" |
| `blackbox` | section | - | Settings of the driver that drives the site through the browser only. It has no options, and it is the fallback for a scenario that selects no other driver. |
| `drupal` | section | - | Settings of the driver that bootstraps Drupal in-process. |
| `drupal.drupal_root` | string | required | Path to the Drupal root the in-process driver bootstraps. |
| `drush` | section | - | Settings of the driver that reaches the site by running Drush. |
| `drush.alias` | string | - | Drush site alias to run every command against. |
| `drush.binary` | string | `'vendor/bin/drush'` | Path to the Drush binary. |
| `drush.root` | string | - | Drupal root passed to Drush, for a site Drush cannot locate on its own. |
| `drush.global_options` | string | - | Options appended to every Drush command, such as "--uri=http://example.com". |

[//]: # (END_EXTENSION_OPTIONS)

## 3. Tags

A tag configures one scenario or one feature. A parametrized tag takes its value after a colon, never a hyphen: `@module:redirect`, not `@module-redirect`. A flag tag stands alone.

```gherkin
@api @module:redirect @behat-steps-skip:watchdogAfterStep
Scenario: Editor publishes a page
```

[//]: # (START_TAGS)

| Tag | Description |
| --- | --- |
| `@behat-steps-skip:VALUE` | Turn a hook off, named either by its method (`emailBeforeScenario`) or by the trait it belongs to (`ElementTrait`). |
| `@behat-steps-entity-cleanup-skip:VALUE` | Keep entities of the named entity type after the scenario. Repeat the tag to keep several types. |
| `@module:VALUE` | Enable the named module for the scenario, or disable it when the name is prefixed with `!`. The original state is restored afterwards. |
| `@breakpoint:VALUE` | Resize the viewport to the named breakpoint before the first step. One tag per scenario, and the scenario has to be `@javascript`. |
| `@email:VALUE` | Collect email for the scenario with the named handler type. A bare `@email` uses the `default` handler. |
| `@watchdog:VALUE` | Track the named Watchdog message type in addition to `php`, which is always tracked. |
| `@disable-config-override:VALUE` | Disable `settings.php` overrides for the named configuration object for the duration of the scenario. |
| `@accessibility:VALUE` | Assess every page the scenario visits. The value sets the impact threshold that fails the scenario: `critical`, `serious`, `moderate`, `minor`, `any`, `warning` or `strict`. |
| `@bigpipe` | Render BigPipe placeholders server-side, for a driver without JavaScript. |
| `@disable-form-validation` | Strip HTML5 validation from every form on the page so a scenario can submit values the browser would block. |
| `@js-errors` | Allow JavaScript errors, which otherwise fail the scenario. |
| `@download` | Prepare the download directory for the scenario and clean it up afterwards. |
| `@testmode` | Enable the Testmode module for the scenario. |
| `@debug` | Print detailed diagnostics while the scenario runs. |
| `@error` | Expect the scenario to log an error, which turns the Watchdog check off. |

[//]: # (END_TAGS)

`@api` and `@javascript` are Behat and Mink tags rather than tags of this package: `@api` selects the driver named by `api_driver`, and `@javascript` selects the browser session. A step that reaches Drupal's API directly needs `api_driver` set to `drupal`, the driver that bootstraps Drupal in-process; on the default `drush` it throws. Both tags are documented in [CONTRIBUTING.md](../CONTRIBUTING.md#what-a-trait-needs-from-the-driver).

## 4. Environment variables

These vary a run without changing any committed configuration. Nothing else in the package reads the environment.

| Variable | Read by | Effect |
| --- | --- | --- |
| `BEHAT_STEPS_DISABLE_CLEANUP` | `RawContext` | Set to `1`, `true`, `yes` or `on` to keep the entities, users and roles a scenario created, instead of deleting them in the teardown. For inspecting the state a failing scenario left behind, not for CI. |
| `BEHAT_ACCESSIBILITY_PRINT` | `AccessibilityTrait` | Set to any value other than `0` to print a one-line accessibility summary per page to the console. |
| `COMPOSER_BIN_DIR` | `DrushDriver` | Names the directory the Drush binary is resolved from, before the driver falls back to `vendor/bin/drush` under the working directory. Composer sets it inside its own scripts. |

`BEHAT_PARAMS` is Behat's own override channel and applies here as it does to any extension: it carries a JSON object merged over the loaded configuration, which is the usual way to point `base_url` or a driver's `api_url` somewhere else for one run.

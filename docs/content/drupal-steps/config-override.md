---
title: "Config overrides - Drupal steps"
sidebar_label: "Config overrides"
sidebar_position: 5
description: "Disable Drupal config overrides from settings.php during a scenario."
custom_edit_url: null
---

# Config overrides

`DrevOps\BehatSteps\Steps\Drupal\ConfigOverrideTrait` - [Source](../../../src/Steps/Drupal/ConfigOverrideTrait.php), [Example](../../../tests/behat/features/drupal_config_override.feature)

>  Disable Drupal config overrides from settings.php during a scenario.
>  <br/><br/>
>  Config overrides set in `settings.php` replace the stored configuration at
>  runtime. They cannot be disabled from the Behat process because tests run
>  in a separate process from the system under test (SUT).
>  <br/><br/>
>  This trait signals the SUT that specific config objects should be read
>  from their original (unoverridden) values. The signal is the
>  `X-Config-No-Override` request header. The browser sends it, and every
>  backend providing `RequestEnvironmentCapabilityInterface` passes it to the
>  SUT code it runs. The in-process backend passes it as the
>  `HTTP_X_CONFIG_NO_OVERRIDE` `$_SERVER` entry, and the Drush backend as the
>  environment variable of the same name for each command.
>  <br/><br/>
>  The SUT is responsible for reading that signal and calling
>  `ImmutableConfig::getOriginal()` instead of `ImmutableConfig::get()` for
>  the listed config names.
>  <br/><br/>
>  Activated by adding `@disable-config-override:CONFIG_NAME` tags to a
>  feature or scenario. Multiple tags are combined into a comma-separated
>  list.
>  <br/><br/>
>  The signal is applied before every step because some steps reset headers
>  set earlier in the scenario.
>  <br/><br/>
>  Limitations:
>  - The request header reaches the SUT only on a browser driver providing
>  `RequestHeaderCapabilityInterface`. A WebDriver session carries no request
>  headers, so a scenario running on Selenium reaches only the SUT code the
>  backends run.
>  - The SUT must implement support for the `X-Config-No-Override` header,
>  the `HTTP_X_CONFIG_NO_OVERRIDE` `$_SERVER` entry or the matching
>  environment variable. An example implementation:
>  ```
>    public function getConfigValue(string $name, string $key): mixed {
>      $config = $this->configFactory->get($name);
>      $header = $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] ?? getenv('HTTP_X_CONFIG_NO_OVERRIDE') ?: '';
>      if (in_array($name, array_map('trim', explode(',', $header)), TRUE)) {
>        return $config->getOriginal($key, FALSE);
>      }
>      return $config->get($key);
>    }
>  ```
>  <br/><br/>
>  The signal is also written to the request-header bag, so a trait that
>  issues its own HTTP requests - `RestTrait` - carries it too. The bag is a
>  property of the context object, so the signal reaches `RestTrait` when the
>  same context composes both traits.
>  <br/><br/>
>  Example:
>  ```
>  @disable-config-override:system.site @disable-config-override:myconfig.settings
>  Scenario: Render the page with original config values
>    When I visit "/"
>    Then the response should contain "Original site name"
>  ```
>  <br/><br/>
>  Skip processing with tag: `@behat-steps-skip:ConfigOverrideTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `config_override.enabled` | boolean | `TRUE` | `@behat-steps-skip:ConfigOverrideTrait` | Apply the `@disable-config-override:` tags of a scenario and restore the overrides afterwards. |

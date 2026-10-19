---
title: "Basic authentication - Web steps"
sidebar_label: "Basic authentication"
sidebar_position: 2
description: "Keep HTTP basic authentication applied across session resets."
custom_edit_url: null
---

# Basic authentication

`DrevOps\BehatSteps\Steps\Web\BasicAuthTrait` - [Source](../../../src/Steps/Web/BasicAuthTrait.php), [Example](../../../tests/behat/features/basic_auth.feature), [Helpers](../web-helpers/basic-auth.md)

>  Keep HTTP basic authentication applied across session resets.
>  - Re-apply the configured credentials before every scenario and step.
>  
>  Mink resets the session before every scenario and on every fast logout,
>  which clears request headers and drops the credentials. Without the
>  re-application, a site behind webserver-level basic auth answers 401
>  mid-scenario.
>  <br/><br/>
>  The hooks are a no-op when no credentials are configured.
>  <br/><br/>
>  Skip with tag: `@behat-steps-skip:BasicAuthTrait`.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `basic_auth.enabled` | boolean | `TRUE` | `@behat-steps-skip:BasicAuthTrait` | Apply the configured basic authentication credentials to every request. |

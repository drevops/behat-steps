---
title: "IframeTrait steps"
sidebar_label: "IframeTrait"
sidebar_position: 11
description: "Switch between iframes and the root document."
custom_edit_url: null
---

# IframeTrait steps

[Source](../../../../src/Steps/Web/IframeTrait.php), [Example](../../../../tests/behat/features/iframe.feature), [Helpers](../../helpers/web/iframe.md)

>  Switch between iframes and the root document.
>  - Switch to iframes by CSS selector, including unnamed iframes.
>  - Switch back to the root (top-level) document.

## Steps

<details>
  <summary><code>@When I switch to the iframe :selector</code></summary>

<br/>
Switch to an iframe identified by CSS selector
<br/><br/>

```gherkin
When I switch to the iframe "iframe.payment-form"
When I switch to the iframe "#recaptcha iframe"
```

</details>

<details>
  <summary><code>@When I switch to the root document</code></summary>

<br/>
Switch back to the root (top-level) document from an iframe
<br/><br/>

```gherkin
When I switch to the root document
```

</details>

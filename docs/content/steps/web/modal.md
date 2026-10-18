---
title: "ModalTrait steps"
sidebar_label: "ModalTrait"
sidebar_position: 19
description: "Interact with and assert modals."
custom_edit_url: null
---

# ModalTrait steps

[Source](../../../../src/Steps/Web/ModalTrait.php), [Example](../../../../tests/behat/features/modal.feature), [Helpers](../../helpers/web/modal.md)

>  Interact with and assert modals.
>  - Assert modal visibility.
>  - Assert modal content.
>  - Interact with modal buttons.
>  
>  Supports multiple modal implementations (jQuery UI dialogs, Bootstrap
>  modals, native HTML dialog element, custom modals) through the
>  `modal.selectors`, `modal.content_selectors` and `modal.close_selectors`
>  options. All steps require a JavaScript-enabled browser driver.

## Options

| Option | Type | Default | Tag | Description |
| --- | --- | --- | --- | --- |
| `modal.selectors` | map | `[...]` | - | CSS selectors of the modal container, tried in order. |
| `modal.content_selectors` | map | `[...]` | - | CSS selectors of the modal content element, tried in order. |
| `modal.close_selectors` | map | `[...]` | - | CSS selectors of the modal close button, tried in order. |
| `modal.wait_timeout` | integer | `3` | - | Maximum time, in seconds, to wait for a modal to appear. |

## Steps

<details>
  <summary><code>@When I close the modal</code></summary>

<br/>
Close the modal by clicking the close button
<br/><br/>

```gherkin
When I close the modal
```

</details>

<details>
  <summary><code>@When I click on the element :selector in the modal</code></summary>

<br/>
Click an element in the modal by CSS selector, button label, or link text
<br/><br/>

```gherkin
When I click on the element "Save" in the modal
When I click on the element ".btn-save" in the modal
When I click on the element "Cancel" in the modal
```

</details>

<details>
  <summary><code>@When I wait for the modal to appear</code></summary>

<br/>
Wait for the modal to appear
<br/><br/>

```gherkin
When I wait for the modal to appear
```

</details>

<details>
  <summary><code>@Then I should see the modal</code></summary>

<br/>
Assert that the modal is visible
<br/><br/>

```gherkin
Then I should see the modal
```

</details>

<details>
  <summary><code>@Then I should not see the modal</code></summary>

<br/>
Assert that the modal is not visible
<br/><br/>

```gherkin
Then I should not see the modal
```

</details>

<details>
  <summary><code>@Then I should see the text :text in the modal</code></summary>

<br/>
Assert that the modal contains text
<br/><br/>

```gherkin
Then I should see the text "Welcome message" in the modal
```

</details>

<details>
  <summary><code>@Then I should not see the text :text in the modal</code></summary>

<br/>
Assert that the modal does not contain text
<br/><br/>

```gherkin
Then I should not see the text "Error message" in the modal
```

</details>

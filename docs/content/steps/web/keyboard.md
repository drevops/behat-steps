---
title: "KeyboardTrait steps"
sidebar_label: "KeyboardTrait"
sidebar_position: 14
description: "Simulate keyboard interactions in the browser."
custom_edit_url: null
---

# KeyboardTrait steps

[Source](../../../../src/Steps/Web/KeyboardTrait.php), [Example](../../../../tests/behat/features/keyboard.feature)

>  Simulate keyboard interactions in the browser.
>  - Trigger key press events, including named special keys.
>  - Press a string of characters 1 key at a time.
>  - Target a key press at a page element or at the focused element.

## Steps

<details>
  <summary><code>@When I press the key :key</code></summary>

<br/>
Press a single keyboard key
<br/><br/>

```gherkin
When I press the key "a"
When I press the key "tab"
```

</details>

<details>
  <summary><code>@When I press the key :key on the element :selector</code></summary>

<br/>
Press a single keyboard key on the element
<br/><br/>

```gherkin
When I press the key "a" on the element "#edit-title"
When I press the key "tab" on the element "#edit-title"
```

</details>

<details>
  <summary><code>@When I press the keys :keys</code></summary>

<br/>
Press multiple keyboard keys
<br/><br/>

```gherkin
When I press the keys "abc"
```

</details>

<details>
  <summary><code>@When I press the keys :keys on the element :selector</code></summary>

<br/>
Press multiple keyboard keys on the element
<br/><br/>

```gherkin
When I press the keys "abc" on the element "#edit-title"
```

</details>

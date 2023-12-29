# Component Libraries: Inject

This module provides an alternative syntax for [CL Components](https://wwww.drupal.org/project/cl_components). This
syntax is designed to be more familiar to other technologies like React.

Let's imagine we want to start using CL Inject in the example card component in `cl_components_examples`.

## Component Definition

Using the **traditional syntax** you would declare the template for the component as:

```twig
{# OLD SYNTAX #}
{# This component is at @cl_components_examples/components/my-card/my-card.twig #}
<div {{ clAttributes }}>
  <h2 class="cl-component--my-card__header">{{ header }}</h2>
  <div class="cl-component--my-card__body">
    {% block card_body %}
      Default contents of a card
    {% endblock %}
  </div>
</div>
```

You need to replace the `{% block block_name %}...{% endblock %}` by `{{ children }}` (or `{{ children|raw }}` if you
need to render markup) to migrate to the **new embed syntax**. This is what it will look like:

```twig
{# NEW SYNTAX #}
{# This component is at @cl_components_examples/components/my-card/my-card.twig #}
<div {{ clAttributes }}>
  <h2 class="cl-component--my-card__header">{{ header }}</h2>
  <div class="cl-component--my-card__body">
    {{ children }}
  </div>
</div>
```

With this simple change in the template of your component definition you can benefit of the improved embed syntax.

There are two considerations to this approach:

  - If the template of your component definition contains several `{% block %}` only one can become `{{ children }}`. You will need to migrate the other blocks to different variable names. Later on you will need to pass the value for those other variables using the `with { ... }` syntax.
  - If you need to keep the default value you will need to use Twig's native syntax `{{ children|default('Default contents of a card') }}`.

## Embedding the component

The main benefit of using CL Inject is the new embed syntax. This syntax will offer two main benefits:

  1. The template contents between `{% inject %}` and `{% endinject %}` will be captured as the `children` variable. This aims to improve DX by making component embedding similar to other projects, like React.
  2. The closing tag also contains the component ID you are embedding. This improves readability and reduces the chance of bugs.

Using the **traditional syntax** you would embed the component like this:

```twig
{# OLD SYNTAX #}
{# This goes in whatever template you are embedding the component: a content type template, a field template, etc. #}
{% embed '@cl_components_examples/components/my-card/my-card--light.twig' with {
  heading: 'Map this to a contex variable'
} %}
  {% block card_body %}
    <p>This is the contents of the card. There can be other components inside:</p>
    {% include '@cl_components_examples/components/my-button/my-button.twig' with {
      text: 'Also map this'
    } %}
  {% endblock %}
{% endembed %}
```

To do the same thing with the **improved syntax** you need to:

```twig
{# NEW SYNTAX #}
{# This goes in whatever template you are embedding the component: a content type template, a field template, etc. #}
{% inject 'my-card' variant 'light' with { heading: 'Map this to a contex variable' } %}
  <p>This is the contents of the card. There can be other components inside:</p>
  {% inject 'my-button' with { text: 'Also map this' } %}{% endinject 'my-button' %}
{% endinject 'my-card' %}
```

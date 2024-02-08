✨ Seamless integration between Drupal and Storybook ✨

The _Storybook Drupal_ module enhances the Twig templating language
by introducing two new Twig tags: `stories` and `story`, so you can write
Storybook stories in Twig. With this module, you can easily create and manage
Storybook stories directly in your Twig templates, making it a powerful tool for
**documenting and showcasing your frontend templates**.

## Installation

You can install Twig Storybook via Composer:

```bash
composer require drupal/storybook
```

### Creating Stories

Once the Twig Storybook extension is registered, you can start creating
stories within your Twig templates. We recommend writing the stories in a file
with name `<file-name>.stories.twig`.

- Use the `{% stories %}` tag to define a group of stories.
- Use the `{% story %}` tag to define an individual story.

Here's an example:

```twig
{# some/path/in/your/code/base/my-card.stories.twig #}
{% stories my_card with { title: 'Components/Examples/Card' } %}

  {% story default with {
    name: '1. Default',
    args: { header: 'I am a header!', text: 'Learn more', iconType: 'power' }
  } %}
    {# Write any Twig for the "default" story. The `args` above will be made #}
    {# available as variables for the template 👇 #}
    {% embed '@examples/my-card' with { header } %}
      {% block card_body %}
        <p>I am the <em>card</em> contents.</p>
        {% include '@examples/my-button' with { text, iconType } %}
      {% endblock %}
    {% endembed %}
  {% endstory %}

{% endstories %}
```

This will render as:

![Storybook Screenshot](./docs/sb-screenshot.png)

### Drupal setup
In `development.services.yml` you want to add some configuration for Twig, so you don't need to clear caches so often. This is not needed for the Storybook integration, but it will make things easier when you need to move components to your Drupal templates.

You also need to enable CORS, so the Storybook application can talk to your Drupal site. You want this CORS configuration to be in `development.services.yml` so it does not get changed in your production environment. If you mean to use _CL Server_ in production, make sure to restrict CORS as much as possible. Remember _CL Server_ development mode **SHOULD** be disabled in production.

The configuration you want looks like this:

```yaml
parameters:
  # ...
  twig.config:
    debug: true
    cache: false
  # Remember to disable development mode in production!
  storybook.development: true
  cors.config:
    enabled: true
    allowedHeaders: ['*']
    allowedMethods: ['*']
    allowedOrigins: ['*']
    exposedHeaders: false
    maxAge: false
    supportsCredentials: true
services:
  # ...
```

⚠ Make sure to **grant permission** to _Render Storybook stories_ for anonymous users. Keep this permission disabled in production.

#### Use ddev-storybook addon

["ddev-storybook"](https://github.com/tyler36/ddev-storybook) is a community addon that provides extra commands to improve usability.

1. Install the addon.

```shell
ddev get tyler36/storybook
ddev restart
```

1. Start the storybook server.

```shell
ddev storybook -s
```

1. Launch the storybook UI.

```shell
ddev storybook
```

<details><summary><strong>Manually support missing assets (fonts, etc)</strong></summary>

Some users have reported that even with CORS enabled on Drupal, font assets (i.e. `woff/woff2` fonts) won't be served due to CORS.

As a workaround, you can take control of the `nginx-site.conf` file and tweak it. Just do the following:

1. Remove the `#ddev-generated` line (usually, the third line) on `.ddev/nginx_full/nginx-site.conf`. This will allow you to override DDEV defaults, see more info [here](https://ddev.readthedocs.io/en/latest/users/extend/customization-extendibility/#custom-nginx-configuration).
2. Locate this line and manually add the CORS header:
```yml
  # Media: images, icons, video, audio, HTC
  location ~* \.(png|jpg|jpeg|gif|ico|svg|woff|woff2)$ { # <--- Add the missing extensions
    add_header Access-Control-Allow-Origin *; # <--- Add the CORS header
    try_files $uri @rewrite;
    expires max;
    log_not_found off;
  }
```
3. Run `ddev restart`

</details>

### Storybook setup

Install Storybook as usual:

```bash
# Make use of modern versions of yarn.
yarn set version berry
# Avoid pnp.
echo 'nodeLinker: node-modules' >> .yarnrc.yml
# Install and configure stock Storybook.
yarn dlx sb init --builder webpack5 --type server
```

Then update `.storybook/main.js` to scan for stories where your application stores them.

### Compiling Twig stories into JSON

The Storybook application will does not understand stories in Twig format. It will fail to render them. You need to
compile them into a `*.stories.json`. To do so you can run:

```bash
drush storybook:generate-all-stories
```

If you want to monitor story changes to compile Twig stories into JSON, execute it with `watch`. Like so:

```bash
watch --color drush storybook:generate-all-stories
```

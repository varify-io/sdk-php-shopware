# varify-io/shopware

Server-side A/B testing with Varify for the Shopware 6.7 storefront. The server decides which variation a visitor sees and renders it in Twig, so there is no flicker and no client-side DOM rewriting. Works with Shopware's HTTP cache.

## Requirements

- Shopware 6.7.7 or later (6.7.x)
- An experiment of type **server-side** in Varify
- varify.js on the storefront, if exposures should be tracked. The plugin hands every exposure to varify.js, which applies consent and sends it to your tracking provider.

## Install

```sh
composer require varify-io/shopware
bin/console plugin:refresh
bin/console plugin:install --activate VarifyShopware
```

Then set your **team id** under Settings › Extensions › Varify. It is your team's public id from the Varify app, not the numeric `iid` in the varify.js snippet. The settings apply to the whole shop; values per sales channel are ignored, because Shopware's HTTP cache needs them before it knows the sales channel. Without a team id the plugin does nothing.

## Templates

```twig
{% if varify_is(269, 311) %}
    {# variation 311 #}
{% else %}
    {# original #}
{% endif %}

{% if varify_is_original(269) %}…{% endif %}
```

Reading an assignment counts as an exposure. The plugin adds the tracking snippet to the page itself, once per experiment, so there is nothing to add for tracking.

## Previewing a variation

Add `?varify-force={experimentId}-{variationId}` to any storefront URL, for example `?varify-force=269-311`. Variation `0` forces the original. The forced variation is stored in the cookie and sticks for later pages.

## Behaviour to know

- **Sticky assignments.** Every visitor gets a `varify` cookie (400 days) on their first page and keeps their variations for as long as it lasts. Assignments are identical to the JavaScript SDK, so both can share the cookie.
- **HTTP cache.** Pages stay cached: one copy per combination of assignments, never per visitor. A page is cached up to as many times as there are combinations, e.g. 4 times with two experiments of two variations each, even if it shows no experiment.
  - A response that assigns a visitor (first visit, a new experiment, `varify-force`) is never cached.
  - A visitor without a cookie always bypasses Shopware's own cache, so their first page assigns them.
  - Behind Varnish or Fastly, the variant travels in Shopware's `sw-cache-hash` cookie, which the Shopware VCL already varies on. Bot pages are not cached there, because the proxy can't tell a bot from a new visitor.
  - When an experiment starts, stops or changes its split, cached pages are not reused any more: the config version is part of the cache key. Behind Varnish or Fastly, cached pages expire by their TTL.
- **Header and footer** are rendered separately from the page (ESI). On a visitor's very first page they can't see the assignment that page makes and show the original; from the second page on they show the visitor's variation.
- **Failure.** The config is loaded with a 300 ms timeout and cached for 60 seconds. If it can't be loaded, the last loaded config is used; without one, every experiment shows the original and nothing is tracked. Failures are logged as warnings. Your storefront never breaks because of Varify.
- **Bots** get no cookie and always see the original.
- **Cookie consent.** The plugin does not register the `varify` cookie in Shopware's cookie consent manager. Whether it needs consent is for you to decide with your legal advice.

## Settings

| Setting                 | Default                                       | Description                                                       |
| ----------------------- | --------------------------------------------- | ----------------------------------------------------------------- |
| Team id                 | none (plugin inactive)                        | Your team's public id                                             |
| Config URL              | `https://delivery.varify.io/ss/{teamId}.json` | Your own mirror of the config. Serve it unchanged and keep it fresh |
| Timeout (ms)            | `300`                                         | How long to wait for the config before showing the original       |
| Config cache (seconds)  | `60`                                          | How long the config is reused; changes in Varify show up after at most this long |
| Cookie domain           | current host                                  | e.g. `.example.com` to share assignments across subdomains         |

# Perspective_ZarazServerSide

Server-side events for [Perspective_Zaraz](https://github.com/PerspectiveTeam/module-zaraz). The core module sends everything from the
browser; this package covers the cases where there is no browser, or the browser never comes back:

- **Order Completed fallback.** Sent from the server only when the browser did not deliver the purchase to Zaraz:
  the customer did not return from PayPal / 3-D Secure, closed the tab, or uses an ad blocker.
- **Order Refunded** for every refunded credit memo. A refund happens in the admin, so the browser cannot send it.
- **Order Cancelled**, optional and disabled by default (Zaraz has no GA4 / Meta / TikTok mapping for it).

Page views, cart and checkout events intentionally stay browser-only. Server copies of them have no session,
referrer or UTM parameters: GA4 reports them as "Unassigned" traffic and counts them twice. Zaraz is already
resistant to blockers, because the browser sends events to the store's own domain and Cloudflare forwards them to the
vendors server-to-server.

| | |
|---|---|
| Composer package | `perspectiveteam/module-zaraz-server-side` |
| Magento module | `Perspective_ZarazServerSide` |
| Config path | `perspective_zaraz/server/*` |
| Requires | `perspectiveteam/module-zaraz` ^0.2, Magento cron |

## How it works

```
checkout (REST)  ──► client context: cfz_* / cfzs_* cookies (GA4 client and session id), IP, user agent, language
                     └─ stored on the quote and on the order (column perspective_zaraz_context)
order placed     ──► order_completed row: pending, scheduled_at = now + Purchase Fallback Delay (20 min)
success page: Zaraz accepted Order Completed (PerspectiveZaraz.onDelivered)
                 ──► POST perspective_zaraz_server/order/delivered ──► status delivered, the server sends nothing
cron, every min  ──► due pending rows ──► Perspective_Zaraz builders (the same payload as in the browser)
                 ──► transport ──► sent | retry after 5, 10, 20… min (up to 6 h) | failed | skipped
credit memo      ──► order_refunded row: pending immediately
```

A unique key on event + order increment id guarantees that each event is sent at most once, and the purchase is
counted either from the browser or from the server, never both. GA4 also drops duplicates by `transaction_id`.

When an order is placed without a browser (a payment gateway callback), the module uses the context saved on the
quote when the checkout was opened. Orders created in the admin are not sent by default.

## Transports

| | Zaraz HTTP Events API (default) | GA4 Measurement Protocol |
|---|---|---|
| Destinations | every Zaraz tool (GA4 purchase / refund, Meta Purchase, TikTok CompletePayment…) | GA4 only |
| Identity | `system.cookies` with the Zaraz tool cookies → the same visitor as in the browser | `client_id` / `session_id` from the `cfz_google-analytics_v4` cookie |
| Requests | one batch per store | one request per event |
| Needs | an endpoint path in Zaraz → Settings → Endpoints → HTTP Events API | Measurement ID + API secret |

HTTP Events API request format:
`{"events":[{"client":{"__zarazTrack":"Order Completed","__zarazEcommerce":true,…payload},
"system":{"cookies":{…},"device":{"ip","user-agent","language"},"page":{"url"}}}]}`.

## Cloudflare setup

1. **Zaraz → Tag setup → Settings → Endpoints → HTTP Events API**: set a unique path, for example
   `/zaraz/api-<random>`. The API has no authentication, so the path must not be guessable.
2. The Magento server must reach this path on its own domain without login or challenges:
   - with **Cloudflare Access** on the domain, add a separate self-hosted application for the path `zaraz/api-…` with
     a **Bypass → Everyone** policy (the most specific path wins);
   - WAF rules and Bot Fight Mode must not block POST requests from the server IP.
3. Check from the server: `curl -X POST -H 'Content-Type: application/json' -d '{"events":[]}' https://<domain>/<path>`
   should return `{"results":[]}`. A 302 response means Access intercepts the request; 404 means the path does not
   match.

## Magento configuration

**Stores → Configuration → Perspective Extensions → Cloudflare Zaraz → Server-side Events**, store view scope:

| Field | Default | Purpose |
|---|---|---|
| Enable Server-side Events | No | turns scheduling and sending on |
| Transport | Zaraz HTTP Events API | or GA4 Measurement Protocol |
| HTTP Events API Path / Host | — / base URL host | the path from the Zaraz dashboard; the host only if Zaraz runs on another subdomain |
| GA4 Measurement ID / API Secret / Validation Mode | — | for Measurement Protocol; the secret is stored encrypted |
| Purchase Fallback Delay, Minutes | 20 | how long to wait for the browser confirmation |
| Send Orders Created in Admin | No | phone / manual orders |
| Max Attempts | 5 | the event becomes `failed` after this |
| Bot User Agents | bot, crawl, spider, headless… | the HTTP Events API does not apply Cloudflare bot detection, so bots are filtered here |
| Forwarded Cookies | `cfz_`, `cfzs_`, `cf_zaraz`, `_ga`, `_fbp`, `_fbc`, `_ttp`, `ttclid` | cookie prefixes stored with the order and forwarded to Zaraz |
| Keep Data, Days | 30 | processed events and client context (IP, UA, cookies) are deleted after this |
| Log Request Bodies | No | write request bodies to the log, for debugging only |

Which events go from the server is decided by the **Events** table of the core module (**From server** column):
`Order Completed` is enabled, `Order Refunded` is enabled without a row, `Order Cancelled` needs a row.

## Operations

Automatic sending needs Magento cron (`bin/magento cron:install`), `default` group jobs:
`perspective_zaraz_server_send` every minute and `perspective_zaraz_server_cleanup` daily.

```bash
bin/magento perspective:zaraz:server:list [--status=pending] [--limit=20]
bin/magento perspective:zaraz:server:process                          # the same as cron
bin/magento perspective:zaraz:server:process --now --order=000000018  # do not wait for the delay
bin/magento perspective:zaraz:server:process --now --dry-run          # print request bodies, send nothing
```

Log: `var/log/perspective_zaraz_server.log` (sent / skipped / retry / error).

Statuses:

- `pending` — waiting for the delay or a retry;
- `delivered` — the browser delivered the event, nothing was sent from the server;
- `sent` — sent from the server;
- `failed` — all attempts used;
- `skipped` — bot, event disabled, order filtered by the core "Orders" settings, or the transport does not support
  the event (Order Cancelled in GA4 Measurement Protocol).

## Privacy

The module stores the IP, user agent and analytics cookies with the order only for server-side events, and deletes
them after "Keep Data, Days". Magento session cookies are never stored, only the prefixes from "Forwarded Cookies".
Zaraz applies its consent settings to its own tools; if the store has its own consent banner, add a check before
scheduling (for example, an observer that does not store the context without consent).

## Extending

- New transport: implement `Api\TransportInterface` and add it to `Model\Transport\TransportPool` (`di.xml`,
  argument `transports`); add the code to the source model to make it selectable.
- New server-side event: a builder in `Perspective\Zaraz\Model\Event\BuilderPool`, default channels in
  `Perspective\Zaraz\Model\Event\Generator::defaultChannels`, scheduling through `Model\Scheduler::schedule()`.
- Service contracts: `Api\ServerEventRepositoryInterface`, `Api\BrowserDeliveryInterface`.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/perspectiveteam/module-zaraz-server-side/Test/Unit
cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../vendor/perspectiveteam/module-zaraz-server-side/Test/Integration
```

Integration tests place real orders (fallback, browser confirmation, retries, bot, refund) with a stub transport and
make no external requests.

## Limitations

- The HTTP Events API response (`fetch` / `execute` / `cookies` for the browser) is not processed: tools that need a
  browser receive only the server part.
- Deduplication: GA4 uses `transaction_id`; Meta (Conversions API only) and Pinterest CAPI use `event_id`
  (`order-<increment_id>`, the same in the browser and server `Order Completed`). The Zaraz TikTok tool does not
  pass `event_id` at all. The fallback still sends a purchase only when the browser did not confirm delivery.
- Location (country / city) cannot be overridden in the HTTP Events API: Zaraz takes it from the server's request.
- GA4 Measurement Protocol accepts `timestamp_micros` only within 72 hours; older events are sent with the current
  time.

# SYS — Sales Management System

**English** · [Türkçe](README.tr.md)

A panel that produces **quotation text** from a customer request: it takes the
request, gives it to a language model together with the price list and the
knowledge base, and presents the result to the sales team as a customer e-mail
plus an internal note.

It signs in through the Identity Management Center (merkez.ornek.test) and is
installed the same way as its sibling panels (KYM, EYS, NYS): one image, one
command.


## Three jobs

1. **Quotation** — the request is written, the job enters the queue, and the
   text appears on the chat screen when ready. The conversation can continue;
   the history goes to the model along with it.
2. **Knowledge base** — the company knowledge used while writing quotations. It
   lives in a vector store (Chroma); similarity search comes from there.
3. **Prices** — item, unit, currency and VAT information. This list is given to
   the model when producing a quotation.


## Permissions

Permissions inside the panel are **per person** and are what the administrator
grants on the Users screen: quotation, knowledge base, prices. A user can
request a missing permission from the panel; the administrator approves it.

The role in the center only decides **the door**, and **the center** decides
whom the door opens for. The panel has no local
allow list. Permissions are not derived from roles — someone who can hand out
roles in the center must not be able to authorize themselves here without any
administrator here knowing.


## API

External systems read and request quotations through the endpoints under
`api/v1`. The credential is a **token** (`Authorization: Bearer ...`); session
cookies are not accepted.

A token speaks on behalf of its owner and **cannot exceed them**: its permission
is the intersection of the token's own list and the owner's panel permissions.
Tokens and the endpoint list are under Settings → **API jetonları** (API tokens);
the documentation page is generated from the routes.


## Audit log

Every meaningful action is recorded and the rows are chained with HMAC-SHA256:
anyone who later changes or deletes a row breaks the chain.

    php artisan denetim:dogrula


## Installation

Installation uses `docker-compose.yml` at the repository root (Compose standard:
MariaDB, redis and Chroma as separate services, the panel speaks HTTP only; the
same file on every platform). Details and variables: `docker/README.md`,
`.env.example`.

    cp .env.example .env && docker compose up -d --build   # install / update
    docker compose logs -f panel                           # logs
    docker compose exec panel supervisorctl status         # services

No company name, website or business rule lives in the repository: the company
name and site come from the environment (`SIRKET_ADI`, `SIRKET_SITESI`), a
company-specific quotation prompt and the site pages to import are optional
files under `storage/app/company/` (see `docker/README.md`). Without them the
panel uses a generic prompt and skips the site import.


## Commands

| | |
|---|---|
| `php artisan kimlik:esitle` | applies access changes from the center (scheduler: nightly at 04:10) |
| `php artisan denetim:dogrula` | verifies the audit chain (scheduler: 03:30) |
| `php artisan queue:work` | quotation generation (supervisord runs it in the container) |


## Tests

    vendor/bin/phpunit

The server has no PHP; tests run inside the image. How it is done is described
in `CLAUDE.md` under "Bu Panele Özel" (specific to this panel).


## Rules

`CLAUDE.md`. In short: thin controllers, authorization in Policies, validation in
Form Requests, no PHP in Blade, security comes before everything, and comments
explain **why** the code is the way it is.

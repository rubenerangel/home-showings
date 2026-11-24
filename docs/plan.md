# Plan

The brief asks for a two sided application: sellers set up their house as a listing with "allowed showing dates and times", and a buyer "inputs their availability, and is shown listings that have openings within that availability that they can schedule showings on".

## Decisions

| # | Topic | Decision |
|---|---|---|
| D1 | Scheduling | Immediate confirmation, no seller approval |
| D2 | Openings | Fixed slots anchored at the window start, private, one buyer per slot |
| D3 | Two sides | One application, areas `/seller` and `/buyer`, and a REST API with the same functionality. Actors are sellers and buyers only |
| D4 | Allowed dates and times | Windows on specific dates (date, start, end), not weekly rules |
| D5 | Changing windows | Windows are created and closed, never edited. Closing is refused while a showing is active |
| D6 | Availability | Saved per buyer, several ranges on specific dates |
| D7 | What a buyer can book | Only slots fully inside their availability |
| D8 | Showings per home | One active showing per buyer and listing |
| D9 | Relevant information | Address, city, state, price, bedrooms, bathrooms, short description, 0 to 5 photos with a room label |
| D10 | Listings per seller | Several. Listings are not deleted: with no open windows they leave the results |
| D11 | Stack | PHP 8.4, Laravel 13, Inertia 3, Vue 3.5, TypeScript 6.0.3, Vite 8, Tailwind 4, PostgreSQL 18, PHPUnit 12, Docker Compose |
| D12 | Authentication | Registration and login with email and password over a real session. One role per user, chosen when registering, authorization on the server |
| D13 | Duration | On the listing (15, 30, 45, 60 min, default 30), copied to each window when created |
| D14 | Time zone | Per listing. Windows and availability are local to the house. Showings store UTC instants |
| D15 | Overlapping windows | Rejected within a listing, adjacent ones accepted |
| D16 | Cancelling | Both sides, before the start. Buyer frees the slot, seller closes it |
| D17 | Notifications | In-app, written in the same transaction |
| D18 | Creative extra | Web push, separate per side, only after the MVP is finished and tested |
| D19 | Minimum notice | Per listing: 0, 2, 12, 24 or 48 h, default 2. Exactly the limit is valid |
| D20 | Buyer overlaps | A buyer cannot hold two confirmed showings that overlap. Adjacent is fine |
| D21 | Double booking | Row locks (buyer, then window) plus PostgreSQL exclusion constraints |
| D22 | Image | Multi-stage on `alpine:3.22` with `php84` packages. Stages `vendor`, `assets`, `runtime` (the shipped one, unprivileged user) and `test` (`runtime` plus dev dependencies). Manifests before source, BuildKit cache mounts for Composer and npm |
| D23 | API | `/api/v1`, JSON. Personal access tokens (Laravel Sanctum) that expire 30 days after they are created, whatever their use. Same classes and rules as the web. Subscribing to push is only in the web, because it belongs to a browser. What is done by API does send push to the browsers already subscribed |

Time zones: America/New_York, America/Chicago, America/Denver, America/Phoenix, America/Los_Angeles.

## Data model

All intervals are half-open: `[start, end)`.

| Table | Columns | Database guarantees |
|---|---|---|
| users | name, email, password (hash), role (`seller`, `buyer`) | role check, unique email in lower case |
| listings | seller_id, address, city, state, price (whole dollars, from 1), bedrooms (from 0), bathrooms (from 0.5, steps of 0.5), description null, timezone, showing_duration_minutes, min_notice_hours | checks on timezone, duration and notice |
| listing_photos | listing_id, path, room (exterior, living_room, kitchen, bedroom, bathroom, other), position | unique (listing_id, position) |
| showing_windows | listing_id, local_starts_at, local_ends_at (without time zone), duration_minutes, closed_at null | end > start, same date, exclusion of overlaps per listing among open windows |
| availability_ranges | buyer_id, local_starts_at, local_ends_at | end > start, same date |
| showings | showing_window_id, listing_id, buyer_id, starts_at, ends_at (with time zone), status (`confirmed`, `cancelled_by_buyer`, `cancelled_by_seller`), cancelled_at null | exclusion per listing and per buyer, where confirmed |
| notifications | Laravel's table | |
| personal_access_tokens | Sanctum's table: token (hash), expires_at | unique token |

A showing is **active** while it is confirmed and has not ended (`ends_at > now`).

## Rules

- R1 Slots. Window 10:00-11:10, 30 min: 10:00 and 10:30, 10 min unused. Window 10:00-10:45, 60 min: rejected.
- R2 Availability ranges that touch or overlap are merged. 10:00-10:30 and 10:30-11:00 fit slot 10:15-10:45.
- R3 A slot matches when it is fully inside a merged range. Window 10:00-12:00, 30 min, buyer 10:10-11:20: only 10:30.
- R4 Notice. 9:00 in Denver with 2 h: 10:30 hidden, 11:00 shown. A slot that has started can never be booked, even with no notice (`starts_at > now` and `starts_at >= now + notice`).
- R5 A slot is open for a buyer when its window is not closed, R3 and R4 hold, no confirmed showing of the listing overlaps it, no showing cancelled by the seller in the same window overlaps it, it does not overlap a confirmed showing of the buyer, and the buyer has no active showing on that listing.
- R6 Booking locks the buyer, then the window, reads the clock after the locks and checks everything again. The client sends window and start; the server derives the end. Refusal reasons, in this order: not available, notice, outside availability, already has a showing at this home, just booked, overlaps another showing.
- R7 Closing is refused with active showings. Showing 10:00-10:30: refused at 10:15, allowed at 10:30.
- R8 Cancelling: only while confirmed and before the start, once. A slot cancelled by the seller stays closed in that window; a new window opens it again.
- R9 UTC. Denver 10:00 is 17:00Z on 2027-03-13 and 16:00Z on 2027-03-15. Phoenix 10:00 is 17:00Z on both days.
- R10 Permissions. Someone else's resource: 404. The other role's area: 403. No session: redirect to the login page.
- R11 On a day with a daylight-saving change in the listing's zone, a window must start at 3:00 AM or later.
- R12 A window must start in the future. An availability range is past once it is over in Pacific time; past ranges are rejected on create and hidden afterwards. Nothing ends at midnight.
- R13 Address, city and state are locked while the listing has active showings. The zone is locked while it has an open window or any showing. Changing the duration does not touch existing windows.
- R14 Photos: 0 to 5 per listing, JPG, PNG or WebP, up to 5 MB each. The first one is the cover.
- R15 Results are ordered by earliest matching slot (UTC), then listing id.
- R16 Access. Only a Bearer token opens the API: a web session does not, and the API opens none. Guest routes ignore tokens and sessions. Missing, unknown, expired or revoked token: 401. A token is valid strictly before its expiry. The same device_name gives another token. device_name: required, up to 100 characters, no control characters. Logging out of the web revokes nothing.
- R17 Answers. For `/api/*`, when the request reaches the application: JSON with or without an Accept header, and never a redirect. Success `{"data"}`. Errors `{"message"}`, with no class names. 422 adds `errors`. 409 is exactly a case of `Refusal`, with its code in `reason` and the message of the web. Everything else invalid (R12, R13, R14, wrong credentials) is a 422. Someone else's, missing and malformed ids give the same 404. The other role is a 403. Wrong method is a 405.
- R18 Limits. Login failures share the counter of the web: over it, 429 with Retry-After. 60 requests a minute for each account, and on guest routes for each address: 429. A bad token, an unknown path and a wrong method are not counted. A missing resource is.
- R19 Text with a null byte, or that is not UTF-8, is refused in web and API.

## API

Under `/api/v1`. Fields and input formats are the ones of the pages. Photo URLs are absolute.

| Who | Route | Success |
|---|---|---|
| Guest | POST /register (as the web, plus device_name) | 201, user, token, expires_at |
| Guest | POST /tokens (email, password, device_name) | 201, user, token, expires_at |
| Both | DELETE /tokens/current, DELETE /tokens (all of the account) | 204 |
| Both | GET /me | 200 |
| Both | GET /notifications (latest 50, with `unread` beside `data`), POST /notifications/read | 200, 204 |
| Buyer | GET, POST /buyer/availability, DELETE /buyer/availability/{id} | 200, 201, 204 |
| Buyer | GET /buyer/listings (matches with slots, with `has_availability` beside `data`) | 200 |
| Buyer | GET, POST /buyer/showings, POST /buyer/showings/{id}/cancel | 200, 201, 200 |
| Seller | GET, POST /seller/listings, GET, PUT /seller/listings/{id} (with photos and locks) | 200, 201, 200, 200 |
| Seller | POST /seller/listings/{id}/photos (multipart, answers all the photos), PATCH (room), DELETE /seller/listings/{id}/photos/{id} | 201, 200, 204 |
| Seller | GET /seller/listings/{id}/schedule (windows and showings) | 200 |
| Seller | POST /seller/listings/{id}/windows, POST /seller/windows/{id}/close | 201, 200 |
| Seller | POST /seller/showings/{id}/cancel | 200 |
| Seller | GET /seller/listing-options | 200 |

## Acceptance criteria

| # | Criterion |
|---|---|
| 1 | Slots, leftover and short window as in R1 |
| 2 | Merging and fit as in R2 and R3, on both boundaries |
| 3 | A listing with no bookable slot is not shown. With no ranges, a message points to adding availability |
| 4 | Notice as in R4, on both sides of the limit |
| 5 | Range 10:00-12:00 matches 10:00 in Denver and 10:00 in New York |
| 6 | Not offered: booked, closed, cancelled by the seller, overlapping the buyer's showings, listings with an active showing of the buyer. Cancelled by the buyer is offered again if R4 holds |
| 7 | Booking creates one confirmed showing with the instants in R9 |
| 8 | Each reason in R6 is refused with its message and writes nothing |
| 9 | Ten processes released together on one slot: 1 confirmed, 9 business refusals, 0 server errors, 1 notification |
| 10 | A direct overlapping insert fails with SQLSTATE 23P01, by listing and by buyer |
| 11 | One buyer from two processes, overlapping slots in two homes, or two slots of the same home in different windows: one confirmed |
| 12 | Booking and closing at once, in both orders: no closed window has a showing that ends after the closing |
| 13 | Cancelling as in R8. Both sides at once: one final status, one notification |
| 14 | Closing as in R7, at the three moments |
| 15 | Overlapping windows rejected naming the conflict, also when simultaneous. Adjacent ones accepted |
| 16 | R11, R12 and R13 |
| 17 | Permissions as in R10 |
| 18 | Booked notifies the seller, cancelled by buyer notifies the seller, cancelled by seller notifies the buyer. If the operation fails there is no notification |
| 19 | Seller: create and edit listings with photos (R14), add and close windows with a preview, see and cancel showings |
| 20 | Buyer: add and remove ranges, see listings and slots with a photo carousel, book, see and cancel showings |
| 21 | House time with its zone, and the browser's time only if it differs on that date, with the date if it changes |
| 22 | Unread count and list on both sides, "Mark all as read" |
| 23 | Clean clone and `docker compose up`: matches on any first-start date, for 14 days. A second start neither duplicates nor deletes |
| 24 | The suite runs with one documented command, `docker compose run --rm test` |
| 25 | (Push extra) Each side gets only its own notifications, with the tab closed. Switching user removes the subscription |
| 26 | The `runtime` image has no Composer, Node, dev dependencies or tests, and runs as an unprivileged user. The README shows measured sizes per stage (`docker history`) and build times: cold, unchanged, and after a one-line change |
| 27 | Registering creates the account, opens a session with a new id and goes to the area of the role |
| 28 | Refused, with nothing written and never a server error: taken email (in any case, also if taken in the meantime), password under 8 characters, over 72 bytes or not confirmed, unknown role, malformed input |
| 29 | Logging in goes to the area of the role. An unknown email and a wrong password give the same message |
| 30 | After 5 failures in a minute for one email and address, even the right password is refused until that minute ends |
| 31 | The password is stored hashed and never reaches the client or the session |
| 32 | With a session, the login and registration pages redirect to the area. Logging in as someone else removes the push subscription of this browser, and failing to does not |
| 33 | Without a valid token every route but the two guest ones is a 401 in JSON, also without an Accept header, also with a web session |
| 34 | Registering: the account of 27, the refusals of 28 as 422, a working token, no session. Account and token are written together or not at all |
| 35 | /tokens: same answer for an unknown email and a wrong password. Five failures split between web and API block both. A missing device_name is a 422 that is not counted |
| 36 | A token is stored hashed, works one second before 30 days and not at 30 days, and revoking one, or all, ends exactly those |
| 37 | Other role 403. Someone else's, missing and malformed ids: the same 404, and nothing changes |
| 38 | Buyer: every operation has the effect of the web one, and the matches are the web's |
| 39 | Every case of `Refusal` is a 409 with its code and message, and writes nothing |
| 40 | Seller: every operation has the effect of the web one, with R13 and R14 as 422 |
| 41 | Notifications: the own ones only, unread count, mark all as read. Booking and cancelling by API notify as in 18, and queue push for a subscribed browser |
| 42 | A slot booked through one door is refused through the other. The web tests do not change |
| 43 | Request 60 is accepted and 61 is a 429, two tokens of one account share the limit, and it ends after a minute |
| 44 | The README documents every route (compared once with `route:list`) and has a curl example that was run against the demo data |
| 45 | The design rules of CLAUDE.md that can be read from the code: an injected service is not `final`, nothing is looked up with `app()`, `resolve()` or `new`, a static method does not touch the database, a controller does not write, and only looks up by id what belongs to its user (R10) |

Push (25) starts only if everything else is finished and tested by 8 h 50 min of the 12 h budget.

## Out of scope

Email verification, password recovery, "remember me", email or SMS, recurring windows, agents as actors, travel time between homes, several buyers per slot. In the API: push subscriptions, pagination, abilities per token, listing tokens, other versions, OpenAPI, a list of allowed origins (CORS).

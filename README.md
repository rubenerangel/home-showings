# Home Showings

Sellers publish the dates and times when their home can be visited. Anyone can look at the homes. Buyers book a showing in the home they like, or enter when they are free and see only the homes with an opening that fits.

Laravel 13, Inertia 3, Vue 3.5 with TypeScript, Tailwind 4 and PostgreSQL 18, on PHP 8.4.

## Run it

You need Docker with Compose. Nothing else: PHP, Composer and Node run in containers, and Docker is the only supported way to run the project. There is no environment file to edit.

```sh
docker compose up --build
```

Open http://localhost:8080. The first start generates the application and push keys, creates the tables and loads demo data with openings for at least the next 14 days. Later starts keep everything.

| Task | Command |
|---|---|
| Run the tests (PHPUnit, Pint, vue-tsc, Node) | `docker compose run --rm test` |
| Run one test class | `docker compose run --rm test vendor/bin/phpunit --filter BookingTest` |
| Use another port | `APP_PORT=8090 docker compose up --build` |
| Stop | `docker compose down` |
| Stop and delete all data (also needed if you ran a version without accounts, or have an account with an incomplete email: the migration refuses it) | `docker compose --profile test down --volumes` |

`vue-tsc` and the Node tests run while the image of `test` is built, so Docker skips them when nothing under `resources/` or the manifests changed. Pint and PHPUnit run in the container every time. The tests use their own database server, in memory, so they never touch the data of the application.

## Try it

Every demo account has the password `maple-street-48`. You can also create your own.

| Side | Emails |
|---|---|
| Sellers | `grace@example.com`, `daniel@example.com`, `priya@example.com` |
| Buyers | `emma@example.com`, `noah@example.com`, `olivia@example.com` |

1. Open http://localhost:8080 without an account: every home with openings, by city. Open one, and choose **Book a showing**: it goes to register, and comes back to that home.
2. Log in as **Emma Clark** (buyer). Her availability is already there, so **Homes that fit** has slots. Pick one and book it.
3. Choose **Log out** and log in as the seller of that home (Grace Miller, Daniel Ortiz or Priya Shah). The notification is there. Open the schedule of the home, see the showing and cancel it.
4. Back as Emma, a notification says so, and that slot is no longer offered.
5. Log in as **Olivia Reed** (buyer) to see what someone without availability sees.
6. As any seller, open **Notifications**, turn on push and close the tab. Book as a buyer from another browser or profile: the notification arrives. Logging out ends it.

## Using it

There are two roles and no administrator. An account has one role, chosen when registering, and it does not change.

| | Anyone | Buyer | Seller |
|---|---|---|---|
| Homes, photos, price, and days with openings | Yes | Yes | Yes |
| Times, and booking | No | Yes | No |
| Availability, and the homes that fit it | No | Own | No |
| Homes, photos, windows | No | No | Own |
| Showings: see and cancel | No | Own | Of their homes |
| Notifications, push and API tokens | No | Own | Own |

**Creating an account.** Choose **Create an account**, then Buyer or Seller, and fill in name, email and a password of 8 characters or more. It signs in at once. Emails are not verified.

**As a buyer**, in either of two ways:

1. From a home: open it, pick a time, and book. Picking a time already says that you are free then.
2. From your time: add it in **Availability**, and **Homes that fit** shows only the homes and times inside it.

Then **My showings** has the showing, and cancelling it frees the time for others. There can be one active showing in each home.

**As a seller:**

1. **Add a home** with its address, price, time zone, showing duration and minimum notice.
2. Add up to 5 photos and put them in order. The first one is the cover.
3. In **Schedule**, add windows: a date, from and to. The page says how many showings fit before saving.
4. **Notifications** says when someone books or cancels. Turn push on to know it with the tab closed.
5. Cancel a showing if you have to: the buyer is told, and that time stays closed. A window with showings cannot be closed before.

## How it works

```
app/Scheduling/       The rules. No HTTP in here.
  Interval, Availability, Slots, Slot, HouseClock       small immutable values
  OpenSlots                                             the slots that are open, for a buyer or for anyone
  BookShowing, CancelShowing, OpenWindow, CloseWindow,
  AddAvailability                                       the actions that write
  Refusal, Refused                                      why an action is refused, and its exception
app/Listings/         The public catalog, photos, and what can be edited while showings exist
app/Http/             Thin controllers, for the pages and under Api/: find the resource of this user, call an action.
                      Inputs (what is accepted) and presenters (what is answered) are shared by both
app/Notifications/    In-app notifications, and their push version
app/Accounts/         Registering, checking a password, issuing a token
app/Push/             Which browser is subscribed, and which push services are accepted
resources/js/pages/   One Vue page per screen, under seller/ and buyer/
database/             Migrations (with the constraints), factories, demo data
tests/                Unit, Feature, and Feature/Concurrency with real processes
docs/plan.md          The plan this was built from: rules R1 to R25 and the acceptance criteria
```

### Time

Windows and availability are stored as **local times without a zone**: they mean what the clock at the house says. Showings are stored as **UTC instants**, because once a slot is booked it is a moment in the world. `HouseClock` is the only bridge between both, so daylight saving is handled in one place. A buyer who is free from 10:00 to 12:00 matches 10:00 in Denver and 10:00 in New York, which are different instants.

### Matching

`OpenSlots` narrows the candidates in SQL (listings with an open window that touches the availability) and then applies the exact rules in PHP, where they are readable and unit tested:

1. Each window is cut into fixed slots from its start (`Slots`).
2. The availability ranges that touch or overlap are merged (`Availability`), and a slot has to fit completely in one.
3. A slot also needs the notice the listing asks for, no confirmed booking, no cancellation by the seller in that window, and no overlap with the buyer's own showings.

### Booking without double booking

`BookShowing` runs in one transaction:

1. Lock the buyer row, then the window row, always in that order. The buyer lock serializes everything one buyer books. The window lock serializes booking and closing.
2. Read the clock **after** the locks, because waiting for them takes time.
3. Check every rule again. The client sends the window and the start, and the server works out the rest.
4. Insert the showing and the notification, or neither.

If some code ever forgets a lock, PostgreSQL exclusion constraints still reject two confirmed showings that overlap, by listing and by buyer. That error (`23P01`) becomes a normal refusal and not a server error. `tests/Feature/Concurrency` runs real processes released together: ten buyers on one slot, one buyer on two homes, booking against closing in both orders, both sides cancelling at once.

### The database has the last word

**The data is the most important part of this application, so the rules that protect it do not depend on the code that writes it.** The page helps, the server decides, and PostgreSQL refuses what should never be stored, also from a script, a console or a bug:

| Data | What the database refuses |
|---|---|
| Email | One that is not complete, like `test@testco`, with capitals, or taken |
| Account | An unknown role, or no password |
| Home | A price, rooms, zone, duration or notice outside the allowed values. A photo with an unknown room, or in a taken position |
| Window and availability | An end that is not after the start, or on another date. Two open windows of one home that overlap |
| Showing | Two confirmed ones that overlap, by home and by buyer. A status that contradicts its cancellation |

The email shows how the layers work together. The pattern is in [EmailFormat.php](app/Accounts/EmailFormat.php): the server applies it and the page receives it. A migration keeps the copy that it made a constraint, because a migration does not change later, and a test fails if they differ. JavaScript, PHP and PostgreSQL run the same list of addresses. To check it, the layers were turned off one by one, registering with `test@testco` in a browser:

| Turned off | Who stopped it | What the person sees |
|---|---|---|
| Nothing | The page, and nothing is sent | The error under the field |
| The page | The server | The same error |
| The page and the server | The database (`23514`, `users_email_check`) | The same error, and not a server error |
| Everything, with SQL in `psql` | The database | |

PostgreSQL writes the refused row in its log, with the email and the password hash, so in `compose.yaml` it is told not to (`log_error_verbosity=terse`).

### Notifications

`TellOtherSide` runs inside the transaction of the action. It writes the in-app notification and, if the other side has a subscribed browser, a push job. The queue is a table of the same database, so the job exists only if the action is committed, and it is not lost if the process dies right after. The `worker` service sends it.

A browser is subscribed for one user. Logging out, or logging in as someone else, removes the subscription on the server and in the browser. The server sends requests to the address of the subscription, so only the push services of the browsers are accepted, over HTTPS, without redirects.

### Permissions

Every query starts from the signed-in user, for example `$request->user()->listings()->findOrFail($id)`, so a resource of someone else is a 404. The area of the other role is a 403, and without a session every page of both areas redirects to the login. The homes are public, and answer days and not times: the times of a home say when it is empty.

Passwords are stored with bcrypt. Five failures in a minute for one email and address stop that email from that address until the minute ends. An unknown email and a wrong password get the same answer. The session id changes when logging in.

## Interface

The visual system is the US street sign: green guides, yellow warns, red stops, and [Overpass](#credits) is derived from the lettering of those signs. The sign with the address is the one bold element, and everything around it is quiet. The tokens and components are in [app.css](resources/css/app.css), without a component library.

Three Claude Code skills were used as criteria, and each change was then checked in a browser:

| Skill | What it was used for | What came out of it |
|---|---|---|
| `frontend-design` | Direction: refine what exists instead of adding decoration | A few options side by side instead of a menu (duration, notice), a lock inside what cannot change, one chevron everywhere, the height of a notification saying whether it was read |
| `web-design-guidelines` | Reviewing the forms against its rules | The price formatted while it is typed, "Saving…" while it works, a warning before losing changes, `Select…`, errors with an icon and not only a color |
| `emil-design-eng` | Movement and touch | Things move with `transform` and `opacity` only, under 250 ms, and leave faster than they enter. Fields change their border and halo. Photos slide to their new place with a mouse and do not with a keyboard. Hover effects only where there is a hover. Buttons answer to the press |

- **Homes.** The main page goes by city, and a city takes the columns its homes need. The filter by state is in the address, so it can be shared. The whole tile is one link, with one focus ring.
- **Forms.** One [FormField](resources/js/components/FormField.vue) and one [ChoiceField](resources/js/components/ChoiceField.vue) carry the label, the hint, the error and what screen readers need. After a refused form the focus goes to the first field to fix, and a message leaves when its field is edited.
- **Photos.** Any order, by dragging with a mouse or with buttons. The new order shows at once and goes back if it is not accepted. Removing asks over the photo, so nothing else moves.
- **Notifications.** Each one is a card with its own shadow, and the height says something: what was not read is higher, and settles when it is read. An icon tells a booking from a cancellation, so it is not only a color. Push is a switch that says On, Off or Blocked in words, color and shape, and keeps the focus while it works.
- **Accessible.** Keyboard for everything that is dragged or swiped, visible focus also in forced colors, reduced motion respected, controls of at least 44 px with touch, and no horizontal scroll from 320 px.

Dragging was tried in Chromium. With touch, the buttons are the way.

## REST API

Everything the pages do, except subscribing a browser to push. Under `/api/v1`, with the same rules, messages and permissions. Try it with the demo data:

```sh
TOKEN=$(curl -s http://localhost:8080/api/v1/tokens -H 'Content-Type: application/json' \
  -d '{"email":"emma@example.com","password":"maple-street-48","device_name":"terminal"}' \
  | sed 's/.*"token":"\([^"]*\)".*/\1/')

curl -s http://localhost:8080/api/v1/buyer/listings -H "Authorization: Bearer $TOKEN"
```

Each slot of the answer has a `window_id` and a `starts_at`. Sending both books it:

```sh
curl -s http://localhost:8080/api/v1/buyer/showings -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"window_id":1,"starts_at":"2027-03-10T17:00:00Z"}'
```

| Who | Route | What it does |
|---|---|---|
| Guest | `POST /register` | Creates an account: `name`, `email`, `password`, `password_confirmation`, `role`, `device_name`. Answers its first token |
| Guest | `POST /tokens` | Logs in: `email`, `password`, `device_name`. Answers a token |
| Anyone | `GET /homes`, `GET /homes/{listing}` | Every available home, with `?state=CO` as a filter, and one of them with its days. No times |
| Both | `DELETE /tokens/current`, `DELETE /tokens` | Revokes this token, or all the tokens of the account |
| Both | `GET /me` | The account of the token |
| Both | `GET /notifications`, `POST /notifications/read` | The latest 50 with the unread count. Marks all as read |
| Buyer | `GET /buyer/availability`, `POST /buyer/availability` | Ranges. Adds one: `date`, `start`, `end`, on the clock of the house |
| Buyer | `DELETE /buyer/availability/{range}` | Removes a range |
| Buyer | `GET /buyer/listings` | The homes with an opening that fits, with their slots |
| Buyer | `GET /buyer/homes/{listing}`, `POST /buyer/homes/{listing}/showings` | A home with its open slots, which ones fit, and the own showing. Books one: `window_id`, `starts_at` in UTC |
| Buyer | `GET /buyer/showings`, `POST /buyer/showings` | Own showings. Books one: `window_id`, `starts_at` in UTC |
| Buyer | `POST /buyer/showings/{showing}/cancel` | Cancels a showing |
| Seller | `GET /seller/listing-options` | Accepted states, time zones, durations, notices and rooms |
| Seller | `GET /seller/listings`, `POST /seller/listings` | Own homes. Creates one |
| Seller | `GET /seller/listings/{listing}`, `PUT /seller/listings/{listing}` | A home with its photos and what is locked. Edits it, with all its fields |
| Seller | `GET /seller/listings/{listing}/schedule` | Open windows and showings |
| Seller | `POST /seller/listings/{listing}/photos` | Adds photos, as multipart: `photos[0][file]`, `photos[0][room]` |
| Seller | `PUT /seller/listings/{listing}/photos/order` | Puts the photos in the order of `photos`, a list with the id of every one. The first is the cover |
| Seller | `PATCH /seller/listings/{listing}/photos/{photo}`, `DELETE /seller/listings/{listing}/photos/{photo}` | Changes the `room`, or removes the photo |
| Seller | `POST /seller/listings/{listing}/windows` | Opens a window: `date`, `start`, `end` |
| Seller | `POST /seller/windows/{window}/close` | Closes a window |
| Seller | `POST /seller/showings/{showing}/cancel` | Cancels a showing |

Success is `{"data": ...}`, with `has_availability` beside it in the matches, `states` in the homes and `unread` in the notifications, or an empty 204. Errors are `{"message": ...}`:

| Status | Meaning |
|---|---|
| 401 | No token, or it is unknown, revoked or expired |
| 403 | The area of the other role |
| 404 | Someone else's resource, or one that does not exist |
| 409 | A rule refused it. `reason` has the code, one of the cases of [Refusal](app/Scheduling/Refusal.php) |
| 422 | Invalid input, with `errors` by field. Also a wrong password |
| 429 | More than 60 requests in a minute (the public routes count apart), or five failed logins, which count together with the ones of the pages. `Retry-After` says how long |

A session of the pages does not open the API, so it needs no protection against CSRF.

## Assumptions and decisions

The brief leaves room. This is what I chose, what I discarded and why, and what I would ask the client.

| # | What the brief says | Chosen | Discarded, and why | Question for the client |
|---|---|---|---|---|
| D1 | "schedule showings" | Immediate confirmation | Seller approval, or a per listing setting: adds pending states, holds and expiry. Publishing "allowed" times already is the seller's consent | Must the seller accept each showing? |
| D2 | "openings within that availability" | Fixed slots from the window start, private, fully inside the buyer's range | Starts every 15 minutes or at any minute: one 10:15 booking wastes 10:00-10:15 and lowers the seller's capacity. Partial overlap: offers visits the buyer cannot attend in full. Cost of the choice: a buyer who almost fits sees nothing | Private visits? At fixed times or any time in the window? |
| D3 | "two sided application", then "an application where home sellers" and "a home buyer application" | One application with `/seller` and `/buyer`, and a REST API with the same functionality | Two applications over one API: more setup, same behavior. Agents as actors: the text only names sellers and buyers | Does the owner or an agent schedule? |
| D4 | "allowed showing dates and times" | Windows on specific dates | Weekly rules with exceptions: the text says dates, and rules need expansion and exceptions | Do the times repeat every week? |
| D5 | Nothing about a seller changing a window that has showings | Closing refused while a showing is active. Windows are created and closed, not edited | Moving only the end: small gain for now. Cascade cancellation: breaks an immediate promise. Letting the showing outlive the window: a new window could produce overlapping slots | What happens to showings when the seller's plans change? |
| D6 | "inputs their availability" | Saved per buyer, several ranges on specific dates | A search filter in the URL: the most literal reading, but it is retyped every time and the server cannot apply the same rule as the screen. Weekly pattern: sellers use dates, so it would need expansion and a horizon | One-off search, or a profile they come back to? |
| D7 | "listings that have openings [...] that they can schedule showings on" | From the search, only slots inside the availability. From the page of a home, any open slot: picking a time already says that the buyer is free then | Saving the picked time as availability: it would stay after cancelling, and match other homes. Only the search: nobody books a home that they have not seen | Should the search also show times that do not fit? |
| D8 | Nothing about how many showings per home | One active showing per buyer and home | No limit: one buyer could take every slot. A new one is allowed after the first ends | Are second showings allowed before the first? |
| D9 | "relevant information". Another project in the same document sets a minimum, this one does not | What someone needs to decide on a visit, plus up to 5 photos by room | A full portal page: the core here is scheduling | What detail would stop someone from asking for a visit? |
| D10 | "their house" | Several listings per seller, never deleted | One per seller: costs the same and blocks whoever sells two | Can a seller have more than one? |
| D12 | Nothing about accounts | Registration and login with email and password, one role per account, server-side permissions | A "Continue as" picker: it was the first version, and anyone could enter as anyone. Email verification and password recovery: they need a mail service. Both roles in one account: every page would need to say which side it is on | How do users sign in today? Can one person buy and sell? |
| D13 | Nothing about how long a showing lasts | On the listing (15, 30, 45, 60 min), copied to each window | Per window: repeated by hand, though it depends on the home. Live value on the listing: changing it would move booked slots. Fixed 30 min: a studio and a large house take different times. No buffer between showings: whoever needs one picks a longer duration | Do you need time between showings? |
| D14 | Nothing about time zones | Zone per listing, availability in house time, UTC instants on showings, viewer's time as a second line | One zone for the whole application: wrong times elsewhere. Zone per buyer: a buyer travelling to the house would have to change it. A showing is in person, so the clock that matters is the one at the door | Do you operate in several zones? |
| D15 | Nothing about overlapping windows | Rejected | Merging: which duration wins? Allowing: overlapping slots on screen | |
| D16 | Nothing about cancelling | Both sides, before the start. Buyer frees the slot, seller closes it | No cancelling: the seller could never close a booked window. Always freeing the slot: someone else would take the time the seller just gave up | Is there a deadline to cancel? |
| D17 | Nothing about notifications | In-app, in the same transaction | None: a buyer could reach a closed house. Email, SMS: need credentials or a local mail catcher | Which channel do your users expect? |
| D18 | Nothing about push | Web push per side, as the creative extra, built once the rest was finished and tested | Only in-app: nobody hears anything with the tab closed | |
| D19 | Nothing about notice | Per listing: 0, 2, 12, 24, 48 h, default 2 | None: a 9:58 booking for 10:00. One global value: an empty house and an occupied one differ | How long do you need to get the house ready? |
| D20 | Nothing about a buyer's own overlaps | Rejected and hidden from results | Allowing: a sure no-show. Travel buffer: an invented number without map data | |
| D21 | Implied by "openings" | Row locks plus exclusion constraints | Constraints alone: cannot express "active now" or a window closing meanwhile. Locks alone: any code that forgets one allows a double booking | |
| D22 | Only that the README "provides steps to run the code" | A small multi-stage image and a separate `test` stage | One image with everything: ships Composer, Node and tests. `php:8.4-cli-alpine` as the base: 121 MB against 50.6 MB for `alpine:3.22` with `php84` packages, both with `pdo_pgsql`, before adding the application (`docker images`, measured) | |
| D23 | Nothing about other systems | `/api/v1` with personal tokens that expire in 30 days, over the same classes as the pages | Pages that consume the API: two steps for what one request does today. Tokens without expiry: a lost one would work forever. No API: other systems could not connect | Which system would connect first, and for whom does it act? |
| D24 | "is shown listings" | A public main page with every available home, by state and city, besides the search by availability | Only the search: a buyer has to enter times before seeing a home. A map or a text search: more than five homes would need them | Are homes found here, or do buyers arrive from another site? |
| D25 | Nothing about visitors | A guest sees address, photos, price and the days with openings, and the times only as a buyer | Showing the times to anyone: they tell when a home is empty. Hiding the address: the home could not be recognized | Can the address be public? |
| D26 | Nothing about the format of an email | One pattern in the page, the server and the database, which has the last word | Only the rule of the framework: it accepts a domain without a dot. Only in the server: any other code could store it. Asking the DNS: registering would depend on the network, and it does not prove that the mailbox exists. `NOT VALID`: it would leave the bad ones already stored | Should the address be confirmed with a message? |

## Docker image

Four stages matter: `vendor` (Composer), `assets` (Vite), `runtime` (what ships, with an unprivileged user) and `test` (`runtime` plus the development dependencies). Two more are internal: `base` has the PHP packages that `vendor` and `runtime` share, so dependencies are resolved with the PHP that ships, and `vendor-dev` keeps the development dependencies cached when the source changes.

Measured on WSL 2 with 8 CPUs and Docker 29.9. Sizes are from `docker images` and layers from `docker history`.

| Stage | Size | Compressed |
|---|---|---|
| `vendor` | 142 MB | 25.8 MB |
| `assets` | 404 MB | 92.5 MB |
| `runtime` | 141 MB | 26.9 MB |
| `test` | 236 MB | 40.0 MB |

| Layer of `runtime` | Size |
|---|---|
| PHP, its extensions and nginx | 53 MB |
| Composer dependencies, without the development ones | 49.5 MB |
| Alpine 3.22 | 8.97 MB |
| Database folder, mostly the demo photos | 2 MB |
| Everything else: code, configuration and built assets | about 1 MB |

| Build of `runtime` | Time |
|---|---|
| Cold: new builder, no layers, no package caches, base images downloaded | 52.4 s |
| Unchanged | 1.8 s |
| After changing one line of PHP | 1.9 s |
| After changing one line of Vue (type check, tests and Vite run again) | 8.4 s |

Manifests are copied before the source, and Composer and npm use BuildKit cache mounts, so a code change never reinstalls dependencies. The shipped image has no Composer, Node, development dependencies or tests, and runs as the user `app`.

## How it was built

1. A written plan first, in [docs/plan.md](docs/plan.md): decisions, rules with examples, and numbered acceptance criteria.
2. Built with Claude Code. For matching, booking, notifications, push, accounts, the API and the public homes: tests from the criteria, seen failing, approved by me and frozen. Then the smallest code that passes, and a review by an agent that receives only the plan and the diff.
3. The agent that wrote the code could not edit the frozen tests or finish with a red suite. The gate is in [.claude/hooks](.claude/hooks): one script that I run records the SHA-256 of the tests, a `PreToolUse` hook denies edits to them, and a `Stop` hook checks the hashes and runs the suite. The shell rule of the `PreToolUse` hook matches names, so it prevents accidents. The hash check is the guarantee.
4. Rules and locks were broken on purpose, one at a time, to confirm that some test fails.
5. Screens and push were also driven in real browsers, with mouse, keyboard and touch. That found what the tests with a fake push service could not: the image lacked the BCMath extension that the encryption needs.
6. Two agents with a clean context, which only read: `qa` looks for edge cases in a plan before there is any test, and `reviewer` checks a change with only the plan and the diff. Both are in [.claude/agents](.claude/agents). Changes that were only of the screens did not go through them.
7. The design rules are in [CLAUDE.md](CLAUDE.md), and `ArchitectureTest` checks the ones that can be read from the code.
8. Three design skills were the criteria for the screens: see [Interface](#interface). Context7 was the source of the documentation of Docker, Sanctum and Inertia.

## Known limitations

- The format of an email is checked, and not that it exists: emails are not verified, and a forgotten password cannot be recovered. Registering tells whether an email has an account. Two tabs share one session, so logging in as someone else in one changes the other.
- In the API, the tokens of an account cannot be listed, only revoked one by one or all together. Lists have no pages, and any origin may call it from a browser, always with a token. A request for a path that ends in `.php`, or over 32 MB, is answered by nginx in HTML.
- The demo accounts share a password that is written above.
- The main page has no map, text search or pages. The address and the photos of an available home are public.
- In **Homes that fit**, a buyer whose free time almost fits a slot does not see the home.
- With homes in two zones, a range matches local time in both, which does not mean there is time to travel.
- Adjacent showings in different homes leave no travel time.
- In-app notifications reach only people who open the application. Push needs a browser that supports it, permission in the browser and in the settings of the operating system, and an internet connection to the push service of that browser.
- Availability is self-declared, so enforcing it on the server is consistency, not security.
- Nothing can end at midnight.
- Two notifications written in the same second can appear in either order.
- The database password in `compose.yaml` is for this local setup only. The database is not published outside the Compose network, and the application listens on the loopback interface.

## Credits

The typeface is [Overpass](https://github.com/RedHatOfficial/Overpass), under the SIL Open Font License ([resources/fonts/OFL.txt](resources/fonts/OFL.txt)), served from the application.

Demo photos from [Unsplash](https://unsplash.com), credited in [database/seeders/photos/CREDITS.md](database/seeders/photos/CREDITS.md).

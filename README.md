# Home Showings

Sellers publish the dates and times when their home can be visited. Buyers enter when they are free, see only the homes with an opening that fits, and book a showing.

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
| Stop and delete all data | `docker compose --profile test down --volumes` |

`vue-tsc` and the Node tests run while the image of `test` is built, so Docker skips them when nothing under `resources/` or the manifests changed. Pint and PHPUnit run in the container every time. The tests use their own database server, in memory, so they never touch the data of the application.

## Try it

1. Continue as **Emma Clark** (buyer). Her availability is already there, so she sees homes with slots. Pick one and book it.
2. Choose **Switch user** and continue as the seller of that home (Grace Miller, Daniel Ortiz or Priya Shah). The notification is there. Open the schedule of the home, see the showing and cancel it.
3. Back as Emma, a notification says so, and that slot is no longer offered.
4. Continue as **Olivia Reed** (buyer) to see what someone without availability sees.
5. As any seller, open **Notifications**, turn on push and close the tab. Book as a buyer from another browser or profile: the notification arrives. Switching user ends it.

Users are picked, not authenticated: see [Known limitations](#known-limitations).

## How it works

```
app/Scheduling/       The rules. No HTTP in here.
  Interval, Availability, Slots, Slot, HouseClock       small immutable values
  OpenSlots                                             the slots each listing has open for a buyer
  BookShowing, CancelShowing, OpenWindow, CloseWindow   the actions that write
  Refusal, Refused                                      why an action is refused, and its exception
app/Listings/         Photos, and what can be edited while showings exist
app/Http/             Thin controllers: find the resource of this user, call an action
app/Notifications/    In-app notifications, and their push version
app/Push/             Which browser is subscribed, and which push services are accepted
resources/js/pages/   One Vue page per screen, under seller/ and buyer/
database/             Migrations (with the constraints), factories, demo data
tests/                Unit, Feature, and Feature/Concurrency with real processes
docs/plan.md          The plan this was built from: rules R1 to R15 and the acceptance criteria
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

### Notifications

`TellOtherSide` runs inside the transaction of the action. It writes the in-app notification and, if the other side has a subscribed browser, a push job. The queue is a table of the same database, so the job exists only if the action is committed, and it is not lost if the process dies right after. The `worker` service sends it.

A browser is subscribed for one user. Switching user removes the subscription on the server and in the browser. The server sends requests to the address of the subscription, so only the push services of the browsers are accepted, over HTTPS, without redirects.

### Permissions

Every query starts from the signed-in user, for example `$request->user()->listings()->findOrFail($id)`, so a resource of someone else is a 404. The area of the other role is a 403, and without a session every page redirects to the picker.

## Assumptions and decisions

The brief leaves room. This is what I chose, what I discarded and why, and what I would ask the client.

| # | What the brief says | Chosen | Discarded, and why | Question for the client |
|---|---|---|---|---|
| D1 | "schedule showings" | Immediate confirmation | Seller approval, or a per listing setting: adds pending states, holds and expiry. Publishing "allowed" times already is the seller's consent | Must the seller accept each showing? |
| D2 | "openings within that availability" | Fixed slots from the window start, private, fully inside the buyer's range | Starts every 15 minutes or at any minute: one 10:15 booking wastes 10:00-10:15 and lowers the seller's capacity. Partial overlap: offers visits the buyer cannot attend in full. Cost of the choice: a buyer who almost fits sees nothing | Private visits? At fixed times or any time in the window? |
| D3 | "two sided application", then "an application where home sellers" and "a home buyer application" | One application with `/seller` and `/buyer` | Two applications over one API: more setup, same behavior. Agents as actors: the text only names sellers and buyers | Does the owner or an agent schedule? |
| D4 | "allowed showing dates and times" | Windows on specific dates | Weekly rules with exceptions: the text says dates, and rules need expansion and exceptions | Do the times repeat every week? |
| D5 | Nothing about a seller changing a window that has showings | Closing refused while a showing is active. Windows are created and closed, not edited | Moving only the end: small gain for now. Cascade cancellation: breaks an immediate promise. Letting the showing outlive the window: a new window could produce overlapping slots | What happens to showings when the seller's plans change? |
| D6 | "inputs their availability" | Saved per buyer, several ranges on specific dates | A search filter in the URL: the most literal reading, but it is retyped every time and the server cannot apply the same rule as the screen. Weekly pattern: sellers use dates, so it would need expansion and a horizon | One-off search, or a profile they come back to? |
| D7 | "listings that have openings [...] that they can schedule showings on" | Only matching slots can be booked | Any slot of a matching home: makes the availability pointless | Should we also show times that do not fit? |
| D8 | Nothing about how many showings per home | One active showing per buyer and home | No limit: one buyer could take every slot. A new one is allowed after the first ends | Are second showings allowed before the first? |
| D9 | "relevant information". Another project in the same document sets a minimum, this one does not | What someone needs to decide on a visit, plus up to 5 photos by room | A full portal page: the core here is scheduling | What detail would stop someone from asking for a visit? |
| D10 | "their house" | Several listings per seller, never deleted | One per seller: costs the same and blocks whoever sells two | Can a seller have more than one? |
| D12 | Nothing about accounts | "Continue as" picker with a real session and server-side permissions | Passwords or full registration: time that shows nothing about the problem. Not deployable as it is | How do users sign in today? |
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

## Docker image

Four stages matter: `vendor` (Composer), `assets` (Vite), `runtime` (what ships, with an unprivileged user) and `test` (`runtime` plus the development dependencies). Two more are internal: `base` has the PHP packages that `vendor` and `runtime` share, so dependencies are resolved with the PHP that ships, and `vendor-dev` keeps the development dependencies cached when the source changes.

Measured on WSL 2 with 8 CPUs and Docker 29.9. Sizes are from `docker images` and layers from `docker history`.

| Stage | Size | Compressed |
|---|---|---|
| `vendor` | 142 MB | 25.8 MB |
| `assets` | 404 MB | 92.4 MB |
| `runtime` | 141 MB | 26.8 MB |
| `test` | 235 MB | 39.8 MB |

| Layer of `runtime` | Size |
|---|---|
| PHP, its extensions and nginx | 53 MB |
| Composer dependencies, without the development ones | 49.3 MB |
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
2. For matching, booking, notifications and push: tests from the criteria, seen failing, approved by me and frozen. Then the smallest code that passes, and a review by an agent that receives only the plan and the diff.
3. The agent that wrote the code could not edit the frozen tests or finish with a red suite. The gate is in [.claude/hooks](.claude/hooks): one script that I run records the SHA-256 of the tests, a `PreToolUse` hook denies edits to them, and a `Stop` hook checks the hashes and runs the suite. The shell rule of the `PreToolUse` hook matches names, so it prevents accidents. The hash check is the guarantee.
4. Rules and locks were broken on purpose, one at a time, to confirm that some test fails.
5. Screens and push were also driven in real browsers. That found what the tests with a fake push service could not: the image lacked the BCMath extension that the encryption needs.

## Known limitations

- No passwords: anyone can continue as anyone. Two tabs share one session, so switching user in one changes the other.
- A buyer whose free time almost fits a slot does not see the home.
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

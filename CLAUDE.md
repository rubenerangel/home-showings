# Working on this repository

The rules and numbered criteria are in `docs/plan.md`. The README describes only what exists and works.

## Commands

Everything runs in containers. Do not use PHP, Composer or npm from the host.

- Whole suite (PHPUnit, Pint, vue-tsc, Node): `docker compose run --rm test`
- One class: `docker compose run --rm test vendor/bin/phpunit --filter BookingTest`
- Application: `docker compose up -d --build --wait`

All suites share the `db-test` server and its database. Two at once fail with deadlocks and missing tables, so check `docker ps --filter name=test-run` first. The Stop gate runs the suite too: do not work from two sessions in this directory.

## Design

1. **DRY.** A business rule is written once. Code that only looks alike can stay. Search the existing code before writing a rule. Duplication outside the change is reported, not refactored on the way.
2. **Dependency injection.** A class of ours with side effects (database, session, disk, network) is injected: in the constructor, or in the controller method when one action uses it. No `app()`, `resolve()` or `new` for it in `app/`.
3. **Replaceable.** An injected service is not `final`, or Mockery cannot replace it. Value objects, enums, exceptions and input classes stay `final`.
4. **Static** only for pure functions, named constructors, input classes and reading the clock.
5. **Facades and the clock** are fine inside a service. Tests use the fakes of Laravel and `travelTo`.
6. **SOLID, without overengineering.** One reason to change for each class. No interface with a single implementation unless a test needs it, and no layer that only forwards.
7. **Web and API share** the actions, the input classes and the presenters. A controller only authorizes, calls and answers.

## Tests

- `tests/Unit`: no database. Pure rules and value objects.
- `tests/Feature`: real PostgreSQL. Never mock locks, transactions or constraints.
- `tests/Feature/Concurrency`: real processes released together.
- Mock what is outside (push service), or a service of ours to force a failure that is hard to cause. Never mock value objects or enums.
- Names are `test_` and the business rule, in snake case. Use `#[TestWith]` or a data provider for the boundaries.
- Every criterion has a test, and the test fails if its rule is removed.
- After changing data through HTTP, `refresh()` a model before `update()`: Eloquent skips attributes that look unchanged.

## Workflow

Full flow for scheduling, notifications, push, accounts and the API. For screens and seeders, steps 1, 4, 5 and 6.

1. **Plan.** Goal, files, numbered criteria that can be tested, what is out of scope, risks. A value that the user did not give (limit, timeout, retention) is a question, never invented. In the full flow, send the draft to the `qa` agent and turn the accepted cases into criteria. Wait for approval.
2. **Red tests.** Written while nothing is frozen. Show the red run and the map from criterion to test. Wait for approval.
3. **Freeze.** Only the user runs `node .claude/hooks/freeze.mjs freeze`. Check that the output lists the new tests before writing code.
4. **Code.** The smallest diff that passes.
5. **Review.** The `reviewer` agent gets the plan path and the diff path, and nothing about how the code was written. Two cycles at most, then open findings go to the user.
6. **Report.** Criteria met or not with their evidence, output of the last run, findings fixed, open and dismissed, and the commit message. Wait for approval.

When a frozen test is wrong, do not edit it or code around it. Show the failure, the line that explains it and the fix. If the user agrees, they interrupt the turn, run `node .claude/hooks/freeze.mjs release`, and freeze again after the fix.

## Commits

- Conventional Commits without scope, imperative, lower case, no period, 72 characters at most: `fix: keep push when logging in fails`.
- Small, one purpose each. Stage by path and check `git diff --staged --stat` for unexpected files.
- Commit and push only when the user asks. Each commit message is approved first.

## Logs and secrets

- Never read, print or change `.env`, keys or credentials.
- Logs carry ids, status and reason. Never request bodies, emails, passwords, tokens or push endpoints.
- A limit or timeout changes only with a reason that is written in the plan.
- This repository is public: nothing from other projects, clients or companies.

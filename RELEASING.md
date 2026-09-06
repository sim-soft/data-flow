# Releasing

Steps for cutting a release of `simsoft/data-flow`.

The order matters in one place: **the consumer install check runs against the
published package, so it happens after the tag, not before.** That is deliberate
— it is the only step that sees what a user actually gets, and it is the step
that caught both bugs in 3.0.0. If it fails, the fix is a patch release, so keep
the window between publishing and checking short.

## Before tagging

- [ ] `composer test` — full suite green
- [ ] `composer qc` — PHPStan level 8 and PHPMD clean
- [ ] `composer coverage-check` — coverage has not dropped below the floor
      (needs the pcov or xdebug extension; CI runs this on every PR)
- [ ] `composer validate --strict` — manifest is valid
- [ ] CI is green **on the merge commit you intend to tag**, not just on the
      feature branch. A branch can pass and the merge still break.
- [ ] `CHANGELOG.md` has a section for this version, dated
- [ ] `UPGRADING.md` covers any behaviour change, including in a patch release
- [ ] Working tree is clean and `master` is in sync with `origin/master`
- [ ] Version number matches the change: a behaviour change is not a patch
      unless you have documented it and decided to accept it

## Tag and publish

- [ ] `git tag -a <version> -m "..."` — annotated, not lightweight
- [ ] `git push origin <version>`
- [ ] Publish the GitHub release with notes
- [ ] Confirm Packagist has picked up the new version

## After publishing: the consumer install check

Install the published package the way a user installs it, in a directory outside
this repository, and run something real with it.

```bash
php scripts/verify-release.php 3.0.1
```

The script installs from Packagist into a temp directory, prints the versions
Composer actually resolved, and runs a dry run followed by a real write.

- [ ] The package installs from Packagist with no errors
- [ ] Optional dependencies resolve to **compatible** versions — check the
      resolved number, not just that install succeeded
- [ ] A dry run leaves no file behind
- [ ] A real run writes the expected output
- [ ] Spot-check the archive contents: `git archive --worktree-attributes
      --format=tar <version> | tar -t` should not contain `tests/`, `docs/`, or
      `.github/`

### Why this step exists

Two bugs shipped in 3.0.0 and neither was reachable from the test suite or CI:

1. **`suggest` does not constrain anything.** OpenSpout was listed only under
   `suggest`, so `composer require simsoft/data-flow openspout/openspout`
   resolved v5 — which removed the APIs this library calls, giving an immediate
   fatal. `require-dev` pinned `^4.0`, so every CI job installed a working v4 and
   stayed green. Only a fresh consumer install resolves the way a user's does.
   Upper bounds belong in `conflict`, which *is* enforced.

2. **A dry run left a 0-byte file.** `SpoutLoader` opened its writer in the
   constructor, and opening a writer creates the file on disk. There was no
   dry-run test for that loader, so nothing caught it.

The shared root cause is that the test suite runs against the working tree with
dev dependencies pinned. It cannot see resolution behaviour, packaging, or
anything that depends on how the package is consumed. This step can.

## Coverage

```bash
composer coverage        # reports only
composer coverage-check  # reports, then enforce the floor
```

Needs pcov or xdebug, plus the `zip` and `gd` extensions — without them the
spreadsheet tests error out and coverage reads ~2.5 points low, which looks like
a regression but is not one. CI installs all of these. Writes `coverage.xml`
(clover) and `coverage/` (HTML), both gitignored; CI uploads the HTML as an
artifact.

The floor in `scripts/coverage-check.php` is a **ratchet, not a target**: it was
set at the coverage that existed when it was introduced, so it catches
regressions without demanding tests for untouched code. Raise it when coverage
genuinely improves. Do not lower it to make a build pass — if a PR drops
coverage, either add tests or say in the PR why the drop is acceptable.

Note that coverage measures which lines *ran*, not whether they were meaningfully
asserted on. The dry-run bug in 3.0.0 sat in a method the suite executed; what
was missing was a test asserting the file was absent. Treat a high number as
weak evidence, and the consumer install check below as the stronger one.

## If the check fails

Do not delete or move a published tag — people and caches may already have it.
Fix forward with a patch release.

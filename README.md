# ddev-quality

DDEV add-on with tests, static analysis and code style of Cyberhouse TYPO3 projects based on
[typo3base](https://bitbucket.org/cyberhouse/typo3base): phpunit, codeception, phpstan and php-cs-fixer in `.quality/`,
an isolated composer root of the project.

| File | Purpose |
|---|---|
| `commands/host/qa` | `ddev qa unit\|functional\|acceptance\|analyse\|baseline\|rector\|fractor\|migrations\|cs\|fix\|quick [options of the tool]`, starts the selenium of the acceptance tests |
| `quality/qa` | the runner of `ddev qa` in the web container |
| `quality/codeception/*` | support of the acceptance tests: actor, test instance with every installed extension, router of the PHP server |
| `docker-compose.quality.yaml` | `.quality/vendor` and `.quality/public` in docker volumes, the test instances in a tmpfs |

## Installation / update

```
ddev add-on get cyberhouse/ddev-quality --version vX.Y.Z
```

It requires [ddev-typo3base](https://github.com/cyberhouse/ddev-typo3base) (installed with it if missing), v1.4.0 or
newer for pipelines. The files are copied to `.ddev/` and committed with the project, so every project is pinned to a
version. An update is the same command with a newer version.

## Configuration (`.quality/` of the project)

The add-on contains the logic, the configuration belongs to the project:

| File | Content |
|---|---|
| `composer.json` | the root package of the project (by its `name`) and the QA packages, installed on top of the project's `composer.lock`, so the checks run on the deployed versions - packages of that lock (e.g. `typo3/testing-framework` as `require-dev` for the IDE) keep its version |
| `phpunit/UnitTests.xml`, `phpunit/FunctionalTests.xml` | test suites, e.g. `ext/*/Tests/Unit` |
| `codeception/codeception.yml` | acceptance suite: the tests of the site package, `support: ../../.ddev/quality/codeception` and the `Environment` extension |
| `phpstan/phpstan.neon`, `phpstan/baseline.neon` | static analysis |
| `php-cs-fixer/config.php` | code style |
| `rector/rector.php` | TYPO3 migrations of the PHP code, the TYPO3 version is raised with the TYPO3 update |
| `rector/fractor.php` | TYPO3 migrations of the non-PHP files, the TYPO3 version is raised with the TYPO3 update |

`.quality/vendor/.gitkeep` and `.quality/public/.gitkeep` are the mount points of the docker volumes.

The acceptance tests run on one TYPO3 instance per run on sqlite, set up by the `Environment` extension by convention:

- every extension the project installs, the same set production runs
- the records of every `Acceptance/Fixtures/*.csv` of the tests
- `Acceptance/Fixtures/sites` as site configuration, each entry of `Acceptance/Fixtures/fileadmin` in its fileadmin
- the production configuration (`config/system/additional.php`), the frontend (`BUILD_FRONTEND`) and the directories
  the project links into `public/` (`BUILD_LINKS`, e.g. the built frontend - `ddev build fe` first). Files like
  `config/.htaccess` are not linked: the setup of the instance writes its own and would overwrite them.

Further links and fixtures are added in `codeception.yml` (`pathsToLinkInTestInstance`, `csvDatabaseFixtures`).

`rector` and `fractor` are the tools of a TYPO3 update: raise the TYPO3 version of the level sets in `rector.php` and
`fractor.php`, run them with `ddev qa migrations --dry-run`, review and apply the changes (`ddev qa migrations`), then
`ddev qa fix`. In between, the dry run is a check of code using an API they migrate. They need `ssch/typo3-rector` and
`a9f/typo3-fractor` in `.quality/composer.json`, `migrations` skips one that is missing. typo3-rector does not
support Symfony 8 yet: as `.quality` installs on top of the project's `composer.lock`, the project keeps
`symfony/string` below 8 (`"conflict": {"symfony/string": ">=8.0"}` in its `composer.json`) until it does.

## Commands

```
ddev qa unit           # phpunit (options are passed on: ddev qa unit --filter TelUtil)
ddev qa functional     # phpunit on sqlite
ddev qa acceptance     # codeception in a real chrome, needs the built frontend (ddev build fe)
ddev qa analyse        # phpstan
ddev qa baseline       # regenerate the phpstan baseline
ddev qa rector         # TYPO3 migrations of the PHP code (--dry-run to only show them, ddev qa fix afterwards)
ddev qa fractor        # TYPO3 migrations of TypoScript, TSconfig, Fluid, FlexForms and YAML (--dry-run to only show them)
ddev qa migrations     # rector and fractor with the same options (--dry-run), each if installed
ddev qa cs             # php-cs-fixer and invisible characters
ddev qa fix            # php-cs-fixer, fixing
ddev qa quick          # every check but acceptance (migrations with --dry-run)
```

`migrations` and `quick` run their targets one after another, continue after a failure and list the failed ones.

The reports are written to `.quality/test-reports/`.

## Bitbucket pipeline

`.ddev/bitbucket/ddev.sh` of ddev-typo3base runs `qa` like ddev, selenium included. A push runs every check as a step
of its own, the acceptance tests can be started by hand after them (a manual step can't be part of a parallel group,
and only starts when the steps before are green). A deployment runs `quick` and the acceptance tests
in parallel to the builds, the deploy step only starts when all of them are green - one step for the checks, as they
already ran on every push and most of a step is its start (image and `.quality` installation). The acceptance step
builds the frontend first:

```yaml
quality: &QUALITY
  <<: *DDEV
  caches: [ composer ]
  artifacts: [ .quality/test-reports/** ]

steps:
  - step: &QUICKCHECKS
      <<: *QUALITY
      name: Quick Checks
      script: [ .ddev/bitbucket/ddev.sh qa quick ]
  - step: &ACCEPTANCE
      <<: *QUALITY
      name: Acceptance Tests
      caches: [ composer, node ]
      script: [ .ddev/bitbucket/ddev.sh build fe --prod, .ddev/bitbucket/ddev.sh qa acceptance ]

pipelines:
  default:
    - parallel:
        - step: { <<: *QUALITY, name: Unit Tests, script: [ .ddev/bitbucket/ddev.sh qa unit ] }
        - step: { <<: *QUALITY, name: Functional Tests, script: [ .ddev/bitbucket/ddev.sh qa functional ] }
        - step: { <<: *QUALITY, name: Static Analysis, script: [ .ddev/bitbucket/ddev.sh qa analyse ] }
        - step: { <<: *QUALITY, name: Code Style, script: [ .ddev/bitbucket/ddev.sh qa cs ] }
        - step: { <<: *QUALITY, name: TYPO3 Migrations, script: [ .ddev/bitbucket/ddev.sh qa migrations --dry-run ] }
    - step: { <<: *ACCEPTANCE, trigger: manual }
  custom:
    deploy-prod:
      - parallel:
          - step: *FRONTEND
          - step: *TYPO3
          - step: *QUICKCHECKS
          - step: *ACCEPTANCE
      - step: { name: Deploy to Prod, … }
```

The JUnit reports of `.quality/test-reports/` are shown as test results of the step.

`docker-compose.quality.yaml` is not applied in pipelines, the test instances are written to the clone dir.

## Project specific changes

Remove the `#ddev-generated` line of a file to change it in a project: DDEV no longer overwrites it on updates.

## Contributing

This repository is public: it contains logic only, never values.

# ArborXR - Unreleased Log

## Overview

This helper takes all JSON unreleased changelog files from the unreleased changes directory, merges them and creates or updates a UNRELEASED.md file with the new CHANGELOG entry that can be added to the CHANGELOG file at release time.

## Installation

This is a private repository. You will need to have access to the ArborXR GitHub account and add the following to your composer file.

```
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ArborXR/unreleased-log/"
        }
    ]
```
Then you can install.

```
$ composer require --dev arborxr/unreleased-log
```

## CI Pipeline Usage Configuration (GitLab + GitLabCI)

### Setting GitLab personal access token to GitLabCI

GitLab personal access token is required for sending merge requests to your repository.

1. Go to [your account's settings page](https://gitlab.com/profile/personal_access_tokens) and generate a personal access token with "api" scope
1. On GitLab dashboard, go to your application's "Settings" -> "CI /CD" -> "Environment variables"
1. Add an environment variable `GITLAB_API_PRIVATE_TOKEN` with your GitLab personal access token

### Configure .gitlab-ci.yml

Configure your `.gitlab-ci.yml` to run `unreleased-log-helper`, for example:

```yaml
stages:
  # ...
  - fixer

# ...

fixer-commit:
  image: composer:latest
  stage: fixer
  script:
    - "composer install"
    - "$COMPOSER_HOME/vendor/bin/unreleased-log-helper <username> <email> <output_path> <update changelog file [true|false(default)]> <skip git commands [true|false(default)]>"
```

NOTE: Please make sure you replace `<username>` and `<email>` with yours.

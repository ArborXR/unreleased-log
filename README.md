# ArborXR - Unreleased Log

## Overview

This helper takes all individual unreleased JSON changelog files from the `unreleased-changes` directory, merges them and creates or updates a `UNRELEASED.md` file with the new CHANGELOG entry that can be added to the `CHANGELOG.md` file at release time.

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

## How to Run

```
./vendor/bin/unreleased-log-helper [options]

Options:

-h, --help                     Display help for the given command. When no command is given display help for the list command
-c, --changelog-write          Write new unreleased changelog section to the actual CHANGELOG.md file (default: false)
-s, --skip-cleanup             Skip the cleanup process that removes all of the JSON files that were merged
-o, --output-dir=OUTPUT-DIR    If specified, use the given directory as the output directory (default: "./")
-f, --files-dir=FILES-DIR      If specified, use the given directory as the individual changelog JSON files directory (default: "./")
```

## Usage

### Individual Branch Unreleased Changelog Files

This package assumes that there is an `unreleased-changes` directory that houses all the JSON files that contain the unreleased changelog entries. The structure of the file should be as follows:

```json
{
    "unreleased": {
        "added": [],
        "fixed": [],
        "changed": [],
        "removed": [],
        "security": [],
        "deprecated": []
    }
}
```

How you choose to name your file is up to you, but as a best practice, your filename should match your git branch name. If your branch name includes a directory separator character `/`, that is not permitted and should be excluded from the filename.

#### Shortcut Story Support (Optional)

If your filename includes your Shortcut ticket information (e.g. `sc-87954`), when all the individual json files are merged, files each entry from one individual file with a link to the shortcut ticket.

### Skip Cleanup (Optional)

By default, the script will cleanup the individual JSON files by deleted them after they have been merged. By using this option, you can skip the cleanup and keep the files.

### Final Changelog Interaction (Optional)

You are able to decide whether to add the new unreleased section to the top of the existing CHANGELOG.md file.
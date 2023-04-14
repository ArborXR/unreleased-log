<?php

namespace ArborXR\UnreleasedLog;

class UnreleasedLogHelper
{
    public string $outputPath = './';

    public bool $updateChangelogFile = false;

    public bool $skipCleanup = false;

    public string $changelogFilename = 'CHANGELOG.md';

    public string $unreleasedChangesPath = './unreleased-changes';

    public string $unreleasedChangelogFilename = 'UNRELEASED.md';

    /**
     * @return void
     */
    public static function main()
    {
        fwrite(STDOUT, '-------------------------------------------------' . PHP_EOL);
        fwrite(STDOUT, 'UNRELEASED LOG GENERATION STARTED' . PHP_EOL);
        fwrite(STDOUT, '-------------------------------------------------' . PHP_EOL);

        $command = new static;
        $command->setup();
        $command->generate();
        $command->cleanup();

        fwrite(STDOUT, PHP_EOL . 'Unreleased Changelog Generation Complete' . PHP_EOL);
    }

    public function getHelpOutput()
    {
        return <<<OUPTUT
Unreleased Log

Usage:
  ./vendor/bin/unreleased-log-helper [options] [arguments]

Options:
  -h, --help                     Display help for the given command. When no command is given display help for the list command
  -c, --changelog-write          Write new unreleased changelog section to the actual CHANGELOG.md file (default: false)
  -s, --skip-cleanup             Skip the cleanup process that removes all of the JSON files that were merged (default: false)
  -o, --output-dir=OUTPUT-DIR    If specified, use the given directory as the output directory (default: "./")
  -f, --files-dir=FILES-DIR      If specified, use the given directory as the individual changelog JSON files directory (default: "./")

OUPTUT;
    }

    public function setup(): void
    {
        $short_options = "hcso:f:";
        $long_options = ["help", "changelog-write", "output-dir:", "files-dir:"];
        $options = getopt($short_options, $long_options);

        if (isset($options['h']) || isset($options['help'])) {
            fwrite(STDOUT, $this->getHelpOutput());
            exit();
        }

        $this->updateChangelogFile = (isset($options['c']) || isset($options['changelog-write']));

        $this->skipCleanup = (isset($options['s']) || isset($options['skip-cleanup']));

        if (isset($options['o']) || isset($options['output-dir'])) {
            $this->outputPath = isset($options['o']) ? $options['o'] : $options['output-dir'];
        }

        if (isset($options['f']) || isset($options['files-dir'])) {
            $this->unreleasedChangesPath = isset($options['f']) ? $options['f'] : $options['files-dir'];
        }
    }

    public function generate()
    {
        $unreleasedNotes = $this->mergedReleaseNotes();
        if (empty($unreleasedNotes)) {
            return;
        }

        fwrite(STDOUT, ' > Creating Markdown Section for Unreleased Changes' . PHP_EOL);

        $unreleasedMarkdown = trim($this->createUnreleasedMarkdown($unreleasedNotes, 2));

        if (!$this->createUnreleasedDocument($unreleasedMarkdown)) {
            fwrite(STDERR, '     * Error Creating Unreleased Document or nothing to write' . PHP_EOL);
        }

        if ($this->updateChangelogFile) {
            if (!$this->updateChangelogFile($unreleasedMarkdown)) {
                fwrite(STDERR, '     * Error Updating Changelog Document or nothing to write' . PHP_EOL);
            }
        } else {
            fwrite(STDOUT, ' > Skipping Changelog Update' . PHP_EOL);
        }
    }

    public function cleanup()
    {
        if ($this->skipCleanup) {
            fwrite(STDOUT, ' > Skipping Cleanup' . PHP_EOL);
            return;
        }

        fwrite(STDOUT, ' > Cleaning up individual changelog files' . PHP_EOL);

        array_map('unlink', glob($this->unreleasedChangesPath . '/*.json'));
    }

    protected function mergedReleaseNotes(): array
    {
        fwrite(STDOUT, ' > Merging Unreleased Release Note Files' . PHP_EOL);

        $mergedReleaseNotes = [];
        $releaseNoteFilenames = glob($this->unreleasedChangesPath . '/*.json');
        if (count($releaseNoteFilenames) === 0) {
            fwrite(STDOUT, '     - No Unreleased Release Note Files To Process' . PHP_EOL);
            return [];
        }

        $orderedReleaseNotes = [];
        $unTicketCount = 0;

        foreach ($releaseNoteFilenames as $releaseNoteFilename) {
            fwrite(STDOUT, '   > Processing file: ' . $releaseNoteFilename . PHP_EOL);
            preg_match('/(sc\-[0-9]+)/', $releaseNoteFilename, $matches);
            $ticket = $matches[0] ?? null;
            if (!$ticket) {
                $unTicketCount++;
            }

            $jsonData = file_get_contents($releaseNoteFilename);
            if (!$this->isValidJson($jsonData)) {
                fwrite(STDERR, '     * ERROR: Cannot proceed. The file does not contain valid JSON. Fix file and try again.' . PHP_EOL);
                exit();
            }

            $releaseNotes = $this->addTicketRelation(json_decode(file_get_contents($releaseNoteFilename), true), $ticket);
            $orderedReleaseNotes[str_replace('sc-', '', $ticket ?? 'zzz' . $unTicketCount)] = $releaseNotes;
        }

        ksort($orderedReleaseNotes);

        foreach ($orderedReleaseNotes as $orderedReleaseNote) {
            $mergedReleaseNotes = array_merge_recursive($mergedReleaseNotes, $orderedReleaseNote);
        }

        return $mergedReleaseNotes;
    }

    protected function createUnreleasedMarkdown($releaseNotes, $headingLevel = 1): string
    {
        $markdown = '';

        foreach ($releaseNotes as $title => $sections) {
            if (is_array($sections)) {
                $titlePrefix = str_pad('', $headingLevel, '#');
                $title = $headingLevel === 2 ? '[' . ucfirst($title) . ']' : ucfirst($title);
                $markdown .= $titlePrefix . ' ' . $title . "\n\n";
                $markdown .= $this->createUnreleasedMarkdown($sections, $headingLevel + 1);
                $markdown .= "\n";
            } else {
                $markdown .= ' - ' . $sections . "\n";
            }
        }

        return $markdown;
    }

    protected function addTicketRelation(array $releaseNotes, ?string $ticketRelation = null)
    {
        if (!$ticketRelation) {
            return $releaseNotes;
        }

        foreach ($releaseNotes as $key => $value) {
            if (is_array($value)) {
                $releaseNotes[$key] = $this->addTicketRelation($value, $ticketRelation);
            } else {
                $releaseNotes[$key] = $value . ' [[' . $ticketRelation . '](https://app.shortcut.com/springboardvr/story/' . explode('-', $ticketRelation)[1] . ')]';
            }
        }

        return $releaseNotes;
    }

    protected function createUnreleasedDocument($markdown): bool|int
    {
        fwrite(STDOUT, '   > Saving ' . $this->unreleasedChangelogFilename . ' File' . PHP_EOL);

        $handle = fopen($this->outputPath . '/' . $this->unreleasedChangelogFilename, 'w+');

        $written = fwrite($handle, $markdown);

        fclose($handle);

        return $written;
    }

    protected function updateChangelogFile($markdownToInsert): bool|int
    {
        fwrite(STDOUT, '   > Updating the ' . $this->changelogFilename . ' file' . PHP_EOL);

        $existingChangelog = file_get_contents($this->outputPath . '/' . $this->changelogFilename);
        $changelogLines = explode("\n", $existingChangelog);
        $newChangelogLines = [];
        $insertCompleted = false;

        foreach ($changelogLines as $changelogLine) {
            if (substr($changelogLine, 0, 2) === '##' && !$insertCompleted) {
                $unreleasedLines = explode("\n", $markdownToInsert);
                foreach ($unreleasedLines as $unreleasedLine) {
                    $newChangelogLines[] = $unreleasedLine;
                }
                $newChangelogLines[] = '';
                $newChangelogLines[] = '---';
                $newChangelogLines[] = '';

                $insertCompleted = true;
            }

            $newChangelogLines[] = $changelogLine;
        }

        $newWrite = implode("\n", $newChangelogLines);

        $handle = fopen($this->outputPath . '/' . $this->changelogFilename, 'w+');

        $written = fwrite($handle, $newWrite);

        fclose($handle);

        return $written;
    }

    protected function isValidJson($data): bool
    {
        if (!empty($data)) {
            return is_string($data) &&
            is_array(json_decode($data, true)) ? true : false;
        }
        return false;
    }
}

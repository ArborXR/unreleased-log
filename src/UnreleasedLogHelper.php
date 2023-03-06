<?php

namespace ArborXR\UnreleasedLog;

class UnreleasedLogHelper
{
    public string $committerName = '';

    public string $committerEmail = '';

    public string $outputPath = __DIR__;

    public string $changelogFilename = 'CHANGELOG.md';

    public string $unreleasedChangesPath = __DIR__.'/unreleased-changes';

    public string $unreleasedChangelogFilename = 'UNRELEASED.md';

    public bool $updateChangelogFile = false;

    public bool $skipGitCommands = true;

    /**
     * @return void
     */
    public static function main()
    {
        $command = new static;
        $command->setup();
        $command->run();
    }

    public function setup(): void
    {
        $argv = $_SERVER['argv'];
        $argvCount = count($argv);

        if ($argvCount !== 3 && $argvCount !== 4 && $argvCount !== 5 && $argvCount !== 6) {
            fwrite(STDERR, 'Invalid arguments.'.PHP_EOL);
            exit(1);
        }

        [, $this->committerName, $this->committerEmail, $this->outputPath] = $argv;

        $this->unreleasedChangesPath = $this->outputPath.'unreleased-changes';

        $this->updateChangelogFile = isset($argv[4]) && $argv[4] === 'true';
        $this->skipGitCommands = isset($argv[5]) && $argv[5] === 'true';
    }

    /**
     * @param  array  $argv
     * @return void
     */
    public function run()
    {
        $this->generate();

        system('git clean -df');

        if (strpos(system('git status -sb'), '.md') === false) {
            fwrite(STDOUT, 'No changes.'.PHP_EOL);
            exit(0);
        } else {
            fwrite(STDOUT, 'New changes to be committed.'.PHP_EOL);
        }

        if ($this->skipGitCommands) {
            exit(1);
        }

        $this->setupGitCommitter($this->committerName, $this->committerEmail);
        $this->createCommit();
    }

    public function generate()
    {
        fwrite(STDOUT, 'Unreleased Changelog Generation Started'.PHP_EOL);
        fwrite(STDOUT, '-------------------------------------------------'.PHP_EOL);

        $unreleasedNotes = $this->mergedReleaseNotes();
        file_put_contents($this->unreleasedChangesPath.'/merged-changes.json', json_encode($unreleasedNotes));

        fwrite(STDOUT, 'Creating Markdown Section for Unreleased Changes'.PHP_EOL);

        $unreleasedMarkdown = trim($this->createUnreleasedMarkdown($unreleasedNotes, 2));

        if (!$this->createUnreleasedDocument($unreleasedMarkdown)) {
            fwrite(STDERR, '*** Error Creating Unreleased Document or nothing to write'.PHP_EOL);
        }

        if ($this->updateChangelogFile) {
            if (!$this->updateChangelogFile($unreleasedMarkdown)) {
                fwrite(STDERR, '*** Error Updating Changelog Document or nothing to write'.PHP_EOL);
            }
        }

        echo PHP_EOL.'Unreleased Changelog Generation Complete'.PHP_EOL;
    }

    protected function mergedReleaseNotes(): array
    {
        fwrite(STDOUT, 'Merging Unreleased Release Note Files'.PHP_EOL);

        $mergedReleaseNotes = [];
        $releaseNoteFilenames = glob($this->unreleasedChangesPath.'/*.json');

        foreach ($releaseNoteFilenames as $releaseNoteFilename) {
            preg_match('/(sc\-[0-9]+)/', $releaseNoteFilename, $matches);
            $ticket = $matches[0] ?? null;

            $releaseNotes = $this->addTicketRelation(json_decode(file_get_contents($releaseNoteFilename), true), $ticket);

            $mergedReleaseNotes = array_merge_recursive($mergedReleaseNotes, $releaseNotes);
        }

        return $mergedReleaseNotes;
    }

    protected function createUnreleasedMarkdown($releaseNotes, $headingLevel = 1): string
    {
        $markdown = '';

        foreach ($releaseNotes as $title => $sections) {
            if (is_array($sections)) {
                $titlePrefix = str_pad('', $headingLevel, '#');
                $title = $headingLevel === 2 ? '['.ucfirst($title).']' : ucfirst($title);
                $markdown .= $titlePrefix.' '.$title."\n\n";
                $markdown .= $this->createUnreleasedMarkdown($sections, $headingLevel + 1);
                $markdown .= "\n";
            } else {
                $markdown .= ' - '.$sections."\n";
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
                $releaseNotes[$key] = $value.' [['.$ticketRelation.'](https://app.shortcut.com/springboardvr/story/'.explode('-', $ticketRelation)[1].')]';
            }
        }

        return $releaseNotes;
    }

    protected function createUnreleasedDocument($markdown): bool|int
    {
        fwrite(STDOUT, 'Saving '.$this->unreleasedChangelogFilename.' File'.PHP_EOL);

        $handle = fopen($this->outputPath.'/'.$this->unreleasedChangelogFilename, 'w+');

        $written = fwrite($handle, $markdown);

        fclose($handle);

        return $written;
    }

    protected function updateChangelogFile($markdownToInsert): bool|int
    {
        fwrite(STDOUT, 'Updating the '.$this->changelogFilename.' file'.PHP_EOL);

        $existingChangelog = file_get_contents($this->outputPath.'/'.$this->changelogFilename);
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

        $handle = fopen($this->outputPath.'/'.$this->changelogFilename, 'w+');

        $written = fwrite($handle, $newWrite);

        fclose($handle);

        return $written;
    }

    /**
     * @param  string  $name
     * @param  string  $email
     * @return void
     */
    private function setupGitCommitter($name, $email): void
    {
        system("git config user.name {$name}");
        system("git config user.email {$email}");
    }

    /**
     * @return void
     */
    private function createCommit(): void
    {
        if ((bool) getenv('GITHUB_ACTIONS')) {
            $branch = substr(system('git branch'), 2);
            $accessToken = getenv('GITHUB_TOKEN');
            $repositoryName = getenv('GITHUB_REPOSITORY');

            system("git remote set-url origin https://{$accessToken}@github.com/{$repositoryName}/");
            system('git add -u');
            system('git commit -m "ci: unreleased changelog published"');
            system("git push -q origin {$branch}");
        } elseif ((bool) getenv('CIRCLECI')) {
            $branch = getenv('CIRCLE_BRANCH');
            $accessToken = getenv('GITHUB_ACCESS_TOKEN');
            $repositoryName = getenv('CIRCLE_PROJECT_REPONAME');
            $repositoryUserName = getenv('CIRCLE_PROJECT_USERNAME');

            system("git remote set-url origin https://{$accessToken}@github.com/{$repositoryUserName}/{$repositoryName}/");
            system('git add -u');
            system('git commit -m "ci: unreleased changelog published"');
            system("git push -q origin {$branch}");
        } elseif ((bool) getenv('GITLAB_CI')) {
            $branch = getenv('CI_COMMIT_REF_NAME');
            $token = getenv('GITLAB_API_PRIVATE_TOKEN');
            $repositoryUrl = getenv('CI_REPOSITORY_URL');
            preg_match('/https:\/\/gitlab-ci-token:(.*)@(.*)/', $repositoryUrl, $matches);

            system("git remote set-url origin https://gitlab-ci-token:{$token}@{$matches[2]}");
            system("git checkout {$branch}");
            system('git add -u');
            system('git commit -m "ci: unreleased changelog published"');
            system("git push -q origin {$branch}");
        }
    }

}

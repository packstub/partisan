<?php

namespace Packstub\Partisan\Console;

use Illuminate\Console\Command;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    use InteractsWithPackage;

    protected $signature = 'partisan:install
        {--link : Create the artisan entry script without asking}
        {--alias : Add the pa shortcut to your shell profile without asking}';

    protected $description = 'Set up the optional artisan entry script and pa shell shortcut';

    public function handle(): int
    {
        $this->installArtisanScript();
        $this->installShellShortcut();

        if (! $this->input->isInteractive() && ! $this->option('link') && ! $this->option('alias')) {
            $this->components->info('Running non-interactively — pass --link and/or --alias to choose what to set up.');
        }

        return self::SUCCESS;
    }

    protected function installArtisanScript(): void
    {
        $root = $this->package()->rootPath;
        $path = $root.'/artisan';

        if (file_exists($path)) {
            $this->components->twoColumnDetail('artisan script', 'already exists — skipped');

            return;
        }

        $wanted = $this->option('link') || (
            $this->input->isInteractive()
            && confirm('Create an artisan script so `php artisan make:…` works in this package?')
        );

        if (! $wanted) {
            return;
        }

        file_put_contents($path, "#!/usr/bin/env php\n<?php require __DIR__.'/vendor/bin/partisan';\n");
        chmod($path, 0755);

        $this->components->info('artisan script created — try `php artisan make:model Invoice`.');

        if (! is_file($root.'/vendor/bin/partisan')) {
            $this->components->warn('vendor/bin/partisan is missing — install dev dependencies (`composer install`) before using the script.');
        }

        $this->offerGitignore($root);
    }

    protected function offerGitignore(string $root): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $answer = select(
            label: 'Commit the artisan script, or keep it out of git?',
            options: [
                'commit' => 'Commit it — every contributor gets php artisan for free',
                'ignore' => 'Add /artisan to .gitignore — keep it as a personal convenience',
            ],
            default: 'commit',
        );

        if ($answer !== 'ignore') {
            return;
        }

        $gitignore = $root.'/.gitignore';
        $contents = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';

        if (preg_match('/^\/?artisan$/m', $contents)) {
            return;
        }

        file_put_contents($gitignore, ($contents === '' ? '' : rtrim($contents)."\n")."/artisan\n");

        $this->components->info('/artisan added to .gitignore.');
    }

    protected function installShellShortcut(): void
    {
        $interactive = $this->input->isInteractive();

        if (! $interactive && ! $this->option('alias')) {
            return;
        }

        $style = 'function';

        if ($interactive) {
            $answer = select(
                label: 'Add a global pa shortcut to your shell profile?',
                options: [
                    'function' => 'Yes — a pa() function that finds the nearest vendor/bin/partisan (works from subdirectories)',
                    'alias' => 'Yes — a plain alias for vendor/bin/partisan (package root only)',
                    'skip' => 'No',
                ],
                default: 'function',
            );

            if ($answer === 'skip') {
                return;
            }

            $style = $answer;
        }

        [$profile, $flavor] = $this->shellProfile();
        $snippet = $this->paSnippet($style, $flavor);

        if ($profile === null) {
            $this->components->info('Could not detect your shell profile — add this to it yourself:');
            note($snippet);

            return;
        }

        $existing = is_file($profile) ? (string) file_get_contents($profile) : '';

        if (preg_match('/^\s*(alias pa[= ]|pa\s*\(\)|function pa\b)/m', $existing)) {
            $this->components->twoColumnDetail('pa shortcut', basename($profile).' already defines pa — skipped');

            return;
        }

        if ($interactive && ! confirm("Append the pa {$style} to {$profile}?")) {
            $this->components->info('No problem — here it is to add wherever you like:');
            note($snippet);

            return;
        }

        if (! is_dir(dirname($profile))) {
            mkdir(dirname($profile), 0755, true);
        }

        file_put_contents(
            $profile,
            ($existing === '' ? '' : rtrim($existing)."\n\n")."# Added by partisan:install\n{$snippet}\n",
        );

        $this->components->info("pa shortcut added to {$profile} — restart your shell or `source {$profile}` to use it.");
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    protected function shellProfile(): array
    {
        $home = getenv('HOME');
        $shell = basename((string) getenv('SHELL'));

        if (! is_string($home) || $home === '') {
            return [null, 'sh'];
        }

        return match ($shell) {
            'zsh' => [$home.'/.zshrc', 'sh'],
            'bash' => [$home.'/.bashrc', 'sh'],
            'fish' => [$home.'/.config/fish/config.fish', 'fish'],
            default => [null, 'sh'],
        };
    }

    protected function paSnippet(string $style, string $flavor): string
    {
        if ($style === 'alias') {
            return 'alias pa="vendor/bin/partisan"';
        }

        if ($flavor === 'fish') {
            return <<<'FISH'
                function pa
                    set -l dir $PWD
                    while test "$dir" != /
                        if test -x "$dir/vendor/bin/partisan"
                            "$dir/vendor/bin/partisan" $argv
                            return
                        end
                        set dir (dirname $dir)
                    end
                    echo "no vendor/bin/partisan here — composer require --dev packstub/partisan" >&2
                    return 1
                end
                FISH;
        }

        return <<<'SH'
            pa() {
              local dir=$PWD
              while [ "$dir" != "/" ]; do
                [ -x "$dir/vendor/bin/partisan" ] && { "$dir/vendor/bin/partisan" "$@"; return; }
                dir=$(dirname "$dir")
              done
              echo "no vendor/bin/partisan here — composer require --dev packstub/partisan" >&2
              return 1
            }
            SH;
    }
}

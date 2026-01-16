<?php

namespace Websyspro\WpEngine\Installer;

class WordPressInstaller
{
    private string $version;
    private string $svnUrl;
    private string $tempDir;
    private string $targetDir;

    public function __construct()
    {
        $this->version   = $this->resolveVersion();
        $this->svnUrl    = "https://core.svn.wordpress.org/tags/{$this->version}";
        $this->tempDir   = sys_get_temp_dir() . "/wp-{$this->version}";
        $this->targetDir = getcwd() . "/vendor/wpcore";
    }

    public function install(): void
    {
        if (is_dir($this->targetDir)) {
            echo "✔ WordPress core already installed\n";
            return;
        }

        $this->downloadFromSvn();
        $this->installCore();

        echo "✔ WordPress {$this->version} installed successfully\n";
    }

    private function resolveVersion(): string
    {
        $composer = json_decode(
            file_get_contents(getcwd() . '/composer.json'),
            true
        );

        return $composer['extra']['wp-engine']['wordpress'] ?? '6.9';
    }

    private function downloadFromSvn(): void
    {
        if (is_dir($this->tempDir)) {
            return;
        }

        echo "⬇ Downloading WordPress {$this->version} from SVN...\n";

        $cmd = sprintf(
            'svn export %s %s',
            escapeshellarg($this->svnUrl),
            escapeshellarg($this->tempDir)
        );

        passthru($cmd);
    }

    private function installCore(): void
    {
        mkdir($this->targetDir, 0777, true);

        foreach (['wp-admin', 'wp-includes'] as $dir) {
            $this->copyDir(
                "{$this->tempDir}/{$dir}",
                "{$this->targetDir}/{$dir}"
            );
        }

        foreach (glob("{$this->tempDir}/*.php") as $file) {
            copy($file, "{$this->targetDir}/" . basename($file));
        }

        copy(
            "{$this->tempDir}/license.txt",
            "{$this->targetDir}/license.txt"
        );
    }

    private function copyDir(string $src, string $dst): void
    {
        mkdir($dst, 0777, true);

        foreach (scandir($src) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $from = "$src/$file";
            $to   = "$dst/$file";

            if (is_dir($from)) {
                $this->copyDir($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }
}

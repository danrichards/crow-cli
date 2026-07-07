<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BrowserLauncher
{
    public function open(string $url): bool
    {
        try {
            $command = $this->command($url);

            if ($command === null) {
                return false;
            }

            return (new Process($command))->run() === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, string>|null */
    private function command(string $url): ?array
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return ['open', $url];
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return ['cmd', '/c', 'start', '', $url];
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        $binary = collect(['xdg-open', 'wslview'])
            ->first(fn (string $binary): bool => (new ExecutableFinder)->find($binary) !== null);

        return $binary ? [$binary, $url] : null;
    }
}

<?php

namespace Websyspro\WpEngine;

class ComposerConfigReader
{
    public static function getWpEngineConfig(string $composerPath): ?array
    {
        if (!file_exists($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);
        $data = json_decode($content, true);

        return $data['extra']['wp-engine'] ?? null;
    }
}

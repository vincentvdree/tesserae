<?php

declare(strict_types=1);

namespace Tesserae\Development;

class DevelopmentLoader
{
    public static function register(): void
    {
        add_filter('tesserae/block_sources', [__CLASS__, 'blockSources']);
    }

    /**
     * @param string[] $sources
     *
     * @return string[]
     */
    public static function blockSources(array $sources): array
    {
        $plugin_dir = plugin_dir_path(__DIR__).'../examples/blocks';
        $plugin_url = plugins_url().'tesserae/examples/blocks';

        return [$plugin_dir => $plugin_url];
    }
}

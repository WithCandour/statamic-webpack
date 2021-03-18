<?php

namespace WithCandour\StatamicWebpack\Tags;

use Statamic\Facades\File;
use Statamic\Facades\Path;
use Statamic\Facades\URL;
use Statamic\Tags\Tags;

class WebpackTags extends Tags
{
    /**
     * @var string
     */
    protected static $handle = 'webpack';

    /**
     * @param bool $legacy
     * @return object|null
     */
    private function stats(bool $legacy = false)
    {
        // Build a path (relative to public/)
        $path = Path::assemble(
            config( $legacy ? 'statamic.webpack.legacy_dist_directory' : 'statamic.webpack.modern_dist_directory' ),
            config('statamic.webpack.stats_filename')
        );

        $disk = File::disk(config('statamic.webpack.js_disk'));

        if($disk->exists($path)) {
            return json_decode($disk->get($path));
        }

        return null;
    }

    /**
     * Get all assets from the stats info
     *
     * @param array $chunks
     * @param string $extension
     * @param bool $legacy
     */
    private function assetsByExtension($chunks, string $extension = 'js', bool $legacy = false)
    {
        $files = null;
        $stats = $this->stats($legacy);

        if (!empty($stats) && isset($stats->assetsByChunkName)) {
            $files = collect($chunks)
                ->filter(function ($chunk) use ($stats) {
                    return isset($stats->assetsByChunkName->{$chunk});
                })
                ->flatMap(function ($chunk) use ($stats, $extension) {
                    $assets = $stats->assetsByChunkName->{$chunk};

                    if (!is_array($assets)) {
                        $assets = [$assets];
                    }

                    return collect($assets)
                        ->filter(function ($asset) use ($extension) {
                            return pathinfo($asset, PATHINFO_EXTENSION) === $extension;
                        })
                        ->map(function ($asset) {
                            return basename($asset);
                        });
                });
        }

        if (empty($files)) {
            return null;
        }

        return $files;
    }


    /**
     * Return a stylesheet <link />
     *
     * @return string
     */
    public function styles()
    {
        /**
         * @var string
         */
        $chunk = $this->params->get('chunk', 'main');

        /**
         * @var array
         */
        $chunks = ['runtime~'.$chunk, $chunk];

        $assets = $this->assetsByExtension($chunks, 'css', false);
        $content = collect($assets)
            ->map(function ($asset) {
                $path = Path::assemble(
                    config('statamic.webpack.css_directory'),
                    config('statamic.webpack.modern_dist_directory'),
                    $asset
                );

                $url = URL::buildFromPath($path);

                return '<link rel="stylesheet" href="'.$url.'" />';
            })
            ->implode("\n");

        return $content;
    }

    /**
     * Return the <script /> tags for this chunk
     *
     * @return string
     */
    public function scripts()
    {
        /**
         * @var string
         */
        $chunk = $this->params->get('chunk', 'main');

        /**
         * @var bool
         */
        $preload = $this->params->bool('preload', false);

        /**
         * @var array
         */
        $chunks = ['runtime~'.$chunk, $chunk];

        $assets = $this->assetsByExtension($chunks, 'js', false);
        $content = collect($assets)
            ->map(function ($asset) use ($preload) {
                $path = Path::assemble(
                    config('statamic.webpack.js_directory'),
                    config('statamic.webpack.modern_dist_directory'),
                    $asset
                );

                $url = URL::buildFromPath($path);

                if ($preload === 'module') {
                    return '<link rel="modulepreload" href="'.$url.'">';
                }

                if ($preload === true) {
                    return '<link rel="preload" as="script" href="'.$url.'">';
                }

                return '<script type="module" src="'.$url.'"></script>';

            })
            ->implode("\n");

        $legacyAssets = $this->assetsByExtension($chunks, 'js', true);
        $content .= collect($legacyAssets)
            ->map(function ($asset) use ($preload) {
                $path = Path::assemble(
                    '/',
                    config('statamic.webpack.js_directory'),
                    config('statamic.webpack.legacy_dist_directory'),
                    $asset
                );

                $url = URL::buildFromPath($path);

                if ($preload !== false) {
                    if ($preload === 'module') {
                        return null;
                    }

                    return '<link rel="preload" as="script" href="'.$url.'">';
                }

                return '<script nomodule src="'.$url.'"></script>';

            })
            ->implode("\n");

        return $content;
    }
}

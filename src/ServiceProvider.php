<?php

namespace WithCandour\StatamicWebpack;

use WithCandour\StatamicWebpack\Tags\WebpackTags;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{

    /**
     * @inheritdoc
     */
    protected $tags = [
        WebpackTags::class
    ];

    /**
     * @inheritdoc
     */
    public function boot()
    {
        parent::boot();

        $this->mergeConfigFrom(__DIR__ . '/../config/statamic/webpack.php', 'statamic.webpack');
        $this->publishes([
            __DIR__ . '/../config/statamic/webpack.php' => config_path('statamic/webpack.php'),
        ], 'config');
    }
}

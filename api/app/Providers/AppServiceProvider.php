<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\AI\AnthropicDriver;
use App\Services\AI\OpenAIDriver;
use Illuminate\Support\ServiceProvider;
use OpenAI\Client as OpenAIClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIClient::class, function () {
            return \OpenAI::client(config('ai.drivers.openai.api_key', ''));
        });

        $this->app->singleton(AnthropicClient::class, function () {
            return \Anthropic::client(config('ai.drivers.anthropic.api_key', ''));
        });

        $this->app->singleton(OpenAIDriver::class, function ($app) {
            return new OpenAIDriver($app->make(OpenAIClient::class));
        });

        $this->app->singleton(AnthropicDriver::class, function ($app) {
            return new AnthropicDriver($app->make(AnthropicClient::class));
        });
    }

    public function boot(): void
    {
        //
    }
}

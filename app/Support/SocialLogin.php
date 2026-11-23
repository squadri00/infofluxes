<?php

namespace App\Support;

/** Which "Continue with ..." buttons are on. A button shows only when its keys are in the .env file. */
class SocialLogin
{
    public const PROVIDERS = ['google' => 'Google', 'facebook' => 'Facebook', 'microsoft' => 'Microsoft'];

    public static function enabled(string $provider): bool
    {
        // Microsoft is only offered for personal accounts (see config/services.php), because only those emails are confirmed by Microsoft.
        if ($provider === 'microsoft' && config('services.microsoft.tenant') !== 'consumers') {
            return false;
        }

        return isset(self::PROVIDERS[$provider]) && filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret"));
    }

    /** @return array<string, string> provider => name, of the buttons that are on */
    public static function available(): array
    {
        return array_filter(self::PROVIDERS, fn ($name, $key) => static::enabled($key), ARRAY_FILTER_USE_BOTH);
    }
}

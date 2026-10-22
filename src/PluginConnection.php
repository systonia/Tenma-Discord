<?php

namespace Tenma\Discord;

use Messaging\Channel;
use Tenma\Plugin;

/** Talks to libtenma_discord, loaded with `tenma --plugin`. */
final class PluginConnection implements Connection
{
    private const string PLUGIN = 'discord';

    public function call(string $function, string $input): string
    {
        return Plugin::call(self::PLUGIN, $function, $input);
    }

    public function await(string $function, string $input): string
    {
        return Plugin::await(self::PLUGIN, $function, $input);
    }

    public function subscribe(string $channel, \Closure $handler): void
    {
        new Channel($channel)->subscribe($handler);
    }
}

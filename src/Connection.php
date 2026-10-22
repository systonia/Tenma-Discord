<?php

namespace Tenma\Discord;

/** The way to the native plugin. PluginConnection in production, a fake in tests. */
interface Connection
{
    /** Runs a quick plugin function on this thread. */
    public function call(string $function, string $input): string;

    /** Runs a plugin function while the fiber waits; throws \RuntimeException with the plugin's error. */
    public function await(string $function, string $input): string;

    /** @param \Closure(string): void $handler */
    public function subscribe(string $channel, \Closure $handler): void;
}

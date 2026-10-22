<?php

// A radio bot: /radio plays a station in your voice channel, buttons mute it or leave.
// Run: DISCORD_TOKEN=... tenma --plugin plugin/target/release/libtenma_discord.dylib examples/radio.php

require __DIR__ . '/../vendor/autoload.php';

use Tenma\Discord\{Bot, Button, ButtonStyle, Embed, Message, Option};
use Tenma\Discord\Events\Ready;
use Tenma\Discord\Interactions\{Click, Command};

const STATION = 'https://listen.moe/stream';

$bot = new Bot(getenv('DISCORD_TOKEN') ?: exit("DISCORD_TOKEN is missing\n"));

$bot->on(Ready::class, function (Ready $ready) {
    echo "online as {$ready->user->name}\n";
});

$bot->command('ping', 'Answers with pong', fn (Command $command) => 'pong');

$bot->command(
    'radio',
    'Plays a radio station in your voice channel',
    function (Command $command) use ($bot) {
        if ($command->voiceChannel === null || $command->guildId === null) {
            return new Message('Join a voice channel first.', ephemeral: true);
        }

        // Joining takes longer than the 3 seconds Discord waits
        $command->defer();
        $url = $command->string('url') ?? STATION;
        $bot->voice($command->guildId)->join($command->voiceChannel)->play($url);

        $nowPlaying = new Embed(title: 'On air', description: $url, color: 0xE91E63);
        $buttons = [
            new Button('mute:on', 'Mute', ButtonStyle::Secondary),
            new Button('leave', 'Leave', ButtonStyle::Danger),
        ];

        return new Message(embeds: [$nowPlaying], buttons: $buttons);
    },
    [Option::string('url', 'Stream URL, listen.moe if empty')],
);

// The button's id says what it does next: mute:on mutes, mute:off unmutes, and the button flips
$bot->button('mute', function (Click $click) use ($bot) {
    $muted = $click->buttonId === 'mute:on';
    $bot->voice((string) $click->guildId)->mute($muted);

    $toggle = $muted
        ? new Button('mute:off', 'Unmute', ButtonStyle::Success)
        : new Button('mute:on', 'Mute', ButtonStyle::Secondary);
    $buttons = [$toggle, new Button('leave', 'Leave', ButtonStyle::Danger)];
    $status = $muted ? "Muted by {$click->user->mention()}" : 'On air';

    // No embeds given: the "On air" card stays as it is
    $click->update(new Message($status, buttons: $buttons));
    return null;
});

$bot->button('leave', function (Click $click) use ($bot) {
    $bot->voice((string) $click->guildId)->leave();
    $click->update(new Message('Bye', embeds: [], buttons: []));
    return null;
});

$bot->start();

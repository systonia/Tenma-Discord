<?php

// A radio bot: /radio plays a station in your voice channel, /play queues songs, buttons mute it or leave.
// Run: DISCORD_TOKEN=... tenma --plugin plugin/target/release/libtenma_discord.dylib examples/radio.php

require __DIR__ . '/../vendor/autoload.php';

use Tenma\Discord\{Bot, Button, ButtonStyle, Embed, Message, Modal, Option, TextInput, Voice};
use Tenma\Discord\Events\{Ready, TrackFailed, TrackStarted};
use Tenma\Discord\Interactions\{Autocomplete, Click, Command, Submit};

const STATION = 'https://listen.moe/stream';
const STATIONS = [
    'listen.moe J-pop' => 'https://listen.moe/stream',
    'listen.moe K-pop' => 'https://listen.moe/kpop/stream',
];

$bot = new Bot(getenv('DISCORD_TOKEN') ?: exit("DISCORD_TOKEN is missing\n"));

$bot->on(Ready::class, function (Ready $ready) {
    echo "online as {$ready->user->name}\n";
});

$bot->on(TrackStarted::class, function (TrackStarted $track) {
    echo "playing {$track->url}\n";
});

$bot->on(TrackFailed::class, function (TrackFailed $track) {
    echo "failed {$track->url}: {$track->error}\n";
});

$bot->command('ping', 'Answers with pong', fn (Command $command) => 'pong');

// Joins the voice channel of whoever ran the command; null when they sit in none
$join = function (Command $command) use ($bot): ?Voice {
    if ($command->voiceChannel === null || $command->guildId === null) {
        return null;
    }

    // Joining takes longer than the 3 seconds Discord waits
    $command->defer();

    return $bot->voice($command->guildId)->join($command->voiceChannel);
};

$bot->command(
    'radio',
    'Plays a radio station in your voice channel',
    function (Command $command) use ($join) {
        $voice = $join($command);
        if ($voice === null) {
            return new Message('Join a voice channel first.', ephemeral: true);
        }

        $url = $command->string('url') ?? STATION;
        $voice->play($url);

        $nowPlaying = new Embed(title: 'On air', description: $url, color: 0xE91E63);
        $buttons = [
            new Button('mute:on', 'Mute', ButtonStyle::Secondary),
            new Button('leave', 'Leave', ButtonStyle::Danger),
        ];

        return new Message(embeds: [$nowPlaying], buttons: $buttons);
    },
    [Option::string('url', 'Stream URL, listen.moe if empty', autocomplete: suggestStations(...))],
);

$bot->command(
    'play',
    'Queues a song (an MP3 or Ogg URL)',
    function (Command $command) use ($join) {
        $voice = $join($command);
        if ($voice === null) {
            return new Message('Join a voice channel first.', ephemeral: true);
        }

        $voice->queue((string) $command->string('url'));

        return 'Queued, ' . count($voice->tracks()) . ' in the queue';
    },
    [Option::string('url', 'Song URL', required: true)],
);

// Opens a form; what the user enters arrives at the 'station' modal handler below
$bot->command('suggest', 'Suggests a station', function (Command $command) {
    $command->modal(new Modal('station', 'Suggest a station', [
        new TextInput('name', 'Name', maxLength: 50),
        new TextInput('url', 'Stream URL', placeholder: 'https://'),
    ]));
    return null;
});

$bot->modal('station', function (Submit $submit) {
    $station = new Embed(title: (string) $submit->value('name'), description: $submit->value('url'));

    return new Message("{$submit->user->mention()} suggests", embeds: [$station]);
});

$bot->command('skip', 'Skips the current song', function (Command $command) use ($bot) {
    $bot->voice((string) $command->guildId)->skip();

    return new Message('Skipped', ephemeral: true);
});

$bot->command('queue', 'Lists the queue', function (Command $command) use ($bot) {
    $tracks = $bot->voice((string) $command->guildId)->tracks();

    return new Message($tracks === [] ? 'The queue is empty' : implode("\n", $tracks), ephemeral: true);
});

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

/**
 * The stations whose name contains what the user typed.
 * @return array<string, string>
 */
function suggestStations(Autocomplete $typed): array
{
    return array_filter(
        STATIONS,
        fn (string $name) => stripos($name, $typed->value) !== false,
        ARRAY_FILTER_USE_KEY,
    );
}

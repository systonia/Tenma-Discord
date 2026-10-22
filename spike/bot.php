<?php

// Spike: /ping answers, /radio joins your voice channel and plays a station, /stop leaves.
// Run: tenma --plugin plugin/target/release/libtenma_discord.dylib spike/bot.php

use Messaging\Channel;
use Tenma\Plugin;

const RADIO = 'https://listen.moe/stream';
const GUILDS = 1 << 0;
const GUILD_VOICE_STATES = 1 << 7;

$token = getenv('DISCORD_TOKEN') ?: exit("DISCORD_TOKEN is missing\n");

function rest(string $method, string $path, ?array $body = null): mixed
{
    $answer = Plugin::await('discord', 'rest', json_encode([
        'method' => $method,
        'path' => $path,
        'body' => $body,
    ]));

    return $answer === '' ? null : json_decode($answer, true);
}

function voice(string $function, array $input): void
{
    Plugin::await('discord', "voice.$function", json_encode($input));
}

$commands = [
    ['name' => 'ping', 'description' => 'Answers with pong'],
    [
        'name' => 'radio',
        'description' => 'Plays a radio station in your voice channel',
        'options' => [['type' => 3, 'name' => 'url', 'description' => 'Stream URL', 'required' => false]],
    ],
    ['name' => 'stop', 'description' => 'Leaves the voice channel'],
];

$application = null;
// guild => user => voice channel, from GUILD_CREATE and VOICE_STATE_UPDATE
$voiceChannels = [];

new Channel('discord.events')->subscribe(function (string $payload) use ($commands, &$application, &$voiceChannels) {
    $event = json_decode($payload, true);
    $data = $event['d'];

    switch ($event['t']) {
        case 'READY':
            $application = $data['application']['id'];
            echo "ready as {$data['user']['username']}\n";
            break;

        case 'GUILD_CREATE':
            foreach ($data['voice_states'] ?? [] as $state) {
                $voiceChannels[$data['id']][$state['user_id']] = $state['channel_id'];
            }
            rest('PUT', "/applications/$application/guilds/{$data['id']}/commands", $commands);
            echo "commands registered in {$data['name']}\n";
            break;

        case 'VOICE_STATE_UPDATE':
            $voiceChannels[$data['guild_id']][$data['user_id']] = $data['channel_id'];
            break;

        case 'INTERACTION_CREATE':
            interaction($data, $application, $voiceChannels);
            break;
    }
});

function interaction(array $data, string $application, array $voiceChannels): void
{
    $callback = "/interactions/{$data['id']}/{$data['token']}/callback";
    $reply = fn (string $content) => rest('POST', $callback, ['type' => 4, 'data' => ['content' => $content]]);
    $guild = $data['guild_id'];

    switch ($data['data']['name']) {
        case 'ping':
            $reply('pong');
            break;

        case 'radio':
            $channel = $voiceChannels[$guild][$data['member']['user']['id']] ?? null;
            if ($channel === null) {
                $reply('Join a voice channel first.');
                break;
            }
            // Joining takes longer than the 3 s Discord waits for an answer
            rest('POST', $callback, ['type' => 5]);
            $url = $data['data']['options'][0]['value'] ?? RADIO;
            voice('join', ['guild' => $guild, 'channel' => $channel]);
            voice('play', ['guild' => $guild, 'url' => $url]);
            rest('PATCH', "/webhooks/$application/{$data['token']}/messages/@original", ['content' => "On air: $url"]);
            break;

        case 'stop':
            voice('leave', ['guild' => $guild]);
            $reply('Bye');
            break;
    }
}

Plugin::call('discord', 'start', json_encode(['token' => $token, 'intents' => GUILDS | GUILD_VOICE_STATES]));

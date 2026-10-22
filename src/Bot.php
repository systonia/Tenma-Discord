<?php

namespace Tenma\Discord;

use Tenma\Discord\Events\{Dispatch, Event, GuildCreated, MemberJoined, MessageCreated, Ready};
use Tenma\Discord\Events\{TrackEnded, TrackFailed, TrackStarted, VoiceStateUpdated};
use Tenma\Discord\Interactions\{Autocomplete, Click, Command, Interaction, Submit};

/**
 * A Discord bot. Register commands, buttons and event handlers, then start():
 *
 *     $bot = new Bot($token);
 *     $bot->command('ping', 'Answers with pong', fn (Command $command) => 'pong');
 *     $bot->start();
 *
 * Under `tenma --workers N` every worker runs this; each gets the events of its share of the guilds, so one
 * guild's events stay in order on one worker. Slash commands are synced with Discord on start, only when
 * they changed.
 */
final class Bot
{
    /** @var array<string, class-string<Event>> gateway name => event class */
    private const array EVENTS = [
        Ready::TYPE => Ready::class,
        GuildCreated::TYPE => GuildCreated::class,
        MessageCreated::TYPE => MessageCreated::class,
        VoiceStateUpdated::TYPE => VoiceStateUpdated::class,
        MemberJoined::TYPE => MemberJoined::class,
        TrackStarted::TYPE => TrackStarted::class,
        TrackEnded::TYPE => TrackEnded::class,
        TrackFailed::TYPE => TrackFailed::class,
    ];

    public readonly Rest $rest;

    private readonly Connection $connection;

    /** @var array<string, array{definition: array<string, mixed>, handler: \Closure}> */
    private array $commands = [];

    /** @var array<string, array<string, \Closure>> command => option => autocomplete handler */
    private array $autocompletes = [];

    /** @var array<string, \Closure> button id => handler */
    private array $buttons = [];

    /** @var array<string, \Closure> modal id => handler */
    private array $modals = [];

    /** @var array<class-string<Event>, list<\Closure>> */
    private array $listeners = [];

    /** @var array<string, array<string, string>> guild => user => voice channel */
    private array $voiceChannels = [];

    private ?string $applicationId = null;
    private int $worker = 0;

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
        private readonly int $intents = Intents::Default,
        ?Connection $connection = null,
    ) {
        $this->connection = $connection ?? new PluginConnection();
        $this->rest = new Rest($this->connection);
    }

    /**
     * A slash command. The handler gets a Command; what it returns (a string or a Message) is the reply.
     * @param \Closure(Command): (string|Message|null) $handler
     * @param list<Option> $options
     */
    public function command(string $name, string $description, \Closure $handler, array $options = []): self
    {
        $definition = [
            'name' => $name,
            'description' => $description,
            'options' => array_map(fn (Option $option) => $option->toArray(), $options),
        ];
        $this->commands[$name] = ['definition' => $definition, 'handler' => $handler];

        foreach ($options as $option) {
            if ($option->autocomplete !== null) {
                $this->autocompletes[$name][$option->name] = $option->autocomplete;
            }
        }

        return $this;
    }

    /**
     * Clicks on buttons with this id, or with an id that starts with "$id:" (`new Button("vote:$pollId", ...)`).
     * @param \Closure(Click): (string|Message|null) $handler
     */
    public function button(string $id, \Closure $handler): self
    {
        $this->buttons[$id] = $handler;

        return $this;
    }

    /**
     * Sent modals with this id, or with an id that starts with "$id:", like buttons.
     * @param \Closure(Submit): (string|Message|null) $handler
     */
    public function modal(string $id, \Closure $handler): self
    {
        $this->modals[$id] = $handler;

        return $this;
    }

    /**
     * A gateway event: Ready, GuildCreated, MessageCreated, VoiceStateUpdated, MemberJoined, the voice
     * queue's TrackStarted, TrackEnded and TrackFailed, or Dispatch for every event as it came.
     * @template T of Event
     * @param class-string<T> $event
     * @param \Closure(T): mixed $handler
     */
    public function on(string $event, \Closure $handler): self
    {
        if ($event !== Dispatch::class && !in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException("$event is no event the bot knows; use Dispatch for the others");
        }
        $this->listeners[$event][] = $handler;

        return $this;
    }

    /** The bot's voice connection in a guild. */
    public function voice(string $guildId): Voice
    {
        return new Voice($this->connection, $guildId);
    }

    /** The voice channel a user sits in, as far as this worker has seen; null when none. */
    public function voiceChannel(string $guildId, string $userId): ?string
    {
        return $this->voiceChannels[$guildId][$userId] ?? null;
    }

    /** Known once the bot is connected. */
    public function applicationId(): string
    {
        return $this->applicationId ?? throw new \LogicException('The bot is not connected yet');
    }

    /** Connects and keeps running; returns at once, the events arrive in their own fibers. */
    public function start(): void
    {
        $channel = $this->connection->call('register', '');
        $this->worker = (int) substr($channel, strlen('discord.w'));
        $this->connection->subscribe($channel, $this->receive(...));

        $start = ['token' => $this->token, 'intents' => $this->intents];
        $this->connection->call('start', json_encode($start, JSON_THROW_ON_ERROR));
    }

    private function receive(string $json): void
    {
        $dispatch = Payload::decode($json);
        $type = $dispatch->string('t');
        $data = $dispatch->get('d');

        $this->track($type, $data);

        if ($type === 'INTERACTION_CREATE') {
            $this->interact($data);
        }

        $class = self::EVENTS[$type] ?? null;
        if ($class !== null && isset($this->listeners[$class])) {
            $event = $class::fromPayload($data, $this);
            $this->notify($class, $event);
        }
        if (isset($this->listeners[Dispatch::class])) {
            $this->notify(Dispatch::class, Dispatch::fromPayload($dispatch, $this));
        }
    }

    /** What the bot itself keeps: its application, and who sits in which voice channel. */
    private function track(string $type, Payload $data): void
    {
        switch ($type) {
            case Ready::TYPE:
                $this->applicationId = $data->get('application')->string('id');
                // Every worker gets READY; one is enough to sync the commands
                if ($this->worker === 0) {
                    $this->syncCommands();
                }
                break;

            case GuildCreated::TYPE:
                $guild = $data->string('id');
                foreach ($data->list('voice_states') as $state) {
                    $this->voiceChannels[$guild][$state->string('user_id')] = $state->string('channel_id');
                }
                break;

            case VoiceStateUpdated::TYPE:
                $guild = $data->string('guild_id');
                $user = $data->string('user_id');
                $channel = $data->optionalString('channel_id');
                if ($channel === null) {
                    unset($this->voiceChannels[$guild][$user]);
                } else {
                    $this->voiceChannels[$guild][$user] = $channel;
                }
                break;
        }
    }

    /** Overwrites the bot's global commands, unless Discord already has these. */
    private function syncCommands(): void
    {
        $path = "/applications/{$this->applicationId()}/commands";
        $wanted = array_column($this->commands, 'definition');

        $known = [];
        foreach ($this->rest->request('GET', $path)->list() as $command) {
            $known[] = self::definition($command);
        }

        if (self::sorted($known) !== self::sorted($wanted)) {
            $this->rest->request('PUT', $path, $wanted);
        }
    }

    /** @return array<string, mixed> a command as Discord lists it, in the shape command() builds */
    private static function definition(Payload $command): array
    {
        $options = [];
        foreach ($command->list('options') as $option) {
            $known = [
                'type' => $option->int('type'),
                'name' => $option->string('name'),
                'description' => $option->string('description'),
                'required' => $option->value('required') === true,
            ];
            if ($option->value('autocomplete') === true) {
                $known['autocomplete'] = true;
            }
            $options[] = $known;
        }

        return [
            'name' => $command->string('name'),
            'description' => $command->string('description'),
            'options' => $options,
        ];
    }

    /**
     * @param list<array<string, mixed>> $commands
     * @return array<string, array<string, mixed>> by name, in name order
     */
    private static function sorted(array $commands): array
    {
        $byName = [];
        foreach ($commands as $command) {
            $name = $command['name'];
            $byName[is_string($name) ? $name : ''] = $command;
        }
        ksort($byName);

        return $byName;
    }

    private function interact(Payload $data): void
    {
        $type = $data->int('type');

        if ($type === 2) {
            $interaction = new Command($data, $this);
            $handler = $this->commands[$interaction->name]['handler'] ?? null;
        } elseif ($type === 3) {
            $interaction = new Click($data, $this);
            $handler = self::byId($this->buttons, $interaction->buttonId);
        } elseif ($type === 4) {
            $interaction = new Autocomplete($data, $this);
            $handler = $this->autocompletes[$interaction->command][$interaction->option] ?? null;
        } elseif ($type === 5) {
            $interaction = new Submit($data, $this);
            $handler = self::byId($this->modals, $interaction->modalId);
        } else {
            return;
        }

        if ($handler === null) {
            return;
        }
        $this->answer($interaction, $handler);
    }

    /** @param array<string, \Closure> $handlers */
    private static function byId(array $handlers, string $id): ?\Closure
    {
        $prefix = strstr($id, ':', true);

        return $handlers[$id] ?? ($prefix === false ? null : $handlers[$prefix] ?? null);
    }

    /** Runs the handler; its return value is the reply. A failure tells the user before it is logged. */
    private function answer(Interaction $interaction, \Closure $handler): void
    {
        try {
            $reply = $handler($interaction);
        } catch (\Throwable $error) {
            self::apologize($interaction);
            throw $error;
        }

        if ($interaction instanceof Autocomplete) {
            $interaction->suggest(is_array($reply) ? $reply : []);
            return;
        }
        if (is_string($reply) || $reply instanceof Message) {
            $interaction->reply($reply);
        }
    }

    private static function apologize(Interaction $interaction): void
    {
        try {
            $interaction->reply(new Message('Something went wrong.', ephemeral: true));
        } catch (\Throwable) {
            // The original error matters more than this one
        }
    }

    /** @param class-string<Event> $class */
    private function notify(string $class, Event $event): void
    {
        foreach ($this->listeners[$class] as $listener) {
            $listener($event);
        }
    }
}

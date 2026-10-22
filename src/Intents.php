<?php

namespace Tenma\Discord;

/**
 * The gateway events a bot asks for, combined with `|`. MessageContent, GuildMembers and GuildPresences are
 * privileged: switch them on for the bot in the Developer Portal first.
 */
final class Intents
{
    public const int Guilds = 1 << 0;
    public const int GuildMembers = 1 << 1;
    public const int GuildModeration = 1 << 2;
    public const int GuildExpressions = 1 << 3;
    public const int GuildIntegrations = 1 << 4;
    public const int GuildWebhooks = 1 << 5;
    public const int GuildInvites = 1 << 6;
    public const int GuildVoiceStates = 1 << 7;
    public const int GuildPresences = 1 << 8;
    public const int GuildMessages = 1 << 9;
    public const int GuildMessageReactions = 1 << 10;
    public const int GuildMessageTyping = 1 << 11;
    public const int DirectMessages = 1 << 12;
    public const int DirectMessageReactions = 1 << 13;
    public const int DirectMessageTyping = 1 << 14;
    public const int MessageContent = 1 << 15;
    public const int GuildScheduledEvents = 1 << 16;

    /** Guilds and voice states: enough for commands, buttons and knowing who sits in which voice channel. */
    public const int Default = self::Guilds | self::GuildVoiceStates;
}

# tenma/discord

Discord bots in PHP on [Tenma](https://github.com/systonia/Tenma). A native plugin holds the gateway, REST and voice connections; PHP gets the events and calls a small API.

Early work: `spike/bot.php` is a radio bot with `/ping`, `/radio` and `/stop`.

```bash
cd plugin && cargo build --release && cd ..
DISCORD_TOKEN=... tenma --plugin plugin/target/release/libtenma_discord.dylib spike/bot.php
```

Building the plugin needs Rust and CMake (for the statically linked Opus).

## License

MIT or Apache-2.0, at your option.

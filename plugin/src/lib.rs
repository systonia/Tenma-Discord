//! The native half of tenma/discord: the gateway connection, REST calls and voice, with PHP on the
//! other side of `Tenma\Plugin` and `Messaging\Channel`.

use std::collections::HashMap;
use std::num::NonZeroU64;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex, OnceLock};
use std::thread::ThreadId;
use std::time::Duration;

use futures_util::StreamExt;
use serde::Deserialize;
use songbird::{EventContext, EventHandler, Songbird, TrackEvent};
use songbird::input::HttpRequest;
use songbird::shards::TwilightMap;
use tenma_plugin::{LOG_ERROR, LOG_INFO, LOG_WARNING, Plugin, Reply, Token, api};
use tokio::runtime::Runtime;
use twilight_gateway::{CloseFrame, Event, EventTypeFlags, Intents, Message, MessageSender, Shard, ShardId};
use twilight_model::gateway::payload::outgoing::UpdateVoiceState;
use twilight_model::id::Id;
use twilight_model::id::marker::{ChannelMarker, GuildMarker};

const API: &str = "https://discord.com/api/v10";

static PLUGIN: Plugin = Plugin {
    name: c"discord".as_ptr(),
    call: Some(call),
    call_async: Some(call_async),
    adopt: None,
    message: None,
    closed: None,
    shutdown: Some(shutdown),
};

static RUNTIME: OnceLock<Runtime> = OnceLock::new();
static BOT: OnceLock<Bot> = OnceLock::new();
/// The workers that asked for events, by the thread they run on; an event goes to one of them.
static WORKERS: Mutex<Vec<ThreadId>> = Mutex::new(Vec::new());
static STOPPING: AtomicBool = AtomicBool::new(false);

struct Bot {
    token: String,
    http: reqwest::Client,
    voice: OnceLock<Arc<Songbird>>,
    gateway: OnceLock<MessageSender>,
}

/// A call's token, moved into the task that completes it.
struct Pending(*mut Token);

unsafe impl Send for Pending {}

#[unsafe(no_mangle)]
extern "C" fn tenma_plugin_init(api: &'static tenma_plugin::Api, abi: u32) -> *const Plugin {
    if abi != tenma_plugin::ABI {
        return std::ptr::null();
    }
    tenma_plugin::set_api(api);
    &PLUGIN
}

#[derive(Deserialize)]
struct Start {
    token: String,
    intents: u64,
}

extern "C" fn call(
    function: *const std::ffi::c_char,
    function_len: usize,
    input: *const u8,
    input_len: usize,
    out: *mut Reply,
) -> i32 {
    let (function, input) = unsafe {
        (
            tenma_plugin::slice(function.cast(), function_len),
            tenma_plugin::slice(input, input_len),
        )
    };
    let result = match function {
        b"start" => start(input).map(|()| String::new()),
        b"register" => Ok(register()),
        _ => Err(format!("unknown function {}", String::from_utf8_lossy(function))),
    };
    match result {
        Ok(reply) => {
            api().reply(out, reply.as_bytes());
            0
        }
        Err(message) => {
            api().reply(out, message.as_bytes());
            1
        }
    }
}

/// Connects to the gateway once per process; later calls, from other workers, find it running.
fn start(input: &[u8]) -> Result<(), String> {
    let start: Start = serde_json::from_slice(input).map_err(|error| error.to_string())?;
    let bot = Bot {
        token: start.token.clone(),
        // Some stations (listen.moe behind Cloudflare) refuse requests without a user agent
        http: reqwest::Client::builder()
            .user_agent(concat!("tenma-discord/", env!("CARGO_PKG_VERSION")))
            .build()
            .map_err(|error| error.to_string())?,
        voice: OnceLock::new(),
        gateway: OnceLock::new(),
    };
    if BOT.set(bot).is_err() {
        return Ok(());
    }
    let runtime = RUNTIME.get_or_init(|| Runtime::new().expect("tokio runtime"));
    runtime.spawn(gateway(start.token, Intents::from_bits_truncate(start.intents)));
    Ok(())
}

/// The channel this worker receives its events on. A call runs on the worker's own thread, so the
/// thread tells workers apart; a worker that registers again keeps its channel.
fn register() -> String {
    let thread = std::thread::current().id();
    let mut workers = WORKERS.lock().expect("workers");
    let index = match workers.iter().position(|known| *known == thread) {
        Some(index) => index,
        None => {
            workers.push(thread);
            workers.len() - 1
        }
    };
    channel(index)
}

fn channel(worker: usize) -> String {
    format!("discord.w{worker}")
}

#[derive(Deserialize)]
struct Dispatch {
    op: u8,
    t: Option<String>,
    d: Option<Keys>,
}

#[derive(Deserialize)]
struct Keys {
    guild_id: Option<String>,
    channel_id: Option<String>,
    id: Option<String>,
}

/// Sends a dispatch to the worker that owns its guild (or its channel, for direct messages), so the
/// events of one guild stay in order on one worker. Events of no guild, like READY, reach every worker.
fn route(text: &str) {
    let Ok(dispatch) = serde_json::from_str::<Dispatch>(text) else {
        return;
    };
    // Only dispatches go to PHP, not heartbeat acks and the like
    if dispatch.op != 0 {
        return;
    }
    let workers = WORKERS.lock().expect("workers").len();
    if workers == 0 {
        return;
    }

    let keys = dispatch.d;
    let guild_event = matches!(dispatch.t.as_deref(), Some("GUILD_CREATE" | "GUILD_UPDATE" | "GUILD_DELETE"));
    let key = keys.and_then(|keys| {
        keys.guild_id
            .or(if guild_event { keys.id } else { None })
            .or(keys.channel_id)
    });

    match key.and_then(|key| key.parse::<u64>().ok()) {
        Some(key) => api().post(&channel((key % workers as u64) as usize), text.as_bytes()),
        None => {
            for worker in 0..workers {
                api().post(&channel(worker), text.as_bytes());
            }
        }
    }
}

async fn gateway(token: String, intents: Intents) {
    let mut shard = Shard::new(ShardId::ONE, token, intents);
    let senders = Arc::new(TwilightMap::new(HashMap::from([(0, shard.sender())])));
    let _ = BOT.get().expect("started").gateway.set(shard.sender());
    let wanted = EventTypeFlags::READY
        | EventTypeFlags::VOICE_STATE_UPDATE
        | EventTypeFlags::VOICE_SERVER_UPDATE;

    while let Some(message) = shard.next().await {
        let text = match message {
            Ok(Message::Text(text)) => text,
            Ok(Message::Close(_)) if STOPPING.load(Ordering::Relaxed) => break,
            Ok(Message::Close(frame)) => {
                api().log(LOG_WARNING, &format!("discord gateway closed: {frame:?}"));
                continue;
            }
            Err(error) => {
                api().log(LOG_WARNING, &format!("discord gateway: {error}"));
                continue;
            }
        };

        // Voice needs the voice state and server updates; READY names the bot's user
        if let Ok(Some(event)) = twilight_gateway::parse(text.clone(), wanted) {
            let event = Event::from(event);
            if let Event::Ready(ready) = &event {
                let voice = Songbird::twilight(senders.clone(), ready.user.id);
                let _ = BOT.get().expect("started").voice.set(Arc::new(voice));
                api().log(LOG_INFO, &format!("discord: connected as {}", ready.user.name));
            }
            if let Some(voice) = BOT.get().and_then(|bot| bot.voice.get()) {
                voice.process(&event).await;
            }
        }

        route(&text);
    }
    if STOPPING.load(Ordering::Relaxed) {
        return;
    }
    api().log(LOG_ERROR, "discord gateway: gave up, the token or the intents were refused");
}

/// Leaves every voice channel and closes the gateway, so the bot goes offline at once instead of
/// after Discord's timeout. Bounded, since tenma waits for it.
extern "C" fn shutdown() {
    let (Some(runtime), Some(bot)) = (RUNTIME.get(), BOT.get()) else {
        return;
    };
    STOPPING.store(true, Ordering::Relaxed);
    let leave = async {
        if let Some(voice) = bot.voice.get() {
            for (guild, _) in voice.iter().collect::<Vec<_>>() {
                let _ = voice.remove(guild).await;
            }
        }
        if let Some(gateway) = bot.gateway.get() {
            let _ = gateway.close(CloseFrame::NORMAL);
        }
    };
    runtime.block_on(async {
        let _ = tokio::time::timeout(Duration::from_secs(3), leave).await;
    });
}

extern "C" fn call_async(
    function: *const std::ffi::c_char,
    function_len: usize,
    input: *const u8,
    input_len: usize,
    token: *mut Token,
) {
    let (function, input) = unsafe {
        (
            tenma_plugin::slice(function.cast(), function_len).to_vec(),
            tenma_plugin::slice(input, input_len).to_vec(),
        )
    };
    let pending = Pending(token);
    let Some(runtime) = RUNTIME.get() else {
        api().complete(pending.0, Err("discord: call start first"));
        return;
    };
    runtime.spawn(async move {
        let result = match function.as_slice() {
            b"rest" => rest(&input).await,
            b"voice.join" => voice_join(&input).await,
            b"voice.play" => voice_play(&input).await,
            b"voice.stop" => voice_stop(&input).await,
            b"voice.mute" => voice_mute(&input).await,
            b"voice.leave" => voice_leave(&input).await,
            _ => Err(format!("unknown function {}", String::from_utf8_lossy(&function))),
        };
        let pending = pending;
        match result {
            Ok(bytes) => api().complete(pending.0, Ok(&bytes)),
            Err(message) => api().complete(pending.0, Err(&message)),
        }
    });
}

#[derive(Deserialize)]
struct Rest {
    method: String,
    path: String,
    body: Option<serde_json::Value>,
}

/// A REST call with the bot's token: the response body, or an error with Discord's answer.
async fn rest(input: &[u8]) -> Result<Vec<u8>, String> {
    let rest: Rest = serde_json::from_slice(input).map_err(|error| error.to_string())?;
    let bot = BOT.get().ok_or("discord: call start first")?;
    let method = reqwest::Method::from_bytes(rest.method.as_bytes()).map_err(|error| error.to_string())?;

    let mut request = bot
        .http
        .request(method, format!("{API}{}", rest.path))
        .header("Authorization", format!("Bot {}", bot.token));
    if let Some(body) = rest.body {
        request = request.json(&body);
    }

    let response = request.send().await.map_err(|error| error.to_string())?;
    let status = response.status();
    let body = response.bytes().await.map_err(|error| error.to_string())?;
    if status.is_client_error() || status.is_server_error() {
        return Err(format!("discord {status}: {}", String::from_utf8_lossy(&body)));
    }
    Ok(body.to_vec())
}

#[derive(Deserialize)]
struct Voice {
    guild: String,
    channel: Option<String>,
    url: Option<String>,
    muted: Option<bool>,
}

fn voice(input: &[u8]) -> Result<(Voice, Arc<Songbird>), String> {
    let voice: Voice = serde_json::from_slice(input).map_err(|error| error.to_string())?;
    let songbird = BOT
        .get()
        .and_then(|bot| bot.voice.get())
        .ok_or("discord: not connected yet")?;
    Ok((voice, songbird.clone()))
}

fn id(value: &str) -> Result<NonZeroU64, String> {
    value.parse().map_err(|_| format!("not a Discord id: {value}"))
}

async fn voice_join(input: &[u8]) -> Result<Vec<u8>, String> {
    let (voice, songbird) = voice(input)?;
    let channel = voice.channel.as_deref().ok_or("voice.join needs a channel")?;
    songbird
        .join(id(&voice.guild)?, id(channel)?)
        .await
        .map_err(|error| error.to_string())?;
    Ok(Vec::new())
}

async fn voice_play(input: &[u8]) -> Result<Vec<u8>, String> {
    let (voice, songbird) = voice(input)?;
    let url = voice.url.ok_or("voice.play needs a url")?;
    let call = songbird.get(id(&voice.guild)?).ok_or("not in a voice channel of this guild")?;
    let http = BOT.get().expect("started").http.clone();

    let mut call = call.lock().await;
    call.stop();
    let track = call.play_input(HttpRequest::new(http, url).into());
    let _ = track.add_event(TrackEvent::Error.into(), TrackFailed);
    Ok(Vec::new())
}

async fn voice_stop(input: &[u8]) -> Result<Vec<u8>, String> {
    let (voice, songbird) = voice(input)?;
    if let Some(call) = songbird.get(id(&voice.guild)?) {
        call.lock().await.stop();
    }
    Ok(Vec::new())
}

/// Mutes the bot in its voice channel: the stream keeps running, nothing is sent, Discord shows it muted.
async fn voice_mute(input: &[u8]) -> Result<Vec<u8>, String> {
    let (voice, songbird) = voice(input)?;
    let call = songbird.get(id(&voice.guild)?).ok_or("not in a voice channel of this guild")?;
    let muted = voice.muted.unwrap_or(true);
    call.lock().await.mute(muted).await.map_err(|error| error.to_string())?;
    Ok(Vec::new())
}

/// Logs why a track stopped playing: an unreachable URL, a format nobody can decode.
struct TrackFailed;

#[async_trait::async_trait]
impl EventHandler for TrackFailed {
    async fn act(&self, context: &EventContext<'_>) -> Option<songbird::Event> {
        if let EventContext::Track(tracks) = context {
            for (state, _) in *tracks {
                api().log(LOG_WARNING, &format!("discord voice: {:?}", state.playing));
            }
        }
        None
    }
}

/// Leaves the guild's voice channel, also one this process never joined (a call from before a restart).
async fn voice_leave(input: &[u8]) -> Result<Vec<u8>, String> {
    let (voice, songbird) = voice(input)?;
    let guild = id(&voice.guild)?;
    if songbird.get(guild).is_some() {
        songbird.remove(guild).await.map_err(|error| error.to_string())?;
        return Ok(Vec::new());
    }
    let gateway = BOT.get().and_then(|bot| bot.gateway.get()).ok_or("discord: not connected yet")?;
    let leave = UpdateVoiceState::new(Id::<GuildMarker>::from(guild), None::<Id<ChannelMarker>>, false, false);
    gateway.command(&leave).map_err(|error| error.to_string())?;
    Ok(Vec::new())
}

import asyncio
from pyrogram import Client, filters
from pyrogram.types import Message
from pytgcalls import PyTgCalls, StreamType
from pytgcalls.types.input_stream import AudioPiped
import yt_dlp

# Bot Configuration
API_ID = "29030564"
API_HASH = "ca60e3c789ea8eb59580c845de56a541"
BOT_TOKEN = "7724333720:AAFlhf9WTyKgf9IUnoMRqqwQLFJFt6rOeWw"

app = Client("my_bot", api_id=API_ID, api_hash=API_HASH, bot_token=BOT_TOKEN)

call = PyTgCalls(app)

# Queue System
music_queue = {}

# Extract YouTube Audio URL
def get_audio_url(query):
    ydl_opts = {
        "format": "bestaudio/best",
        "quiet": True
    }
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(f"ytsearch:{query}", download=False)
        url = info['entries'][0]['url']
        title = info['entries'][0]['title']
    return url, title

# Start Music in Voice Chat
@app.on_message(filters.command("play") & filters.group)
async def play_music(client, message: Message):
    chat_id = message.chat.id
    query = " ".join(message.command[1:])
    
    if not query:
        return await message.reply_text("❌ Please provide a song name or link!")

    url, title = get_audio_url(query)
    
    if chat_id in music_queue:
        music_queue[chat_id].append((url, title))
        return await message.reply_text(f"🎶 **{title}** added to queue!")

    music_queue[chat_id] = [(url, title)]
    
    await call.join_group_call(chat_id, AudioPiped(url), stream_type=StreamType().pulse_stream)
    await message.reply_text(f"🎵 Now Playing: **{title}**")

# Skip Current Song
@app.on_message(filters.command("skip") & filters.group)
async def skip_song(client, message: Message):
    chat_id = message.chat.id
    
    if chat_id in music_queue and len(music_queue[chat_id]) > 1:
        music_queue[chat_id].pop(0)
        next_url, next_title = music_queue[chat_id][0]
        await call.change_stream(chat_id, AudioPiped(next_url))
        await message.reply_text(f"⏩ Skipping... Now Playing: **{next_title}**")
    else:
        await call.leave_group_call(chat_id)
        music_queue.pop(chat_id, None)
        await message.reply_text("✅ Music Stopped!")

# Stop Music
@app.on_message(filters.command("stop") & filters.group)
async def stop_music(client, message: Message):
    chat_id = message.chat.id
    await call.leave_group_call(chat_id)
    music_queue.pop(chat_id, None)
    await message.reply_text("⏹ Music Stopped!")

# Show Current Queue
@app.on_message(filters.command("queue") & filters.group)
async def show_queue(client, message: Message):
    chat_id = message.chat.id
    
    if chat_id not in music_queue or not music_queue[chat_id]:
        return await message.reply_text("📜 No songs in the queue!")
    
    queue_text = "\n".join([f"{i+1}. {title}" for i, (_, title) in enumerate(music_queue[chat_id])])
    await message.reply_text(f"📜 **Current Queue:**\n{queue_text}")

# owner-Only Volume Control
@app.on_message(filters.command("volume") & filters.group)
async def volume_control(client, message: Message):
    chat_id = message.chat.id
    volume = int(message.command[1])
    
    if 0 <= volume <= 400:
        await call.change_stream(chat_id, AudioPiped(music_queue[chat_id][0][0], volume=volume))
        await message.reply_text(f"🔊 Volume set to {volume}%")
    else:
        await message.reply_text("❌ Please enter a volume between 0 and 200!")

# Bot Start
@app.on_message(filters.command("start"))
async def start(client, message: Message):
    await message.reply_text("🎶 **Music Bot is Online!**\nUse `/play [song name]` to start.")

# Run Bot
app.start()
call.start()
print("🎵 Music Bot is Running...")
asyncio.get_event_loop().run_forever()




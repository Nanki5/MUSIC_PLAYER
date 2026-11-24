# Seed data

`seed.json` drives `backend/seed.php`. Every `music`, `cover` and `picture` path in it
is relative to this folder.

| Folder | Contents |
|--------|----------|
| `music/` | 10 MP3 files (`soundhelix-01.mp3` … `soundhelix-10.mp3`) |
| `covers/` | 10 generated gradient PNG files (`cover-01.png` … `cover-10.png`) |

## Attribution

The audio is the public set of SoundHelix example songs by **T. Schürger**.
SoundHelix states: *"You may use these audio examples in any way you like, but
you must give credit to SoundHelix and the artist of the respective song."*
Source: https://www.soundhelix.com/audio-examples — each track's exact URL is
stored in the `source` field of its `seed.json` entry. The cover images were
generated for this demo and need no attribution.

## Re-download the audio

From this folder (bash):

```bash
for i in 1 2 3 4 5 6 7 8 9 10; do
  n=$(printf '%02d' "$i")
  curl -fL --retry 3 -o "music/soundhelix-$n.mp3" \
    "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-$i.mp3"
done
```

## Run the seed

```powershell
docker compose -f backend/docker-compose.yml exec php php /var/www/html/seed.php
```

The `seed/` folder is mounted read-only into the PHP container, so no image
rebuild is needed after editing `seed.json` or replacing a file.

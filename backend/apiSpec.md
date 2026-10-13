API Specification

Style: REST/JSON under /api, errors as {"error": "..."}
Auth: stateless JWT (payload {user_id}, HS256, no expiry) in persistent cookie (Max-Age 30 days),
      HttpOnly, SameSite=None; Secure (Secure accepted over http://localhost in dev; prod needs HTTPS);
      every protected request verifies the user still exists
CSRF: state-changing requests (POST/PATCH/DELETE) must carry Origin or Referer matching
      ALLOWED_ORIGINS, else 403 (required because SameSite=None sends the cookie cross-site)
CORS: Allow-Credentials true, origin allowlist from ALLOWED_ORIGINS env
      (frontend and API/media are different origins in dev, e.g. :5500 vs :8000; CORS + absolute
      media URLs cover both. Media GETs are no-cors <img>/<audio> loads and work cross-origin
      without CORS; /media responses still carry ACAO from the allowlist as insurance for future
      crossorigin/Web Audio use)
Env: JWT_SECRET, ALLOWED_ORIGINS, PUBLIC_BASE_URL, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD,
      MINIO_ENDPOINT, MINIO_ACCESS_KEY, MINIO_SECRET_KEY, MINIO_BUCKET
Stack: vanilla PHP (no Composer), plain SQL, docker-compose (nginx + php-fpm + PostgreSQL + MinIO);
       nginx client_max_body_size and php.ini upload_max_filesize/post_max_size set to 60 MB (headroom
       above the true max body: 50 MB audio + 5 MB cover in one multipart request; UPLOAD_ERR_INI_SIZE
       and post_max_size-exceeded mapped to JSON 413) so PHP owns size checks;
       media served via nginx /media/ -> MinIO proxy (public-read bucket, Range -> 206)

Objects
Song:     {id, title, author, audio_url, cover_url, uploaded_at, like_count}
          (cover_url is never null: default object cover/default is assigned at upload)
Playlist: {id, name, published, picture_url, songs:[Song...]}   (song objects in playlist also carry "position")
          (picture_url is never null: default object playlistPicture/default is assigned at creation)
Media URLs: audio_url/cover_url/picture_url are absolute, built at response time as
          PUBLIC_BASE_URL + "/media/..." (e.g. http://localhost:8000/media/cover/42); the DB
          stores no host, only bare ids
Params:   limit default 20 / max 100, offset >= 0; invalid params -> 422
Status:   401 auth failure, 403 CSRF/origin rejection, 404 missing (incl. private playlists),
          409 conflict, 413 too large, 415 wrong media type, 422 validation, 204 no body

AUTH
POST   /api/auth/register   {login, password}   -> 201 {login}, sets cookie   | 409 taken, 422 invalid
POST   /api/auth/login      {login, password}   -> 200 {login}, sets cookie   | 401 generic
POST   /api/auth/logout                         -> 204, clears cookie
DELETE /api/auth/account    (cookie)            -> 204: DB cascade purge, MinIO cleanup, cookie cleared
        storage cleanup deletes only the user's own non-default keys (song/{id}, cover/{songId} for
        songs they uploaded, playlistPicture/{playlistId} for playlists they own); the shared
        cover/default and playlistPicture/default objects are never deleted
GET    /api/me              (cookie)            -> 200 {login}

SONGS (anon unless noted)
GET    /api/songs?query=&sort=likes|uploaded_at&limit=&offset=  -> 200 [Song]
        query = case-insensitive substring on title OR author; sort default uploaded_at; always DESC,
        tie-break id DESC; sort=likes uses COUNT(likes) subquery; 422 on unknown sort
POST   /api/songs (authed) multipart: title, author, file, cover? -> 201 Song
        audio: mp3/flac/ogg/m4a <=50MB (415 wrong type / 413 too big); cover: jpg/png/webp <=5MB, optional
        -> defaults to cover/default; acceptance: extension whitelist AND finfo must not contradict
        it (m4a/ogg aliases accepted); Content-Type stored from fixed extension->MIME map;
        no GET /songs/{id} (not an FR)

LIKES (authed)
POST   /api/songs/{id}/like          -> 200 {liked: bool}   (toggle) | 404 song
GET    /api/me/likes?limit=&offset=  -> 200 [Song + liked_at], liked_at DESC, tie-break song_id DESC

PLAYLISTS
POST   /api/playlists (authed) multipart: name, picture?, songs=[JSON array of ids, may be []] -> 201 Playlist
        picture optional (jpg/png/webp <=5MB, 415/413) -> defaults to playlistPicture/default;
        songs order preserved via position 0..n-1; unknown id or duplicate id in array -> 422
GET    /api/playlists/{id}           -> 200 Playlist, songs ordered position ASC |
        404 if missing OR unpublished-and-not-owner (404, not 403 - do not leak existence)
GET    /api/me/playlists (authed)    -> 200 [{id, name, published, picture_url}], id DESC, not paginated
PATCH  /api/playlists/{id} (owner) {name}           -> 200 Playlist   | 404  (name only, FR-14)
PATCH  /api/playlists/{id}/picture (owner) multipart: picture -> 200 Playlist | 404  (FR-17)
        flow: overwrite the object at playlistPicture/{playlistId}, then set picture_id to that key
        (pointer leaves the shared default on first custom change; upload failure leaves old object intact)
DELETE /api/playlists/{id} (owner)   -> 204 (cascades playlist_songs; also deletes the custom
        playlistPicture/{playlistId} object unless it still points at default)
POST   /api/playlists/{id}/songs/{songId} (owner)  -> 200 {added: bool} (toggle, FR-7) | 404
        non-owner -> 404 (no-leak, same as reads); add appends at position max+1;
        remove leaves gaps (no renumbering)

MEDIA
GET    /media/song/{id} | /media/cover/{id} | /media/playlistPicture/{id}
        nginx proxies to the public-read MinIO bucket (private port); Range -> 206;
        416 unsatisfiable; responses carry X-Content-Type-Options: nosniff and
        Access-Control-Allow-Origin from the ALLOWED_ORIGINS allowlist
        cover/picture keys reuse the parent entity's id (1:1); upload flow: validate -> insert row
        -> store objects (audio + cover) -> on any object failure delete the row AND any objects
        already stored, return 500

SEED (deliverable, db admin authority)
- creates the MINIO_BUCKET bucket if missing, applies the public-read bucket policy
- creates cover/default and playlistPicture/default objects
- demo users, songs with royalty-free audio/cover files, one published=true playlist exercising FR-6

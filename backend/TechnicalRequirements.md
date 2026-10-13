FR-1 The user can register only with a login that doesnt exist in the db; success sets the auth cookie
FR-2 The user can login only with a login and password that exist in db; success sets the auth cookie
FR-3 Logout clears the auth cookie; no user data is deleted
FR-4 A non-authenticated user can search songs by case-insensitive substring of title or author and sort them by likes or uploadedAt (always desc, limit/offset pagination)
FR-5 A non-authenticated user can get a song's audio starting from the time they want (HTTP Range)
FR-6 A non-authenticated user can get songs from a published playlist
FR-7 An authenticated user can toggle the song being in their playlist (appended at the end on add; owner only, non-owners get 404)
FR-8 An authenticated user can create a playlist using a list of songs that may be empty (order preserved) and a playlist name; the picture is optional and falls back to a default if not provided
FR-9 An authenticated user can toggle like
FR-10 An authenticated user can get the songs they liked, ordered by liked date desc
FR-11 An authenticated user can upload a song: title, free-text author, audio file, optional cover (falls back to a default if not provided)
FR-12 An authenticated user can delete their account: their playlists, likes and uploaded songs are cascade-deleted, the related files removed from storage (shared default objects are never touched), and the auth cookie cleared
FR-13 An authenticated user can delete their playlist (a custom picture object is removed from storage)
FR-14 An authenticated user can rename their playlist (name only)
FR-15 An authenticated user can list their playlists and read any playlist they own regardless of published state (unpublished playlists of other users return 404)
FR-16 An authenticated user can get their own login
FR-17 An authenticated user can change their playlist picture

NFR-1 Passwords are stored hashed (bcrypt via password_hash)
NFR-2 Audio uploads: mp3/flac/ogg/m4a, max 50 MB; cover and playlist picture uploads: jpg/png/webp, max 5 MB. Acceptance: extension must be in the whitelist and finfo must not contradict it (known finfo aliases for m4a/ogg accepted); stored Content-Type comes from a fixed extension-to-MIME map; /media responses carry X-Content-Type-Options: nosniff
NFR-3 Text length caps: login 50, song title and author 100, playlist name 100
NFR-4 Login format ^[a-zA-Z0-9_.]{3,50}$; password 8-72 characters
NFR-5 Auth cookie is persistent (Max-Age 30 days), HttpOnly, SameSite=None; Secure (Secure cookies are accepted over http://localhost in dev; prod requires HTTPS)
NFR-6 Every state-changing request (POST/PATCH/DELETE) must carry an Origin or Referer header matching the ALLOWED_ORIGINS allowlist, else 403 (CSRF defense required by SameSite=None)

Out of scope
OOS-1 An authenticated user can publish their playlist (published is set only by the db admin / seed script)
OOS-2 Password change and password reset
OOS-3 Editing or deleting an uploaded song

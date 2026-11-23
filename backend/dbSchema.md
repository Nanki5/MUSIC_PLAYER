DB structure (PostgreSQL)

CREATE TABLE users (
    id              serial PRIMARY KEY,
    login           varchar(50) NOT NULL UNIQUE,
    hashed_password text        NOT NULL
);

CREATE TABLE songs (
    id          serial PRIMARY KEY,              -- object-storage key: song/{id}
    title       varchar(100) NOT NULL,
    author      varchar(100) NOT NULL,
    cover_id    varchar NOT NULL,                -- object-storage key: cover/{id}; default assigned at upload
    uploader_id int NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    uploaded_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE likes (
    user_id  int NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    song_id  int NOT NULL REFERENCES songs (id) ON DELETE CASCADE,
    liked_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, song_id)
);

CREATE TABLE playlists (
    id         serial PRIMARY KEY,
    name       varchar(100) NOT NULL,
    published  boolean NOT NULL DEFAULT false,   -- set only by db admin / seed
    picture_id varchar NOT NULL,                 -- object-storage key: playlistPicture/{id}; default assigned at creation
    user_id    int NOT NULL REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE playlist_songs (
    playlist_id int NOT NULL REFERENCES playlists (id) ON DELETE CASCADE,
    song_id     int NOT NULL REFERENCES songs (id) ON DELETE CASCADE,
    position    int NOT NULL,                    -- 0-based, assigned by the controller; order within playlist
    PRIMARY KEY (playlist_id, song_id)
);

CREATE INDEX idx_songs_uploaded_at ON songs (uploaded_at DESC);
CREATE INDEX idx_likes_song_id     ON likes (song_id);

Notes
- Object storage is MinIO (local), one public-read bucket (policy applied at seed, required for the anonymous nginx /media/ proxy), keys are bare ids: song/{id}, cover/{id}, playlistPicture/{id}; cover and picture reuse the parent entity's id (1:1); ContentType is stored with the object at upload time (finfo-validated, mapped from extension), so no format column
- default objects seeded: cover/default and playlistPicture/default; controllers assign them when no file is uploaded
- like_count for FR-4 sorting is computed with a COUNT subquery, not denormalized
- playlist song order comes from position (0-based; FR-8 create preserves request order, FR-7 toggle-appends at max+1; removal leaves gaps, no renumbering)
- no sessions table: auth is a stateless JWT cookie validated against users on every protected request

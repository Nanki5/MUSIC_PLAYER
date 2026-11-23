CREATE TABLE users (
    id              serial PRIMARY KEY,
    login           varchar(50) NOT NULL UNIQUE,
    hashed_password text        NOT NULL
);

CREATE TABLE songs (
    id          serial PRIMARY KEY,
    title       varchar(100) NOT NULL,
    author      varchar(100) NOT NULL,
    cover_id    varchar NOT NULL,
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
    published  boolean NOT NULL DEFAULT false,
    picture_id varchar NOT NULL,
    user_id    int NOT NULL REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE playlist_songs (
    playlist_id int NOT NULL REFERENCES playlists (id) ON DELETE CASCADE,
    song_id     int NOT NULL REFERENCES songs (id) ON DELETE CASCADE,
    position    int NOT NULL,
    PRIMARY KEY (playlist_id, song_id)
);

CREATE INDEX idx_songs_uploaded_at ON songs (uploaded_at DESC);
CREATE INDEX idx_likes_song_id     ON likes (song_id);

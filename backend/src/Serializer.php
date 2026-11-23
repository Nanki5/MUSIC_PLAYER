<?php

class Serializer
{
    public static function song(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'audio_url' => Http::mediaUrl('song/' . $row['id']),
            'cover_url' => Http::mediaUrl($row['cover_id']),
            'uploaded_at' => $row['uploaded_at'],
            'like_count' => isset($row['like_count']) ? (int)$row['like_count'] : 0,
        ];
    }

    public static function songWithPosition(array $row): array
    {
        $song = self::song($row);
        $song['position'] = (int)$row['position'];
        return $song;
    }

    public static function likedSong(array $row): array
    {
        $song = self::song($row);
        $song['liked_at'] = $row['liked_at'];
        return $song;
    }

    public static function playlist(array $row, array $songs): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'published' => (bool)$row['published'],
            'picture_url' => Http::mediaUrl($row['picture_id']),
            'songs' => $songs,
        ];
    }

    public static function playlistMeta(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'published' => (bool)$row['published'],
            'picture_url' => Http::mediaUrl($row['picture_id']),
        ];
    }
}

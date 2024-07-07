<?php
// Start the session
session_start();

// Display errors
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Đặt múi giờ thành múi giờ Hồ Chí Minh
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khóa API của bạn từ Google Console
$API_KEY = 'AIzaSyCAph9O436dGbS5lGnMOZy2HY6yjgza2xg';

// Get the start of the day in the server's timezone
$startTimestamp = strtotime("2024-07-01 15:20:00");

// Calculate elapsedTimeInSeconds
$elapsedTimeInSeconds = time() - $startTimestamp;

// Hàm lấy thông tin video từ ID video YouTube
function getVideoInfo($videoId, $apiKey)
{
    $url = "https://www.googleapis.com/youtube/v3/videos?id=$videoId&key=$apiKey&part=snippet,contentDetails";
    $response = @file_get_contents($url);

    if ($response === false) {
        throw new Exception("Failed to fetch video info for video ID: $videoId");
    }

    $data = json_decode($response, true);

    if ($data === null || !isset($data['items'][0])) {
        throw new Exception("Invalid video info received for video ID: $videoId");
    }

    return $data['items'][0];
}


function getIdlePlaylist()
{
    $url = "https://c4k60.com/api/v1.0/radio/idle/";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    return $data['idle_playlist'];
}

// Lấy thông tin video từ ID video YouTube
$videoId = getIdlePlaylist();

shuffle($videoId);

$currentVideoIndex = 0;

// Check if videoInfos is set in the session
if (!isset($_SESSION['videoInfos'])) {
    // If not, fetch all video information at once
    $_SESSION['videoInfos'] = array_map(function ($videoId) use ($API_KEY) {
        $videoInfo = getVideoInfo($videoId, $API_KEY);
        if ($videoInfo === false) {
            throw new Exception("Failed to get video info for video ID: $videoId");
        }
        return $videoInfo;
    }, $videoId);
}

$videoInfo = $_SESSION['videoInfos'][$currentVideoIndex];

if ($videoInfo === false) {
    throw new Exception("Failed to get video info for current video index: $currentVideoIndex");
}

// Thời gian hiện tại
$currentTimestamp = time();

// Thời lượng video đã trôi qua
$videoPublishedAt = strtotime($videoInfo['snippet']['publishedAt']);
$elapsedTimeInSeconds = ($currentTimestamp - $startTimestamp); // Số giây đã trôi qua từ thời điểm bật máy chủ

// Bài hát hiện đang phát (ID video YouTube)
$currentSong = $videoInfo['id'];

// Thứ tự bài hát đang phát
$currentSongOrder = 1; // Đây là bài hát đầu tiên, bạn có thể cập nhật tùy ý

// Thời lượng video hiện tại
$videoDuration = $videoInfo['contentDetails']['duration'];

// Chuyển đổi thời lượng video từ chuỗi ISO 8601 sang giây
function ISO8601ToSeconds($ISO8601)
{
    $interval = new DateInterval($ISO8601);
    return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
}
$videoDurationInSeconds = ISO8601ToSeconds($videoDuration);

// Kiểm tra nếu elapsedTimeInSeconds lớn hơn videoDurationInSeconds
while ($elapsedTimeInSeconds > $videoDurationInSeconds) {
    // Subtract videoDurationInSeconds from elapsedTimeInSeconds
    $elapsedTimeInSeconds -= $videoDurationInSeconds;

    // Move to the next video
    $currentVideoIndex++;
    if ($currentVideoIndex == count($videoId)) {
        $currentVideoIndex = 0;
        $currentSongOrder = 1;
    } else {
        $currentSongOrder++;
    }

    // Get the next video info from the session
    $videoInfo = $_SESSION['videoInfos'][$currentVideoIndex];
    if ($videoInfo === false) {
        throw new Exception("Failed to get video info for current video index: $currentVideoIndex");
    }

    $videoDuration = $videoInfo['contentDetails']['duration'];
    if ($videoDuration === null) {
        throw new Exception("Failed to get video duration for current video index: $currentVideoIndex");
    }

    $videoDurationInSeconds = ISO8601ToSeconds($videoDuration);

    // Update currentSong
    $currentSong = [
        "position" => $currentSongOrder,
        "is_idle_video" => true,
        "video_id" => $videoInfo['id'],
        "video_title" => $videoInfo['snippet']['title'],
        "video_thumbnail" => $videoInfo['snippet']['thumbnails']['medium']['url'],
        "video_duration" => $videoDurationInSeconds,
        "uploaded_by" => $videoInfo['snippet']['channelTitle'],
        "requested_by" => "Dương Tùng Anh",
        "voting" => [
            "like_count" => 0,
            "liked_by" => [],
            "disliked_by" => [],
            "vote_skip" => 0,
            "vote_remove" => 0
        ]
    ];
}

// Kết quả trả về dưới dạng JSON
$response = [
    "elapsed_time" => $elapsedTimeInSeconds,
    "now_playing_video_info" => $currentSong,
    "now_playing_position" => $currentSongOrder,
    "current_video_duration" => $videoDurationInSeconds
];

echo json_encode($response);

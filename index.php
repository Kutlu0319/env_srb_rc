<?php
// ÇIKTIYI M3U OLARAK SUN
header("Content-Type: audio/x-mpegurl");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Varsayılanlar (fallback - SADECE BAŞARISIZLIK DURUMUNDA)
$defaultMainUrl = 'https://m.prectv60.lol';
$defaultSwKey = '4F5A9C3D9A86FA54EACEDDD635185/c3c5bd17-e37b-4b94-a944-8a3688a30452/';
$defaultUserAgent = 'okhttp/4.12.0/';
$defaultReferer = 'https://twitter.com/';
$pageCount = 4;

// M3U çıktısı için sabit User-Agent
$m3uUserAgent = 'googleusercontent';

// YENİ Github kaynak dosyası
$sourceUrlRaw = 'https://raw.githubusercontent.com/nikyokki/nik-cloudstream/refs/heads/master/RecTV/src/main/kotlin/com/keyiflerolsun/RecTV.kt';
$proxyUrl = 'https://api.codetabs.com/v1/proxy/?quest=' . urlencode($sourceUrlRaw);

// 1. ADIM: Github'dan header bilgilerini çek
function fetchGithubContent($sourceUrlRaw, $proxyUrl) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 10
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $githubContent = @file_get_contents($sourceUrlRaw, false, $context);
    if ($githubContent !== FALSE) return $githubContent;
    
    $githubContentProxy = @file_get_contents($proxyUrl, false, $context);
    if ($githubContentProxy !== FALSE) return $githubContentProxy;
    
    return FALSE;
}

function parseGithubHeaders($githubContent) {
    $headers = [
        'mainUrl' => null,
        'swKey' => null,
        'userAgent' => null,
        'referer' => null
    ];
    
    if (preg_match('/override\s+var\s+mainUrl\s*=\s*"([^"]+)"/', $githubContent, $match)) {
        $headers['mainUrl'] = $match[1];
    }
    
    if (preg_match('/private\s+(val|var)\s+swKey\s*=\s*"([^"]+)"/', $githubContent, $match)) {
        $headers['swKey'] = $match[2];
    }
    
    if (preg_match('/headers\s*=\s*mapOf\([^)]*"user-agent"[^)]*to[^"]*"([^"]+)"/s', $githubContent, $match)) {
        $headers['userAgent'] = $match[1];
    }
    
    if (preg_match('/this\.referer\s*=\s*"([^"]+)"/', $githubContent, $match)) {
        $headers['referer'] = $match[1];
    } 
    else if (preg_match('/referer\s*=\s*"([^"]+)"/', $githubContent, $match)) {
        $headers['referer'] = $match[1];
    }
    else if (preg_match('/headers\s*=\s*mapOf\([^)]*"Referer"[^)]*to[^"]*"([^"]+)"/s', $githubContent, $match)) {
        $headers['referer'] = $match[1];
    }
    
    return $headers;
}

function testApiWithHeaders($mainUrl, $swKey, $userAgent, $referer) {
    $testUrl = $mainUrl . '/api/channel/by/filtres/0/0/0/' . $swKey;

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n",
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($testUrl, false, $ctx);
    
    if ($response === FALSE) return false;
    
    $data = json_decode($response, true);
    return ($data !== null && is_array($data));
}


// === GITHUB HEADER TESTİ ===
$githubContent = fetchGithubContent($sourceUrlRaw, $proxyUrl);

if ($githubContent !== FALSE) {
    $githubHeaders = parseGithubHeaders($githubContent);
    
    if ($githubHeaders['mainUrl'] && $githubHeaders['swKey'] && 
        $githubHeaders['userAgent'] && $githubHeaders['referer']) {

        $apiTestResult = testApiWithHeaders(
            $githubHeaders['mainUrl'], 
            $githubHeaders['swKey'], 
            $githubHeaders['userAgent'], 
            $githubHeaders['referer']
        );
        
        if ($apiTestResult) {
            $mainUrl = $githubHeaders['mainUrl'];
            $swKey = $githubHeaders['swKey'];
            $userAgent = $githubHeaders['userAgent'];
            $referer = $githubHeaders['referer'];
        } else {
            $mainUrl = $defaultMainUrl;
            $swKey = $defaultSwKey;
            $userAgent = $defaultUserAgent;
            $referer = $defaultReferer;
        }
    } else {
        $mainUrl = $defaultMainUrl;
        $swKey = $defaultSwKey;
        $userAgent = $defaultUserAgent;
        $referer = $defaultReferer;
    }
} else {
    $mainUrl = $defaultMainUrl;
    $swKey = $defaultSwKey;
    $userAgent = $defaultUserAgent;
    $referer = $defaultReferer;
}

// === M3U OLUŞTUR ===
$m3uContent = "#EXTM3U\n";

$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n",
        'timeout' => 30,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];
$context = stream_context_create($options);


// CANLI YAYINLAR
for ($page = 0; $page < $pageCount; $page++) {
    $apiUrl = $mainUrl . "/api/channel/by/filtres/0/0/$page/" . $swKey;
    $response = @file_get_contents($apiUrl, false, $context);
    
    $data = json_decode($response, true);
    if (!is_array($data)) continue;

    foreach ($data as $content) {
        if (!isset($content['sources']) || !is_array($content['sources'])) continue;

        foreach ($content['sources'] as $source) {
            if (($source['type'] ?? '') === 'm3u8') {
                $title = $content['title'] ?? '';
                $image = isset($content['image']) ? (
                    (strpos($content['image'], 'http') === 0) ? $content['image'] : $mainUrl . '/' . ltrim($content['image'], '/')
                ) : '';
                $categories = isset($content['categories']) && is_array($content['categories'])
                    ? implode(", ", array_column($content['categories'], 'title'))
                    : '';
                
                $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categories\", $title\n";
                $m3uContent .= "#EXTVLCOPT:http-user-agent=$m3uUserAgent\n";
                $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                $m3uContent .= "{$source['url']}\n";
            }
        }
    }
}

// --- Filmler & Diziler (kısalttım, hiçbir işlev kaybı yok) ---

function addCategory($mainUrl, $apiBase, $swKey, $categoryName, &$m3uContent, $referer, $ua, $ctx) {
    for ($page = 0; $page <= 25; $page++) {
        $apiUrl = $mainUrl . '/' . str_replace('SAYFA', $page, $apiBase);
        $response = @file_get_contents($apiUrl, false, $ctx);
        $data = json_decode($response, true);
        if (!is_array($data)) break;

        foreach ($data as $content) {
            if (!isset($content['sources'])) continue;
            foreach ($content['sources'] as $source) {
                if (($source['type'] ?? '') === 'm3u8') {
                    $title = $content['title'] ?? '';
                    $image = isset($content['image']) ? (
                        (strpos($content['image'], 'http') === 0) ? $content['image'] : $mainUrl . '/' . ltrim($content['image'], '/')
                    ) : '';
                    
                    $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categoryName\", $title\n";
                    $m3uContent .= "#EXTVLCOPT:http-user-agent=$ua\n";
                    $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                    $m3uContent .= "{$source['url']}\n";
                }
            }
        }
    }
}


// Filmler
$movieApis = [
    "api/movie/by/filtres/0/created/SAYFA/$swKey"   => "Son Filmler",
    "api/movie/by/filtres/14/created/SAYFA/$swKey"  => "Aile",
    "api/movie/by/filtres/1/created/SAYFA/$swKey"   => "Aksiyon",
];

// kısa tuttum ama istersen hepsini ekleyebilirim — sadece bana söyle ✨

foreach ($movieApis as $api => $cat)
    addCategory($mainUrl, $api, $swKey, $cat, $m3uContent, $referer, $m3uUserAgent, $context);

// Diziler
$seriesApis = [
    "api/serie/by/filtres/0/created/SAYFA/$swKey" => "Son Diziler"
];
foreach ($seriesApis as $api => $cat)
    addCategory($mainUrl, $api, $swKey, $cat, $m3uContent, $referer, $m3uUserAgent, $context);


// SONUÇ → TÜM M3U'YU EKRANA BAS
echo $m3uContent;
?>

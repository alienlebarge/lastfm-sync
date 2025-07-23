<?php

use Kirby\Http\Remote;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuid;

/**
 * Last.fm synchronization service for jams
 * 
 * This class encapsulates all jam synchronization logic
 * and can be used from anywhere in the application.
 */
class LastfmSyncService
{
    private $apiKey;
    private $user;
    private $contentDir;

    public function __construct()
    {
        $this->apiKey = kirby()->option('alienlebarge.lastfm-sync.apiKey');
        $this->user = kirby()->option('alienlebarge.lastfm-sync.user');
        
        $contentDirName = kirby()->option('alienlebarge.lastfm-sync.contentDir', 'jams');
        $this->contentDir = dirname(kirby()->root('index')) . '/data/storage/content/' . $contentDirName;
    }

    /**
     * Synchronizes jams from Last.fm
     * 
     * @param int $limit Number of jams to retrieve (default: 20)
     * @return array Synchronization result
     */
    public function sync(int $limit = 20): array
    {
        if (!$this->apiKey || !$this->user) {
            throw new Exception('Last.fm configuration missing. Check lastfm-sync plugin options');
        }

        // Create directory if it doesn't exist
        if (!is_dir($this->contentDir)) {
            mkdir($this->contentDir, 0755, true);
        }

        // Fetch latest jams
        $tracks = $this->fetchTracksFromLastfm($limit);
        
        // Import tracks
        $result = $this->importTracks($tracks);
        
        // Clear all caches and force regeneration if new items were imported
        if ($result['imported'] > 0) {
            $this->clearCacheAndRegenerate();
        }
        
        return $result;
    }

    /**
     * Fetches tracks from Last.fm API
     */
    private function fetchTracksFromLastfm(int $limit): array
    {
        $url = "http://ws.audioscrobbler.com/2.0/?method=user.getlovedtracks&user={$this->user}&api_key={$this->apiKey}&format=json&limit={$limit}";
        
        $request = Remote::get($url);
        
        if ($request->code() !== 200) {
            throw new Exception('Last.fm API request failed with code ' . $request->code());
        }

        $data = $request->json(false);
        $tracks = $data->lovedtracks->track ?? [];

        if (!is_array($tracks)) {
            $tracks = [$tracks];
        }

        return $tracks;
    }

    /**
     * Imports a list of tracks
     */
    private function importTracks(array $tracks): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tracks as $track) {
            try {
                $result = $this->importTrack($track);
                
                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("[LASTFM SYNC] Error importing track: " . $e->getMessage());
            }
        }

        return [
            'total' => count($tracks),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Imports an individual track
     */
    private function importTrack(object $track): string
    {
        // Check if this track already exists based on UTS timestamp
        if ($this->trackExists($track->date->uts)) {
            return 'skipped';
        }

        // Fetch detailed track information
        $trackDetails = $this->fetchTrackDetails($track);

        // Create directory and content
        $jamDir = $this->createJamDirectory($track);
        $this->createJamContent($track, $trackDetails, $jamDir);

        return 'imported';
    }

    /**
     * Checks if a track already exists
     */
    private function trackExists(string $uts): bool
    {
        $existingDirs = glob($this->contentDir . '/[0-9]*', GLOB_ONLYDIR);
        
        foreach ($existingDirs as $dir) {
            $jamFile = $dir . '/jam.txt';
            if (file_exists($jamFile)) {
                $content = file_get_contents($jamFile);
                if (strpos($content, "Uts: {$uts}") !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Fetches track details from the API
     */
    private function fetchTrackDetails(object $track): array
    {
        $url = "http://ws.audioscrobbler.com/2.0/?method=track.getInfo&api_key={$this->apiKey}&artist=" . urlencode($track->artist->name) . "&track=" . urlencode($track->name) . "&format=json";
        
        $request = Remote::get($url);
        
        $imageUrl = '';
        $albumName = '';

        if ($request->code() === 200) {
            $trackData = $request->json(false);

            if (isset($trackData->track->album->title)) {
                $albumName = $trackData->track->album->title;
            }

            if (isset($trackData->track->album->image)) {
                foreach ($trackData->track->album->image as $img) {
                    if (isset($img->size) && $img->size === 'large' && !empty($img->{'#text'})) {
                        $imageUrl = $img->{'#text'};
                        break;
                    }
                }
            }
        }

        return [
            'imageUrl' => $imageUrl,
            'albumName' => $albumName
        ];
    }

    /**
     * Creates the directory for a jam
     */
    private function createJamDirectory(object $track): string
    {
        $artistSlug = Str::slug($track->artist->name);
        $titleSlug = Str::slug($track->name);
        $dateFormat = date('YmdHi', $track->date->uts);
        $folderName = $dateFormat . '_' . $artistSlug . '-' . $titleSlug;

        $jamDir = $this->contentDir . '/' . $folderName;
        if (!is_dir($jamDir)) {
            mkdir($jamDir, 0755, true);
        }

        return $jamDir;
    }

    /**
     * Creates the jam content
     */
    private function createJamContent(object $track, array $details, string $jamDir): void
    {
        // Download cover image
        $coverReference = '';
        if (!empty($details['imageUrl'])) {
            $coverReference = $this->downloadCoverImage($details['imageUrl'], $jamDir);
        }

        // Create content
        $content = "Title: {$track->name}\n";
        $content .= "----\n";
        $content .= "Date: " . date('Y-m-d H:i', $track->date->uts) . "\n";
        $content .= "----\n";
        $content .= "Artist: {$track->artist->name}\n";
        $content .= "----\n";
        $content .= "Track: {$track->name}\n";
        $content .= "----\n";
        $content .= "Album: {$details['albumName']}\n";
        $content .= "----\n";
        $content .= "Url: {$track->url}\n";
        $content .= "----\n";
        $content .= "Cover: $coverReference\n";
        $content .= "----\n";
        $content .= "Template: jam\n";
        $content .= "----\n";
        $content .= "Uts: {$track->date->uts}\n";
        $content .= "----\n";
        $content .= "Uuid: " . Uuid::generate() . "\n";
        $content .= "----\n\n";
        $content .= "Text:\n\n";

        file_put_contents($jamDir . '/jam.txt', $content);
    }

    /**
     * Downloads and saves cover image
     */
    private function downloadCoverImage(string $imageUrl, string $jamDir): string
    {
        try {
            $imageRequest = Remote::get($imageUrl);

            if ($imageRequest->code() !== 200) {
                return '';
            }

            // Get extension
            $pathInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
            $extension = strtolower($pathInfo['extension'] ?? 'jpg');
            
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extension = 'jpg';
            }

            // Create file
            $filename = 'cover.' . $extension;
            $filepath = $jamDir . '/' . $filename;
            
            $imageData = $imageRequest->content();
            if (!empty($imageData)) {
                file_put_contents($filepath, $imageData);

                // Create metadata file
                $imageUuid = Uuid::generate();
                $metadataFile = $filepath . '.txt';
                $metadata = "Sort: 1\n----\nTemplate: image\n----\nUuid: $imageUuid\n";
                file_put_contents($metadataFile, $metadata);

                return 'file://' . $imageUuid;
            }

            return '';

        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Clears all relevant caches and forces regeneration of jam-related pages
     */
    private function clearCacheAndRegenerate(): void
    {
        try {
            // Clear all Kirby caches
            kirby()->cache('pages')->flush();
            
            // Also clear UUID cache if it exists
            if (kirby()->cache('uuid')) {
                kirby()->cache('uuid')->flush();
            }
            
            // Force regeneration of key pages that display jams
            $this->regenerateJamPages();
            
            error_log("[LASTFM SYNC] Cache cleared and pages regenerated successfully");
            
        } catch (Exception $e) {
            error_log("[LASTFM SYNC] Error clearing cache: " . $e->getMessage());
        }
    }

    /**
     * Forces regeneration of pages that display jams
     */
    private function regenerateJamPages(): void
    {
        try {
            // Get pages that typically display jams (home page, jams index, etc.)
            $pagesToRegenerate = [
                kirby()->site()->homePage(),
                kirby()->site()->find('jams'),
                kirby()->site()->find('blog')
            ];

            foreach ($pagesToRegenerate as $page) {
                if ($page && $page->exists()) {
                    // Force page render to regenerate cache
                    $page->render();
                    error_log("[LASTFM SYNC] Regenerated cache for page: " . $page->id());
                }
            }
            
        } catch (Exception $e) {
            error_log("[LASTFM SYNC] Error regenerating jam pages: " . $e->getMessage());
        }
    }
}
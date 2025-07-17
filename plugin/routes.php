<?php

return [
    [
        'pattern' => 'webhook/sync-jams',
        'method' => 'POST',
        'action' => function () {
            // Check security token
            $webhookSecret = kirby()->option('alienlebarge.lastfm-sync.webhookSecret');
            if ($webhookSecret) {
                $providedSecret = $_POST['secret'] ?? $_GET['secret'] ?? '';
                if ($providedSecret !== $webhookSecret) {
                    return kirby()->response()->json(['error' => 'Invalid secret'], 403);
                }
            }

            try {
                // Use synchronization service
                $limit = kirby()->option('alienlebarge.lastfm-sync.webhookLimit', 20);
                
                // Instantiate and use service
                $syncService = new LastfmSyncService();
                $result = $syncService->sync($limit);
                
                return kirby()->response()->json([
                    'success' => true,
                    'message' => 'Jams synchronization completed via Last.fm plugin',
                    'data' => $result
                ]);
                
            } catch (Exception $e) {
                return kirby()->response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    ]
];
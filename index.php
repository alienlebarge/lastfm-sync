<?php

/**
 * Last.fm Sync Plugin for Kirby
 * 
 * Synchronizes loved tracks from Last.fm API and creates jam pages
 * 
 * @version 1.0.0
 * @author alienlebarge
 */

use Kirby\Cms\App as Kirby;

// Load plugin dependencies
require_once __DIR__ . '/lib/LastfmSyncService.php';

// Register the plugin
Kirby::plugin('alienlebarge/lastfm-sync', [
    'options' => [
        'apiKey' => null,
        'user' => null,
        'webhookLimit' => 20,
        'webhookSecret' => 'your-secret-key-here',
        'contentDir' => 'jams'
    ],
    
    'routes' => require __DIR__ . '/plugin/routes.php',
    
    'pageMethods' => [
        'syncJams' => function (int $limit = null) {
            $limit = $limit ?? kirby()->option('alienlebarge.lastfm-sync.webhookLimit', 20);
            $service = new LastfmSyncService();
            return $service->sync($limit);
        }
    ],
    
    'siteMethods' => [
        'lastfmSync' => function () {
            return new LastfmSyncService();
        }
    ]
]);
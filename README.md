# Last.fm Sync Plugin

Kirby plugin to synchronize loved tracks from Last.fm and automatically create jam pages.

## Installation

1. Place the `lastfm-sync` folder in `site/plugins/`
2. Configure options in `site/config/config.php`

## Configuration

```php
return [
    'alienlebarge.lastfm-sync' => [
        'apiKey' => 'your-lastfm-api-key',
        'user' => 'your-lastfm-username',
        'webhookLimit' => 20,
        'webhookSecret' => 'your-webhook-secret',
        'contentDir' => 'jams'
    ]
];
```

### Options

- `apiKey`: Last.fm API key
- `user`: Last.fm username
- `webhookLimit`: Number of tracks to retrieve (default: 20)
- `webhookSecret`: Security token for webhook
- `contentDir`: Content directory (default: 'jams')

## Usage

### Webhook

Synchronization via POST webhook:

```bash
curl -X POST "https://your-site.com/lastfm-sync/cron/sync-jams" -d "secret=your-webhook-secret"
```

Or call the URL directly with GET parameter:

```bash
curl -X POST "https://your-site.com/lastfm-sync/cron/sync-jams?secret=your-webhook-secret"
```

### Page Methods

```php
// In a template or controller
$result = $page->syncJams(50); // Sync max 50 tracks
```

### Site Methods

```php
// Access to service
$syncService = site()->lastfmSync();
$result = $syncService->sync(20);
```

## Response

```json
{
    "success": true,
    "message": "Jams synchronization completed via Last.fm plugin",
    "data": {
        "total": 20,
        "imported": 2,
        "skipped": 18,
        "errors": 0
    }
}
```

## Security

The webhook uses a secret token for authentication. Configure `webhookSecret` in your options.

## License

MIT
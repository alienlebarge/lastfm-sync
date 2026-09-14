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
    "status": "OK",
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

**It has no default.** The endpoint answers `503` until a secret is set, rather than running unauthenticated — worth knowing if you fill the option from an environment variable, since a missing `.env` then fails closed instead of exposing the endpoint. The placeholder used in this README is refused for the same reason: it is published here.

Secrets are compared with `hash_equals()`.

## Monitoring a scheduled task

Every response carries a `status` field, and **only a successful run contains `OK`**:

```json
{ "status": "OK", "success": true, "message": "…", "data": { … } }
{ "status": "FAILED", "success": false, "error": "…" }
```

Failures say `FAILED` rather than relying on the `success` flag, so a host that monitors a task by matching a string in the body cannot pass on an error. HTTP status codes are set in parallel: `403` on an invalid secret, `503` when no secret is configured, `500` on a failure during synchronization.

## License

MIT
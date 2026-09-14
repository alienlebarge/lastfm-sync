<?php

use Kirby\Http\Response;

/**
 * Webhook endpoint, meant to be called by a scheduled task.
 *
 * Responses are built with Response::json(), not kirby()->response()->json():
 * the Responder method takes the payload only and silently ignores a second
 * argument, so an error built that way still answers 200 — and a scheduled
 * task monitored on its HTTP status looks healthy while failing.
 *
 * Every response also carries a `status` of "OK" or "FAILED", for hosts that
 * monitor a task by matching a string in the body. Nothing but a successful
 * run may contain "OK", which is why failures say "FAILED" rather than
 * relying on the `success` flag alone.
 */
return [
    [
        'pattern' => 'lastfm-sync/cron/sync-jams',
        'method' => 'POST|GET',
        'action' => function () {
            $configured = kirby()->option(
                'alienlebarge.lastfm-sync.webhookSecret'
            );

            // Fail closed. An unset secret used to skip the check entirely,
            // leaving the endpoint open to anyone — and since a site usually
            // fills this option from an environment variable, a missing .env
            // was enough to expose it. The packaged placeholder is refused
            // for the same reason: it is published in this repository.
            if (
                !is_string($configured) ||
                $configured === '' ||
                $configured === 'your-secret-key-here'
            ) {
                return Response::json([
                    'status' => 'FAILED',
                    'success' => false,
                    'error' => 'webhookSecret is not configured'
                ], 503);
            }

            $provided = $_POST['secret'] ?? $_GET['secret'] ?? '';

            if (!is_string($provided) || !hash_equals($configured, $provided)) {
                return Response::json([
                    'status' => 'FAILED',
                    'success' => false,
                    'error' => 'Invalid secret'
                ], 403);
            }

            try {
                $limit = kirby()->option(
                    'alienlebarge.lastfm-sync.webhookLimit',
                    20
                );

                $syncService = new LastfmSyncService();
                $result = $syncService->sync($limit);

                return Response::json([
                    'status' => 'OK',
                    'success' => true,
                    'message' =>
                        'Jams synchronization completed via Last.fm plugin',
                    'data' => $result
                ]);
            } catch (Throwable $e) {
                // Throwable, not Exception: a TypeError from the API payload
                // would otherwise escape as an uncaught fatal, answering an
                // HTML error page instead of the documented JSON.
                return Response::json([
                    'status' => 'FAILED',
                    'success' => false,
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    ]
];

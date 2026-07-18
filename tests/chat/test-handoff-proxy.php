<?php
/**
 * Lightweight smoke checks for live-agent handoff AJAX proxy wiring.
 *
 * Run: php tests/chat/test-handoff-proxy.php
 *
 * @package Licensesender
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (! $cond) {
        echo "FAIL: {$message}\n";
        $failures++;
        return;
    }
    echo "OK: {$message}\n";
}

$chat = file_get_contents($root.'/includes/class-ls-chat.php');
$api = file_get_contents($root.'/includes/class-licensesender-api.php');
$js = file_get_contents($root.'/public/js/ls-chat.js');

assert_true(is_string($chat) && str_contains($chat, 'ls_chat_handoff'), 'AJAX registers ls_chat_handoff');
assert_true(is_string($chat) && str_contains($chat, 'ajax_broadcast_bootstrap'), 'Broadcast bootstrap proxy exists');
assert_true(is_string($chat) && str_contains($chat, 'ajax_broadcast_auth'), 'Broadcast auth proxy exists');
assert_true(is_string($api) && str_contains($api, 'function chat_handoff'), 'API exposes chat_handoff');
assert_true(is_string($api) && str_contains($api, 'chat/broadcasting/auth'), 'API proxies broadcast auth');
assert_true(is_string($js) && str_contains($js, 'ls_chat_handoff'), 'Widget JS posts handoff action');
assert_true(is_string($js) && str_contains($js, 'initRealtime'), 'Widget JS initializes realtime');
assert_true(is_string($js) && str_contains($js, 'pollInFlight'), 'Widget JS prevents poll overlap');
assert_true(is_string($js) && ! str_contains($js, 'X-API-KEY'), 'Widget JS never embeds API key');

exit($failures > 0 ? 1 : 0);

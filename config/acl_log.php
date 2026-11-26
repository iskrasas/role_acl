<?php

return [
    'enabled' => env('ACL_LOG_ENABLED', true),

    /*
     * Time zone the log screens show and filter dates in (rows are stored in the app time zone).
     * Europe/Moscow is GMT+3 all year.
     */
    'timezone' => 'Europe/Moscow',

    /*
     * The prune command (acl:prune-logs) is scheduled by the package at this time every night.
     * null = do not schedule it (then run it yourself).
     */
    'prune_at' => '03:30',

    /*
     * Retention in days. Payloads are kept shorter than log rows. The values are floors:
     * the prune command never goes below 2 days for payloads and 14 days for logs.
     */
    'payload_days' => 2,
    'log_days' => 14,

    /*
     * Input keys (any depth, case-insensitive) whose values are replaced with "***" before saving.
     */
    'hidden' => ['password', 'password_confirmation', 'current_password', '_token', 'token', 'api_key', 'secret'],

    /*
     * A payload whose JSON is bigger than this is replaced by {"_truncated": true, "bytes": N}.
     */
    'max_payload_bytes' => 1_000_000,
];

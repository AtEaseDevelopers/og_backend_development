<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value system configuration maintained by Admin (Master Data → System Settings).
 *
 * Keys used by the order flow:
 *  - pending_customer_review_days  (int, default 7)   auto-close quotations left under customer review
 *  - emails_enabled                (bool, default true) master switch for system-generated emails
 *  - whatsapp_enabled              (bool, default true) master switch for WhatsApp share links / logs
 *  - enquiry_lock_seconds          (int, default 10)  a salesperson lock expires without a heartbeat
 */
class SystemSettings
{
    public const PENDING_REVIEW_DAYS = 'pending_customer_review_days';

    public const EMAILS_ENABLED = 'emails_enabled';

    public const WHATSAPP_ENABLED = 'whatsapp_enabled';

    public const ENQUIRY_LOCK_SECONDS = 'enquiry_lock_seconds';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        self::PENDING_REVIEW_DAYS => 7,
        self::EMAILS_ENABLED => true,
        self::WHATSAPP_ENABLED => true,
        self::ENQUIRY_LOCK_SECONDS => 10,
    ];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::PENDING_REVIEW_DAYS => 'Pending customer review duration (days)',
            self::EMAILS_ENABLED => 'Send system-generated emails',
            self::WHATSAPP_ENABLED => 'Enable WhatsApp share links',
            self::ENQUIRY_LOCK_SECONDS => 'Enquiry lock timeout (seconds)',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function int(string $key): int
    {
        return (int) static::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(static::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(static::cacheKey());
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::remember(static::cacheKey(), 60, function (): array {
            if (! Schema::hasTable('system_settings')) {
                return self::DEFAULTS;
            }

            $stored = DB::table('system_settings')
                ->pluck('value', 'key')
                ->map(fn ($value) => json_decode((string) $value, true))
                ->all();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    private static function cacheKey(): string
    {
        return 'og.system_settings';
    }
}

<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Outgoing email (SMTP) set up by Admin under System Settings. Until it is set up the .env mailer is used
 * (locally "log": emails are written to the log, not sent). The password is stored encrypted.
 */
class MailSettings
{
    public const MAILER = 'mail_mailer';

    public const HOST = 'mail_host';

    public const PORT = 'mail_port';

    public const ENCRYPTION = 'mail_encryption';

    public const USERNAME = 'mail_username';

    public const PASSWORD = 'mail_password';

    public const FROM_ADDRESS = 'mail_from_address';

    public const FROM_NAME = 'mail_from_name';

    /** @var list<string> */
    public const KEYS = [self::MAILER, self::HOST, self::PORT, self::ENCRYPTION, self::USERNAME, self::PASSWORD, self::FROM_ADDRESS, self::FROM_NAME];

    public static function isSmtp(): bool
    {
        return SystemSettings::get(self::MAILER) === 'smtp' && filled(SystemSettings::get(self::HOST));
    }

    public static function hasPassword(): bool
    {
        return filled(SystemSettings::get(self::PASSWORD));
    }

    /** Point Laravel's mailer at the saved SMTP settings (no-op until SMTP is set up). */
    public static function apply(): void
    {
        try {
            if (! static::isSmtp()) {
                return;
            }

            $encryption = SystemSettings::get(self::ENCRYPTION);

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) SystemSettings::get(self::HOST),
                'mail.mailers.smtp.port' => (int) (SystemSettings::get(self::PORT) ?: 587),
                'mail.mailers.smtp.encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null,
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
                'mail.mailers.smtp.username' => SystemSettings::get(self::USERNAME) ?: null,
                'mail.mailers.smtp.password' => static::password(),
            ]);

            if (filled(SystemSettings::get(self::FROM_ADDRESS))) {
                config([
                    'mail.from.address' => (string) SystemSettings::get(self::FROM_ADDRESS),
                    'mail.from.name' => (string) (SystemSettings::get(self::FROM_NAME) ?: config('app.name')),
                ]);
            }

            // rebuild the mailer with the new settings
            Mail::purge('smtp');
        } catch (Throwable) {
            // settings table missing (fresh install): keep the .env mailer
        }
    }

    public static function password(): ?string
    {
        $stored = SystemSettings::get(self::PASSWORD);

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function encryptPassword(string $password): string
    {
        return Crypt::encryptString($password);
    }
}

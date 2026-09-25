<?php

declare(strict_types=1);

namespace AbuseIpDb\Enum;

/**
 * AbuseIPDB report category IDs (https://www.abuseipdb.com/categories).
 */
final class ReportCategory
{
    /** DNS Compromise. */
    public const DNS_COMPROMISE = 1;

    /** DNS Poisoning. */
    public const DNS_POISONING = 2;

    /** Fraud Orders. */
    public const FRAUD_ORDERS = 3;

    /** DDoS Attack. */
    public const DDOS_ATTACK = 4;

    /** FTP Brute-Force. */
    public const FTP_BRUTE_FORCE = 5;

    /** Ping of Death. */
    public const PING_OF_DEATH = 6;

    /** Phishing. */
    public const PHISHING = 7;

    /** Fraud VoIP. */
    public const FRAUD_VOIP = 8;

    /** Open Proxy. */
    public const OPEN_PROXY = 9;

    /** Web Spam. */
    public const WEB_SPAM = 10;

    /** Email Spam. */
    public const EMAIL_SPAM = 11;

    /** Blog Spam. */
    public const BLOG_SPAM = 12;

    /** VPN IP. */
    public const VPN_IP = 13;

    /** Port Scan. */
    public const PORT_SCAN = 14;

    /** Hacking. */
    public const HACKING = 15;

    /** SQL Injection. */
    public const SQL_INJECTION = 16;

    /** Spoofing. */
    public const SPOOFING = 17;

    /** Brute-Force. */
    public const BRUTE_FORCE = 18;

    /** Bad Web Bot. */
    public const BAD_WEB_BOT = 19;

    /** Exploited Host. */
    public const EXPLOITED_HOST = 20;

    /** Web App Attack. */
    public const WEB_APP_ATTACK = 21;

    /** SSH. */
    public const SSH = 22;

    /** IoT Targeted. */
    public const IOT_TARGETED = 23;

    private const NAMES = [
        1 => 'DNS Compromise', 2 => 'DNS Poisoning', 3 => 'Fraud Orders', 4 => 'DDoS Attack',
        5 => 'FTP Brute-Force', 6 => 'Ping of Death', 7 => 'Phishing', 8 => 'Fraud VoIP',
        9 => 'Open Proxy', 10 => 'Web Spam', 11 => 'Email Spam', 12 => 'Blog Spam',
        13 => 'VPN IP', 14 => 'Port Scan', 15 => 'Hacking', 16 => 'SQL Injection',
        17 => 'Spoofing', 18 => 'Brute-Force', 19 => 'Bad Web Bot', 20 => 'Exploited Host',
        21 => 'Web App Attack', 22 => 'SSH', 23 => 'IoT Targeted',
    ];

    private function __construct()
    {
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return self::NAMES;
    }

    public static function isValid(int $id): bool
    {
        return isset(self::NAMES[$id]);
    }

    public static function nameOf(int $id): ?string
    {
        return self::NAMES[$id] ?? null;
    }
}

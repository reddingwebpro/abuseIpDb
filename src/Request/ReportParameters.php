<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Enum\ReportCategory;
use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Support\Assert;

/**
 * Payload parameters for the `report` endpoint.
 */
final class ReportParameters
{
    public const MAX_COMMENT_LENGTH = 1024;

    private string $ip;
    /** @var list<int> */
    private array $categories;
    private ?string $comment;
    private ?\DateTimeImmutable $timestamp;

    /**
     * @param string $ip the IPv4 or IPv6 address being reported
     * @param list<int> $categories one or more ReportCategory IDs; duplicates are removed
     * @param string|null $comment optional free-text details, up to self::MAX_COMMENT_LENGTH characters
     * @param \DateTimeInterface|null $timestamp optional time the abuse occurred; only \DateTimeImmutable and \DateTime instances are kept, others are treated as null
     */
    public function __construct(string $ip, array $categories, ?string $comment = null, ?\DateTimeInterface $timestamp = null)
    {
        Assert::ip($ip, 'ip');
        if ($categories === []) {
            throw new InvalidArgumentException('At least one report category is required.');
        }
        foreach ($categories as $category) {
            if (!ReportCategory::isValid((int) $category)) {
                throw new InvalidArgumentException(sprintf('Unknown report category "%d".', $category));
            }
        }
        if ($comment !== null && strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new InvalidArgumentException(sprintf('comment must not exceed %d characters.', self::MAX_COMMENT_LENGTH));
        }
        $this->ip = $ip;
        $this->categories = array_values(array_unique($categories));
        $this->comment = $comment;
        if ($timestamp instanceof \DateTime) {
            $timestamp = \DateTimeImmutable::createFromMutable($timestamp);
        }
        $this->timestamp = $timestamp instanceof \DateTimeImmutable ? $timestamp : null;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    /** @return list<int> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getTimestamp(): ?\DateTimeImmutable
    {
        return $this->timestamp;
    }

    /** @return array<string, string|list<int>> */
    public function toBody(): array
    {
        $b = ['ip' => $this->ip, 'categories' => $this->categories];
        if ($this->comment !== null && $this->comment !== '') {
            $b['comment'] = $this->comment;
        }
        if ($this->timestamp !== null) {
            $b['timestamp'] = $this->timestamp->format(\DateTimeInterface::ATOM);
        }

        return $b;
    }
}

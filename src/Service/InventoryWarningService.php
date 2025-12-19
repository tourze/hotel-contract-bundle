<?php

namespace Tourze\HotelContractBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'hotel_contract')]
final class InventoryWarningService
{
    private const WARNING_CACHE_KEY = 'inventory_warning_sent_%s_%s_%s'; // hotel_id_roomType_id_date

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly InventoryConfig $inventoryConfig,
        private readonly InventorySummaryRepository $inventorySummaryRepository,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * 检查库存并发送预警邮件
     *
     * @param \DateTimeInterface|null $date 指定日期，为空则检查未来90天
     *
     * @return array 处理结果
     */
    /**
     * @return array<string, mixed>
     */
    public function checkAndSendWarnings(?\DateTimeInterface $date = null): array
    {
        $config = $this->inventoryConfig->getWarningConfig();

        $validationResult = $this->validateWarningConfig($config);
        $isValid = $validationResult['success'] ?? false;
        if (!is_bool($isValid) || !$isValid) {
            return $validationResult;
        }

        $dateRange = $this->getDateRange($date);
        $inventorySummaries = $this->findWarningInventories($dateRange);

        if ([] === $inventorySummaries) {
            return [
                'success' => true,
                'message' => '未发现需要预警的库存',
                'sent_count' => 0,
            ];
        }

        $emailRecipients = $config['email_recipients'] ?? '';
        $recipientsString = is_string($emailRecipients) ? $emailRecipients : '';
        $recipients = $this->parseEmailRecipients($recipientsString);
        if ([] === $recipients) {
            return [
                'success' => false,
                'message' => '预警邮件收件人格式错误',
                'sent_count' => 0,
            ];
        }

        $sentCount = $this->sendWarningNotifications($inventorySummaries, $recipients, $config);

        return [
            'success' => true,
            'message' => sprintf('成功发送%d条库存预警通知', $sentCount),
            'sent_count' => $sentCount,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateWarningConfig(array $config): array
    {
        $enableWarning = $config['enable_warning'] ?? false;
        if (!is_bool($enableWarning) || !$enableWarning) {
            return [
                'success' => true,
                'message' => '库存预警功能未启用',
                'sent_count' => 0,
            ];
        }

        $emailRecipients = $config['email_recipients'] ?? '';
        if ('' === $emailRecipients) {
            return [
                'success' => false,
                'message' => '未设置预警邮件收件人',
                'sent_count' => 0,
            ];
        }

        return ['success' => true];
    }

    /**
     * @return array<string, \DateTimeInterface>
     */
    private function getDateRange(?\DateTimeInterface $date): array
    {
        if (null !== $date) {
            return ['start' => clone $date, 'end' => clone $date];
        }

        $start = new \DateTimeImmutable();
        $end = $start->modify('+90 days');

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param array<string, \DateTimeInterface> $dateRange
     * @return array<InventorySummary>
     */
    private function findWarningInventories(array $dateRange): array
    {
        /** @var array<InventorySummary> */
        return $this->inventorySummaryRepository
            ->createQueryBuilder('is')
            ->select('is')
            ->leftJoin('is.hotel', 'h')
            ->leftJoin('is.roomType', 'rt')
            ->where('is.status = :status')
            ->andWhere('is.date >= :startDate')
            ->andWhere('is.date <= :endDate')
            ->setParameter('status', InventorySummaryStatusEnum::WARNING)
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<string>
     */
    private function parseEmailRecipients(string $recipients): array
    {
        return array_filter(array_map('trim', explode("\n", $recipients)), static fn (string $email): bool => '' !== $email);
    }

    /**
     * @param array<InventorySummary> $inventorySummaries
     * @param array<string> $recipients
     * @param array<string, mixed> $config
     */
    private function sendWarningNotifications(array $inventorySummaries, array $recipients, array $config): int
    {
        $sentCount = 0;
        $intervalValue = $config['warning_interval'] ?? 24;
        if (!is_int($intervalValue) && !is_float($intervalValue) && !is_numeric($intervalValue)) {
            $intervalValue = 24;
        }
        $warningInterval = (int) $intervalValue * 3600;

        foreach ($inventorySummaries as $summary) {
            if ($this->shouldSendWarning($summary, $warningInterval)) {
                if ($this->sendWarningForSummary($summary, $recipients, $warningInterval)) {
                    ++$sentCount;
                }
            }
        }

        return $sentCount;
    }

    private function shouldSendWarning(InventorySummary $summary, int $warningInterval): bool
    {
        $hotel = $summary->getHotel();
        $roomType = $summary->getRoomType();
        $date = $summary->getDate();

        if (null === $hotel || null === $roomType || null === $date) {
            return false;
        }

        $cacheKey = sprintf(
            self::WARNING_CACHE_KEY,
            $hotel->getId(),
            $roomType->getId(),
            $date->format('Y-m-d')
        );

        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $lastSent = $cacheItem->get();
            $lastSentTime = is_int($lastSent) ? $lastSent : 0;

            return time() - $lastSentTime >= $warningInterval;
        }

        return true;
    }

    /**
     * @param array<string> $recipients
     */
    private function sendWarningForSummary(InventorySummary $summary, array $recipients, int $warningInterval): bool
    {
        try {
            $this->sendWarningEmail($summary, $recipients);
            $this->updateWarningCache($summary, $warningInterval);

            return true;
        } catch (\Throwable $e) {
            $hotel = $summary->getHotel();
            $roomType = $summary->getRoomType();
            $date = $summary->getDate();

            $this->logger->error('发送库存预警邮件失败: ' . $e->getMessage(), [
                'summary_id' => $summary->getId(),
                'hotel' => $hotel?->getName() ?? 'Unknown',
                'room_type' => $roomType?->getName() ?? 'Unknown',
                'date' => $date?->format('Y-m-d') ?? 'Unknown',
            ]);

            return false;
        }
    }

    private function updateWarningCache(InventorySummary $summary, int $warningInterval): void
    {
        $hotel = $summary->getHotel();
        $roomType = $summary->getRoomType();
        $date = $summary->getDate();

        if (null === $hotel || null === $roomType || null === $date) {
            return;
        }

        $cacheKey = sprintf(
            self::WARNING_CACHE_KEY,
            $hotel->getId(),
            $roomType->getId(),
            $date->format('Y-m-d')
        );

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set(time());
        $cacheItem->expiresAfter($warningInterval);
        $this->cache->save($cacheItem);
    }

    /**
     * 发送库存预警邮件
     *
     * @param InventorySummary $summary    库存统计记录
     * @param array<string>    $recipients 收件人列表
     */
    private function sendWarningEmail(InventorySummary $summary, array $recipients): void
    {
        $hotel = $summary->getHotel();
        $roomType = $summary->getRoomType();
        $date = $summary->getDate();

        if (null === $hotel || null === $roomType || null === $date) {
            throw new InvalidEntityException('库存统计记录缺少必要的关联数据');
        }

        $dateString = $date->format('Y-m-d');
        $availableRate = $summary->getTotalRooms() > 0
            ? round(($summary->getAvailableRooms() / $summary->getTotalRooms()) * 100, 2)
            : 0;

        $subject = sprintf(
            '【库存预警】%s - %s 在 %s 的库存仅剩 %.2f%%',
            $hotel->getName(),
            $roomType->getName(),
            $dateString,
            $availableRate
        );

        $body = sprintf(
            "尊敬的管理员：\n\n酒店 %s 的 %s 房型在 %s 的库存状态已经触发预警：\n\n" .
                "- 总房间数: %d\n" .
                "- 可用房间数: %d\n" .
                "- 已售房间数: %d\n" .
                "- 待确认房间数: %d\n" .
                "- 预留房间数: %d\n" .
                "- 剩余可用率: %.2f%%\n\n" .
                "请及时处理此库存问题。\n\n" .
                '此邮件由系统自动发送，请勿回复。',
            $hotel->getName(),
            $roomType->getName(),
            $dateString,
            $summary->getTotalRooms(),
            $summary->getAvailableRooms(),
            $summary->getSoldRooms(),
            $summary->getPendingRooms(),
            $summary->getReservedRooms(),
            $availableRate
        );

        $email = (new Email())
            ->subject($subject)
            ->text($body)
        ;

        // 添加所有收件人
        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);
    }
}

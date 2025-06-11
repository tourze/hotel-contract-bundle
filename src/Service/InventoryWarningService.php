<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;

class InventoryWarningService
{
    private const WARNING_CACHE_KEY = 'inventory_warning_sent_%s_%s_%s'; // hotel_id_roomType_id_date

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly InventoryConfig $inventoryConfig,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * 检查库存并发送预警邮件
     *
     * @param \DateTimeInterface|null $date 指定日期，为空则检查未来90天
     * @return array 处理结果
     */
    public function checkAndSendWarnings(?\DateTimeInterface $date = null): array
    {
        $config = $this->inventoryConfig->getWarningConfig();

        // 如果未启用预警功能，直接返回
        if (!$config['enable_warning']) {
            return [
                'success' => true,
                'message' => '库存预警功能未启用',
                'sent_count' => 0
            ];
        }

        // 如果没有设置收件人，无法发送邮件
        if (empty($config['email_recipients'])) {
            return [
                'success' => false,
                'message' => '未设置预警邮件收件人',
                'sent_count' => 0
            ];
        }

        // 如果指定了日期，只检查该日期的库存
        if ($date) {
            $startDate = clone $date;
            $endDate = clone $date;
        } else {
            // 默认检查从今天开始的90天内的库存
            $startDate = new \DateTime();
            $endDate = clone $startDate;
            $endDate->modify('+90 days');
        }

        // 查询库存预警状态的记录
        $inventorySummaries = $this->entityManager->getRepository(InventorySummary::class)
            ->createQueryBuilder('is')
            ->select('is')
            ->leftJoin('is.hotel', 'h')
            ->leftJoin('is.roomType', 'rt')
            ->where('is.status = :status')
            ->andWhere('is.date >= :startDate')
            ->andWhere('is.date <= :endDate')
            ->setParameter('status', InventorySummaryStatusEnum::WARNING)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        if (empty($inventorySummaries)) {
            return [
                'success' => true,
                'message' => '未发现需要预警的库存',
                'sent_count' => 0
            ];
        }

        // 获取邮件收件人列表
        $recipients = array_filter(array_map('trim', explode("\n", $config['email_recipients'])));
        if (empty($recipients)) {
            return [
                'success' => false,
                'message' => '预警邮件收件人格式错误',
                'sent_count' => 0
            ];
        }

        $sentCount = 0;
        $warningInterval = (int)$config['warning_interval'] * 3600; // 转换为秒

        foreach ($inventorySummaries as $summary) {
            $cacheKey = sprintf(
                self::WARNING_CACHE_KEY,
                $summary->getHotel()->getId(),
                $summary->getRoomType()->getId(),
                $summary->getDate()->format('Y-m-d')
            );

            // 检查是否在发送间隔期内已经发送过预警
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $lastSent = $cacheItem->get();
                if (time() - $lastSent < $warningInterval) {
                    continue;
                }
            }

            // 发送预警邮件
            try {
                $this->sendWarningEmail($summary, $recipients);

                // 更新缓存
                $cacheItem->set(time());
                $cacheItem->expiresAfter($warningInterval);
                $this->cache->save($cacheItem);

                $sentCount++;
            } catch (\Throwable $e) {
                $this->logger->error('发送库存预警邮件失败: ' . $e->getMessage(), [
                    'summary_id' => $summary->getId(),
                    'hotel' => $summary->getHotel()->getName(),
                    'room_type' => $summary->getRoomType()->getName(),
                    'date' => $summary->getDate()->format('Y-m-d')
                ]);
            }
        }

        return [
            'success' => true,
            'message' => sprintf('成功发送%d条库存预警通知', $sentCount),
            'sent_count' => $sentCount
        ];
    }

    /**
     * 发送库存预警邮件
     *
     * @param InventorySummary $summary 库存统计记录
     * @param array $recipients 收件人列表
     */
    private function sendWarningEmail(InventorySummary $summary, array $recipients): void
    {
        $hotel = $summary->getHotel();
        $roomType = $summary->getRoomType();
        $date = $summary->getDate()->format('Y-m-d');
        $availableRate = $summary->getTotalRooms() > 0
            ? round(($summary->getAvailableRooms() / $summary->getTotalRooms()) * 100, 2)
            : 0;

        $subject = sprintf(
            '【库存预警】%s - %s 在 %s 的库存仅剩 %.2f%%',
            $hotel->getName(),
            $roomType->getName(),
            $date,
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
                "此邮件由系统自动发送，请勿回复。",
            $hotel->getName(),
            $roomType->getName(),
            $date,
            $summary->getTotalRooms(),
            $summary->getAvailableRooms(),
            $summary->getSoldRooms(),
            $summary->getPendingRooms(),
            $summary->getReservedRooms(),
            $availableRate
        );

        $email = (new Email())
            ->subject($subject)
            ->text($body);

        // 添加所有收件人
        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);
    }
}

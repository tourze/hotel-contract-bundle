<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Exception\ContractStatusInvalidException;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'hotel_contract')]
readonly class ContractService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private HotelContractRepository $hotelContractRepository,
        private string $adminEmail = 'admin@example.com',
    ) {
    }

    /**
     * 生成合同编号
     * 格式：HT + 年月日 + 3位序号（当日第几个合同）
     */
    public function generateContractNumber(): string
    {
        $today = new \DateTimeImmutable();
        $prefix = 'HT' . $today->format('ymd');

        // 查询今天已有的合同数量
        $startOfDay = $today->setTime(0, 0, 0);
        $endOfDay = $today->setTime(23, 59, 59);

        $count = $this->hotelContractRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.contractNo LIKE :prefix')
            ->andWhere('c.createTime >= :startOfDay')
            ->andWhere('c.createTime <= :endOfDay')
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $sequence = str_pad((string) ((int) $count + 1), 3, '0', STR_PAD_LEFT);

        return $prefix . $sequence;
    }

    /**
     * 审批合同生效
     */
    public function approveContract(HotelContract $contract): void
    {
        if (ContractStatusEnum::PENDING !== $contract->getStatus()) {
            throw new ContractStatusInvalidException('只有待确认状态的合同才能审批生效');
        }

        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $this->entityManager->flush();

        // 记录日志
        $this->logger->info('合同审批生效', [
            'contract_id' => $contract->getId(),
            'contract_no' => $contract->getContractNo(),
        ]);

        // 发送通知
        $this->sendContractStatusChangedNotification($contract, '审批生效');
    }

    /**
     * 终止合同
     */
    public function terminateContract(HotelContract $contract, string $terminationReason): void
    {
        if (ContractStatusEnum::TERMINATED === $contract->getStatus()) {
            throw new ContractStatusInvalidException('合同已经是终止状态');
        }

        $contract->setStatus(ContractStatusEnum::TERMINATED);
        $contract->setTerminationReason($terminationReason);
        $this->entityManager->flush();

        // 记录日志
        $this->logger->info('合同终止', [
            'contract_id' => $contract->getId(),
            'contract_no' => $contract->getContractNo(),
            'reason' => $terminationReason,
        ]);

        // 发送通知
        $this->sendContractStatusChangedNotification($contract, '终止', $terminationReason);
    }

    /**
     * 调整合同优先级
     */
    public function adjustPriority(HotelContract $contract, int $newPriority): void
    {
        $oldPriority = $contract->getPriority();
        $contract->setPriority($newPriority);
        $this->entityManager->flush();

        // 记录日志
        $this->logger->info('调整合同优先级', [
            'contract_id' => $contract->getId(),
            'contract_no' => $contract->getContractNo(),
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority,
        ]);
    }

    /**
     * 发送合同创建通知
     */
    public function sendContractCreatedNotification(HotelContract $contract): void
    {
        try {
            $contractType = $contract->getContractType();
            $contractTypeLabel = $contractType->getLabel();

            $email = new Email();
            $email->from('system@hotel.com');
            $email->to($this->adminEmail);
            $email->subject('Contract Creation Notification');

            $htmlContent = sprintf(
                '<h3>Contract Creation Notification</h3>
                <p><strong>Contract Number</strong>: %s</p>
                <p><strong>Hotel</strong>: %s</p>
                <p><strong>Contract Type</strong>: %s</p>
                <p><strong>Start Date</strong>: %s</p>
                <p><strong>End Date</strong>: %s</p>
                <p><strong>Total Rooms</strong>: %d</p>
                <p><strong>Total Amount</strong>: %s</p>
                <p><strong>Creation Time</strong>: %s</p>',
                $contract->getContractNo(),
                $contract->getHotel()?->getName() ?? 'Unknown Hotel',
                $contractTypeLabel,
                $contract->getStartDate()?->format('Y-m-d') ?? 'Not set',
                $contract->getEndDate()?->format('Y-m-d') ?? 'Not set',
                $contract->getTotalRooms(),
                $contract->getTotalAmount(),
                $contract->getCreateTime()?->format('Y-m-d H:i:s') ?? 'Unknown'
            );

            $email->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Contract creation notification sent', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract creation notification', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送合同更新通知
     */
    public function sendContractUpdatedNotification(HotelContract $contract): void
    {
        try {
            $status = $contract->getStatus();
            $statusLabel = $status->getLabel();

            $email = new Email();
            $email->from('system@hotel.com');
            $email->to($this->adminEmail);
            $email->subject('Contract Update Notification');

            $htmlContent = sprintf(
                '<h3>Contract Update Notification</h3>
                <p><strong>Contract Number</strong>: %s</p>
                <p><strong>Hotel</strong>: %s</p>
                <p><strong>Current Status</strong>: %s</p>
                <p><strong>Update Time</strong>: %s</p>
                
                <p>Contract information has been updated. Please check details.</p>',
                $contract->getContractNo(),
                $contract->getHotel()?->getName() ?? 'Unknown Hotel',
                $statusLabel,
                $contract->getUpdateTime()?->format('Y-m-d H:i:s') ?? 'Unknown'
            );

            $email->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Contract update notification sent', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract update notification', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送合同状态变更通知
     */
    private function sendContractStatusChangedNotification(HotelContract $contract, string $action, ?string $reason = null): void
    {
        try {
            $reasonText = null !== $reason ? sprintf('<p><strong>Reason</strong>: %s</p>', $reason) : '';

            $status = $contract->getStatus();
            $statusLabel = $status->getLabel();

            $email = new Email();
            $email->from('system@hotel.com');
            $email->to($this->adminEmail);
            $email->subject(sprintf('Contract %s Notification', $action));

            $htmlContent = sprintf(
                '<h3>Contract %s Notification</h3>
                <p><strong>Contract Number</strong>: %s</p>
                <p><strong>Hotel</strong>: %s</p>
                <p><strong>Action</strong>: %s</p>
                <p><strong>Current Status</strong>: %s</p>
                %s
                <p><strong>Action Time</strong>: %s</p>',
                $action,
                $contract->getContractNo(),
                $contract->getHotel()?->getName() ?? 'Unknown Hotel',
                $action,
                $statusLabel,
                $reasonText,
                (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            );

            $email->html($htmlContent);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract status change notification', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

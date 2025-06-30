<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Exception\ContractStatusInvalidException;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;

class ContractService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly HotelContractRepository $hotelContractRepository,
        private readonly string $adminEmail = 'admin@example.com',
    ) {}

    /**
     * 生成合同编号
     * 格式：HT + 年月日 + 3位序号（当日第几个合同）
     */
    public function generateContractNumber(): string
    {
        $today = new \DateTimeImmutable();
        $prefix = 'HT' . $today->format('ymd');

        // 查询今天已有的合同数量
        $count = $this->hotelContractRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.contractNo LIKE :prefix')
            ->andWhere('DATE(c.createTime) = :today')
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        $sequence = str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);

        return $prefix . $sequence;
    }

    /**
     * 审批合同生效
     */
    public function approveContract(HotelContract $contract): void
    {
        if ($contract->getStatus() !== ContractStatusEnum::PENDING) {
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
        if ($contract->getStatus() === ContractStatusEnum::TERMINATED) {
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
            $email = (new Email())
                ->from('system@hotel.com')
                ->to($this->adminEmail)
                ->subject('新合同创建通知')
                ->html(sprintf(
                    '<h3>合同创建通知</h3>
                    <p><strong>合同编号</strong>: %s</p>
                    <p><strong>酒店</strong>: %s</p>
                    <p><strong>合同类型</strong>: %s</p>
                    <p><strong>开始日期</strong>: %s</p>
                    <p><strong>结束日期</strong>: %s</p>
                    <p><strong>总房间数</strong>: %d</p>
                    <p><strong>总金额</strong>: ¥%s</p>
                    <p><strong>创建时间</strong>: %s</p>',
                    $contract->getContractNo(),
                    $contract->getHotel()?->getName() ?? '未知酒店',
                    $contract->getContractType()->getLabel(),
                    $contract->getStartDate()?->format('Y-m-d') ?? '未设置',
                    $contract->getEndDate()?->format('Y-m-d') ?? '未设置',
                    $contract->getTotalRooms(),
                    $contract->getTotalAmount(),
                    $contract->getCreateTime()?->format('Y-m-d H:i:s') ?? '未知时间'
                ));

            $this->mailer->send($email);

            $this->logger->info('合同创建通知已发送', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('发送合同创建通知失败', [
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
            $email = (new Email())
                ->from('system@hotel.com')
                ->to($this->adminEmail)
                ->subject('合同更新通知')
                ->html(sprintf(
                    '<h3>合同更新通知</h3>
                    <p><strong>合同编号</strong>: %s</p>
                    <p><strong>酒店</strong>: %s</p>
                    <p><strong>当前状态</strong>: %s</p>
                    <p><strong>更新时间</strong>: %s</p>
                    
                    <p>合同信息已更新，请查看详情。</p>',
                    $contract->getContractNo(),
                    $contract->getHotel()?->getName() ?? '未知酒店',
                    $contract->getStatus()->getLabel(),
                    $contract->getUpdateTime()?->format('Y-m-d H:i:s') ?? '未知时间'
                ));

            $this->mailer->send($email);

            $this->logger->info('合同更新通知已发送', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('发送合同更新通知失败', [
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
            $reasonText = $reason !== null ? sprintf('<p><strong>原因</strong>: %s</p>', $reason) : '';

            $email = (new Email())
                ->from('system@hotel.com')
                ->to($this->adminEmail)
                ->subject(sprintf('合同%s通知', $action))
                ->html(sprintf(
                    '<h3>合同%s通知</h3>
                    <p><strong>合同编号</strong>: %s</p>
                    <p><strong>酒店</strong>: %s</p>
                    <p><strong>操作</strong>: %s</p>
                    <p><strong>当前状态</strong>: %s</p>
                    %s
                    <p><strong>操作时间</strong>: %s</p>',
                    $action,
                    $contract->getContractNo(),
                    $contract->getHotel()?->getName() ?? '未知酒店',
                    $action,
                    $contract->getStatus()->getLabel(),
                    $reasonText,
                    (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('发送合同状态变更通知失败', [
                'contract_id' => $contract->getId(),
                'contract_no' => $contract->getContractNo(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;

class ContractService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'admin@example.com',
    ) {}

    /**
     * 生成合同编号
     * 格式：HT + 年月日 + 3位序号
     */
    public function generateContractNumber(): string
    {
        $today = new \DateTimeImmutable();
        $datePrefix = 'HT' . $today->format('Ymd');

        // 查询今天已有的合同数量
        $count = $this->entityManager->getRepository(HotelContract::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c)')
            ->where('c.contractNo LIKE :prefix')
            ->setParameter('prefix', $datePrefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        // 计算序号并生成编号
        $serialNumber = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        return $datePrefix . $serialNumber;
    }

    /**
     * 审批合同
     */
    public function approveContract(HotelContract $contract): void
    {
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $this->entityManager->flush();

        // 发送审批通知邮件
        $this->sendContractStatusChangedNotification($contract, '合同已审批生效');

        $this->logger->info('合同已审批生效', [
            'contractNo' => $contract->getContractNo(),
            'hotelId' => $contract->getHotel()->getId(),
            'hotelName' => $contract->getHotel()->getName(),
        ]);
    }

    /**
     * 终止合同
     */
    public function terminateContract(HotelContract $contract, string $terminationReason): void
    {
        $contract->setStatus(ContractStatusEnum::TERMINATED);
        $contract->setTerminationReason($terminationReason);
        $this->entityManager->flush();

        // 发送终止通知邮件
        $this->sendContractStatusChangedNotification($contract, '合同已终止', $terminationReason);

        $this->logger->info('合同已终止', [
            'contractNo' => $contract->getContractNo(),
            'hotelId' => $contract->getHotel()->getId(),
            'hotelName' => $contract->getHotel()->getName(),
            'terminationReason' => $terminationReason,
        ]);
    }

    /**
     * 调整合同优先级
     */
    public function adjustPriority(HotelContract $contract, int $newPriority): void
    {
        $oldPriority = $contract->getPriority();
        $contract->setPriority($newPriority);
        $this->entityManager->flush();

        $this->logger->info('合同优先级已调整', [
            'contractNo' => $contract->getContractNo(),
            'hotelId' => $contract->getHotel()->getId(),
            'hotelName' => $contract->getHotel()->getName(),
            'oldPriority' => $oldPriority,
            'newPriority' => $newPriority,
        ]);
    }

    /**
     * 发送合同创建通知
     */
    public function sendContractCreatedNotification(HotelContract $contract): void
    {
        $hotelName = $contract->getHotel()->getName();
        $contractNo = $contract->getContractNo();

        $email = (new Email())
            ->from('noreply@hotel-booking-system.com')
            ->to($this->adminEmail)
            ->subject("新合同创建通知: $contractNo")
            ->html("
                <p>尊敬的管理员：</p>
                <p>系统已创建新的酒店合同，详情如下：</p>
                <ul>
                    <li>合同编号：{$contractNo}</li>
                    <li>酒店名称：{$hotelName}</li>
                    <li>合同类型：{$contract->getContractType()->getLabel()}</li>
                    <li>开始日期：{$contract->getStartDate()->format('Y-m-d')}</li>
                    <li>结束日期：{$contract->getEndDate()->format('Y-m-d')}</li>
                    <li>总金额：{$contract->getTotalAmount()} 元</li>
                </ul>
                <p>请及时审核并确认合同信息。</p>
                <p>此邮件由系统自动发送，请勿回复。</p>
            ");

        try {
            $this->mailer->send($email);
            $this->logger->info('合同创建通知邮件已发送', ['contractNo' => $contractNo]);
        } catch (\Throwable $e) {
            $this->logger->error('合同创建通知邮件发送失败', [
                'contractNo' => $contractNo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送合同更新通知
     */
    public function sendContractUpdatedNotification(HotelContract $contract): void
    {
        $hotelName = $contract->getHotel()->getName();
        $contractNo = $contract->getContractNo();

        $email = (new Email())
            ->from('noreply@hotel-booking-system.com')
            ->to($this->adminEmail)
            ->subject("合同更新通知: $contractNo")
            ->html("
                <p>尊敬的管理员：</p>
                <p>系统合同信息已更新，详情如下：</p>
                <ul>
                    <li>合同编号：{$contractNo}</li>
                    <li>酒店名称：{$hotelName}</li>
                    <li>合同类型：{$contract->getContractType()->getLabel()}</li>
                    <li>合同状态：{$contract->getStatus()->getLabel()}</li>
                    <li>开始日期：{$contract->getStartDate()->format('Y-m-d')}</li>
                    <li>结束日期：{$contract->getEndDate()->format('Y-m-d')}</li>
                    <li>总金额：{$contract->getTotalAmount()} 元</li>
                </ul>
                <p>此邮件由系统自动发送，请勿回复。</p>
            ");

        try {
            $this->mailer->send($email);
            $this->logger->info('合同更新通知邮件已发送', ['contractNo' => $contractNo]);
        } catch (\Throwable $e) {
            $this->logger->error('合同更新通知邮件发送失败', [
                'contractNo' => $contractNo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送合同状态变更通知
     */
    private function sendContractStatusChangedNotification(HotelContract $contract, string $action, ?string $reason = null): void
    {
        $hotelName = $contract->getHotel()->getName();
        $contractNo = $contract->getContractNo();
        $statusText = $contract->getStatus()->getLabel();

        $reasonHtml = $reason ? "<p>变更原因：{$reason}</p>" : '';

        $email = (new Email())
            ->from('noreply@hotel-booking-system.com')
            ->to($this->adminEmail)
            ->subject("合同状态变更通知: $contractNo - $statusText")
            ->html("
                <p>尊敬的管理员：</p>
                <p>系统合同状态已变更，详情如下：</p>
                <ul>
                    <li>合同编号：{$contractNo}</li>
                    <li>酒店名称：{$hotelName}</li>
                    <li>状态变更：{$action}</li>
                    <li>当前状态：{$statusText}</li>
                </ul>
                {$reasonHtml}
                <p>此邮件由系统自动发送，请勿回复。</p>
            ");

        try {
            $this->mailer->send($email);
            $this->logger->info('合同状态变更通知邮件已发送', [
                'contractNo' => $contractNo,
                'status' => $statusText,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('合同状态变更通知邮件发送失败', [
                'contractNo' => $contractNo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

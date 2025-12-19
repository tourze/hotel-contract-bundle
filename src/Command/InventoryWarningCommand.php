<?php

namespace Tourze\HotelContractBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\HotelContractBundle\Service\InventorySummaryService;
use Tourze\HotelContractBundle\Service\InventoryWarningService;

#[AsCommand(
    name: self::NAME,
    description: '检查库存预警并发送通知邮件',
)]
final class InventoryWarningCommand extends Command
{
    protected const NAME = 'app:inventory:check-warnings';

    public function __construct(
        private readonly InventorySummaryService $summaryService,
        private readonly InventoryWarningService $warningService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sync', null, InputOption::VALUE_NONE, '在检查前同步库存统计数据')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, '指定检查特定日期的库存(格式: Y-m-d)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('库存预警检查');

        try {
            $date = $this->parseDate($input, $io);
        } catch (\Throwable $e) {
            return Command::FAILURE;
        }

        if ($this->shouldSync($input)) {
            $syncResult = $this->performSync($io, $date);
            if (!$syncResult) {
                return Command::FAILURE;
            }
        }

        return $this->performWarningCheck($io, $date);
    }

    private function parseDate(InputInterface $input, SymfonyStyle $io): ?\DateTimeImmutable
    {
        $dateStr = $input->getOption('date');
        if (null === $dateStr || !is_string($dateStr) || '' === trim($dateStr)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($dateStr);
            $io->note(sprintf('检查特定日期: %s', $date->format('Y-m-d')));

            return $date;
        } catch (\Throwable $e) {
            $io->error(sprintf('日期格式错误: %s', $dateStr));
            throw $e; // 重新抛出异常，让调用者处理
        }
    }

    private function shouldSync(InputInterface $input): bool
    {
        return (bool) $input->getOption('sync');
    }

    private function performSync(SymfonyStyle $io, ?\DateTimeImmutable $date): bool
    {
        $io->section('同步库存统计数据');
        $syncResult = $this->summaryService->syncInventorySummary($date);

        if (isset($syncResult['success']) && true === $syncResult['success']) {
            $message = $syncResult['message'] ?? '同步成功';
            $messageStr = \is_string($message) ? $message : '同步成功';
            $io->success($messageStr);

            return true;
        }

        $message = $syncResult['message'] ?? '同步失败';
        $messageStr = \is_string($message) ? $message : '同步失败';
        $io->error($messageStr);

        return false;
    }

    private function performWarningCheck(SymfonyStyle $io, ?\DateTimeImmutable $date): int
    {
        $io->section('检查库存预警');
        $result = $this->warningService->checkAndSendWarnings($date);

        if (!isset($result['success']) || true !== $result['success']) {
            $message = $result['message'] ?? '检查预警失败';
            $messageStr = \is_string($message) ? $message : '检查预警失败';
            $io->error($messageStr);

            return Command::FAILURE;
        }

        $message = $result['message'] ?? '检查预警完成';
        $messageStr = \is_string($message) ? $message : '检查预警完成';

        if ($result['sent_count'] > 0) {
            $io->success($messageStr);
        } else {
            $io->info($messageStr);
        }

        return Command::SUCCESS;
    }
}

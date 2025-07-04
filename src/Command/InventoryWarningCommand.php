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
class InventoryWarningCommand extends Command
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
            ->addOption('date', null, InputOption::VALUE_REQUIRED, '指定检查特定日期的库存(格式: Y-m-d)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('库存预警检查');

        // 解析日期参数
        $date = null;
        $dateStr = $input->getOption('date');
        if ($dateStr !== null && is_string($dateStr) && trim($dateStr) !== '') {
            try {
                $date = new \DateTimeImmutable($dateStr);
                $io->note(sprintf('检查特定日期: %s', $date->format('Y-m-d')));
            } catch (\Throwable $e) {
                $io->error(sprintf('日期格式错误: %s', $dateStr));
                return Command::FAILURE;
            }
        }

        // 是否先同步库存统计
        if ((bool) $input->getOption('sync')) {
            $io->section('同步库存统计数据');
            $syncResult = $this->summaryService->syncInventorySummary($date);

            if ($syncResult['success']) {
                $io->success((string) $syncResult['message']);
            } else {
                $io->error((string) $syncResult['message']);
                return Command::FAILURE;
            }
        }

        // 执行库存预警检查
        $io->section('检查库存预警');
        $result = $this->warningService->checkAndSendWarnings($date);

        if ($result['success']) {
            if ($result['sent_count'] > 0) {
                $io->success((string) $result['message']);
            } else {
                $io->info((string) $result['message']);
            }
            return Command::SUCCESS;
        } else {
            $io->error((string) $result['message']);
            return Command::FAILURE;
        }
    }
}

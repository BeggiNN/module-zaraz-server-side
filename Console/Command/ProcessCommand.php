<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Perspective\ZarazServerSide\Model\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * perspective:zaraz:server:process [--now] [--order=000000014 ...] [--dry-run] [--limit=200]
 */
class ProcessCommand extends Command
{
    private const OPTION_NOW = 'now';
    private const OPTION_ORDER = 'order';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_LIMIT = 'limit';

    /**
     * @param Processor $processor
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param State $appState
     */
    public function __construct(
        private readonly Processor $processor,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly State $appState,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('perspective:zaraz:server:process')
            ->setDescription('Send due Zaraz server-side events (what the cron job does)')
            ->addOption(self::OPTION_NOW, null, InputOption::VALUE_NONE, 'Do not wait for the scheduled time')
            ->addOption(
                self::OPTION_ORDER,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only events of these order increment IDs'
            )
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Print request bodies, send nothing')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Max events', '200');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        }
        $orderIds = [];
        $increments = (array)$input->getOption(self::OPTION_ORDER);
        if ($increments) {
            $orderIds = $this->orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', ['in' => $increments])
                ->getAllIds();
            if (!$orderIds) {
                $output->writeln('<error>No orders with these increment IDs.</error>');
                return Command::FAILURE;
            }
        }
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        $summary = $this->processor->process(
            max(1, (int)$input->getOption(self::OPTION_LIMIT)),
            (bool)$input->getOption(self::OPTION_NOW),
            array_map('intval', $orderIds),
            $dryRun
        );
        if ($dryRun) {
            $output->writeln((string)json_encode(
                $summary['requests'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));
        }
        $output->writeln(sprintf(
            '%sSent: %d, retry later: %d, failed: %d, skipped: %d',
            $dryRun ? '[dry run] ' : '',
            $summary['sent'],
            $summary['retry'],
            $summary['failed'],
            $summary['skipped']
        ));
        return Command::SUCCESS;
    }
}

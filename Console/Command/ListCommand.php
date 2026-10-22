<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Console\Command;

use Perspective\ZarazServerSide\Api\Data\ServerEventInterface as Row;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * perspective:zaraz:server:list [--status=pending] [--limit=20]
 */
class ListCommand extends Command
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('perspective:zaraz:server:list')
            ->setDescription('Latest Zaraz server-side events')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'pending|delivered|sent|failed|skipped')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Rows', '20');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collection = $this->collectionFactory->create()
            ->setOrder(Row::EVENT_ID, 'DESC')
            ->setPageSize(max(1, (int)$input->getOption('limit')));
        if ($input->getOption('status')) {
            $collection->addFieldToFilter(Row::STATUS, (string)$input->getOption('status'));
        }
        $table = new Table($output);
        $table->setHeaders(['ID', 'Event', 'Reference', 'Store', 'Status', 'Attempts', 'Scheduled (UTC)', 'Note']);
        /** @var Row $row */
        foreach ($collection as $row) {
            $table->addRow([
                $row->getEventId(),
                $row->getEventCode(),
                $row->getReference(),
                $row->getStoreId(),
                $row->getStatus(),
                $row->getAttempts(),
                $row->getScheduledAt(),
                mb_strimwidth((string)$row->getLastError(), 0, 70, '…'),
            ]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}

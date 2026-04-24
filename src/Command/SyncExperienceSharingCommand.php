<?php

namespace App\Command;

use App\Service\ExperienceSharingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sorties:sync-experience-sharing', description: 'Active le partage d experience pour les sorties terminees.')]
class SyncExperienceSharingCommand extends Command
{
    public function __construct(
        private readonly ExperienceSharingService $experienceSharingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->experienceSharingService->syncFinishedSorties();

        $io->success(sprintf('%d sortie(s) synchronisee(s) pour le partage d experience.', $count));

        return Command::SUCCESS;
    }
}

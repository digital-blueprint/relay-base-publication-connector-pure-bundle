<?php


declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Command;

use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListPublicationsCommand extends Command
{
    protected static $defaultName = 'dbp:publication:list';

    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        parent::__construct();
        $this->publicationService = $publicationService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List available publications with their identifiers')
            ->setHelp('This command shows all available publications and their identifiers for debugging.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Available Publications from Pure API');

        try {
            $publications = $this->publicationService->getPublications([], 20);

            if (empty($publications)) {
                $io->warning('No publications found');
                return Command::SUCCESS;
            }

            $io->section('Found Publications');

            $rows = [];
            foreach ($publications as $index => $publication) {
                $rows[] = [
                    $index + 1,
                    $publication->getIdentifier(),
                    $publication->getTitle(),
                ];
            }

            $io->table(['#', 'Identifier', 'Title'], $rows);

            $io->note('Use any of these identifiers with the debug-author command');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to list publications: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
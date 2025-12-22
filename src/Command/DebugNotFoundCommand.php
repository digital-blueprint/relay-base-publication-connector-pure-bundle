<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Command;

use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dbp:publication:debug-not-found')]
class DebugNotFoundCommand extends Command
{
    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        parent::__construct();
        $this->publicationService = $publicationService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Debug why a publication is not found')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Publication identifier to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $identifier = $input->getArgument('identifier');
        $io->title("Debug Not Found: {$identifier}");

        try {
            $debugInfo = $this->publicationService->debugPublicationNotFound($identifier);

            $io->section('Search Results');
            foreach ($debugInfo as $searchType => $result) {
                $io->writeln("<info>{$searchType}:</info>");
                $io->writeln("  Search term: {$result['search_term']}");
                $io->writeln("  Items found: {$result['items_found']}");
                $io->writeln('  Identifiers: '.implode(', ', $result['identifiers_found']));
                $io->newLine();
            }

            // Also try to get the publication directly
            $io->section('Direct Lookup Attempt');
            $publication = $this->publicationService->getPublicationById($identifier);
            if ($publication) {
                $io->success('Publication found directly!');
                $io->writeln("Title: {$publication->getTitle()}");
            } else {
                $io->error('Publication still not found with direct lookup');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Debug failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

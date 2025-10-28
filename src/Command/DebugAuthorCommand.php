<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Command;

use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugAuthorCommand extends Command
{
    protected static $defaultName = 'dbp:publication:debug-author';

    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        parent::__construct();
        $this->publicationService = $publicationService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Debug author data for a specific publication')
            ->setHelp('This command shows where author data is located in the Pure API response for a specific publication.')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Publication identifier to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $identifier = $input->getArgument('identifier');
        $io->title("Debug Author Data for: {$identifier}");

        try {
            $debugInfo = $this->publicationService->debugAuthorData($identifier);

            if (isset($debugInfo['error'])) {
                $io->error($debugInfo['error']);

                return Command::FAILURE;
            }

            $io->section('Publication Information');
            $io->writeln("Type: <info>{$debugInfo['publication_type']}</info>");

            $io->section('Author Field Analysis');
            foreach (['contributors', 'personsAssociations', 'personAssociations', 'authors', 'person'] as $field) {
                $fieldInfo = $debugInfo[$field];
                if ($fieldInfo['exists']) {
                    $io->writeln("<info>✓ {$field}</info>");
                    $io->writeln("  Type: {$fieldInfo['type']}");
                    $io->writeln("  Count: {$fieldInfo['count']}");
                    if (isset($fieldInfo['sample'])) {
                        $sample = is_array($fieldInfo['sample']) ?
                            json_encode($fieldInfo['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) :
                            $fieldInfo['sample'];
                        $io->writeln("  Sample:\n{$sample}");
                    }
                } else {
                    $io->writeln("<comment>✗ {$field}</comment> - Not found");
                }
                $io->newLine();
            }

            $io->section('All Fields (with array data)');
            foreach ($debugInfo['all_fields_sample'] as $field => $sample) {
                $io->writeln("<info>{$field}</info>:");
                $io->writeln(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $io->newLine();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Debug failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Command;

use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dbp:publication:simple-debug')]
class SimpleDebugCommand extends Command
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
            ->setDescription('Simple debug - show raw API response')
            ->setHelp('This command shows the raw API response for debugging.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Simple Debug - Raw API Response');

        try {
            // Get the connection and make a direct API call
            $connection = $this->publicationService->getConnection();
            $response = $connection->postJSON('research-outputs/search', [
                'size' => 1,
                'searchString' => '',
            ], [
                'headers' => [
                    'api-key' => $this->publicationService->getConfig()->getPureApiKey(),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $io->writeln("Status Code: <info>{$statusCode}</info>");

            $contents = $response->getBody()->getContents();
            $data = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('JSON decode error: '.json_last_error_msg());
                $io->writeln("Raw response: {$contents}");

                return Command::FAILURE;
            }

            $io->section('Complete API Response');
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if (isset($data['items']) && !empty($data['items'])) {
                $io->section('First Publication Structure');
                $firstItem = $data['items'][0];
                $io->writeln(json_encode($firstItem, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $io->section('Available Fields in First Publication');
                $io->listing(array_keys($firstItem));

                // Look for author-related fields
                $authorFields = [];
                foreach ($firstItem as $key => $value) {
                    if (stripos($key, 'person') !== false
                        || stripos($key, 'author') !== false
                        || stripos($key, 'contributor') !== false) {
                        $authorFields[$key] = $value;
                    }
                }

                if (!empty($authorFields)) {
                    $io->success('Found author-related fields: '.implode(', ', array_keys($authorFields)));
                    $io->section('Author-Related Fields Data');
                    foreach ($authorFields as $field => $data) {
                        $io->writeln("<info>{$field}:</info>");
                        $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $io->newLine();
                    }
                } else {
                    $io->warning('No author-related fields found!');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Simple debug failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

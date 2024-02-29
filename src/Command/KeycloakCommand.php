<?php

namespace Micro\TraeffikGenericBundle\Command;

use Micro\TraeffikGenericBundle\Service\KeycloakService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * @psalm-import-type YamlParameters from Micro\TraeffikGenericBundle\Service\KeycloakService
 */
#[AsCommand(
    name: 'micro:keycloak:manage',
    description: 'Import roles and groups in keycloak',
)]
class KeycloakCommand extends Command
{
    public function __construct(
        private readonly KeycloakService $keycloakService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->keycloakService->setIo($io);
        $io->title('Manage roles and groups in keycloak');
        $file = __DIR__ . '/../../../../../config/packages/keycloak.yaml';
        if (!file_exists($file)) {
            $io->error('This file ' . $file . " don't exists !!!");

            return Command::FAILURE;
        }

        /** @var array{parameters: YamlParameters} */
        $yaml = Yaml::parseFile($file);
        $this->keycloakService->manageGroupRole($yaml['parameters']);
        $this->keycloakService->manageDeleleRole($yaml['parameters']);

        $io->success('Import finish');

        return Command::SUCCESS;
    }
}

<?php

namespace Micro\TraeffikGenericBundle\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-type YamlParameters array{
 *  clients: array<string,Client>,
 *  groups: array<string,RoleGroups>
 * }
 * @psalm-type Client array{roles: array<string>}
 * @psalm-type RoleGroups array<string>
 * @psalm-type Options array<string,mixed>
 * @psalm-type RoleKeycloak array{name: string}
 * @psalm-type KeycloakResponseDefault array{clientMappings?: array<string, array{mappings: RoleKeycloak[]}>}
 * @psalm-type KeycloakResponseGroups array<array{id: string, name: string, clientId: string}>
 */
class KeycloakService
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly TokenProviderService $tokenProviderService,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param YamlParameters $file
     */
    public function manageGroupRole(array $file): void
    {
        foreach ($file['clients'] as $client => $infos) {
            $clientInfos = $this->getClientByName($client);
            if (!$clientInfos) {
                $this->createClient($client);
                $clientInfos = $this->getClientByName($client);
                $this->io->writeln('This client ' . $client . ' created');
            }
            $this->manageRoles($infos['roles'], $clientInfos[0]['id']);
            $this->manageGroups($file['groups'], $client, $clientInfos[0]['id']);
        }
    }

    /**
     * @param string[] $infosRoles
     */
    private function manageRoles(array $infosRoles, string $client): void
    {
        foreach ($infosRoles as $role) {
            if (!$this->getRolesClient($client, $role)) {
                $this->createRoleClient($client, $role);
                $this->io->writeln('This role ' . $role . ' created for this client ' . $client);
            }
        }
    }

    /**
     * @param array<string,RoleGroups> $groups
     */
    private function manageGroups(array $groups, string $clientName, string $clientId): void
    {
        foreach ($groups as $group => $roleGroups) {
            if (!$groupInfos = $this->getGroupByName($group)) {
                $this->createGroup($group);
                $groupInfos = $this->getGroupByName($group);
                $this->io->writeln('This group ' . $group . ' created');
            }
            if ($groupInfos) {
                $roleMapping = $this->getRolesGroup($groupInfos[0]['id']);
                foreach ($roleGroups as $roleGroup) {
                    $mapping = ($roleMapping && array_key_exists($clientName, $roleMapping['clientMappings'])) ? $roleMapping['clientMappings'][$clientName]['mappings'] : [];
                    $roleExist = $this->checkRoleExist($mapping, $roleGroup);
                    if (!$roleExist) {
                        $this->createRoleGroup($groupInfos[0]['id'], $this->getRolesClient($clientId, $roleGroup), $clientId);
                        $this->io->writeln('This role ' . $roleGroup . ' created for this client ' . $clientName . ' in this group ' . $group);
                    }
                }
            }
        }
    }

    /**
     * @param RoleKeycloak[] $roleKeycloaks
     */
    private function checkRoleExist(array $roleKeycloaks, string $roleGroup): bool
    {
        foreach ($roleKeycloaks as $roleKeycloak) {
            if ($roleKeycloak['name'] === $roleGroup) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return KeycloakResponseGroups
     */
    private function getGroupByName(string $group)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'groups';
        $options['query'] = [
            'search' => $group,
        ];

        /** @var KeycloakResponseGroups */
        $result = $this->callKeycloak('GET', $url, $options);

        return $result;
    }

    /**
     * @return KeycloakResponseDefault
     */
    private function createGroup(string $group)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'groups';
        $options['json'] = [
            'name' => $group,
        ];

        /** @var KeycloakResponseDefault */
        $result = $this->callKeycloak('POST', $url, $options);

        return $result;
    }

    /**
     * @return KeycloakResponseGroups
     */
    private function getClientByName(string $clientName)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'clients';
        $options['query'] = [
            'clientId' => $clientName,
        ];

        /** @var KeycloakResponseGroups */
        $result = $this->callKeycloak('GET', $url, $options);

        return $result;
    }

    /**
     * @return KeycloakResponseDefault
     */
    private function createClient(string $clientName)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'clients';
        $options['json'] = [
            'clientId' => $clientName,
        ];

        /** @var KeycloakResponseDefault */
        $result = $this->callKeycloak('POST', $url, $options);

        return $result;
    }

    /**
     * @psalm-return KeycloakResponseGroups
     */
    private function getRolesClient(string $client, ?string $role = null)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'clients/' . $client . '/roles';
        if ($role) {
            $options['query'] = [
                'search' => $role,
            ];
        }

        /** @psalm-var KeycloakResponseGroups */
        $result = $this->callKeycloak('GET', $url, $options);

        return $result;
    }

    /**
     * @return KeycloakResponseDefault
     */
    private function createRoleClient(string $client, string $role)
    {
        /** @psalm-var Options */
        $options = [];
        $url = 'clients/' . $client . '/roles';
        $options['json'] = [
            'name' => $role,
        ];

        /** @var KeycloakResponseDefault */
        $result = $this->callKeycloak('POST', $url, $options);

        return $result;
    }

    private function deleteRoleClient(string $client, string $role): void
    {
        $options = [];
        $url = 'clients/' . $client . '/roles/' . $role;

        $this->callKeycloak('DELETE', $url, $options);
    }

    /**
     * @param Options $options
     *
     * @return KeycloakResponseDefault
     */
    private function getRolesGroup(string $group, $options = [])
    {
        $url = 'groups/' . $group . '/role-mappings';

        /** @var KeycloakResponseDefault */
        $result = $this->callKeycloak('GET', $url, $options);

        return $result;
    }

    /**
     * @param KeycloakResponseGroups $role
     *
     * @return KeycloakResponseDefault
     */
    private function createRoleGroup(string $group, array $role, string $client)
    {
        $data = [];
        $url = 'groups/' . $group . '/role-mappings/clients/' . $client;
        $body = [
            'id' => $role[0]['id'],
            'name' => $role[0]['name'],
        ];
        $data['body'] = '[' . json_encode($body, JSON_THROW_ON_ERROR) . ']';

        /** @var KeycloakResponseDefault */
        $result = $this->callKeycloak('POST', $url, $data);

        return $result;
    }

    /**
     * @param Options $options
     */
    private function callKeycloak(string $method, string $url, array $options = []): mixed
    {
        $this->tokenProviderService->setClientKeycloak($_ENV['KEYCLOAK_MANAGE_CLIENT_ID'], $_ENV['KEYCLOAK_MANAGE_CLIENT_SECRET']);
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->tokenProviderService->getToken(),
            'Content-Type' => 'application/json',
        ];
        try {
            $result = $this->httpClient->request($method, $_ENV['KEYCLOAK_API_URL'] . $url, $options)->getContent();
        } catch (ClientExceptionInterface) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->tokenProviderService->refreshToken();

            $result = $this->httpClient->request($method, $_ENV['KEYCLOAK_API_URL'] . $url, $options)->getContent();
        }

        if ($result) {
            return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }

    public function setIo(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    /**
     * @param YamlParameters $file
     */
    public function manageDeleleRole(array $file): void
    {
        foreach ($file['clients'] as $client => $infos) {
            $clientInfos = $this->getClientByName($client);
            $this->deleteRoles($clientInfos[0], $infos['roles']);
        }
    }

    /**
     * @param array{id: string, clientId: string} $client
     * @param string[]                            $rolesYaml
     */
    private function deleteRoles(array $client, array $rolesYaml): void
    {
        $roles = $this->getRolesClient($client['id']);

        /** @var string[] */
        $keycloackRoles = array_map(fn ($role) => $role['name'], $roles);
        foreach ($keycloackRoles as $keycloackRole) {
            if (!in_array($keycloackRole, $rolesYaml)) {
                $this->deleteRoleClient($client['id'], $keycloackRole);
                $this->io->writeln('This role ' . $keycloackRole . ' deleted for this client ' . $client['clientId']);
            }
        }
    }
}

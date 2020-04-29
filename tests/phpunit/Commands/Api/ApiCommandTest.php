<?php

namespace Acquia\Ads\Tests\Api;

use Acquia\Ads\Command\Api\ApiCommandBase;
use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Command\Command;

/**
 * Class ApiCommandTest
 * @property ApiCommandBase $command
 * @package Acquia\Ads\Tests\Api
 */
class ApiCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new ApiCommandBase();
    }

    /**
     * Tests the 'api:*' commands.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandWithHttpGet(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);
        $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $cloud_client->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();

        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->command->setAcquiaCloudClient($cloud_client->reveal());
        $this->executeCommand();
        $this->prophet->checkPredictions();

        $output = $this->getDisplay();
        $this->assertNotNull($output);
        $this->assertJson($output);

        $contents = json_decode($output, true);
        $this->assertArrayHasKey(0, $contents);
        $this->assertArrayHasKey('uuid', $contents[0]);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandWithHttpPost(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);
        $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
        $mock_response_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
        foreach ($mock_request_args as $name => $value) {
            $cloud_client->addOption('form_params', [$name => $value])->shouldBeCalled();
        }
        $cloud_client->request('post', '/account/ssh-keys')->willReturn($mock_response_body)->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
        $this->command->setAcquiaCloudClient($cloud_client->reveal());
        $this->executeCommand($mock_request_args);
        $this->prophet->checkPredictions();
        $output = $this->getDisplay();
        $this->assertNotNull($output);
        $this->assertJson($output);
        $this->assertStringContainsString('Adding SSH key.', $output);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandDefinitionForGetEndpoint(): void
    {
        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $resource = $this->getResourceFromSpec('/account/ssh-keys', 'get');
        $this->assertEquals($resource['summary'], $this->command->getDescription());

        $expected_command_name = 'api:' . $resource['x-cli-name'];
        $this->assertEquals($expected_command_name, $this->command->getName());

        foreach ($resource['parameters'] as $parameter) {
            $param_name = strtolower(str_replace('#/components/parameters/', '', $parameter['$ref']));
            $this->assertTrue(
                $this->command->getDefinition()->hasOption($param_name) ||
                $this->command->getDefinition()->hasArgument($param_name),
                "Command $expected_command_name does not have expected argument or option $param_name"
                // @todo Validate usage example.
            );
        }
    }


    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandDefinitionForPostEndpoint(): void
    {
        $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
        $resource = $this->getResourceFromSpec('/account/ssh-keys', 'post');
        foreach ($resource['requestBody']['content']['application/json']['example'] as $key => $value) {
            $this->assertTrue(
                $this->command->getDefinition()->hasArgument($key) ||
                $this->command->getDefinition()->hasOption($key),
                "Command {$this->command->getName()} does not have expected argument or option $key"
            );
            // @todo Validate usage example.
        }
    }

    // @todo Assert parameters are actually passed to the client. E.g., --limit.

    /**
     * @param $name
     *
     * @return \Acquia\Ads\Command\Api\ApiCommandBase|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getApiCommandByName($name): ?ApiCommandBase
    {
        $api_command_helper = new ApiCommandHelper();
        $api_commands = $api_command_helper->getApiCommands();
        foreach ($api_commands as $api_command) {
            if ($api_command->getName() === $name) {
                return $api_command;
            }
        }

        return null;
    }
}
<?php

namespace Tests\E2E\Services\Projects;

use Tests\E2E\Client;
use Tests\E2E\Scopes\ProjectConsole;
use Tests\E2E\Scopes\Scope;
use Tests\E2E\Scopes\SideClient;
use Utopia\Database\Helpers\ID;

/**
 * Classe de testes TDD para correção do bug SMTP #9067
 * 
 * Esta classe implementa testes seguindo a metodologia TDD para corrigir
 * o bug onde credenciais SMTP inválidas eram aceitas pelo sistema.
 * 
 * Ciclos TDD implementados:
 * 1. testRejectInvalidSmtpCredentials - Rejeitar credenciais inválidas
 * 2. testAcceptSmtpWithoutCredentials - Aceitar servidores sem autenticação
 * 3. testAcceptValidSmtpCredentials - Aceitar credenciais válidas
 * 4. testValidateRequiredSmtpFields - Validar campos obrigatórios
 * 
 * @author Daniel Ferreira Nunes - 211061565
 * @group smtp
 * @group tdd
 */
class ProjectsSmtpTest extends Scope
{
    use ProjectsBase;
    use ProjectConsole;
    use SideClient;

    protected array $project = [];

    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test project for SMTP tests
        $team = $this->client->call(Client::METHOD_POST, '/teams', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'teamId' => ID::unique(),
            'name' => 'SMTP Test Team',
        ]);

        $this->project = $this->client->call(Client::METHOD_POST, '/projects', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'projectId' => ID::unique(),
            'name' => 'SMTP Test Project',
            'teamId' => $team['body']['$id'],
            'region' => 'default',
        ]);
    }

    public function tearDown(): void
    {
        // Clean up test project
        if (!empty($this->project['body']['$id'])) {
            $this->client->call(Client::METHOD_DELETE, '/projects/' . $this->project['body']['$id'], array_merge([
                'content-type' => 'application/json',
                'x-appwrite-project' => $this->getProject()['$id'],
            ], $this->getHeaders()));
        }
        
        parent::tearDown();
    }

    /**
     * Primeiro Ciclo TDD: Teste que verifica se credenciais SMTP inválidas são rejeitadas
     * 
     * @group smtp
     * @group tdd
     */
    public function testRejectInvalidSmtpCredentials(): void
    {
        $projectId = $this->project['body']['$id'];
        
        // Tentativa de configurar SMTP com credenciais inválidas
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'test@example.com',
            'senderName' => 'Test Sender',
            'host' => 'smtp.gmail.com', // Host válido
            'port' => 587,
            'username' => 'invalid_user@gmail.com', // Credenciais INVÁLIDAS
            'password' => 'wrong_password_123',
            'secure' => 'tls',
        ]);

        // O sistema DEVE rejeitar credenciais inválidas
        $this->assertEquals(400, $response['headers']['status-code']);
        $this->assertEquals('project_smtp_config_invalid', $response['body']['type']);
        $this->assertStringContainsString('Could not connect to SMTP server', $response['body']['message']);
    }

    /**
     * Segundo Ciclo TDD: Teste que verifica se SMTP funciona sem credenciais (servidor aberto)
     * 
     * @group smtp
     * @group tdd
     */
    public function testAcceptSmtpWithoutCredentials(): void
    {
        $projectId = $this->project['body']['$id'];
        
        // Configurar SMTP sem credenciais (servidor aberto)
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'noreply@localhost.dev',
            'senderName' => 'Local Mailer',
            'host' => 'maildev', // Servidor local sem autenticação
            'port' => 1025,
            'username' => '', // SEM credenciais
            'password' => '',
            'secure' => '',
        ]);

        // Deve aceitar configuração sem credenciais
        $this->assertEquals(200, $response['headers']['status-code']);
        $this->assertTrue($response['body']['smtpEnabled']);
        $this->assertEquals('noreply@localhost.dev', $response['body']['smtpSenderEmail']);
        $this->assertEquals('Local Mailer', $response['body']['smtpSenderName']);
        $this->assertEquals('maildev', $response['body']['smtpHost']);
        $this->assertEquals(1025, $response['body']['smtpPort']);
        $this->assertEquals('', $response['body']['smtpUsername']);
        $this->assertEquals('', $response['body']['smtpPassword']);
    }

    /**
     * Terceiro Ciclo TDD: Teste que verifica se credenciais válidas são aceitas
     * 
     * @group smtp
     * @group tdd
     */
    public function testAcceptValidSmtpCredentials(): void
    {
        $projectId = $this->project['body']['$id'];
        
        // Configurar SMTP com credenciais válidas
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'valid@testmail.com',
            'senderName' => 'Valid Test Sender',
            'replyTo' => 'reply@testmail.com',
            'host' => 'maildev', // Servidor que aceita credenciais de teste
            'port' => 1025,
            'username' => 'testuser',
            'password' => 'testpass123',
            'secure' => '',
        ]);

        // Deve aceitar credenciais válidas
        $this->assertEquals(200, $response['headers']['status-code']);
        $this->assertTrue($response['body']['smtpEnabled']);
        $this->assertEquals('valid@testmail.com', $response['body']['smtpSenderEmail']);
        $this->assertEquals('Valid Test Sender', $response['body']['smtpSenderName']);
        $this->assertEquals('reply@testmail.com', $response['body']['smtpReplyTo']);
        $this->assertEquals('maildev', $response['body']['smtpHost']);
        $this->assertEquals(1025, $response['body']['smtpPort']);
        $this->assertEquals('testuser', $response['body']['smtpUsername']);
        $this->assertEquals('testpass123', $response['body']['smtpPassword']);
        $this->assertEquals('', $response['body']['smtpSecure']);
    }

    /**
     * Quarto Ciclo TDD: Teste que verifica validação de campos obrigatórios
     * 
     * @group smtp
     * @group tdd
     */
    public function testValidateRequiredSmtpFields(): void
    {
        $projectId = $this->project['body']['$id'];
        
        // Teste 1: Sender name obrigatório
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'test@example.com',
            'senderName' => '', // CAMPO OBRIGATÓRIO VAZIO
            'host' => 'smtp.gmail.com',
            'port' => 587,
        ]);

        $this->assertEquals(400, $response['headers']['status-code']);
        $this->assertEquals('general_argument_invalid', $response['body']['type']);
        $this->assertStringContainsString('Sender name is required', $response['body']['message']);

        // Teste 2: Sender email obrigatório
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => '', // CAMPO OBRIGATÓRIO VAZIO
            'senderName' => 'Test Sender',
            'host' => 'smtp.gmail.com',
            'port' => 587,
        ]);

        $this->assertEquals(400, $response['headers']['status-code']);
        $this->assertEquals('general_argument_invalid', $response['body']['type']);
        $this->assertStringContainsString('Sender email is required', $response['body']['message']);

        // Teste 3: Host obrigatório
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'test@example.com',
            'senderName' => 'Test Sender',
            'host' => '', // CAMPO OBRIGATÓRIO VAZIO
            'port' => 587,
        ]);

        $this->assertEquals(400, $response['headers']['status-code']);
        $this->assertEquals('general_argument_invalid', $response['body']['type']);
        $this->assertStringContainsString('Host is required', $response['body']['message']);
    }

    /**
     * Teste de integração: Verifica o fluxo completo de configuração SMTP
     * 
     * @group smtp
     * @group tdd
     * @group integration
     */
    public function testCompleteSmtpConfigurationFlow(): void
    {
        $projectId = $this->project['body']['$id'];
        
        // 1. Configurar SMTP com sucesso
        $response = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => true,
            'senderEmail' => 'integration@test.com',
            'senderName' => 'Integration Test',
            'replyTo' => 'reply@test.com',
            'host' => 'maildev',
            'port' => 1025,
            'username' => 'testuser',
            'password' => 'testpass',
            'secure' => '',
        ]);

        $this->assertEquals(200, $response['headers']['status-code']);
        $this->assertTrue($response['body']['smtpEnabled']);

        // 2. Verificar se as configurações foram salvas corretamente
        $projectResponse = $this->client->call(Client::METHOD_GET, '/projects/' . $projectId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(200, $projectResponse['headers']['status-code']);
        $this->assertTrue($projectResponse['body']['smtpEnabled']);
        $this->assertEquals('integration@test.com', $projectResponse['body']['smtpSenderEmail']);
        $this->assertEquals('Integration Test', $projectResponse['body']['smtpSenderName']);

        // 3. Desabilitar SMTP
        $disableResponse = $this->client->call(Client::METHOD_PATCH, '/projects/' . $projectId . '/smtp', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'enabled' => false,
        ]);

        $this->assertEquals(200, $disableResponse['headers']['status-code']);
        $this->assertFalse($disableResponse['body']['smtpEnabled']);
    }
}

<?php

namespace App\Tests\Controller\Api;

use App\Entity\App\Company;
use App\Entity\App\User;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $tenantManager;
    private $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->tenantManager = $this->client->getContainer()->get(TenantManager::class);
        $this->passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);
        
        // Set the tenant for testing
        $this->tenantManager->setCurrentTenant('ts');
    }

    public function testRegister(): void
    {
        // Create a test company
        $company = new Company();
        $company->setName('Test Company');
        $company->setStatus(Status::ACTIVE);
        
        $this->entityManager->persist($company);
        $this->entityManager->flush();
        
        // Create an inactive user
        $user = new User();
        $user->setCurp('ABCD123456EFGHIJK');
        $user->setStatus(Status::INACTIVE);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Test registration
        $this->client->request(
            'POST',
            '/ts/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'company_id' => $company->getId(),
                'employee_number' => '12345',
                'email' => 'test@example.com',
                'phone_number' => '1234567890',
                'curp' => 'ABCD123456EFGHIJK',
                'password' => 'password123'
            ])
        );
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('user_id', $responseData);
        
        // Clean up
        $this->entityManager->remove($company);
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
    
    public function testChangePassword(): void
    {
        // Create a test company
        $company = new Company();
        $company->setName('Test Company');
        $company->setStatus(Status::ACTIVE);
        
        $this->entityManager->persist($company);
        $this->entityManager->flush();
        
        // Create an active user
        $user = new User();
        $user->setCurp('ABCD123456EFGHIJK');
        $user->setEmail('test@example.com');
        $user->setPhoneNumber('1234567890');
        $user->setEmployeeNumber('12345');
        $user->setCompany($company);
        $user->setStatus(Status::ACTIVE);
        
        // Set password
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'current_password');
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Test change password
        $this->client->request(
            'PATCH',
            '/ts/api/users/' . $user->getId() . '/password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'current_password' => 'current_password',
                'new_password' => 'new_password123'
            ])
        );
        
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        // Clean up
        $this->entityManager->remove($user);
        $this->entityManager->remove($company);
        $this->entityManager->flush();
    }
}
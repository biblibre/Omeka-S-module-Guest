<?php

namespace GuestUserTest\Controller;

use Zend\Http\Request as HttpRequest;
use Omeka\Test\AbstractHttpControllerTestCase;
use GuestUserTest\Service\MockMailerFactory;

abstract class GuestUserControllerTestCase extends AbstractHttpControllerTestCase
{
    protected $testSite;
    protected $testUser;

    public function setUp()
    {
        $this->loginAsAdmin();

        $this->setupMockMailer();

        $this->testSite = $this->createSite('test', 'Test');
        $this->testUser = $this->createTestUser();
    }

    public function tearDown()
    {
        $this->loginAsAdmin();
        $this->api()->delete('users', $this->testUser->id());
        $this->api()->delete('sites', $this->testSite->id());
    }

    protected function setupMockMailer()
    {
        $serviceLocator = $this->getServiceLocator();
        $config = $serviceLocator->get('Config');
        $config['service_manager']['factories']['Omeka\Mailer'] = 'GuestUserTest\Service\MockMailerFactory';
        $serviceLocator->setAllowOverride(true);
        $serviceLocator->setService('Config', $config);
        $serviceLocator->setFactory('Omeka\Mailer', new MockMailerFactory);
        $serviceLocator->setAllowOverride(false);
    }

    protected function createSite($slug, $title)
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => '1',
        ]);
        if ($response->isError()) {
            error_log('Failed creating site');
            error_log(var_export($response->getErrors(), true));
        }
        return $response->getContent();
    }

    protected function createTestUser()
    {
        $response = $this->api()->create('users', [
            'o:email' => 'test@test.fr',
            'o:name' => 'Tester',
            'o:role' => 'global_admin',
            'o:is_active' => '1',
        ]);
        if ($response->isError()) {
            error_log('Failed creating test user');
            error_log(var_export($response->getErrors(), true));
        }
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword('test');
        $this->getEntityManager()->persist($userEntity);
        $this->getEntityManager()->flush();

        return $user;
    }

    protected function postDispatch($url, $data) {
        return $this->dispatch($url, HttpRequest::METHOD_POST, $data);
    }

    protected function loginAsAdmin()
    {
        $this->login('admin@example.com', 'root');
    }

    protected function login($username, $password)
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($username);
        $adapter->setCredential($password);
        $auth->authenticate();
    }

    protected function logout()
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    protected function api()
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function resetApplication()
    {
        $this->application = null;
        $this->setupMockMailer();
    }
}

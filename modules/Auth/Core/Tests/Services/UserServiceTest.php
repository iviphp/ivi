<?php

namespace Modules\Auth\Core\Tests\Services;

use Ivi\Core\Utils\FlashMessage;
use PHPUnit\Framework\TestCase;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\ValueObjects\Role;
use Ivi\Http\JsonResponse;
use Ivi\Http\RedirectResponse;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\Models\User;
use Modules\Auth\Core\Services\AuthService;
use Modules\User\Core\Services\UserRegistrationService;
use Modules\User\Core\Services\UserSecurityService;
use Modules\User\Core\Validator\UserValidator;
use Modules\User\Core\ValueObjects\Email;

final class UserServiceTest extends TestCase
{
    private UserRegistrationService $registrationService;
    private AuthService $authService;

    /** @var UserRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repositoryMock;

    /** @var UserSecurityService&\PHPUnit\Framework\MockObject\MockObject */
    private $securityMock;

    /** @var AuthService&\PHPUnit\Framework\MockObject\MockObject */
    private $authMock;


    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(UserRepository::class);
        $this->securityMock = $this->createMock(UserSecurityService::class);
        $this->authMock = $this->createMock(AuthService::class);

        $this->registrationService = new UserRegistrationService(
            $this->repositoryMock,
            $this->authMock
        );

        $this->authService = new AuthService(
            $this->repositoryMock,
            $this->securityMock
        );
    }

    public function testRegisterSuccess(): void
    {
        $fullname = 'Gaspard Kirira';
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';

        // --- 1) Email disponible
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        // --- 2) Mock save() + id assignation
        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function ($user) {
                $reflectionUser = new \ReflectionClass($user);
                $propertyUserId = $reflectionUser->getProperty('id');
                $propertyUserId->setAccessible(true);
                $propertyUserId->setValue($user, 1);

                foreach ($user->getRoles() as $role) {
                    $reflectionRole = new \ReflectionClass($role);
                    $propertyRoleId = $reflectionRole->getProperty('id');
                    $propertyRoleId->setAccessible(true);
                    $propertyRoleId->setValue($role, 1);
                }

                return $user;
            });

        // --- 3) Validator mock
        $validatorMock = $this->getMockBuilder(UserValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
        $validatorMock->method('validate')->willReturn([]);

        // --- 4) override issueAuthForUser()
        $testService = new class($this->repositoryMock, $this->authMock) extends UserRegistrationService {
            public function issueAuthForUser(User $user): string
            {
                return 'fake-jwt-token';
            }
        };

        $testService->setValidator($validatorMock);
        $testService->setJsonResponseHandler(fn(?JsonResponse $resp) => $GLOBALS['testResponse'] = $resp);

        // Appel register (SANS phone)
        $GLOBALS['testResponse'] = null;
        $testService->register($fullname, $email, $password);

        // --- 5) Vérification
        $response = $GLOBALS['testResponse'];
        $this->assertNotNull($response);

        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(201, $responseObj->status());
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals('Account created successfully.', $data['message']);
    }

    public function testRegisterEmailAlreadyTaken(): void
    {
        $fullname = 'Gaspard Kirira';
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';

        // --- 1) Fake user déjà existant
        $role = new Role(1, 'user');
        $existingUser = UserFactory::createFromArray([
            'fullname' => $fullname,
            'email' => $email,
            'password' => $password,
            'roles' => [$role],
            'status' => 'active',
            'verifiedEmail' => true,
            'coverPhoto' => null,
            'bio' => null,
            'phone' => null, // Phone removed
        ]);

        $reflectionUser = new \ReflectionClass($existingUser);
        $propertyUserId = $reflectionUser->getProperty('id');
        $propertyUserId->setAccessible(true);
        $propertyUserId->setValue($existingUser, 1);

        $this->repositoryMock
            ->method('findByEmail')
            ->with($email)
            ->willReturn($existingUser);

        // --- 2) Validator mock
        $validatorMock = $this->getMockBuilder(UserValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
        $validatorMock->method('validate')->willReturn([]);

        // --- 3) override issueAuthForUser
        $testService = new class($this->repositoryMock, $this->authMock) extends UserRegistrationService {
            public function issueAuthForUser(User $user): string
            {
                return 'fake-jwt-token';
            }
        };

        $testService->setValidator($validatorMock);
        $testService->setJsonResponseHandler(fn(?JsonResponse $resp) => $GLOBALS['testResponse'] = $resp);

        // Appel register (SANS phone)
        $GLOBALS['testResponse'] = null;
        $testService->register($fullname, $email, $password);

        // --- 5) Vérification
        $response = $GLOBALS['testResponse'];
        $this->assertNotNull($response);

        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(409, $responseObj->status());
        $this->assertEquals('This email is already taken.', $data['error']);
    }


    public function testLoginWithCredentialsSuccess(): void
    {
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';

        // --- 1) Mock User
        $role = new Role(1, 'user');

        $userMock = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPassword', 'getId', 'getEmail', 'getUsername', 'getRoles'])
            ->getMock();
        $userMock->method('getPassword')->willReturn(UserHelper::hashPassword($password));
        $userMock->method('getId')->willReturn(1);
        $userMock->method('getEmail')->willReturn(new Email($email));
        $userMock->method('getUsername')->willReturn('gaspard');
        $userMock->method('getRoles')->willReturn([$role]);

        // --- 2) Mock Repository
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($userMock);

        // --- 3) Mock UserSecurityService
        $this->securityMock->expects($this->once())
            ->method('resetFailedAttempts')
            ->with($email);
        $this->securityMock->method('incrementFailedAttempts')->with($email);
        $this->securityMock->method('acquireLock')->willReturn(true);
        $this->securityMock->method('releaseLock')->willReturn(true);
        $this->securityMock->method('getFailedAttempts')->with($email)->willReturn([
            'failed_attempts' => 0,
            'last_failed_login' => null,
        ]);

        // --- 4) Mock AuthService pour surcharger issueAuthForUser
        $authServiceMock = $this->getMockBuilder(AuthService::class)
            ->setConstructorArgs([$this->repositoryMock, $this->securityMock])
            ->onlyMethods(['issueAuthForUser'])
            ->getMock();
        $authServiceMock->method('issueAuthForUser')->willReturn('fake-jwt-token');

        // --- 5) Capturer la réponse JSON
        $response = null;
        $authServiceMock->setJsonResponseHandler(function (?JsonResponse $resp) use (&$response) {
            $response = $resp;
        });

        // --- 6) Appel login sur AuthService
        $authServiceMock->loginWithCredentials($email, $password);

        // --- 7) Vérification
        $this->assertNotNull($response, 'Une réponse JSON doit être générée');
        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(200, $responseObj->status());
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals('fake-jwt-token', $data['token']);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($email, (string)$data['user']['email']);
    }

    public function testLoginWithGoogleOAuth_NewUser(): void
    {
        // 1️⃣ Préparer un googleUser simulé
        $googleUser = (object)[
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'picture' => 'avatar.jpg',
            'verifiedEmail' => true,
        ];

        // 2️⃣ Mock repository pour findByEmail et createWithRoles
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with(strtolower($googleUser->email))
            ->willReturn(null);

        $this->repositoryMock
            ->expects($this->once())
            ->method('createWithRoles')
            ->with(
                $this->callback(
                    fn($user) =>
                    $user instanceof User &&
                        (string)$user->getEmail() === strtolower($googleUser->email) &&
                        $user->getFullname() === $googleUser->name
                ),
                $this->isType('array')
            )
            ->willReturnCallback(fn($user, $roles) => $user);

        // 3️⃣ Mock UserSecurityService (sécurité)
        $this->securityMock->method('resetFailedAttempts');
        $this->securityMock->method('incrementFailedAttempts');
        $this->securityMock->method('acquireLock')->willReturn(true);
        $this->securityMock->method('releaseLock')->willReturn(true);

        // 4️⃣ Créer un vrai AuthService (pas mock)
        $authService = new AuthService($this->repositoryMock, $this->securityMock);

        // Surcharge issueAuthForUser pour éviter d’avoir besoin de JWT réel
        $authService = new class($this->repositoryMock, $this->securityMock) extends AuthService {
            public function issueAuthForUser(User $user): string
            {
                return 'fake-jwt-token';
            }
        };

        // 5️⃣ Capturer les flash messages et redirections
        $flashMessages = [];
        FlashMessage::setHandler(function ($type, $msg) use (&$flashMessages) {
            if (!isset($flashMessages[$type])) $flashMessages[$type] = [];
            $flashMessages[$type][] = $msg;
        });

        $redirectUrl = '';
        RedirectResponse::setHandler(function ($url) use (&$redirectUrl) {
            $redirectUrl = $url;
        });

        // 6️⃣ Appel de la méthode
        $authService->loginWithGoogleOAuth($googleUser);

        // 7️⃣ Assertions flash
        $this->assertArrayHasKey('success', $flashMessages, 'Un message de succès doit être ajouté');
        $this->assertNotEmpty($flashMessages['success'][0], 'Le message de succès ne doit pas être vide');
        $this->assertStringContainsString('Welcome', $flashMessages['success'][0]);

        // 8️⃣ Assertions redirection
        $this->assertNotEmpty($redirectUrl, 'Une redirection doit être déclenchée');
        $this->assertStringContainsString('/finalize-registration', $redirectUrl);
    }
}

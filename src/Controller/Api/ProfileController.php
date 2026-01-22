<?php
namespace App\Controller\Api;

use App\DTO\Profile\ProfileUpdateRequest;
use App\DTO\User\UpdateUserProfileRequest;
use App\Entity\App\Company;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\ProfileErrorCodes;
use App\Enum\Status;
use App\Service\ErrorResponseService;
use App\Service\FileUploader;
use App\Service\ImagePathService;
use App\Service\ImageUploadService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class ProfileController extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private ImageUploadService $imageUploadService;
    private UserPasswordHasherInterface $passwordHasher;
    private ImagePathService $imagePathService;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
        ImageUploadService $imageUploadService,
        UserPasswordHasherInterface $passwordHasher,
        ImagePathService $imagePathService
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
        $this->imageUploadService = $imageUploadService;
        $this->passwordHasher = $passwordHasher;
        $this->imagePathService = $imagePathService;
    }

    #[Route('/profile', name: 'api_profile_get', methods: ['GET'])]
    public function getProfile(string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhoneNumber(),
            'avatar' => $this->imagePathService->generateFullPath($user->getPhoto())
        ]);
    }

    #[Route('/profile', name: 'api_profile_update_current', methods: ['PUT'])]
    public function updateProfile(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        /** @var User $user */
        $user = $this->getUser();

        // Detectar si es FormData (multipart/form-data) o JSON
        $contentType = $request->headers->get('Content-Type', '');
        $isFormData = strpos($contentType, 'multipart/form-data') !== false;

        if ($isFormData) {
            // Manejar FormData con archivos (como fotos)
            error_log('📸 ProfileController: Procesando FormData con posible foto');

            // Crear el DTO manualmente para manejar archivos
            $profileUpdateRequest = new ProfileUpdateRequest();
            $profileUpdateRequest->email = $request->get('email');
            $profileUpdateRequest->phone_number = $request->get('phone_number');
            $profileUpdateRequest->name = $request->get('name');
            $profileUpdateRequest->last_name = $request->get('last_name');
            $profileUpdateRequest->photo = $request->files->get('photo');

            $hasChanged = false;
            $this->updateFieldIfChanged($user, 'setEmail', $profileUpdateRequest->email, $user->getEmail(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setName', $profileUpdateRequest->name, $user->getName(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setLastName', $profileUpdateRequest->last_name, $user->getLastName(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setPhoneNumber', $profileUpdateRequest->phone_number, $user->getPhoneNumber(), $hasChanged);

            // Manejar la foto solo si hay un archivo nuevo
            if ($profileUpdateRequest->photo) {
                error_log('📸 ProfileController: Procesando foto de perfil');
                $photoPath = $this->handleUserImage($user, $profileUpdateRequest->photo);
                if ($photoPath !== null) {
                    $user->setPhoto($photoPath);
                    $hasChanged = true;
                    error_log('📸 ProfileController: Foto guardada en: ' . $photoPath);
                }
            }

            if ($hasChanged) {
                $em->flush();
                error_log('✅ ProfileController: Perfil actualizado exitosamente');
            }

        } else {
            // Manejar JSON tradicional (sin archivos)
            error_log('📝 ProfileController: Procesando JSON sin archivos');
            $data = json_decode($request->getContent(), true);

            if (isset($data['firstName'])) {
                $user->setName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $user->setLastName($data['lastName']);
            }
            if (isset($data['phone'])) {
                $user->setPhoneNumber($data['phone']);
            }
            if (isset($data['avatar'])) {
                $user->setPhoto($data['avatar']);
            }
            if (isset($data['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }

            $this->tenantManager->getEntityManager()->flush();
        }

        return $this->json([
            'message' => 'Perfil actualizado con éxito.',
            'code' => 200,
            'user' => [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'last_name' => $user->getLastName(),
                'photo' => $this->imagePathService->generateFullPath($user->getPhoto()),
                'phone_number' => $user->getPhoneNumber(),
                'company_name' => $user->getCompany() ? $user->getCompany()->getName() : null,
                'company_id' => $user->getCompany() ? $user->getCompany()->getId() : null,
            ]
        ]);
    }

    #[Route('/profile/change-password', name: 'api_profile_change_password', methods: ['POST'])]
    public function changePassword(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'Contraseña actual y nueva son requeridas'], 400);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['error' => 'Contraseña actual incorrecta'], 400);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
        $user->setPassword($hashedPassword);

        $this->tenantManager->getEntityManager()->flush();

        return $this->json(['message' => 'Contraseña actualizada correctamente']);
    }

    #[Route('/users/{userId}/profile', name: 'api_profile_get_by_id', methods: ['GET'])]
    public function getProfileById(string $dominio, int $userId): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->andWhere('u.status = :status')
            ->setParameter('id', $userId)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$user) {
            return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_OBTAIN_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'userId' => $userId,
                ]
            );
        }

        $response = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'last_name' => $user->getLastName(),
            'photo' => $this->imagePathService->generateFullPath($user->getPhoto()),
            'curp' => $user->getCurp(),
            'birthday' => $user->getBirthday() ? $user->getBirthday()->format('Y-m-d') : null,
            'phone_number' => $user->getPhoneNumber(),
            'gender' => $user->getGender(),
            'education' => $user->getEducation(),
            'employee_number' => $user->getEmployeeNumber(),
            'company_name' => $user->getCompany() ? $user->getCompany()->getName() : null,
            'company_id' => $user->getCompany() ? $user->getCompany()->getId() : null,
        ];

        return new JsonResponse([
            'message' => 'Perfil obtenido con éxito.',
            'profile' => $response,
            'code' => 200,
        ], 200); 
    }

    #[Route('/users/{userId}/profile', name: 'api_profile_update_by_id', methods: ['POST'])]
    public function updateProfileById(string $dominio, int $userId, Request $request, RequestValidatorService $requestValidator): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            // Detectar si es FormData (multipart/form-data) o JSON
            $contentType = $request->headers->get('Content-Type', '');
            $isJson = strpos($contentType, 'application/json') !== false;

            // Crear el DTO manualmente para manejar archivos
            $profileUpdateRequest = new ProfileUpdateRequest();

            if ($isJson) {
                // Procesar JSON
                $data = json_decode($request->getContent(), true) ?? [];
                $profileUpdateRequest->email = $data['email'] ?? null;
                $profileUpdateRequest->phone_number = $data['phone_number'] ?? null;
                $profileUpdateRequest->employee_number = $data['employee_number'] ?? null;
                $profileUpdateRequest->curp = $data['curp'] ?? null;
                $profileUpdateRequest->company_id = isset($data['company_id']) ? (int)$data['company_id'] : null;
                $profileUpdateRequest->name = $data['name'] ?? null;
                $profileUpdateRequest->last_name = $data['last_name'] ?? null;
                $profileUpdateRequest->gender = $data['gender'] ?? null;
                $profileUpdateRequest->education = $data['education'] ?? null;
                $profileUpdateRequest->birthday = $data['birthday'] ?? null;
                $profileUpdateRequest->photo = null; // JSON no soporta archivos
            } else {
                // Procesar FormData
                $profileUpdateRequest->email = $request->get('email');
                $profileUpdateRequest->phone_number = $request->get('phone_number');
                $profileUpdateRequest->employee_number = $request->get('employee_number');
                $profileUpdateRequest->curp = $request->get('curp');
                $profileUpdateRequest->company_id = $request->get('company_id') ? (int)$request->get('company_id') : null;
                $profileUpdateRequest->name = $request->get('name');
                $profileUpdateRequest->last_name = $request->get('last_name');
                $profileUpdateRequest->gender = $request->get('gender');
                $profileUpdateRequest->education = $request->get('education');
                $profileUpdateRequest->birthday = $request->get('birthday');
                $profileUpdateRequest->photo = $request->files->get('photo');
            }
            // Debug logs removidos para producción

            // Obtener el usuario y asegurar que esté managed usando consulta directa
            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $userId)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$user) {
                return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_UPDATE_USER_NOT_FOUND_OR_INACTIVE,
                    [
                        'userId' => $userId,
                    ]
                );
            }

            // Verificar que la entidad esté managed
            if (!$em->contains($user)) {
                // Intentar obtenerlo de nuevo con find() para asegurar que esté managed
                $user = $em->find(User::class, $userId);
                if (!$user) {
                    return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_UPDATE_USER_NOT_FOUND_OR_INACTIVE,
                        [
                            'userId' => $userId,
                        ]
                    );
                }
            }

            // Verificar si el email ya está en uso por otro usuario usando consulta directa
            $emailExists = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.email = :email')
                ->andWhere('u.status = :status')
                ->andWhere('u.verified = :verified')
                ->setParameter('email', $profileUpdateRequest->email)
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('verified', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            // Si existe otro usuario con el mismo email (que no sea el usuario actual)
            if ($emailExists && $emailExists->getId() !== $user->getId()) {
                return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_UPDATE_EMAIL_EXISTS,
                    [
                        'email' => $profileUpdateRequest->email,
                    ]
                );
            }

            if ($profileUpdateRequest->company_id && $profileUpdateRequest->company_id !== ($user->getCompany() ? $user->getCompany()->getId() : null)) {
                $company = $em->createQueryBuilder()
                    ->select('c')
                    ->from('App\Entity\App\Company', 'c')
                    ->where('c.id = :id')
                    ->andWhere('c.status = :status')
                    ->setParameter('id', $profileUpdateRequest->company_id)
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getOneOrNullResult();
                if (!$company) {
                    return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_UPDATE_COMPANY_NOT_FOUND_OR_INACTIVE,
                        [
                            'companyId' => $profileUpdateRequest->company_id,
                        ]
                    );
                }

                $user->setCompany($company);
            }

            $hasChanged = false;
            $this->updateFieldIfChanged($user, 'setEmail', $profileUpdateRequest->email, $user->getEmail(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setName', $profileUpdateRequest->name, $user->getName(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setLastName', $profileUpdateRequest->last_name, $user->getLastName(), $hasChanged);

            // Manejar la foto solo si hay un archivo nuevo
            if ($profileUpdateRequest->photo) {
                $photoPath = $this->handleUserImage($user, $profileUpdateRequest->photo);
                if ($photoPath !== null) {
                    $user->setPhoto($photoPath);
                    $hasChanged = true;
                }
            }
            $this->updateFieldIfChanged($user, 'setGender', $profileUpdateRequest->gender, $user->getGender(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setEducation', $profileUpdateRequest->education, $user->getEducation(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setPhoneNumber', $profileUpdateRequest->phone_number, $user->getPhoneNumber(), $hasChanged);
            $this->updateFieldIfChanged($user, 'setEmployeeNumber', $profileUpdateRequest->employee_number, $user->getEmployeeNumber(), $hasChanged);
            $this->updateFieldIfChanged(
                $user,
                'setBirthday',
                $profileUpdateRequest->birthday ? new \DateTime($profileUpdateRequest->birthday) : null,
                $user->getBirthday(),
                $hasChanged
            );

            if (!$hasChanged) {
                return new JsonResponse([
                    'message' => 'No se realizaron cambios.',
                        'code' => 200,
                ], 200);
            }

            if ($hasChanged) {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $user->setLastSeen(new \DateTimeImmutable());

                $em->flush();
            }

            return new JsonResponse([
                'message' => 'Usuario actualizado con éxito.',
                'code' => 200,
                'user' => [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'last_name' => $user->getLastName(),
                    'photo' => $this->imagePathService->generateFullPath($user->getPhoto()),
                    'curp' => $user->getCurp(),
                    'birthday' => $user->getBirthday() ? $user->getBirthday()->format('Y-m-d') : null,
                    'phone_number' => $user->getPhoneNumber(),
                    'gender' => $user->getGender(),
                    'education' => $user->getEducation(),
                    'employee_number' => $user->getEmployeeNumber(),
                    'company_name' => $user->getCompany() ? $user->getCompany()->getName() : null,
                    'company_id' => $user->getCompany() ? $user->getCompany()->getId() : null,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('Profile update error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new JsonResponse([
                'code' => 500,
                'error' => 'Error interno del sistema',
                'message' => 'Se ha producido un error temporal. Por favor intente nuevamente.',
                'debug' => $e->getMessage() // Temporal para debugging
            ], 500);
        }
    }

    private function updateFieldIfChanged($entity, string $setter, $newValue, $currentValue, &$hasChanged): void
    {
        if ($newValue !== null && $newValue !== $currentValue) {
            $entity->$setter($newValue);
            $hasChanged = true;
        }
    }

    private function handleUserImage(User $user, $uploadImage): ?string
    {
        if (!$uploadImage) {
            return null;
        }

        try {
            $oldPhotoPath = $user->getPhoto();
            if ($oldPhotoPath) {
                $absolutePath = $this->getParameter('uploads_directory') . '/' . $oldPhotoPath;
                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $imagePath = $this->imageUploadService->uploadImage($uploadImage, 'profile');
            if ($imagePath === null) {
                throw new \Exception('Error al subir la foto del perfil: ' . $uploadImage->getClientOriginalName());
            }

            return $imagePath;
        } catch (\Exception $e) {
            throw new \Exception('Error al subir la foto del perfil: ' . $e->getMessage());
        }
    }
    #[Route('/users/{userId}/link-google', name: 'api_profile_link_google', methods: ['POST'])]
    public function linkGoogle(string $dominio, int $userId, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();
       
        /** @var User $user */
        $user = $this->getUser(); 
        
        if (!$user || $user->getId() !== $userId) {
            $user = $em->getRepository(User::class)->find($userId);
        }

        if (!$user) {
            return $this->json(['error' => true, 'msg' => 'Usuario no encontrado'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        $token = $payload['id_token'] ?? null;

        if (!$token) {
             return $this->json(['error' => true, 'msg' => 'Token no proporcionado'], 400);
        }

        try {
            // Decodificar token (igual que en AuthController, idealmente refactorizar a servicio)
            $parts = explode('.', $token);
            if(count($parts) < 2) throw new \Exception("Token invalido");
            $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
            $googleData = json_decode($payloadJson, true);
            
            if(!$googleData || !isset($googleData['sub'])) {
                 throw new \Exception("Datos de Google inválidos");
            }
            
            $googleId = $googleData['sub'];
            
            $existing = $em->getRepository(User::class)->findOneBy(['google_auth' => $googleId]);
            if ($existing && $existing->getId() !== $user->getId()) {
                 return $this->json(['error' => true, 'msg' => 'Esta cuenta de Google ya está vinculada a otro usuario'], 400);
            }

            $user->setGoogleAuth($googleId);
            $em->flush();

            return $this->json(['success' => true, 'msg' => 'Cuenta vinculada correctamente']);

        } catch (\Exception $e) {
            return $this->json(['error' => true, 'msg' => 'Error al vincular: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/users/{userId}/unlink-google', name: 'api_profile_unlink_google', methods: ['POST'])]
    public function unlinkGoogle(string $dominio, int $userId): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();
        
        /** @var User $user */
        $user = $this->getUser();
        if (!$user || $user->getId() !== $userId) {
             $user = $em->getRepository(User::class)->find($userId);
        }

        if (!$user) {
            return $this->json(['error' => true, 'msg' => 'Usuario no encontrado'], 404);
        }

        // Usamos reflection para setear null si el setter es estricto, o pasamos vacio.
        // Dado que User::setGoogleAuth pide string, pasamos cadena vacía.
        $user->setGoogleAuth(''); 
        $em->flush();

        return $this->json(['success' => true, 'msg' => 'Cuenta desvinculada correctamente']);
    }

    #[Route('/users/{userId}/google-linked', name: 'api_profile_google_linked', methods: ['GET'])]
    public function checkGoogleLinked(string $dominio, int $userId): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->andWhere('u.status = :status')
            ->setParameter('id', $userId)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
             return $this->errorResponseService->createErrorResponse(ProfileErrorCodes::PROFILE_OBTAIN_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'userId' => $userId,
                ]
            );
        }

        return $this->json([
            'linked' => !empty($user->getGoogleAuth())
        ]);
    }
}
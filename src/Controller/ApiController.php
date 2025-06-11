<?php


namespace App\Controller;

use App\Entity\SfcUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['email', 'password', 'username', 'firstname', 'lastname', 'device_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Missing field: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $existingUser = $em->getRepository(SfcUser::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
        }

        $user = new SfcUser();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);
        $user->setFirstname($data['firstname']);
        $user->setLastname($data['lastname']);
        $user->setPassword($data['password']);
        $user->setDeviceId($data['device_id']);
        $user->setBalance(0);
        $user->setBonus(false);

        // Bonus logic
        if (!empty($data['bonus']) && $data['bonus'] === true) {
            $bonusUsed = $em->getRepository(SfcUser::class)->findOneBy([
                'deviceId' => $data['device_id'],
                'bonus' => true
            ]);

            if ($bonusUsed) {
                return new JsonResponse(['error' => 'Bonus already claimed on this device'], Response::HTTP_FORBIDDEN);
            }

            $user->setBalance(100);
            $user->setBonus(true);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'User registered successfully',
            'userId' => $user->getId(),
            'bonus' => $user->isBonus(),
            'balance' => $user->getBalance()
        ], Response::HTTP_CREATED);
    }



    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(SfcUser::class)->findOneBy(['email' => $data['email']]);
        if ($data['password'] != $user->getPassword()) {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Generate token
        $token = hash('sha256', uniqid($user->getEmail(), true));

        // Save token to user
        $user->setToken($token);
        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Login successful',
            'token' => $token,
            'userId' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'balance' => $user->getBalance()
        ]);
    }


    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // ველოდებით ტოკენს headers-ში: Authorization: Bearer <token>
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Missing or invalid Authorization header'], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7);

        // ვცდილობთ იპოვოთ იუზერი ტოკენის მიხედვით
        $user = $em->getRepository(SfcUser::class)->findOneBy(['token' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        // წარმატებით ავტორიზებული იუზერის მონაცემების დაბრუნება
        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'balance' => $user->getBalance(),
            'bonus' => $user->isBonus()
        ]);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $token = $request->headers->get('Authorization');

        if (!$token) {
            return new JsonResponse(['error' => 'Missing token'], Response::HTTP_UNAUTHORIZED);
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        $user = $em->getRepository(SfcUser::class)->findOneBy(['token' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setToken(null);
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'Logged out successfully']);
    }


}

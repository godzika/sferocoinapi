<?php

namespace App\Controller;

use App\Entity\SfcUser; // Assuming SfcUser will store the user's wallet address
use App\Service\Web3GatewayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TransferController extends AbstractController
{
    private $entityManager;
    private $web3GatewayService;

    private $urlGenerator;

    public function __construct(EntityManagerInterface $entityManager, Web3GatewayService $web3GatewayService, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->web3GatewayService = $web3GatewayService;
        $this->urlGenerator = $urlGenerator; // NEW: Assign URL Generator
    }

    /**
     * Handles SFC transfer requests.
     * Expects JSON body with 'user_id', 'amount_sfc', 'to_address'.
     */
    #[Route('/api/transfer/sfc', name: 'api_transfer_sfc', methods: ['POST'])]
    public function transferSfc(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Validate incoming request data
        $requiredFields = ['user_id', 'amount_sfc', 'to_address'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['message' => "Missing required parameter: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $userId = (int) $data['user_id'];
        $amountSfc = (float) $data['amount_sfc'];
        $toAddress = $data['to_address'];

        if ($amountSfc <= 0) {
            return new JsonResponse(['message' => 'Transfer amount must be positive.'], Response::HTTP_BAD_REQUEST);
        }

        // --- Security Check (Optional but Recommended): Verify User's Token ---
        // This assumes the API client includes the token in the Authorization header.
        // If your frontend sends it in the body or another way, adjust accordingly.
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['message' => 'Authorization token required.'], Response::HTTP_UNAUTHORIZED);
        }
        $token = substr($authHeader, 7);
        $authenticatedUser = $this->entityManager->getRepository(SfcUser::class)->findOneBy(['token' => $token]);

        if (!$authenticatedUser || $authenticatedUser->getId() !== $userId) {
            // Either token is invalid, or the token doesn't match the user_id in the request.
            // This prevents one user from initiating a transfer for another user.
            return new JsonResponse(['message' => 'Unauthorized or user_id mismatch.'], Response::HTTP_UNAUTHORIZED);
        }
        // --- End Security Check ---


        // 2. Retrieve user's wallet address from your SfcUser entity
        // We assume SfcUser entity has a 'walletAddress' property, or you derive it.
        // In your `SfcUser.php` I see `balance` but no dedicated `walletAddress`.
        // You MUST ensure each SfcUser has a unique blockchain wallet address associated.
        // For this example, I'll assume SfcUser has a `getWalletAddress()` method or similar.
        // If not, you'll need to add a `walletAddress` field to `SfcUser` entity.
        $userSfcUser = $this->entityManager->getRepository(SfcUser::class)->find($userId);

        if (!$userSfcUser) {
            return new JsonResponse(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        // IMPORTANT: Replace this with the actual wallet address associated with SfcUser.
        // This is a placeholder. You need to add a wallet address field to SfcUser entity
        // or have a mechanism to derive it.
        $fromAddress = $userSfcUser->getWalletAddress(); // <--- REPLACE THIS LINE!
        if (empty($fromAddress)) {
            return new JsonResponse(['message' => 'User wallet address not configured.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }


        // 3. Communicate with Web3 Gateway Service
        // 3a. Validate recipient address
        if (!$this->web3GatewayService->isValidAddress($toAddress)) {
            return new JsonResponse(['message' => 'Invalid recipient address.'], Response::HTTP_BAD_REQUEST);
        }

        // 3b. Check sender's SFC balance
        $currentBalance = $this->web3GatewayService->getSfcBalance($fromAddress);

        if ($currentBalance === null) {
            return new JsonResponse(['message' => 'Could not retrieve current balance. Please try again later.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($currentBalance < $amountSfc) {
            return new JsonResponse(['message' => 'Insufficient SFC balance.'], Response::HTTP_BAD_REQUEST);
        }

        // --- NEW: Generate the full callback URL for the Web3Worker ---
        // This generates an absolute URL (e.g., http://localhost:8000/api/webhook/transaction-status)
        $callbackUrl = $this->urlGenerator->generate(
            'api_webhook_transaction_status',
            [], // No route parameters needed for this simple webhook
            UrlGeneratorInterface::ABSOLUTE_URL // Generates a full URL including domain
        );
        // --- END NEW ---

        // 4. Submit transaction to Web3 Gateway for queuing
        $submissionSuccess = $this->web3GatewayService->submitTransferTransaction($userId, $amountSfc, $fromAddress, $toAddress, $callbackUrl);

        if ($submissionSuccess) {
            // Optionally, you might want to log this in your Symfony DB
            // or update the SfcUser's pending balance (if you track it)
            return new JsonResponse(['message' => 'Transfer transaction submitted successfully. It is awaiting processing by the Web3 Worker.', 'status' => 'WAITING'], Response::HTTP_ACCEPTED);
        } else {
            return new JsonResponse(['message' => 'Failed to submit transfer transaction. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
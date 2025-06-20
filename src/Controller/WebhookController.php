<?php

namespace App\Controller;

use App\Entity\SfcUser;
use App\Entity\UserTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/api/webhook/transaction-status', name: 'api_webhook_transaction_status', methods: ['POST'])]
    public function transactionStatusCallback(Request $request): JsonResponse
    {
        // --- NEW DEBUGGING LOGGING ---
        $rawContent = $request->getContent();
        $this->logger->info('Webhook callback: Raw request content received.', ['raw_content' => $rawContent]);

        $data = json_decode($rawContent, true);
        $jsonLastError = json_last_error();
        $jsonLastErrorMessage = json_last_error_msg();

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error(sprintf('Webhook callback: JSON decoding error. Error code: %d, Message: %s', $jsonLastError, $jsonLastErrorMessage), ['raw_content' => $rawContent]);
            return new JsonResponse(['message' => 'Invalid JSON payload. Error: ' . $jsonLastErrorMessage], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Webhook callback: Decoded data received.', ['decoded_data' => $data]);
        // --- END NEW DEBUGGING LOGGING ---


        $requiredFields = [
            'internal_id', 'status', 'tx_hash', 'operation_type', 'asset',
            'amount', 'from_address', 'to_address', 'user_id'
        ];

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                $missingFields[] = $field;
            }
            if (array_key_exists($field, $data) && $data[$field] === null && !in_array($field, ['tx_link', 'confirmations', 'block_number', 'error_message'])) {
                $this->logger->warning(sprintf('Webhook callback: Required field "%s" is present but its value is null.', $field), ['data' => $data]);
                return new JsonResponse(['message' => sprintf('Required field "%s" cannot be null.', $field)], Response::HTTP_BAD_REQUEST);
            }
        }

        if (!empty($missingFields)) {
            $this->logger->warning('Webhook callback: Missing required fields (keys not present): ' . implode(', ', $missingFields), ['data' => $data]);
            return new JsonResponse(['message' => 'Missing required fields: ' . implode(', ', $missingFields)], Response::HTTP_BAD_REQUEST);
        }

        $internalId = $data['internal_id'];
        $status = $data['status'];
        $txHash = $data['tx_hash'];
        $operationType = $data['operation_type'];
        $asset = $data['asset'];
        $amount = (string) $data['amount'];
        $fromAddress = $data['from_address'];
        $toAddress = $data['to_address'];
        $userId = (int) $data['user_id'];
        $txLink = $data['tx_link'] ?? null;
        $confirmations = $data['confirmations'] ?? null;
        $blockNumber = $data['block_number'] ?? null;
        $errorMessage = $data['error_message'] ?? null;

        $this->logger->info(sprintf(
            'Received webhook callback for transaction ID: %s, Status: %s, Hash: %s, User ID: %d',
            $internalId, $status, $txHash, $userId
        ), ['data' => $data]);

        $userTransaction = $this->entityManager->getRepository(UserTransaction::class)->findOneBy(['internalId' => $internalId]);
        $sfcUser = $this->entityManager->getRepository(SfcUser::class)->find($userId);

        if (!$sfcUser) {
            $this->logger->error(sprintf('Webhook callback: SfcUser with ID %d not found for transaction ID: %s. Cannot link transaction.', $userId, $internalId));
            return new JsonResponse(['message' => 'Associated user not found, transaction not fully processed.'], Response::HTTP_NOT_FOUND);
        }

        if (!$userTransaction) {
            $userTransaction = new UserTransaction();
            $userTransaction->setInternalId($internalId);
            $userTransaction->setOperationType($operationType);
            $userTransaction->setAsset($asset);
            $userTransaction->setAmount($amount);
            $userTransaction->setFromAddress($fromAddress);
            $userTransaction->setToAddress($toAddress);
            $userTransaction->setCreatedAt(new \DateTimeImmutable());
            $userTransaction->setSfcUser($sfcUser);
        }

        $userTransaction->setTxHash($txHash);
        $userTransaction->setStatus($status);
        $userTransaction->setTxLink($txLink);
        $userTransaction->setConfirmations($confirmations);
        $userTransaction->setBlockNumber($blockNumber);
        $userTransaction->setErrorMessage($errorMessage);
        $userTransaction->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($userTransaction);
        $this->entityManager->flush();

        $this->logger->info(sprintf('Updated local UserTransaction record for internal ID: %s (User ID: %d) to status: %s', $internalId, $userId, $status));

        if ($status === 'SUCCESSFUL') {
            $this->logger->info(sprintf('Transaction %s (%s %s from %s to %s) successfully confirmed.',
                $internalId, $amount, $asset, $fromAddress, $toAddress
            ));
        } elseif ($status === 'FAILED') {
            $this->logger->warning(sprintf('Transaction %s (%s %s from %s to %s) failed: %s',
                $internalId, $amount, $asset, $fromAddress, $toAddress, $errorMessage
            ));
        }

        return new JsonResponse(['message' => 'Callback received and processed successfully'], Response::HTTP_OK);
    }
}
<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response; // Import Response for status codes

class Web3GatewayService
{
    private HttpClientInterface $httpClient;
    private string $web3GatewayBaseUrl;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, string $web3GatewayBaseUrl, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->web3GatewayBaseUrl = $web3GatewayBaseUrl;
        $this->logger = $logger;
    }

    /**
     * Retrieves the SFC balance for a given wallet address from the Web3 Gateway API.
     *
     * @param string $walletAddress The wallet address to check.
     * @return float|null The SFC balance or null if an error occurred.
     */
    public function getSfcBalance(string $walletAddress): ?float
    {
        try {
            $response = $this->httpClient->request('GET', $this->web3GatewayBaseUrl . '/web3/balance', [
                'query' => ['address' => $walletAddress]
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false); // Get raw content to handle potential non-JSON errors

            if ($statusCode === Response::HTTP_OK && isset($content['balance_sfc'])) {
                return (float) $content['balance_sfc'];
            }

            $this->logger->error('Failed to get balance from Web3 Gateway API.', [
                'status_code' => $statusCode,
                'response' => $content,
                'address' => $walletAddress
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error connecting to Web3 Gateway API for balance.', [
                'exception' => $e->getMessage(),
                'address' => $walletAddress
            ]);
        }

        return null;
    }

    /**
     * Validates a given blockchain address using the Web3 Gateway API.
     *
     * @param string $address The address to validate.
     * @return bool True if the address is valid, false otherwise.
     */
    public function isValidAddress(string $address): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->web3GatewayBaseUrl . '/web3/validate-address', [
                'query' => ['address' => $address]
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            if ($statusCode === Response::HTTP_OK && isset($content['is_valid'])) {
                return (bool) $content['is_valid'];
            }

            $this->logger->warning('Address validation failed or returned unexpected response from Web3 Gateway API.', [
                'status_code' => $statusCode,
                'response' => $content,
                'address' => $address
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error connecting to Web3 Gateway API for address validation.', [
                'exception' => $e->getMessage(),
                'address' => $address
            ]);
        }

        return false;
    }

    /**
     * Submits a new transfer transaction to the Web3 Gateway API for queuing in the worker's database.
     *
     * @param int $userId The internal user's ID from your SfcUser entity.
     * @param float $amount The amount of SFC to transfer.
     * @param string $fromAddress The sender's wallet address (user's wallet).
     * @param string $toAddress The recipient's wallet address.
     * @return bool True if the transaction was submitted successfully (accepted for queuing), false otherwise.
     */
    public function submitTransferTransaction(int $userId, float $amount, string $fromAddress, string $toAddress, string $callbackUrl): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->web3GatewayBaseUrl . '/web3/submit-transaction', [
                'json' => [
                    'user_id' => $userId,
                    'amount_sfc' => $amount,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'callback_url' => $callbackUrl,
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            // The gateway should return HTTP_ACCEPTED (202) for successful queuing
            if ($statusCode === Response::HTTP_ACCEPTED && isset($content['status']) && $content['status'] === 'WAITING') {
                $this->logger->info(sprintf('Transaction %s for user %d submitted to Web3 Gateway.', $content['internal_id'] ?? 'N/A', $userId));
                return true;
            }

            $this->logger->error('Failed to submit transaction to Web3 Gateway API.', [
                'status_code' => $statusCode,
                'response' => $content,
                'payload' => [
                    'user_id' => $userId,
                    'amount_sfc' => $amount,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'callback_url' => $callbackUrl // NEW: Log callbackUrl
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error connecting to Web3 Gateway API for transaction submission.', [
                'exception' => $e->getMessage(),
                'payload' => [
                    'user_id' => $userId,
                    'amount_sfc' => $amount,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'callback_url' => $callbackUrl
                ]
            ]);
        }

        return false;
    }

    /**
     * Calls the Web3 Gateway API to generate a new Ethereum wallet and returns its public address.
     * The private key is stored securely on the Web3 Gateway side.
     *
     * @return string|null The generated public wallet address or null if an error occurred.
     */
    public function generateNewWallet(): ?string
    {
        try {
            $response = $this->httpClient->request('POST', $this->web3GatewayBaseUrl . '/web3/generate-wallet');

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            if ($statusCode === Response::HTTP_CREATED && isset($content['walletAddress'])) {
                $this->logger->info(sprintf('New wallet generated by Web3 Gateway: %s', $content['walletAddress']));
                return $content['walletAddress'];
            }

            $this->logger->error('Failed to generate new wallet from Web3 Gateway API.', [
                'status_code' => $statusCode,
                'response' => $content
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error connecting to Web3 Gateway API for wallet generation.', [
                'exception' => $e->getMessage()
            ]);
        }

        return null;
    }


}
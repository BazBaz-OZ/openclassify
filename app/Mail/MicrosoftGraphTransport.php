<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new \RuntimeException('Microsoft Graph transport requires an Email message.');
        }

        $tokenResponse = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        );

        $tokenResponse->throw();

        $payload = [
            'message' => [
                'subject' => $email->getSubject() ?? '',
                'body' => [
                    'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients' => $this->addresses($email->getTo()),
                'ccRecipients' => $this->addresses($email->getCc()),
                'bccRecipients' => $this->addresses($email->getBcc()),
            ],
            'saveToSentItems' => true,
        ];

        if ($email->getReplyTo() !== []) {
            $payload['message']['replyTo'] = $this->addresses($email->getReplyTo());
        }

        $response = Http::withToken($tokenResponse->json('access_token'))
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->sender).'/sendMail',
                $payload,
            );

        $response->throw();
    }

    /**
     * @param array<int, Address> $addresses
     */
    private function addresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): array => [
                'emailAddress' => [
                    'address' => $address->getAddress(),
                    'name' => $address->getName(),
                ],
            ],
            $addresses,
        );
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}

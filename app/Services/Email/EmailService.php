<?php

namespace App\Services\Email;

use App\Models\EmailNotification;
use App\Services\AuditLog\AuditLogServiceInterface;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EmailService implements EmailServiceInterface
{
    private mixed $endpoint;

    public function __construct(public AuditLogServiceInterface $auditLogService)
    {
        $this->endpoint = config('aurish.email_svc_url');
    }

    public function headers(): array
    {
        return [
            'Authorization'                    => request()->header('Authorization'),
            'Content-Type'                     => 'application/json',
            'Accept'                           => 'application/json',
            'Access-Control-Allow-Credentials' => true,
        ];
    }

    /**
     * @throws \Exception
     */
    public function send(string $template, ?array $users = null, ?array $attributes = null): PromiseInterface|Response
    {
        $query = EmailNotification::with('recipients')->where('template_name', $template)->first();

        if (!$query) {
            throw new Exception("Invalid. Template not found.");
        }

        $url = $attributes ? $this->endpoint . '/api/v1/send-mail-attributes' : $this->endpoint . '/api/v1/send-mail';

        $recipients = array_merge($users ?? [], $query->recipients->pluck('email')->toArray());

        $this->auditLogService->insertLog($this, 'email', $recipients);

        return Http::withHeaders($this->headers())->post($url, [
            'recipients' => $recipients,
            'template'   => $template,
            'attributes' => $attributes,
        ]);
    }
}

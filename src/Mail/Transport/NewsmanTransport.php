<?php

namespace Newsman\LaravelNewsmanSmtp\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Http;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\Log;


class NewsmanTransport extends AbstractTransport
{
    protected string $endpoint;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleepMs;

    public function __construct(
        protected string $apiKey,
        protected string $accountId,
        ?string $endpoint = null,
        int $timeout = 15,
        int $retryTimes = 0,
        int $retrySleepMs = 0,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        // Inițializează partea Symfony Mailer
        parent::__construct($dispatcher, $logger);

        $this->endpoint     = $endpoint ?: 'https://cluster.newsmanapp.com/api/1.0/message.send_raw';
        $this->timeout      = $timeout;
        $this->retryTimes   = $retryTimes;
        $this->retrySleepMs = $retrySleepMs;
    }

    

protected function doSend(SentMessage $message): void
{
    $cfgLog   = config('newsman.log');
    $channel  = $cfgLog['channel'] ?? 'stack';
    $logOnReq = $cfgLog['enabled'] && ($cfgLog['requests'] ?? true);
    $logOnRes = $cfgLog['enabled'] && ($cfgLog['responses'] ?? true);
    $redact   = $cfgLog['redact'] ?? ['api_key','mime_message'];

    $email = $message->getOriginalMessage();
    $raw   = $email->toString();
    $subject = method_exists($email, 'getSubject') ? $email->getSubject() : null;

    $extract = fn($list) => array_map(fn(Address $a) => $a->getAddress(), $list ?? []);
    $recipients = array_values(array_unique(array_merge(
        $extract($email->getTo()),
        $extract($email->getCc()),
        $extract($email->getBcc())
    )));
    if (!$recipients) {
        throw new \RuntimeException('NewsmanTransport: niciun destinatar.');
    }

    $payload = [
        'key'          => $this->apiKey,
        'account_id'   => $this->accountId,
        'mime_message' => $raw,
        'recipients'   => $recipients,
    ];

    // redact helper (nu logăm chei/mesaj raw)
    $sanitize = function (array $data) use ($redact) {
        $copy = $data;
        foreach ($redact as $k) {
            if (isset($copy[$k])) {
                $copy[$k] = '***REDACTED***';
            }
        }
        return $copy;
    };

    if ($logOnReq) {
        Log::channel($channel)->log(
            $cfgLog['level'] ?? 'info',
            'NewsMAN API request',
            [
                'endpoint'   => $this->endpoint,
                'subject'    => $subject,
                'recipients' => $recipients,
                'payload'    => $sanitize($payload),
            ]
        );
    }

    $req = Http::asJson()->timeout($this->timeout);

    // debug HTTP opțional (trimite trafic Guzzle către STDERR -> laravel.log)
    if (config('newsman.http.debug')) {
        $req = $req->withOptions(['debug' => fopen('php://stderr', 'w')]);
    }

    if ($this->retryTimes > 0) {
        $req = $req->retry($this->retryTimes, $this->retrySleepMs);
    }

    $resp = $req->post($this->endpoint, $payload);

    if ($logOnRes) {
        $meta = [
            'status'   => $resp->status(),
            'ok'       => $resp->successful(),
            'headers'  => $resp->headers(),
        ];

        // încerci să citești JSON; dacă nu e, pui body ca text
        $json = null;
        try { $json = $resp->json(); } catch (\Throwable $e) { /* ignore */ }

        Log::channel($channel)->log(
            $cfgLog['level'] ?? 'info',
            'NewsMAN API response',
            $meta + ['json' => $json, 'body' => $json ? null : $resp->body()]
        );
    }

    if (! $resp->successful()) {
        throw new \RuntimeException(
            'NewsmanTransport: '.$resp->status().' - '.$resp->body()
        );
    }
}


    public function __toString(): string
    {
        return 'newsman';
    }
}

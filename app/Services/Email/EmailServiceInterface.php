<?php


namespace App\Services\Email;


use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;

interface EmailServiceInterface
{
    public function send(string $template, ?array $users = null, ?array $attributes = null): PromiseInterface|Response;
}

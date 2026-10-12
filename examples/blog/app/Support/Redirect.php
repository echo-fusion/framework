<?php

declare(strict_types=1);

namespace App\Support;

use EchoFusion\Contracts\SessionManager\SessionInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * "Post, then redirect": after a form was handled, send the browser to a page and show a message there.
 */
final class Redirect
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function to(string $path, ?string $success = null): ResponseInterface
    {
        if ($success !== null) {
            $this->session->flash('success', [$success]);
        }

        return new Response(303, ['Location' => $path]);
    }

    /**
     * Back to the form with the problems found.
     *
     * @param list<string> $errors
     * @param array<string, string> $old what the user typed, so the form can be filled in again
     */
    public function withErrors(string $path, array $errors, array $old = []): ResponseInterface
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', $old);

        return new Response(303, ['Location' => $path]);
    }
}

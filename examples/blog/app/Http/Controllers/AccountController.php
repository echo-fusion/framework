<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\Framework\Http\Request;

/**
 * Where a signed-in user creates the token for the JSON API.
 */
final class AccountController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly View $view,
        private readonly Redirect $redirect,
    ) {
    }

    public function show(Request $request): string
    {
        return $this->view->render($request, 'account', ['hasToken' => $this->users->hasToken((int) $request->user()?->getAuthIdentifier()), 'token' => null]);
    }

    /**
     * The token is shown once, on the page that answers this POST; only its hash is stored.
     */
    public function createToken(Request $request): string
    {
        $token = bin2hex(random_bytes(24));
        $this->users->storeToken((int) $request->user()?->getAuthIdentifier(), $token);

        return $this->view->render($request, 'account', ['hasToken' => true, 'token' => $token]);
    }
}

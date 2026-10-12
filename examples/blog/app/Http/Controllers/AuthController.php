<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\AuthManager\AuthManager;
use EchoFusion\AuthManager\Users\DatabaseUserProvider;
use EchoFusion\Contracts\AuthManager\AuthManagerInterface;
use EchoFusion\Framework\Http\Request;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use const FILTER_VALIDATE_EMAIL;
use function strlen;

/**
 * Register, sign in, sign out. The session guard keeps the user id in the session.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthManagerInterface $auth,
        private readonly UserRepository $users,
        private readonly View $view,
        private readonly Redirect $redirect,
    ) {
    }

    public function showRegister(Request $request): string
    {
        return $this->view->render($request, 'auth/register');
    }

    public function register(Request $request): ResponseInterface
    {
        $name = trim($request->string('name'));
        $email = strtolower(trim($request->string('email')));
        $password = $request->string('password');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Enter your name (at most 100 characters).';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        } elseif ($this->users->emailTaken($email)) {
            $errors[] = 'This email address is already registered.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'The password needs at least 8 characters.';
        }
        if ($errors !== []) {
            return $this->redirect->withErrors('/register', $errors, ['name' => $name, 'email' => $email]);
        }

        // create() hashes the password (bcrypt) before it is stored
        $user = $this->provider()->create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->auth->statefulGuard('web')->login($user);

        return $this->redirect->to('/', 'Welcome, ' . $name . '! Your account is ready.');
    }

    public function showLogin(Request $request): string
    {
        return $this->view->render($request, 'auth/login');
    }

    public function login(Request $request): ResponseInterface
    {
        $email = strtolower(trim($request->string('email')));

        if (!$this->auth->statefulGuard('web')->attempt(['email' => $email, 'password' => $request->string('password')])) {
            return $this->redirect->withErrors('/login', ['These credentials do not match our records.'], ['email' => $email]);
        }

        return $this->redirect->to('/', 'You are signed in.');
    }

    public function logout(): ResponseInterface
    {
        $this->auth->statefulGuard('web')->logout();

        return $this->redirect->to('/login', 'You are signed out.');
    }

    private function provider(): DatabaseUserProvider
    {
        $provider = $this->auth instanceof AuthManager ? $this->auth->provider('users') : null;

        return $provider instanceof DatabaseUserProvider ? $provider : throw new LogicException('The "users" provider must be a DatabaseUserProvider.');
    }
}

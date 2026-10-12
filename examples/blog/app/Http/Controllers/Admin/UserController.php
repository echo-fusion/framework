<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Repositories\UserRepository;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;
use function in_array;
use function sprintf;

/**
 * Who has an account, and who is an admin. Routes: /admin/users.
 */
final class UserController
{
    public function __construct(private readonly UserRepository $users, private readonly View $view, private readonly Redirect $redirect)
    {
    }

    public function index(Request $request): string
    {
        return $this->view->render($request, 'admin/users', ['users' => $this->users->all()]);
    }

    public function role(Request $request, int $id): ResponseInterface
    {
        $user = $this->users->find($id) ?? throw new HttpException(404, 'There is no such user.');
        $role = $request->string('role');
        if (!in_array($role, [UserRepository::ADMIN, UserRepository::USER], true)) {
            throw new HttpException(422, 'The role must be admin or user.');
        }
        if ($user['role'] === UserRepository::ADMIN && $role !== UserRepository::ADMIN && $this->users->admins() <= 1) {
            return $this->redirect->withErrors('/admin/users', ['There must be at least one administrator.']);
        }

        $this->users->setRole($id, $role);

        return $this->redirect->to('/admin/users', sprintf('%s is now %s.', $user['name'], $role === UserRepository::ADMIN ? 'an administrator' : 'a regular user'));
    }

    public function destroy(Request $request, int $id): ResponseInterface
    {
        $user = $this->users->find($id) ?? throw new HttpException(404, 'There is no such user.');
        if ((string) $id === (string) $request->user()?->getAuthIdentifier()) {
            return $this->redirect->withErrors('/admin/users', ['You cannot delete your own account here.']);
        }

        $this->users->delete($id);   // their posts go with them

        return $this->redirect->to('/admin/users', sprintf('%s and their posts were deleted.', $user['name']));
    }
}

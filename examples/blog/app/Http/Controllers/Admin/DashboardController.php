<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Repositories\PostRepository;
use App\Repositories\UserRepository;
use App\Support\View;
use EchoFusion\Framework\Http\Request;

final class DashboardController
{
    public function __construct(private readonly PostRepository $posts, private readonly UserRepository $users, private readonly View $view)
    {
    }

    public function index(Request $request): string
    {
        return $this->view->render($request, 'admin/dashboard', [
            'stats' => $this->posts->stats() + ['users' => $this->users->count(), 'admins' => $this->users->admins()],
            'latest' => $this->posts->latest(5, 0, false),
        ]);
    }
}

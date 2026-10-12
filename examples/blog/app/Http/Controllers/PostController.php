<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\PostRepository;
use App\Support\Access;
use App\Support\PostValidator;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\Contracts\AuthManager\AuthManagerInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Pages for reading and writing posts. Every action may ask for the `Request`: that is how it reads
 * the query string (`$request->query()`), the form (`$request->post()`) and the signed-in user.
 */
final class PostController
{
    private const PER_PAGE = 5;

    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostValidator $validator,
        private readonly Access $access,
        private readonly AuthManagerInterface $auth,
        private readonly View $view,
        private readonly Redirect $redirect,
    ) {
    }

    public function index(Request $request): string
    {
        $page = max(1, $request->int('page', 1));
        $search = trim($request->string('q'));
        $total = $this->posts->count(true, null, $search);

        return $this->view->render($request, 'posts/index', [
            'heading' => $search === '' ? 'Latest posts' : 'Search: ' . $search,
            'search' => $search,
            'posts' => $this->posts->latest(self::PER_PAGE, ($page - 1) * self::PER_PAGE, true, null, $search),
            'previous' => $page > 1 ? $page - 1 : null,
            'next' => $page * self::PER_PAGE < $total ? $page + 1 : null,
            'base' => '/',
        ]);
    }

    /**
     * The signed-in author's own posts, drafts included.
     */
    public function mine(Request $request): string
    {
        $page = max(1, $request->int('page', 1));
        $userId = (int) $request->user()?->getAuthIdentifier();
        $total = $this->posts->count(false, $userId);

        return $this->view->render($request, 'posts/index', [
            'heading' => 'My posts',
            'search' => '',
            'posts' => $this->posts->latest(self::PER_PAGE, ($page - 1) * self::PER_PAGE, false, $userId),
            'previous' => $page > 1 ? $page - 1 : null,
            'next' => $page * self::PER_PAGE < $total ? $page + 1 : null,
            'base' => '/my-posts',
        ]);
    }

    public function show(Request $request, int $id): string
    {
        $post = $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');

        // a draft is visible to its author and to the admins only; to everyone else it does not exist
        if ($post['status'] !== PostRepository::PUBLISHED && !$this->access->canManage($this->auth->guard('web')->user(), $post)) {
            throw new HttpException(404, 'There is no such post.');
        }

        return $this->view->render($request, 'posts/show', ['post' => $post, 'canManage' => $this->access->canManage($this->auth->guard('web')->user(), $post)]);
    }

    public function create(Request $request): string
    {
        return $this->view->render($request, 'posts/form', ['post' => ['title' => '', 'body' => '', 'status' => PostRepository::PUBLISHED], 'action' => '/posts', 'heading' => 'New post']);
    }

    public function store(Request $request): ResponseInterface
    {
        $errors = $this->validator->errors($request);
        if ($errors !== []) {
            return $this->redirect->withErrors('/posts/create', $errors, $this->validator->old($request));
        }

        $id = $this->posts->create((int) $request->user()?->getAuthIdentifier(), trim($request->string('title')), trim($request->string('body')), $this->validator->status($request));

        return $this->redirect->to('/posts/' . $id, 'Your post was saved.');
    }

    public function edit(Request $request, int $id): string
    {
        $post = $this->managed($request, $id);

        return $this->view->render($request, 'posts/form', ['post' => $post, 'action' => '/posts/' . $id . '/edit', 'heading' => 'Edit post']);
    }

    public function update(Request $request, int $id): ResponseInterface
    {
        $this->managed($request, $id);

        $errors = $this->validator->errors($request);
        if ($errors !== []) {
            return $this->redirect->withErrors('/posts/' . $id . '/edit', $errors, $this->validator->old($request));
        }

        $this->posts->update($id, trim($request->string('title')), trim($request->string('body')), $this->validator->status($request));

        return $this->redirect->to('/posts/' . $id, 'The post was saved.');
    }

    public function destroy(Request $request, int $id): ResponseInterface
    {
        $this->managed($request, $id);
        $this->posts->delete($id);

        return $this->redirect->to('/my-posts', 'The post was deleted.');
    }

    /**
     * @return array<string, mixed> the post, when it exists and the signed-in user may change it
     */
    private function managed(Request $request, int $id): array
    {
        $post = $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');
        if (!$this->access->canManage($request->user(), $post)) {
            throw new HttpException(403, 'You can only change your own posts.');
        }

        return $post;
    }
}

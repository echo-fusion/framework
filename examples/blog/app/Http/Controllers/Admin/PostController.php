<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Repositories\PostRepository;
use App\Support\PostValidator;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Create, read, update and delete every post, drafts included. Routes: /admin/posts.
 */
final class PostController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostValidator $validator,
        private readonly View $view,
        private readonly Redirect $redirect,
    ) {
    }

    public function index(Request $request): string
    {
        $page = max(1, $request->int('page', 1));
        $search = trim($request->string('q'));
        $total = $this->posts->count(false, null, $search);

        return $this->view->render($request, 'admin/posts/index', [
            'posts' => $this->posts->latest(self::PER_PAGE, ($page - 1) * self::PER_PAGE, false, null, $search),
            'search' => $search,
            'total' => $total,
            'previous' => $page > 1 ? $page - 1 : null,
            'next' => $page * self::PER_PAGE < $total ? $page + 1 : null,
        ]);
    }

    public function create(Request $request): string
    {
        return $this->view->render($request, 'admin/posts/form', ['post' => ['title' => '', 'body' => '', 'status' => PostRepository::PUBLISHED], 'action' => '/admin/posts', 'heading' => 'New post']);
    }

    public function store(Request $request): ResponseInterface
    {
        $errors = $this->validator->errors($request);
        if ($errors !== []) {
            return $this->redirect->withErrors('/admin/posts/create', $errors, $this->validator->old($request));
        }

        $this->posts->create((int) $request->user()?->getAuthIdentifier(), trim($request->string('title')), trim($request->string('body')), $this->validator->status($request));

        return $this->redirect->to('/admin/posts', 'The post was created.');
    }

    public function edit(Request $request, int $id): string
    {
        return $this->view->render($request, 'admin/posts/form', ['post' => $this->find($id), 'action' => '/admin/posts/' . $id . '/edit', 'heading' => 'Edit post']);
    }

    public function update(Request $request, int $id): ResponseInterface
    {
        $this->find($id);

        $errors = $this->validator->errors($request);
        if ($errors !== []) {
            return $this->redirect->withErrors('/admin/posts/' . $id . '/edit', $errors, $this->validator->old($request));
        }

        $this->posts->update($id, trim($request->string('title')), trim($request->string('body')), $this->validator->status($request));

        return $this->redirect->to('/admin/posts', 'The post was saved.');
    }

    /**
     * Publish a draft or hide a published post with one click.
     */
    public function toggle(Request $request, int $id): ResponseInterface
    {
        $post = $this->find($id);
        $status = $post['status'] === PostRepository::PUBLISHED ? PostRepository::DRAFT : PostRepository::PUBLISHED;
        $this->posts->update($id, (string) $post['title'], (string) $post['body'], $status);

        return $this->redirect->to('/admin/posts', $status === PostRepository::PUBLISHED ? 'The post is published.' : 'The post is now a draft.');
    }

    public function destroy(Request $request, int $id): ResponseInterface
    {
        $this->find($id);
        $this->posts->delete($id);

        return $this->redirect->to('/admin/posts', 'The post was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        return $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');
    }
}

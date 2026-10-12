<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\PostRepository;
use App\Support\Redirect;
use App\Support\View;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;
use function is_string;

/**
 * Pages for reading and writing posts. Every action may ask for the `Request`: that is how it reads
 * the query string (`$request->query()`), the form (`$request->post()`) and the signed-in user.
 */
final class PostController
{
    private const PER_PAGE = 5;

    public function __construct(
        private readonly PostRepository $posts,
        private readonly View $view,
        private readonly Redirect $redirect,
    ) {
    }

    public function index(Request $request): string
    {
        $page = max(1, $request->int('page', 1));
        $total = $this->posts->count();

        return $this->view->render($request, 'posts/index', [
            'posts' => $this->posts->latest(self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page' => $page,
            'previous' => $page > 1 ? $page - 1 : null,
            'next' => $page * self::PER_PAGE < $total ? $page + 1 : null,
        ]);
    }

    public function show(Request $request, int $id): string
    {
        $post = $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');

        return $this->view->render($request, 'posts/show', ['post' => $post]);
    }

    public function create(Request $request): string
    {
        return $this->view->render($request, 'posts/form', ['post' => ['title' => '', 'body' => ''], 'action' => '/posts', 'heading' => 'New post']);
    }

    public function store(Request $request): ResponseInterface
    {
        $input = $request->only('title', 'body');
        $errors = $this->validate($input);
        if ($errors !== []) {
            return $this->redirect->withErrors('/posts/create', $errors, $this->strings($input));
        }

        $id = $this->posts->create((int) $request->user()?->getAuthIdentifier(), trim($request->string('title')), trim($request->string('body')));

        return $this->redirect->to('/posts/' . $id, 'Your post was published.');
    }

    public function edit(Request $request, int $id): string
    {
        $post = $this->owned($request, $id);

        return $this->view->render($request, 'posts/form', ['post' => $post, 'action' => '/posts/' . $id . '/edit', 'heading' => 'Edit post']);
    }

    public function update(Request $request, int $id): ResponseInterface
    {
        $this->owned($request, $id);

        $input = $request->only('title', 'body');
        $errors = $this->validate($input);
        if ($errors !== []) {
            return $this->redirect->withErrors('/posts/' . $id . '/edit', $errors, $this->strings($input));
        }

        $this->posts->update($id, trim($request->string('title')), trim($request->string('body')));

        return $this->redirect->to('/posts/' . $id, 'The post was saved.');
    }

    public function destroy(Request $request, int $id): ResponseInterface
    {
        $this->owned($request, $id);
        $this->posts->delete($id);

        return $this->redirect->to('/', 'The post was deleted.');
    }

    /**
     * @return array<string, mixed> the post, when it exists and belongs to the signed-in user
     */
    private function owned(Request $request, int $id): array
    {
        $post = $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');
        if ((string) $post['user_id'] !== (string) $request->user()?->getAuthIdentifier()) {
            throw new HttpException(403, 'You can only change your own posts.');
        }

        return $post;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $body = is_string($input['body'] ?? null) ? trim($input['body']) : '';

        if ($title === '' || mb_strlen($title) > 200) {
            $errors[] = 'The title is required and may have at most 200 characters.';
        }
        if ($body === '') {
            $errors[] = 'The text is required.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, string>
     */
    private function strings(array $input): array
    {
        return ['title' => is_string($input['title'] ?? null) ? $input['title'] : '', 'body' => is_string($input['body'] ?? null) ? $input['body'] : ''];
    }
}

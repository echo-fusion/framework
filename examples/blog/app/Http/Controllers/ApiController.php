<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\PostRepository;
use EchoFusion\AuthManager\Users\GenericUser;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;

/**
 * JSON API. Returning an array makes the framework answer with JSON.
 * Reading is public; writing needs `Authorization: Bearer <token>` (see the account page).
 */
final class ApiController
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $limit = min(50, max(1, $request->int('limit', 10)));
        $offset = max(0, $request->int('offset', 0));

        return ['data' => $this->posts->latest($limit, $offset), 'total' => $this->posts->count()];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        return ['data' => $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.')];
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            throw new HttpException(422, 'title and body are required.');
        }

        $id = $this->posts->create((int) $request->user()?->getAuthIdentifier(), $title, $body);

        return ['data' => $this->posts->find($id)];
    }

    /**
     * PUT/PATCH: the fields come from the JSON body or from a form-encoded body, `$request->input()` reads both.
     * PATCH changes only the fields that were sent; PUT needs both.
     *
     * @return array<string, mixed>
     */
    public function update(Request $request, int $id): array
    {
        $post = $this->ownedPost($request, $id);

        $title = trim((string) $request->input('title', $request->isMethod('PATCH') ? $post['title'] : ''));
        $body = trim((string) $request->input('body', $request->isMethod('PATCH') ? $post['body'] : ''));
        if ($title === '' || $body === '') {
            throw new HttpException(422, 'title and body are required.');
        }

        $this->posts->update($id, $title, $body);

        return ['data' => $this->posts->find($id)];
    }

    public function destroy(Request $request, int $id): null
    {
        $this->ownedPost($request, $id);
        $this->posts->delete($id);

        return null; // 204 No Content
    }

    /**
     * @return array<string, mixed>
     */
    public function me(Request $request): array
    {
        $user = $request->user();

        return ['data' => $user instanceof GenericUser ? ['id' => $user->getAuthIdentifier(), 'name' => $user->get('name'), 'email' => $user->get('email')] : null];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownedPost(Request $request, int $id): array
    {
        $post = $this->posts->find($id) ?? throw new HttpException(404, 'There is no such post.');
        if ((string) $post['user_id'] !== (string) $request->user()?->getAuthIdentifier()) {
            throw new HttpException(403, 'You can only change your own posts.');
        }

        return $post;
    }
}

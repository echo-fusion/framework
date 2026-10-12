<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\PostRepository;
use EchoFusion\Framework\Http\Request;

/**
 * The rules for a post, shared by the public pages, the admin panel and the API.
 */
final class PostValidator
{
    /**
     * @return list<string> what is wrong; empty when the input is fine
     */
    public function errors(Request $request): array
    {
        $title = trim($request->string('title'));
        $errors = [];

        if ($title === '' || mb_strlen($title) > 200) {
            $errors[] = 'The title is required and may have at most 200 characters.';
        }
        if (trim($request->string('body')) === '') {
            $errors[] = 'The text is required.';
        }

        return $errors;
    }

    /**
     * The form has a "Publish" checkbox; a checkbox that is not ticked is not sent at all.
     */
    public function status(Request $request): string
    {
        return $request->bool('publish') ? PostRepository::PUBLISHED : PostRepository::DRAFT;
    }

    /**
     * What the user typed, to fill the form again after an error.
     *
     * @return array<string, string>
     */
    public function old(Request $request): array
    {
        return ['title' => $request->string('title'), 'body' => $request->string('body'), 'status' => $this->status($request)];
    }
}

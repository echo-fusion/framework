# Controllers and the Request

A controller is any class. The container builds it, so the constructor can ask for services:

```php
final class PostController
{
    public function __construct(private readonly PostRepository $posts) {}

    public function show(int $id): array { ... }
}
```

## What an action can ask for

Each argument of the action is filled from its declaration:

* a route placeholder with the same name (`$id`), cast to `int`, `float`, `bool` or `string`;
* `EchoFusion\Framework\Http\Request` (or your own subclass of it);
* the PSR-7 `ServerRequestInterface`;
* any service the container knows.

You do **not** need a middleware to pass the request on: the framework hands it to the action.

## The Request

```php
public function store(Request $request): ResponseInterface
{
    $request->query('page');             // ?page=2           (also ?filter[status]=open as 'filter.status')
    $request->post('title');             // form field        (POST, and PUT / PATCH / DELETE forms too)
    $request->json('title');             // JSON body         (dot keys: 'author.name')
    $request->input('title');            // form, JSON or query: whatever the client sent
    $request->all();                     // all of them merged
    $request->only('title', 'body');     // pick fields
    $request->except('_token');
    $request->has('title', 'body');      // present
    $request->filled('title');           // present and not empty

    $request->string('title');           // typed, with a default: string('x', 'fallback')
    $request->int('page', 1);
    $request->float('price');
    $request->bool('subscribe');         // "1", "true", "on", "yes"

    $request->file('avatar');            // ?UploadedFileInterface
    $request->header('Accept');
    $request->bearerToken();             // Authorization: Bearer ...
    $request->cookie('theme');
    $request->method(); $request->path(); $request->isJson(); $request->wantsJson(); $request->isAjax(); $request->ip();

    $request->user();                    // the signed-in user (route with the auth middleware)
    $request->csrfToken();               // for forms
    $request->psr();                     // the underlying PSR-7 request
}
```

Priority in `input()` and `all()`: the query string is overridden by the form, which is overridden by the JSON body. A body that says it is JSON but is not answers `400`.

## What an action can return

| Return | Response |
| --- | --- |
| `string` | `200`, `text/html` |
| `array` | `200`, JSON |
| `null` | `204 No Content` |
| a PSR-7 response | as it is (use it for redirects, files, custom status) |

```php
return new Response(303, ['Location' => '/posts/' . $id]);     // redirect after a form (GuzzleHttp\Psr7\Response)
return new JsonResponse(['id' => $id], 201);                    // EchoFusion\ResponseManager\JsonResponse
```

## Errors from a controller

```php
throw new HttpException(404, 'There is no such post.');
throw new HttpException(403, 'You can only edit your own posts.');
throw new HttpException(422, 'title is required.');
```

## Your own request class

```php
final class PostRequest extends Request
{
    public function title(): string { return trim($this->string('title')); }
}

public function store(PostRequest $request) { $request->title(); }
```

The example application's [PostController](../examples/blog/app/Http/Controllers/PostController.php) and [ApiController](../examples/blog/app/Http/Controllers/ApiController.php) use all of this.

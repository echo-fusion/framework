# Sessions, flash messages and CSRF

## Session

`StartSessionMiddleware` (in `middleware.core`) starts the session. Configure the cookie in `config/session.php` (`secure` must be `true` on HTTPS).

```php
public function __construct(private readonly SessionInterface $session) {}

$this->session->put('cart', [1, 2]);
$this->session->get('cart', []);
$this->session->forget('cart');
```

## Flash messages

A flash message lives until it is read once: perfect for "post, then redirect".

```php
$session->flash('success', ['Your post was published.']);
return new Response(303, ['Location' => '/posts/1']);      // the next request reads it:
$messages = $session->getFlash('success');                  // ['Your post was published.'] and it is gone
```

The example's [Redirect](../examples/blog/app/Support/Redirect.php) and [View](../examples/blog/app/Support/View.php) wrap this, including form errors and the old input that refills the form.

Always answer a successful form with a redirect (`303`), never with a page: otherwise the browser's reload button submits the form again.

## CSRF

`VerifyCsrfToken` (in `middleware.core`, after the session) protects forms. Every session has a random token. Any request that is not `GET`, `HEAD` or `OPTIONS` must send it back as the field `_token` or the header `X-CSRF-TOKEN`, otherwise the answer is `419 Page Expired`.

```php
return $this->templates->render('posts/form', ['csrf' => $request->csrfToken()]);
```

```html
<form method="post" action="/posts">
    <input type="hidden" name="_token" value="{{ csrf }}">
    ...
</form>
```

JavaScript: send the token in `X-CSRF-TOKEN`. Paths authenticated by a header token, such as an API, are not exposed to CSRF; exclude them:

```php
// config/app.php
'csrf' => ['except' => ['/api/*']],
```

The `testing` environment has no global middleware (`config/environments/testing.php`), so tests need no token.

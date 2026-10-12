<?php

declare(strict_types=1);

namespace App\Support;

use EchoFusion\AuthManager\Users\GenericUser;
use EchoFusion\Contracts\AuthManager\AuthManagerInterface;
use EchoFusion\Contracts\SessionManager\SessionInterface;
use EchoFusion\Contracts\TemplateManager\TemplateManagerInterface;
use EchoFusion\Framework\Http\Request;

/**
 * Renders a template with what every page needs: the CSRF token, the signed-in user and flash messages.
 */
final class View
{
    public function __construct(
        private readonly TemplateManagerInterface $templates,
        private readonly AuthManagerInterface $auth,
        private readonly SessionInterface $session,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(Request $request, string $template, array $data = []): string
    {
        $user = $this->auth->guard('web')->user();

        return $this->templates->render($template, $data + [
            'csrf' => $request->csrfToken(),
            'me' => $user === null ? null : ['id' => $user->getAuthIdentifier(), 'name' => $user instanceof GenericUser ? (string) $user->get('name', '') : ''],
            'success' => $this->session->getFlash('success'),
            'errors' => $this->session->getFlash('errors'),
            'old' => $this->session->getFlash('old'),
        ]);
    }
}

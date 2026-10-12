<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\PostRepository;
use App\Repositories\UserRepository;
use EchoFusion\Contracts\AuthManager\PasswordHasherInterface;
use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function count;
use function sprintf;

/**
 * Fills an empty database with demo accounts and posts, so there is something to click on.
 * For local demos only: the passwords are public.
 */
final class SeedCommand extends Command
{
    private const PASSWORD = 'password';

    private const USERS = [
        ['Admin', 'admin@example.com', UserRepository::ADMIN],
        ['Ann Author', 'ann@example.com', UserRepository::USER],
        ['Bob Writer', 'bob@example.com', UserRepository::USER],
    ];

    private const TOPICS = [
        'Getting started with EchoFusion', 'Why small packages beat one big framework', 'Routing in five minutes',
        'Forms, CSRF and the Request object', 'Migrations without fear', 'Authentication with sessions and tokens',
        'Templates: layouts, blocks and filters', 'Writing your own console command', 'Testing without a web server',
        'Events and where to use them', 'Swapping the database layer for Doctrine', 'Deploying to a small server',
    ];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PasswordHasherInterface $hasher,
        private readonly PostRepository $posts,
    ) {
    }

    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return 'Create demo users and posts in an empty database';
    }

    public function options(): array
    {
        return ['force' => ['description' => 'Seed even when there are users already (adds more, keeps what exists)']];
    }

    public function handle(Input $input, Output $output): int
    {
        if ((int) $this->db->fetchValue('SELECT COUNT(*) FROM users') > 0 && $input->option('force') !== true) {
            $output->comment('The database already has users, nothing to do. Use --force to add the demo data anyway.');

            return self::SUCCESS;
        }

        $ids = $this->db->transactional(function (ConnectionInterface $db): array {
            $ids = [];
            $hash = $this->hasher->hash(self::PASSWORD);
            foreach (self::USERS as [$name, $email, $role]) {
                $db->execute('INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)', [$name, $email, $hash, $role]);
                $ids[] = (int) $db->fetchValue('SELECT id FROM users WHERE email = ?', [$email]);
            }

            return $ids;
        });

        foreach (self::TOPICS as $i => $title) {
            // the oldest posts first; every ninth post stays a draft
            $this->posts->create($ids[1 + $i % 2], $title, $this->text($title), $i % 9 === 8 ? PostRepository::DRAFT : PostRepository::PUBLISHED);
        }

        $output->success(sprintf('Created %d users and %d posts.', count(self::USERS), count(self::TOPICS)));
        $output->table(['Name', 'Email', 'Role'], array_map(static fn (array $user): array => [$user[0], $user[1], $user[2]], self::USERS));
        $output->comment(sprintf('Every demo account has the password "%s".', self::PASSWORD));

        return self::SUCCESS;
    }

    private function text(string $title): string
    {
        return $title . " is a demo post created by `php bin/console db:seed`.\n\n"
            . 'It exists so the list has something to show, the search has something to find, and you can try editing, '
            . "unpublishing and deleting it from the admin panel.\n\n"
            . 'Sign in as one of the demo users to change it.';
    }
}

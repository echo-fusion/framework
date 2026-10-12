# Database and migrations

## Configuration

```php
// config/database.php
return [
    'default' => 'main',
    'connections' => [
        'main' => ['driver' => env('DB_DRIVER', 'sqlite'), 'host' => env('DB_HOST'), 'dbname' => env('DB_DATABASE'),
                   'user' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD'), 'path' => '/path/to/file.sqlite'],
    ],
];
```

Drivers: `mysql`, `pgsql`, `sqlite`. Nothing connects until the first query.

## Running SQL

Ask for `ConnectionInterface` (from `echo-fusion/contracts`) in a constructor:

```php
final class PostRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);   // ?array
    }
}
```

`fetchAll()`, `fetchOne()`, `fetchValue()`, `execute()` (returns affected rows), `lastInsertId()`. **Always pass values as parameters**, never concatenate them into the SQL.

```php
$db->transactional(function (ConnectionInterface $db) use ($from, $to, $amount): void {
    $db->execute('UPDATE accounts SET balance = balance - ? WHERE id = ?', [$amount, $from]);
    $db->execute('UPDATE accounts SET balance = balance + ? WHERE id = ?', [$amount, $to]);
});   // commit, or roll back when the closure throws; nested calls use savepoints
```

Keep SQL in repository classes like [PostRepository](../examples/blog/app/Repositories/PostRepository.php) so controllers stay small.

## Query builder

```php
$posts = QueryBuilder::table($db, 'posts')->where('user_id', 1)->orderBy('id', 'desc')->limit(10)->get();
```

Table and column names are validated, values are bound, and `update()`/`delete()` refuse to run without a `WHERE`. More in the [databasemanager](https://github.com/echo-fusion/databasemanager) README.

## Another library (Doctrine, an ORM ...)

Everything depends on `ConnectionInterface`, so you can rebind it. With Doctrine DBAL:

```php
'connections' => ['main' => ['class' => EchoFusion\DatabaseManager\Connections\DoctrineConnection::class, 'driver' => 'pdo_mysql', ...]],
```

or in a provider: `$container->set(ConnectionInterface::class, fn () => new MyConnection(...), true);`.

## Migrations

```bash
php bin/console make:migration create_posts_table --create=posts
php bin/console migrate            # run what has not run yet (one batch)
php bin/console migrate:status
php bin/console migrate:rollback   # undo the last batch
```

A migration is a file in `database/migrations` that returns an object:

```php
return new class implements Migration {
    public function up(ConnectionInterface $db): void   { $db->execute('CREATE TABLE ...'); }
    public function down(ConnectionInterface $db): void { $db->execute('DROP TABLE ...'); }
};
```

Files run in name order, each in a transaction (on MySQL, DDL statements commit implicitly, so keep one change per migration). The `migrations` table records what ran. Never edit a migration that has run elsewhere: add a new one. `--database=name` migrates another connection.

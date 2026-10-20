<?php

declare(strict_types=1);

/**
 * Casos de teste do micro ORM contra PostgreSQL real.
 *
 * Uso (na pasta do projeto, com o banco criado):
 *   php tests/test_postgres.php
 *
 * Variáveis opcionais: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 * Cria/zera as tabelas "products" e "users" (sql/schema.sql).
 * Gera também tests/casos_de_teste.md com a tabela de resultados.
 * Código de saída 0 = todos passaram; 1 = pelo menos um falhou.
 */

require __DIR__ . '/../src/Connection.php';
require __DIR__ . '/../src/QueryBuilder.php';
require __DIR__ . '/../src/ActiveRecord.php';

use App\ORM\ActiveRecord;
use App\ORM\Connection;

class Product extends ActiveRecord
{
    public int $id;
    public string $name;
    public float $price;
    public int $stock;
}

class User extends ActiveRecord
{
    public int $id;
    public string $name;
    public string $email;
}

class OrderItem extends ActiveRecord {}
class Category extends ActiveRecord {}
class Box extends ActiveRecord {}

class Legacy extends ActiveRecord
{
    protected static ?string $table = 'tb_legado';
}

class Flag extends ActiveRecord
{
    public int $id;
    public bool $active;
    public ?string $note = null;
}

$env = static fn (string $k, string $d): string => getenv($k) !== false ? (string) getenv($k) : $d;
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $env('DB_HOST', 'localhost'), $env('DB_PORT', '5432'), $env('DB_NAME', 'tcc_orm'));
$user = $env('DB_USER', 'postgres');
$pass = $env('DB_PASS', 'secret');

Connection::configure($dsn, $user, $pass);
$pdo = Connection::getInstance();
$reset = static function () use ($pdo): void {
    $pdo->exec('TRUNCATE products, users RESTART IDENTITY');
};
$pdo->exec((string) file_get_contents(__DIR__ . '/../sql/schema.sql'));

/** @var list<array{string,string,string,string,bool,string}> $results */
$results = [];
function ct(string $id, string $group, string $desc, string $expected, callable $fn): void
{
    global $results, $reset;
    $reset();
    try {
        $out = $fn();
        $ok = $out === true || (is_array($out) && $out[0] === true);
        $got = is_array($out) ? (string) $out[1] : ($ok ? $expected : 'resultado diferente do esperado');
    } catch (Throwable $e) {
        $ok = false;
        $got = get_class($e) . ': ' . $e->getMessage();
    }
    $results[] = [$id, $group, $desc, $expected, $ok, $got];
}

/** Executa $fn e retorna o nome da exceção lançada (ou null). */
function thrown(callable $fn): ?string
{
    try {
        $fn();
    } catch (Throwable $e) {
        return get_class($e);
    }
    return null;
}

function seed(): void
{
    (new Product(['name' => 'Notebook', 'price' => 3500.0, 'stock' => 10]))->save();
    (new Product(['name' => 'Mouse', 'price' => 80.0, 'stock' => 50]))->save();
    (new Product(['name' => 'Teclado', 'price' => 150.0, 'stock' => 30]))->save();
    (new Product(['name' => 'Monitor', 'price' => 1200.0, 'stock' => 5]))->save();
}

// ---------------------------------------------------------------- Convenção de nomes
ct('CT01', 'Convenção', 'Nome da tabela inferido de "Product"', 'products', fn () => Product::tableName() === 'products');
ct('CT02', 'Convenção', 'Nome em CamelCase vira snake_case ("OrderItem")', 'order_items', fn () => OrderItem::tableName() === 'order_items');
ct('CT03', 'Convenção', 'Plural com consoante + "y" ("Category")', 'categories', fn () => Category::tableName() === 'categories');
ct('CT04', 'Convenção', 'Plural com terminação "x" ("Box")', 'boxes', fn () => Box::tableName() === 'boxes');
ct('CT05', 'Convenção', 'Nome da tabela sobrescrito em $table', 'tb_legado', fn () => Legacy::tableName() === 'tb_legado');

// ---------------------------------------------------------------- CRUD
ct('CT06', 'CRUD', 'save() em objeto novo executa INSERT e preenche o id (RETURNING)', 'id > 0', function () {
    $p = new Product(['name' => 'Notebook', 'price' => 3500.0, 'stock' => 10]);
    $p->save();
    return [isset($p->id) && $p->id > 0, 'id = ' . ($p->id ?? 'null')];
});
ct('CT07', 'CRUD', 'Dois INSERTs seguidos geram ids distintos e crescentes', 'id2 > id1', function () {
    $a = (new Product(['name' => 'A', 'price' => 1.0, 'stock' => 1]))->save();
    $b = (new Product(['name' => 'B', 'price' => 2.0, 'stock' => 2]))->save();
    return [$b->id > $a->id, "ids {$a->id} e {$b->id}"];
});
ct('CT08', 'CRUD', 'find() recupera o registro e converte os tipos (int, float, string)', 'tipos corretos', function () {
    $p = (new Product(['name' => 'Notebook', 'price' => 3500.5, 'stock' => 10]))->save();
    $f = Product::find($p->id);
    $ok = $f !== null && is_int($f->id) && is_string($f->name) && is_float($f->price) && is_int($f->stock) && $f->price === 3500.5;
    return [$ok, $f === null ? 'null' : get_debug_type($f->id) . '/' . get_debug_type($f->price)];
});
ct('CT09', 'CRUD', 'find() com id inexistente', 'null', fn () => Product::find(999999) === null);
ct('CT10', 'CRUD', 'save() em objeto com id executa UPDATE', 'preço atualizado', function () {
    $p = (new Product(['name' => 'Mouse', 'price' => 80.0, 'stock' => 50]))->save();
    $p->price = 99.9;
    $p->save();
    $r = Product::find($p->id);
    return [$r->price === 99.9, 'price = ' . $r->price];
});
ct('CT11', 'CRUD', 'UPDATE não cria registro novo', 'contagem inalterada (1)', function () {
    $p = (new Product(['name' => 'Mouse', 'price' => 80.0, 'stock' => 50]))->save();
    $p->stock = 49;
    $p->save();
    $n = count(Product::all());
    return [$n === 1, "contagem = {$n}"];
});
ct('CT12', 'CRUD', 'delete() remove o registro', 'find() retorna null', function () {
    $p = (new Product(['name' => 'Mouse', 'price' => 80.0, 'stock' => 50]))->save();
    $ok = $p->delete();
    return [$ok === true && Product::find($p->id) === null, 'removido'];
});
ct('CT13', 'CRUD', 'all() com a tabela vazia', 'array vazio', fn () => Product::all() === []);
ct('CT14', 'CRUD', 'all() devolve todos os registros como objetos da entidade', '4 objetos Product', function () {
    seed();
    $all = Product::all();
    return [count($all) === 4 && $all[0] instanceof Product, count($all) . ' objetos'];
});
ct('CT15', 'CRUD', 'Construtor com array preenche propriedades e ignora chaves desconhecidas', 'name preenchido, sem erro', function () {
    $p = new Product(['name' => 'X', 'inexistente' => 1]);
    return [$p->name === 'X' && !property_exists($p, 'inexistente'), 'name = ' . $p->name];
});

// ---------------------------------------------------------------- Query Builder
ct('CT16', 'Query Builder', 'where("price", ">", 1000)', '2 registros (Notebook, Monitor)', function () {
    seed();
    $r = Product::where('price', '>', 1000)->get();
    return [count($r) === 2, count($r) . ' registros'];
});
ct('CT17', 'Query Builder', 'Dois where() encadeados combinam com AND', '1 registro (Monitor)', function () {
    seed();
    $r = Product::where('price', '>', 1000)->where('stock', '<', 10)->get();
    return [count($r) === 1 && $r[0]->name === 'Monitor', count($r) . ' registro(s)'];
});
ct('CT18', 'Query Builder', 'orderBy("name", "ASC")', 'Monitor, Mouse, Notebook, Teclado', function () {
    seed();
    $n = array_map(fn ($p) => $p->name, Product::query()->orderBy('name', 'ASC')->get());
    return [$n === ['Monitor', 'Mouse', 'Notebook', 'Teclado'], implode(', ', $n)];
});
ct('CT19', 'Query Builder', 'orderBy("price", "DESC")', 'Notebook primeiro', function () {
    seed();
    $r = Product::query()->orderBy('price', 'DESC')->get();
    return [$r[0]->name === 'Notebook', $r[0]->name . ' primeiro'];
});
ct('CT20', 'Query Builder', 'limit(2)', '2 registros', function () {
    seed();
    $r = Product::query()->orderBy('id')->limit(2)->get();
    return [count($r) === 2, count($r) . ' registros'];
});
ct('CT21', 'Query Builder', 'first() com resultado', 'objeto Product', function () {
    seed();
    $p = Product::where('name', '=', 'Mouse')->first();
    return [$p instanceof Product && $p->name === 'Mouse', $p === null ? 'null' : $p->name];
});
ct('CT22', 'Query Builder', 'first() sem resultado', 'null', function () {
    seed();
    return Product::where('name', '=', 'Inexistente')->first() === null;
});
ct('CT23', 'Query Builder', 'where com LIKE', '1 registro (Notebook)', function () {
    seed();
    $r = Product::where('name', 'LIKE', 'Note%')->get();
    return [count($r) === 1, count($r) . ' registro(s)'];
});

// ---------------------------------------------------------------- Segurança e validação
ct('CT24', 'Segurança', 'Operador inválido em where() ("DROP TABLE")', 'InvalidArgumentException', function () {
    $e = thrown(fn () => Product::where('price', 'DROP TABLE', 1));
    return [$e === InvalidArgumentException::class, (string) $e];
});
ct('CT25', 'Segurança', 'Nome de coluna inválido em where()', 'InvalidArgumentException', function () {
    $e = thrown(fn () => Product::where('price; DROP TABLE products', '=', 1));
    return [$e === InvalidArgumentException::class, (string) $e];
});
ct('CT26', 'Segurança', 'Identificador inválido em orderBy()', 'InvalidArgumentException', function () {
    $e = thrown(fn () => Product::query()->orderBy('id; DROP TABLE products;--'));
    return [$e === InvalidArgumentException::class, (string) $e];
});
ct('CT27', 'Segurança', 'Direção inválida em orderBy()', 'InvalidArgumentException', function () {
    $e = thrown(fn () => Product::query()->orderBy('id', 'SIDEWAYS'));
    return [$e === InvalidArgumentException::class, (string) $e];
});
ct('CT28', 'Segurança', 'limit() negativo', 'InvalidArgumentException', function () {
    $e = thrown(fn () => Product::query()->limit(-1));
    return [$e === InvalidArgumentException::class, (string) $e];
});
ct('CT29', 'Segurança', 'SQL Injection no VALOR do where() é tratado como texto', 'nenhum resultado e tabela intacta', function () use ($pdo) {
    seed();
    $r = Product::where('name', '=', "x'; DROP TABLE products;--")->get();
    $n = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    return [$r === [] && $n === 4, count($r) . ' resultados; ' . $n . ' linhas na tabela'];
});
ct('CT30', 'Segurança', 'SQL Injection no valor de find() ("1 OR 1=1")', 'valor rejeitado (PDOException) e tabela intacta', function () use ($pdo) {
    seed();
    $e = thrown(fn () => Product::find('1 OR 1=1'));
    $n = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    return [$e === PDOException::class && $n === 4, "{$e}; {$n} linhas na tabela"];
});

// ---------------------------------------------------------------- Integridade do banco
ct('CT31', 'Integridade', 'INSERT violando NOT NULL (sem "name")', 'PDOException', function () {
    $e = thrown(fn () => (new Product(['price' => 1.0, 'stock' => 1]))->save());
    return [$e === PDOException::class, (string) $e];
});
ct('CT32', 'Integridade', 'INSERT violando UNIQUE (e-mail repetido)', 'PDOException', function () {
    (new User(['name' => 'Ana', 'email' => 'ana@exemplo.com']))->save();
    $e = thrown(fn () => (new User(['name' => 'Ana 2', 'email' => 'ana@exemplo.com']))->save());
    return [$e === PDOException::class, (string) $e];
});
ct('CT33', 'Integridade', 'Registro continua consistente após erro de UNIQUE', '1 usuário no banco', function () {
    (new User(['name' => 'Ana', 'email' => 'ana@exemplo.com']))->save();
    thrown(fn () => (new User(['name' => 'Ana 2', 'email' => 'ana@exemplo.com']))->save());
    $n = count(User::all());
    return [$n === 1, "{$n} usuário(s)"];
});

// ---------------------------------------------------------------- Hidratação e conexão
ct('CT34', 'Hidratação', 'hydrate() converte "t"/"f" do PostgreSQL para bool e mantém null', 'true, false e null', function () {
    $a = Flag::hydrate(['id' => '1', 'active' => 't', 'note' => null]);
    $b = Flag::hydrate(['id' => '2', 'active' => 'f']);
    return [$a->active === true && $b->active === false && $a->note === null && $a->id === 1, 'bool/int/null convertidos'];
});
ct('CT35', 'Conexão', 'Falha de conexão (porta inválida) vira RuntimeException', 'RuntimeException', function () use ($dsn, $user, $pass) {
    Connection::configure('pgsql:host=localhost;port=1;dbname=nada', $user, $pass);
    $e = thrown(fn () => Connection::getInstance());
    Connection::configure($dsn, $user, $pass);
    return [$e === RuntimeException::class, (string) $e];
});
ct('CT36', 'Conexão', 'getInstance() devolve sempre a mesma conexão (Singleton)', 'mesmo objeto PDO', fn () => Connection::getInstance() === Connection::getInstance());

// ---------------------------------------------------------------- Relatório
$pass_n = count(array_filter($results, fn ($r) => $r[4]));
$total = count($results);
$md = "| ID | Grupo | Caso de teste | Resultado esperado | Resultado |\n|---|---|---|---|---|\n";
foreach ($results as [$id, $g, $d, $exp, $ok, $got]) {
    echo $id . '  ' . ($ok ? 'OK   ' : 'FALHA') . '  [' . $g . '] ' . $d . "\n";
    if (!$ok) {
        echo "        esperado: {$exp}\n        obtido:   {$got}\n";
    }
    $md .= "| {$id} | {$g} | {$d} | {$exp} | " . ($ok ? 'Passou' : 'FALHOU') . " |\n";
}
file_put_contents(__DIR__ . '/casos_de_teste.md', $md);
echo "\n{$pass_n}/{$total} casos de teste passaram.\n";
exit($pass_n === $total ? 0 : 1);

<?php

declare(strict_types=1);

/**
 * Benchmark de desempenho: micro ORM x Eloquent x Doctrine (+ PDO puro
 * como linha de base). Metodologia: Capítulo 3 e Seção 5.3 do TCC.
 *
 * Para cada ferramenta, volume (N) e operação, executa REPS repetições
 * (mais 1 de aquecimento, descartada) e reporta média, desvio padrão e
 * mediana do tempo (ms) e o pico de memória adicional (MB).
 *
 * Uso:   php benchmark/benchmark.php
 * Config por variáveis de ambiente (valores padrão entre parênteses):
 *   DB_HOST (localhost) DB_PORT (5432) DB_NAME (tcc_orm)
 *   DB_USER (postgres)  DB_PASS (secret)
 *   VOLUMES (100,1000,10000)  REPS (10)
 *   TOOLS (pdo,micro,eloquent,doctrine)  TX (0 ou 1)
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/entities/DoctrineProduct.php';

$hasThirdParty = is_file(__DIR__ . '/vendor/autoload.php');
if ($hasThirdParty) {
    require __DIR__ . '/vendor/autoload.php';
}

use App\ORM\ActiveRecord;
use App\ORM\Connection;
use Bench\DoctrineProduct;

// ---------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------
$env = static fn (string $k, string $d): string => getenv($k) !== false ? (string) getenv($k) : $d;

$cfg = [
    'host' => $env('DB_HOST', 'localhost'),
    'port' => $env('DB_PORT', '5432'),
    'name' => $env('DB_NAME', 'tcc_orm'),
    'user' => $env('DB_USER', 'postgres'),
    'pass' => $env('DB_PASS', 'secret'),
];
$volumes = array_map('intval', explode(',', $env('VOLUMES', '100,1000,10000')));
$reps = (int) $env('REPS', '10');
// TX=1: cada bloco de escrita roda dentro de UMA transação, para todas as ferramentas.
// TX=0 (padrão): uso idiomático de cada ferramenta (Doctrine agrupa via flush; as demais confirmam cada comando).
$useTx = $env('TX', '0') === '1';
$suffix = $useTx ? 'tx' : 'padrao';
$toolsWanted = explode(',', $env('TOOLS', 'pdo,micro,eloquent,doctrine'));
$dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']}";

// ---------------------------------------------------------------------
// Entidades
// ---------------------------------------------------------------------
class MicroProduct extends ActiveRecord
{
    protected static ?string $table = 'products';

    public int $id;
    public string $name;
    public float $price;
    public int $stock;
}

if ($hasThirdParty) {
    class EloquentProduct extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'products';
        public $timestamps = false;
        protected $guarded = [];
    }
}

// ---------------------------------------------------------------------
// Adaptadores: cada um expõe as mesmas operações
// ---------------------------------------------------------------------
abstract class Adapter
{
    abstract public function name(): string;
    abstract public function insert(int $n): void;
    abstract public function readOne(int $n): void;
    abstract public function readAll(): int;
    /** Carrega entidades (NÃO cronometrado) para as operações update/delete. */
    abstract public function load(): void;
    abstract public function update(): void;
    abstract public function delete(): void;
    public function reset(): void {}
    /** Abre/fecha uma transação em torno de um bloco de escrita (modo TX=1). */
    public function begin(): void {}
    public function commit(): void {}
}

final class PdoAdapter extends Adapter
{
    private array $ids = [];

    public function __construct(private PDO $pdo) {}

    public function name(): string { return 'PDO puro'; }
    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }

    public function insert(int $n): void
    {
        $st = $this->pdo->prepare('INSERT INTO products (name, price, stock) VALUES (?, ?, ?)');
        for ($i = 1; $i <= $n; $i++) {
            $st->execute(["Produto $i", 10.5 + $i % 100, $i % 50]);
        }
    }

    public function readOne(int $n): void
    {
        $st = $this->pdo->prepare('SELECT * FROM products WHERE id = ?');
        for ($i = 1; $i <= $n; $i++) {
            $st->execute([$i]);
            $st->fetch(PDO::FETCH_ASSOC);
        }
    }

    public function readAll(): int
    {
        return count($this->pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function load(): void
    {
        $this->ids = $this->pdo->query('SELECT id FROM products')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function update(): void
    {
        $st = $this->pdo->prepare('UPDATE products SET price = price + 1, stock = stock + 1 WHERE id = ?');
        foreach ($this->ids as $id) {
            $st->execute([$id]);
        }
    }

    public function delete(): void
    {
        $st = $this->pdo->prepare('DELETE FROM products WHERE id = ?');
        foreach ($this->ids as $id) {
            $st->execute([$id]);
        }
    }
}

final class MicroAdapter extends Adapter
{
    private array $entities = [];

    public function name(): string { return 'Micro ORM'; }
    public function begin(): void { Connection::getInstance()->beginTransaction(); }
    public function commit(): void { Connection::getInstance()->commit(); }

    public function insert(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $p = new MicroProduct();
            $p->name = "Produto $i";
            $p->price = 10.5 + $i % 100;
            $p->stock = $i % 50;
            $p->save();
        }
    }

    public function readOne(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            MicroProduct::find($i);
        }
    }

    public function readAll(): int { return count(MicroProduct::all()); }

    public function load(): void { $this->entities = MicroProduct::all(); }

    public function update(): void
    {
        foreach ($this->entities as $p) {
            $p->price += 1;
            $p->stock += 1;
            $p->save();
        }
    }

    public function delete(): void
    {
        foreach ($this->entities as $p) {
            $p->delete();
        }
    }
}

final class EloquentAdapter extends Adapter
{
    private array $entities = [];

    public function name(): string { return 'Eloquent'; }
    public function begin(): void { \Illuminate\Database\Capsule\Manager::connection()->beginTransaction(); }
    public function commit(): void { \Illuminate\Database\Capsule\Manager::connection()->commit(); }

    public function insert(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $p = new EloquentProduct();
            $p->name = "Produto $i";
            $p->price = 10.5 + $i % 100;
            $p->stock = $i % 50;
            $p->save();
        }
    }

    public function readOne(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            EloquentProduct::find($i);
        }
    }

    public function readAll(): int { return EloquentProduct::all()->count(); }

    public function load(): void { $this->entities = EloquentProduct::all()->all(); }

    public function update(): void
    {
        foreach ($this->entities as $p) {
            $p->price += 1;
            $p->stock += 1;
            $p->save();
        }
    }

    public function delete(): void
    {
        foreach ($this->entities as $p) {
            $p->delete();
        }
    }
}

/**
 * Doctrine usa Unit of Work: as operações de escrita são acumuladas e
 * enviadas em um único flush() dentro de uma transação. É o uso
 * idiomático da ferramenta e é descrito como tal na monografia.
 */
final class DoctrineAdapter extends Adapter
{
    private array $entities = [];

    public function __construct(private \Doctrine\ORM\EntityManager $em) {}

    public function name(): string { return 'Doctrine'; }

    public function insert(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $p = new DoctrineProduct();
            $p->name = "Produto $i";
            $p->price = number_format(10.5 + $i % 100, 2, '.', '');
            $p->stock = $i % 50;
            $this->em->persist($p);
        }
        $this->em->flush();
        $this->em->clear();
    }

    public function readOne(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $this->em->find(DoctrineProduct::class, $i);
        }
        $this->em->clear();
    }

    public function readAll(): int
    {
        $c = count($this->em->getRepository(DoctrineProduct::class)->findAll());
        $this->em->clear();
        return $c;
    }

    public function load(): void
    {
        $this->entities = $this->em->getRepository(DoctrineProduct::class)->findAll();
    }

    public function update(): void
    {
        foreach ($this->entities as $p) {
            $p->price = number_format((float) $p->price + 1, 2, '.', '');
            $p->stock += 1;
        }
        $this->em->flush();
    }

    public function delete(): void
    {
        foreach ($this->entities as $p) {
            $this->em->remove($p);
        }
        $this->em->flush();
        $this->em->clear();
        $this->entities = [];
    }

    public function reset(): void { $this->em->clear(); $this->entities = []; }
}

// ---------------------------------------------------------------------
// Montagem das ferramentas disponíveis
// ---------------------------------------------------------------------
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

Connection::configure($dsn, $cfg['user'], $cfg['pass']);

$adapters = [];
foreach ($toolsWanted as $tool) {
    switch (trim($tool)) {
        case 'pdo':
            $adapters[] = new PdoAdapter($pdo);
            break;
        case 'micro':
            $adapters[] = new MicroAdapter();
            break;
        case 'eloquent':
            if (!$hasThirdParty) { fwrite(STDERR, "[aviso] Eloquent ignorado: rode 'composer install' em benchmark/\n"); break; }
            $capsule = new \Illuminate\Database\Capsule\Manager();
            $capsule->addConnection([
                'driver' => 'pgsql', 'host' => $cfg['host'], 'port' => $cfg['port'],
                'database' => $cfg['name'], 'username' => $cfg['user'], 'password' => $cfg['pass'],
                'charset' => 'utf8', 'prefix' => '', 'schema' => 'public',
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            $adapters[] = new EloquentAdapter();
            break;
        case 'doctrine':
            if (!$hasThirdParty) { fwrite(STDERR, "[aviso] Doctrine ignorado: rode 'composer install' em benchmark/\n"); break; }
            $config = \Doctrine\ORM\ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/entities'], true);
            $conn = \Doctrine\DBAL\DriverManager::getConnection([
                'driver' => 'pdo_pgsql', 'host' => $cfg['host'], 'port' => (int) $cfg['port'],
                'dbname' => $cfg['name'], 'user' => $cfg['user'], 'password' => $cfg['pass'],
            ], $config);
            $adapters[] = new DoctrineAdapter(new \Doctrine\ORM\EntityManager($conn, $config));
            break;
    }
}

// ---------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------
$count = static fn (): int => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$truncate = static fn () => $pdo->exec('TRUNCATE products RESTART IDENTITY');

/** Cronometra $fn; devolve [ms, MB de pico adicional]. */
$measure = static function (callable $fn): array {
    gc_collect_cycles();
    memory_reset_peak_usage();
    $base = memory_get_usage();
    $t0 = hrtime(true);
    $fn();
    $ms = (hrtime(true) - $t0) / 1e6;
    return [$ms, max(0, memory_get_peak_usage() - $base) / 1048576];
};

$ops = ['insert' => 'Inserção', 'readOne' => 'Leitura por ID', 'readAll' => 'Leitura de todos', 'update' => 'Atualização', 'delete' => 'Remoção'];
$samples = []; // [tool][N][op] => ['t'=>[], 'm'=>[]]

foreach ($volumes as $n) {
    foreach ($adapters as $ad) {
        fwrite(STDERR, sprintf("→ %-10s N=%d ", $ad->name(), $n));
        for ($r = 0; $r <= $reps; $r++) { // r=0 é aquecimento
            $truncate();
            $ad->reset();
            $row = [];

            $row['insert'] = $measure(function () use ($ad, $n, $useTx) {
                $useTx && $ad->begin();
                $ad->insert($n);
                $useTx && $ad->commit();
            });
            if ($count() !== $n) { throw new RuntimeException("{$ad->name()}: inserção gravou {$count()} linhas, esperado $n"); }

            $row['readOne'] = $measure(fn () => $ad->readOne($n));

            $got = 0;
            $row['readAll'] = $measure(function () use ($ad, &$got) { $got = $ad->readAll(); });
            if ($got !== $n) { throw new RuntimeException("{$ad->name()}: leitura retornou $got linhas, esperado $n"); }

            $ad->load();
            $before = (int) $pdo->query('SELECT SUM(stock) FROM products')->fetchColumn();
            $row['update'] = $measure(function () use ($ad, $useTx) {
                $useTx && $ad->begin();
                $ad->update();
                $useTx && $ad->commit();
            });
            $after = (int) $pdo->query('SELECT SUM(stock) FROM products')->fetchColumn();
            if ($after !== $before + $n) { throw new RuntimeException("{$ad->name()}: atualização inconsistente (estoque $before → $after, esperado +$n)"); }

            $ad->load();
            $row['delete'] = $measure(function () use ($ad, $useTx) {
                $useTx && $ad->begin();
                $ad->delete();
                $useTx && $ad->commit();
            });
            if ($count() !== 0) { throw new RuntimeException("{$ad->name()}: remoção deixou {$count()} linhas"); }

            if ($r > 0) {
                foreach ($row as $op => [$ms, $mb]) {
                    $samples[$ad->name()][$n][$op]['t'][] = $ms;
                    $samples[$ad->name()][$n][$op]['m'][] = $mb;
                }
            }
            fwrite(STDERR, '.');
        }
        fwrite(STDERR, "\n");
    }
}

// ---------------------------------------------------------------------
// Estatísticas e saída
// ---------------------------------------------------------------------
$mean = static fn (array $a): float => array_sum($a) / count($a);
$sd = static function (array $a) use ($mean): float {
    if (count($a) < 2) { return 0.0; }
    $m = $mean($a);
    return sqrt(array_sum(array_map(fn ($x) => ($x - $m) ** 2, $a)) / (count($a) - 1));
};
$median = static function (array $a): float {
    sort($a);
    $c = count($a);
    return $c % 2 ? $a[intdiv($c, 2)] : ($a[$c / 2 - 1] + $a[$c / 2]) / 2;
};

$csv = fopen(__DIR__ . "/results/results_{$suffix}.csv", 'w');
fputcsv($csv, ['ferramenta', 'N', 'operacao', 'media_ms', 'desvio_ms', 'mediana_ms', 'pico_mem_MB', 'repeticoes'], ',', '"', '');
$md = "| Operação | N | " . implode(' | ', array_map(fn ($a) => $a->name() . ' (ms)', $adapters)) . " |\n";
$md .= '|---|---|' . str_repeat('---|', count($adapters)) . "\n";

foreach ($ops as $op => $label) {
    foreach ($volumes as $n) {
        $cells = [];
        foreach ($adapters as $ad) {
            $t = $samples[$ad->name()][$n][$op]['t'];
            $m = $samples[$ad->name()][$n][$op]['m'];
            fputcsv($csv, [$ad->name(), $n, $op, round($mean($t), 3), round($sd($t), 3), round($median($t), 3), round($mean($m), 2), count($t)], ',', '"', '');
            $cells[] = sprintf('%.1f ± %.1f', $mean($t), $sd($t));
        }
        $md .= "| $label | $n | " . implode(' | ', $cells) . " |\n";
    }
}
fclose($csv);
file_put_contents(__DIR__ . "/results/results_{$suffix}.md", $md);

echo "\nModo: ", $useTx ? "TX=1 (transação única por bloco)" : "padrão (uso idiomático)", "\n\n", $md, "\nArquivos: benchmark/results/results_{$suffix}.csv e .md\n";
echo 'PHP ', PHP_VERSION, ' | PostgreSQL ', $pdo->query('SHOW server_version')->fetchColumn(), " | repetições: $reps\n";

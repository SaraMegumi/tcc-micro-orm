<?php

declare(strict_types=1);

/**
 * Teste manual de ponta a ponta. Usa SQLite em memória (que também
 * suporta a cláusula RETURNING a partir da versão 3.35) apenas para
 * validar a LÓGICA do micro ORM sem depender de um servidor PostgreSQL
 * disponível no ambiente de execução. A implementação final e os testes
 * de desempenho do TCC devem ser feitos contra PostgreSQL de verdade,
 * conforme especificado na Seção 3.3 e 5.1.1.
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

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHOU: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

// --- Setup: banco SQLite em memória com schema equivalente ---------
Connection::configure(dsn: 'sqlite::memory:', username: '', password: '');
$pdo = Connection::getInstance();
$pdo->exec('
    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price REAL NOT NULL,
        stock INTEGER NOT NULL
    )
');

// --- Teste 1: convenção de nomes ------------------------------------
assertTrue(Product::tableName() === 'products', 'tableName() infere "products" a partir de "Product"');

// --- Teste 2: INSERT via save() + RETURNING -------------------------
$product = new Product();
$product->name = 'Notebook';
$product->price = 3500.00;
$product->stock = 10;
$product->save();

assertTrue(isset($product->id) && $product->id > 0, 'save() insere e popula o id gerado (RETURNING)');

// --- Teste 3: find() ---------------------------------------------------
$found = Product::find($product->id);
assertTrue($found !== null, 'find() localiza o registro pelo id');
assertTrue($found->name === 'Notebook', 'find() hidrata corretamente as propriedades');
assertTrue(is_float($found->price), 'find() converte price para float (cast de tipo)');

// --- Teste 4: UPDATE via save() -------------------------------------
$found->price = 3200.00;
$found->save();

$reloaded = Product::find($product->id);
assertTrue($reloaded->price === 3200.00, 'save() em objeto com id existente executa UPDATE');

// --- Teste 5: mais registros para testar QueryBuilder ---------------
(new Product(['name' => 'Mouse', 'price' => 80.0, 'stock' => 50]))->save();
(new Product(['name' => 'Teclado', 'price' => 150.0, 'stock' => 30]))->save();
(new Product(['name' => 'Monitor', 'price' => 1200.0, 'stock' => 5]))->save();

$all = Product::all();
assertTrue(count($all) === 4, 'all() retorna todos os registros inseridos');

// --- Teste 6: QueryBuilder — where + orderBy + limit -----------------
$expensive = Product::where('price', '>', 1000)
    ->orderBy('name', 'ASC')
    ->limit(5)
    ->get();

assertTrue(count($expensive) === 2, 'where(price > 1000) retorna apenas os produtos caros (Notebook, Monitor)');
assertTrue($expensive[0]->name === 'Monitor', 'orderBy(name, ASC) ordena alfabeticamente (Monitor antes de Notebook)');

// --- Teste 7: limit ---------------------------------------------------
$limited = Product::query()->orderBy('id', 'ASC')->limit(2)->get();
assertTrue(count($limited) === 2, 'limit(2) restringe corretamente o número de resultados');

// --- Teste 8: DELETE ---------------------------------------------------
$toDelete = Product::find($product->id);
$toDelete->delete();
assertTrue(Product::find($product->id) === null, 'delete() remove o registro do banco');
assertTrue(count(Product::all()) === 3, 'delete() reduz a contagem total de registros');

// --- Teste 9: proteção contra operador inválido em where() -----------
try {
    Product::where('price', 'DROP TABLE', 1)->get();
    assertTrue(false, 'where() deveria rejeitar operador não permitido');
} catch (InvalidArgumentException) {
    assertTrue(true, 'where() rejeita operadores não permitidos (proteção contra SQL Injection)');
}

// --- Teste 10: proteção contra nome de coluna inválido ----------------
try {
    Product::query()->orderBy('id; DROP TABLE products;--')->get();
    assertTrue(false, 'orderBy() deveria rejeitar identificador inválido');
} catch (InvalidArgumentException) {
    assertTrue(true, 'orderBy() rejeita identificadores de coluna inválidos');
}

echo "\nTodos os testes passaram.\n";

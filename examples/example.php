<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\ORM\ActiveRecord;
use App\ORM\Connection;

/**
 * Exemplo de entidade (Seção 4.4 do TCC).
 * Cada propriedade pública vira uma coluna da tabela "products"
 * (nome inferido automaticamente por convenção).
 */
class Product extends ActiveRecord
{
    public int $id;
    public string $name;
    public float $price;
    public int $stock;
}

// -----------------------------------------------------------------
// Configuração da conexão (fazer uma única vez, no bootstrap da app)
// -----------------------------------------------------------------
Connection::configure(
    dsn: 'pgsql:host=localhost;port=5432;dbname=tcc_orm',
    username: 'postgres',
    password: '1424'
);

// -----------------------------------------------------------------
// Criação e persistência
// -----------------------------------------------------------------
$product = new Product();
$product->name = 'Notebook';
$product->price = 3500.00;
$product->stock = 10;
$product->save(); // INSERT INTO products ... RETURNING id

echo "Produto criado com id {$product->id}" . PHP_EOL;

// -----------------------------------------------------------------
// Busca com filtros (query builder fluente)
// -----------------------------------------------------------------
$products = Product::where('price', '>', 1000)
    ->orderBy('name', 'ASC')
    ->limit(5)
    ->get();

foreach ($products as $p) {
    echo "- {$p->name}: R$ {$p->price}" . PHP_EOL;
}

// -----------------------------------------------------------------
// Busca por chave primária
// -----------------------------------------------------------------
$product = Product::find(1);

if ($product !== null) {
    // ---------------------------------------------------------
    // Atualização
    // ---------------------------------------------------------
    $product->price = 3200.00;
    $product->save(); // UPDATE products SET ... WHERE id = ?

    echo "Produto {$product->id} atualizado para R$ {$product->price}" . PHP_EOL;
}

// -----------------------------------------------------------------
// Listagem completa
// -----------------------------------------------------------------
foreach (Product::all() as $p) {
    echo "Todos: #{$p->id} {$p->name}" . PHP_EOL;
}

// -----------------------------------------------------------------
// Exclusão
// -----------------------------------------------------------------
$product = Product::find(1);

if ($product !== null) {
    $product->delete(); // DELETE FROM products WHERE id = 1
    echo "Produto {$product->id} removido." . PHP_EOL;
}

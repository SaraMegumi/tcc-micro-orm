<?php

declare(strict_types=1);

namespace App\ORM;

use PDO;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Classe base do padrão Active Record (Seção 4.1 e 4.2 do TCC).
 *
 * Cada subclasse concreta representa uma tabela do banco de dados;
 * cada instância representa uma linha dessa tabela. A classe utiliza a
 * Reflection API do PHP para mapear automaticamente as propriedades
 * públicas da subclasse para colunas da tabela, eliminando a
 * necessidade de configuração manual de mapeamento (decisão de design
 * "a" da Seção 4.1).
 *
 * Exemplo de uso (ver Seção 4.4):
 *
 *   class Product extends ActiveRecord
 *   {
 *       public int $id;
 *       public string $name;
 *       public float $price;
 *       public int $stock;
 *   }
 *
 *   $product = new Product();
 *   $product->name = 'Notebook';
 *   $product->price = 3500.00;
 *   $product->stock = 10;
 *   $product->save(); // INSERT INTO products ... RETURNING id
 */
abstract class ActiveRecord
{
    /**
     * Nome da coluna de chave primária. Por convenção, "id".
     */
    protected static string $primaryKey = 'id';

    /**
     * Nome da tabela associada à entidade. Se omitido (null), o nome é
     * inferido automaticamente a partir do nome da classe (Configuração
     * por Convenção — decisão de design "b" da Seção 4.1). Pode ser
     * sobrescrito explicitamente em uma subclasse, por exemplo:
     *
     *   protected static ?string $table = 'tb_produtos';
     */
    protected static ?string $table = null;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    // -----------------------------------------------------------------
    // Convenção sobre Configuração (Convention over Configuration)
    // -----------------------------------------------------------------

    /**
     * Retorna o nome da tabela associada à entidade, convertendo o nome
     * da classe para snake_case no plural (ex.: "Product" -> "products",
     * "OrderItem" -> "order_items", "Category" -> "categories").
     */
    public static function tableName(): string
    {
        if (static::$table !== null) {
            return static::$table;
        }

        $shortName = (new ReflectionClass(static::class))->getShortName();

        return self::pluralize(self::toSnakeCase($shortName));
    }

    private static function toSnakeCase(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /**
     * Pluralização simples de palavras em inglês, suficiente para os
     * nomes de classe usados neste trabalho. Não pretende cobrir todos
     * os casos irregulares do idioma.
     */
    private static function pluralize(string $value): string
    {
        if (preg_match('/[^aeiou]y$/', $value) === 1) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $value) === 1) {
            return $value . 'es';
        }

        return $value . 's';
    }

    // -----------------------------------------------------------------
    // Reflection: mapeamento de propriedades públicas para colunas
    // -----------------------------------------------------------------

    /**
     * Retorna as propriedades públicas e não estáticas da entidade —
     * são elas que correspondem às colunas da tabela. Propriedades
     * estáticas (como $table) são deliberadamente excluídas.
     *
     * @return list<ReflectionProperty>
     */
    private function mappedProperties(): array
    {
        $reflection = new ReflectionClass($this);

        return array_values(array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => !$property->isStatic()
        ));
    }

    /**
     * Converte a entidade em um array associativo [coluna => valor],
     * considerando apenas as propriedades já inicializadas (PHP permite
     * propriedades tipadas sem valor padrão, que ficam "não inicializadas"
     * até receberem um valor).
     *
     * @return array<string, mixed>
     */
    private function toArray(): array
    {
        $data = [];

        foreach ($this->mappedProperties() as $property) {
            if ($property->isInitialized($this)) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    private function primaryKeyValue(): int|string|null
    {
        $pk = static::$primaryKey;

        return $this->$pk ?? null;
    }

    // -----------------------------------------------------------------
    // Operações CRUD (Seção 4.3)
    // -----------------------------------------------------------------

    /**
     * Persiste a entidade: executa INSERT se a chave primária ainda não
     * está definida, ou UPDATE caso contrário.
     */
    public function save(): static
    {
        $data = $this->toArray();
        $pk = static::$primaryKey;
        unset($data[$pk]);

        if ($this->primaryKeyValue() === null) {
            $this->insert($data);
        } else {
            $this->update($data);
        }

        return $this;
    }

    /**
     * Executa o INSERT utilizando a cláusula RETURNING do PostgreSQL
     * para recuperar o ID gerado em uma única instrução (decisão de
     * design "c" da Seção 4.1), evitando uma consulta adicional do tipo
     * "SELECT LASTVAL()".
     */
    private function insert(array $data): void
    {
        $pdo = Connection::getInstance();
        $table = static::tableName();
        $pk = static::$primaryKey;

        $columns = array_keys($data);
        $columnsSql = implode(', ', $columns);
        $placeholdersSql = implode(', ', array_map(
            static fn (string $column): string => ":{$column}",
            $columns
        ));

        $sql = "INSERT INTO {$table} ({$columnsSql}) "
             . "VALUES ({$placeholdersSql}) RETURNING {$pk}";

        $statement = $pdo->prepare($sql);
        $statement->execute($data);

        $this->$pk = (int) $statement->fetchColumn();
    }

    private function update(array $data): void
    {
        $pdo = Connection::getInstance();
        $table = static::tableName();
        $pk = static::$primaryKey;

        $setSql = implode(', ', array_map(
            static fn (string $column): string => "{$column} = :{$column}",
            array_keys($data)
        ));

        $sql = "UPDATE {$table} SET {$setSql} WHERE {$pk} = :__pk";

        $data['__pk'] = $this->primaryKeyValue();

        $statement = $pdo->prepare($sql);
        $statement->execute($data);
    }

    /**
     * Remove o registro correspondente à entidade.
     */
    public function delete(): bool
    {
        $pdo = Connection::getInstance();
        $table = static::tableName();
        $pk = static::$primaryKey;

        $statement = $pdo->prepare("DELETE FROM {$table} WHERE {$pk} = :pk");

        return $statement->execute(['pk' => $this->primaryKeyValue()]);
    }

    /**
     * Recupera um registro pela chave primária. Retorna null se não
     * encontrado.
     */
    public static function find(int|string $id): ?static
    {
        $pdo = Connection::getInstance();
        $table = static::tableName();
        $pk = static::$primaryKey;

        $statement = $pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = :pk LIMIT 1");
        $statement->execute(['pk' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : static::hydrate($row);
    }

    /**
     * Retorna todos os registros da tabela como uma coleção de objetos.
     *
     * @return list<static>
     */
    public static function all(): array
    {
        $pdo = Connection::getInstance();
        $table = static::tableName();

        $statement = $pdo->query("SELECT * FROM {$table}");
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): static => static::hydrate($row), $rows);
    }

    // -----------------------------------------------------------------
    // Query Builder (Seção 4.3)
    // -----------------------------------------------------------------

    /**
     * Retorna um novo QueryBuilder associado a esta entidade, permitindo
     * construir consultas SELECT mais elaboradas de forma fluente.
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    /**
     * Atalho estático que permite iniciar uma consulta fluente
     * diretamente a partir da entidade, ex.: Product::where(...).
     * Delega para QueryBuilder::where().
     */
    public static function where(string $column, string $operator, mixed $value): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    // -----------------------------------------------------------------
    // Hidratação de objetos
    // -----------------------------------------------------------------

    /**
     * Constrói uma instância da entidade a partir de uma linha do banco
     * de dados (array associativo coluna => valor), convertendo cada
     * valor para o tipo declarado na propriedade correspondente.
     */
    public static function hydrate(array $row): static
    {
        /** @var static $instance */
        $instance = new static();

        foreach ($row as $column => $value) {
            if (property_exists($instance, $column)) {
                $instance->$column = self::castValue($instance, $column, $value);
            }
        }

        return $instance;
    }

    /**
     * Converte o valor bruto retornado pelo PDO (sempre string ou null)
     * para o tipo escalar declarado na propriedade, evitando que, por
     * exemplo, um preço numérico chegue como string na aplicação.
     */
    private static function castValue(object $instance, string $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = (new ReflectionProperty($instance, $property))->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => in_array($value, [true, 't', '1', 1], true),
            'string' => (string) $value,
            default => $value,
        };
    }
}

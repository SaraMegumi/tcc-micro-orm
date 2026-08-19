<?php

declare(strict_types=1);

namespace App\ORM;

use InvalidArgumentException;
use PDO;

/**
 * Query builder fluente para construção de consultas SELECT, com
 * suporte a WHERE, ORDER BY e LIMIT (Seção 4.2 e 4.3 do TCC).
 *
 * É instanciado por ActiveRecord::query() ou pelo atalho estático
 * ActiveRecord::where(), e nunca diretamente pelo consumidor da
 * biblioteca.
 *
 * Exemplo de uso (ver Seção 4.4):
 *
 *   $products = Product::where('price', '>', 1000)
 *       ->orderBy('name', 'ASC')
 *       ->limit(5)
 *       ->get();
 */
final class QueryBuilder
{
    /**
     * Operadores de comparação permitidos nas cláusulas WHERE. A
     * restrição a uma lista fixa evita que um operador arbitrário seja
     * injetado diretamente na consulta SQL.
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'ILIKE'];

    /** @var list<array{column: string, operator: string, value: mixed}> */
    private array $wheres = [];

    private ?string $orderColumn = null;
    private string $orderDirection = 'ASC';
    private ?int $limitValue = null;

    /**
     * @param class-string<ActiveRecord> $entityClass
     */
    public function __construct(
        private readonly string $entityClass
    ) {
    }

    /**
     * Adiciona uma condição de filtro. Múltiplas chamadas são
     * combinadas com AND. O valor é sempre passado como parâmetro de
     * prepared statement, nunca concatenado diretamente na string SQL.
     */
    public function where(string $column, string $operator, mixed $value): static
    {
        $this->assertValidOperator($operator);
        $this->assertValidIdentifier($column);

        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /**
     * Define a ordenação dos resultados.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->assertValidIdentifier($column);

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Direção de ordenação inválida: {$direction}");
        }

        $this->orderColumn = $column;
        $this->orderDirection = $direction;

        return $this;
    }

    /**
     * Limita o número de registros retornados.
     */
    public function limit(int $n): static
    {
        if ($n < 0) {
            throw new InvalidArgumentException('O limite não pode ser negativo.');
        }

        $this->limitValue = $n;

        return $this;
    }

    /**
     * Executa a query construída e retorna um array de objetos
     * hidratados da entidade associada.
     *
     * @return list<object>
     */
    public function get(): array
    {
        [$sql, $params] = $this->toSql();

        $pdo = Connection::getInstance();
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        /** @var class-string<ActiveRecord> $entity */
        $entity = $this->entityClass;

        return array_map(
            static fn (array $row): object => $entity::hydrate($row),
            $rows
        );
    }

    /**
     * Retorna apenas o primeiro resultado da consulta, ou null se
     * nenhum registro corresponder aos filtros.
     */
    public function first(): ?object
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    /**
     * Monta a string SQL final e os parâmetros associados. Todas as
     * condições utilizam placeholders nomeados (:w0, :w1, ...),
     * garantindo que os valores nunca sejam concatenados diretamente
     * na query — a principal defesa contra SQL Injection neste
     * componente.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function toSql(): array
    {
        /** @var class-string<ActiveRecord> $entity */
        $entity = $this->entityClass;
        $table = $entity::tableName();

        $sql = "SELECT * FROM {$table}";
        $params = [];

        if ($this->wheres !== []) {
            $clauses = [];

            foreach ($this->wheres as $index => $where) {
                $placeholder = "w{$index}";
                $clauses[] = "{$where['column']} {$where['operator']} :{$placeholder}";
                $params[$placeholder] = $where['value'];
            }

            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($this->orderColumn !== null) {
            $sql .= " ORDER BY {$this->orderColumn} {$this->orderDirection}";
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        return [$sql, $params];
    }

    private function assertValidOperator(string $operator): void
    {
        if (!in_array(strtoupper($operator), self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Operador não permitido: {$operator}");
        }
    }

    /**
     * Validação simples de nomes de coluna, permitindo apenas
     * letras, números e underscore. Como nomes de coluna não podem ser
     * passados como parâmetros de prepared statement, essa validação é
     * a defesa usada para esses casos específicos (WHERE / ORDER BY).
     */
    private function assertValidIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Nome de coluna inválido: {$identifier}");
        }
    }
}

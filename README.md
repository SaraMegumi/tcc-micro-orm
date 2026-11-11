# AsORM — Micro ORM em PHP 8.3

Implementação do AsORM, descrito no Capítulo 4 do TCC ("Implementação de um
Micro ORM em PHP 8.3 com PostgreSQL"), seguindo o padrão **Active Record**,
sem dependências externas.

## Estrutura do projeto

```
├── composer.json           Metadados e autoload PSR-4 (App\ORM -> src/)
├── src/
│   ├── Connection.php      Singleton de conexão PDO com PostgreSQL
│   ├── ActiveRecord.php    Classe base do padrão Active Record
│   └── QueryBuilder.php    Query builder fluente (WHERE, ORDER BY, LIMIT)
├── examples/
│   └── example.php         Exemplo de uso completo (igual à Seção 4.4 do TCC)
├── sql/
│   └── schema.sql          Script de criação das tabelas de exemplo
├── tests/
│   ├── manual_test.php     Teste funcional de ponta a ponta (SQLite)
│   ├── test_postgres.php   Teste funcional com PostgreSQL
│   └── casos_de_teste.md   Casos de teste e resultados
├── benchmark/
│   ├── benchmark.php       Testes de desempenho (PDO, AsORM, Eloquent, Doctrine)
│   └── results/            Resultados (.csv e .md) dos modos padrão e transação única
└── vendor/
    └── autoload.php        Autoloader simples (não requer Composer instalado)
```

## Como rodar (com PostgreSQL)

1. **Criar o banco e as tabelas:**

   ```bash
   createdb tcc_orm
   psql -d tcc_orm -f sql/schema.sql
   ```

2. **Ajustar a conexão** em `examples/example.php` (host, porta, usuário,
   senha) de acordo com o seu ambiente local.

3. **Rodar o exemplo:**

   ```bash
   php examples/example.php
   ```

Se preferir usar o Composer em vez do autoloader manual incluído em
`vendor/autoload.php`, rode `composer dump-autoload` na raiz do projeto.

## Como rodar os testes

**Com SQLite (sem precisar de servidor PostgreSQL):** valida a lógica do
AsORM usando SQLite em memória, que também suporta a cláusula `RETURNING`
a partir da versão 3.35.

```bash
php tests/manual_test.php
```

**Com PostgreSQL:** executa os testes contra o banco real (precisa do banco
criado, como na seção anterior).

```bash
php tests/test_postgres.php
```

Os testes cobrem: convenção de nomes de tabela, INSERT com `RETURNING`,
`find()`, conversão automática de tipos na hidratação, `UPDATE` via `save()`,
`all()`, o QueryBuilder completo (`where` + `orderBy` + `limit`), `delete()`
e as proteções contra SQL Injection (validação de operadores e de nomes de
coluna). A lista dos casos está em `tests/casos_de_teste.md`.

## Testes de desempenho

Os testes de desempenho (Seção 5.3 do TCC) estão na pasta `benchmark/`;
veja `benchmark/README.md` para rodá-los.

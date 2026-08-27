# Micro ORM em PHP 8.3 — TCC

Implementação do micro ORM descrito no Capítulo 4 do TCC ("Implementação
de um Micro ORM em PHP 8.3 com PostgreSQL"), seguindo o padrão **Active
Record**, sem dependências externas.

## Estrutura do projeto

```
micro-orm/
├── composer.json          Metadados e autoload PSR-4 (App\ORM -> src/)
├── src/
│   ├── Connection.php      Singleton de conexão PDO com PostgreSQL
│   ├── ActiveRecord.php    Classe base do padrão Active Record
│   └── QueryBuilder.php    Query builder fluente (WHERE, ORDER BY, LIMIT)
├── examples/
│   └── example.php         Exemplo de uso completo (igual à Seção 4.4 do TCC)
├── sql/
│   └── schema.sql          Script de criação das tabelas de exemplo
├── tests/
│   └── manual_test.php     Teste funcional de ponta a ponta (usa SQLite)
└── vendor/
    └── autoload.php        Autoloader simples (não requer Composer instalado)
```

## Como rodar (com PostgreSQL de verdade)

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

Se preferir usar o Composer "de verdade" em vez do autoloader manual
incluído em `vendor/autoload.php`, é só rodar `composer dump-autoload`
na raiz do projeto — o `composer.json` já está configurado corretamente.

## Como rodar os testes (sem precisar de PostgreSQL)

O arquivo `tests/manual_test.php` valida toda a lógica do ORM usando
SQLite em memória (que também suporta a cláusula `RETURNING` a partir da
versão 3.35), então dá pra rodar sem precisar configurar um servidor
PostgreSQL:

```bash
php tests/manual_test.php
```

Ele testa: convenção de nomes de tabela, INSERT com `RETURNING`, `find()`,
cast automático de tipos na hidratação, `UPDATE` via `save()`, `all()`,
o QueryBuilder completo (`where` + `orderBy` + `limit`), `delete()`, e as
proteções contra SQL Injection (validação de operadores e de nomes de
coluna).

> **Importante:** esse teste com SQLite serve só para validar a lógica
> da implementação rapidamente. Os testes de desempenho reais do TCC
> (Seção 5.3), com os volumes de 1.000/10.000/100.000 registros, devem
> ser feitos contra PostgreSQL de verdade, como especificado na
> metodologia (Capítulo 3).




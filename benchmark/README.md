# Benchmark: micro ORM x Eloquent x Doctrine

Mede tempo (ms) e memória (MB) de 5 operações (inserção, leitura por ID,
leitura de todos, atualização, remoção) em 3 volumes (100, 1.000 e 10.000
registros), com 10 repetições + 1 de aquecimento, usando PostgreSQL.
O PDO puro entra como linha de base (limite inferior de custo).

## Como rodar

```bash
# 1. dentro da pasta do projeto (onde está o composer.json principal)
PGPASSWORD=secret psql -h localhost -U postgres -d tcc_orm -f sql/schema.sql

# 2. instalar Eloquent e Doctrine (só na pasta benchmark)
cd benchmark && composer install && cd ..

# 3. rodar os DOIS modos (leva um bom tempo; feche outros programas pesados)
php benchmark/benchmark.php            # modo padrão (uso idiomático)
TX=1 php benchmark/benchmark.php       # modo transação única por bloco
```

Saída em `benchmark/results/`: `results_padrao.md/.csv` e `results_tx.md/.csv`.
Teste rápido: `VOLUMES=100 REPS=2 php benchmark/benchmark.php`

Variáveis opcionais: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
VOLUMES, REPS, TOOLS (ex.: `TOOLS=pdo,micro`), TX (0 ou 1).

## Os dois modos
- **Padrão:** cada ferramenta é usada do jeito idiomático. O Doctrine agrupa
  as escritas num único `flush()` (uma transação); PDO, micro ORM e Eloquent
  confirmam cada comando separadamente.
- **TX=1:** todas as ferramentas gravam dentro de uma transação única por
  bloco. Isola o custo do próprio ORM, sem a vantagem do agrupamento.

## Observações metodológicas
- Cada repetição reinicia a tabela (`TRUNCATE ... RESTART IDENTITY`).
- O script confere no banco que cada operação realmente aconteceu.
- Carregar as entidades antes de atualizar/remover NÃO entra no tempo.

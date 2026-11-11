# Benchmark: AsORM x Eloquent x Doctrine

Mede tempo (ms) e memória (MB) de 5 operações (inserção, leitura por ID,
leitura de todos, atualização, remoção) em 3 volumes (100, 1.000 e 10.000
registros), usando PostgreSQL. O PDO puro entra como linha de base (limite
inferior de custo).

Os resultados publicados em `results/` (Tabelas 3 a 6 do TCC) foram obtidos
com **5 repetições + 1 de aquecimento** descartada. O benchmark também foi
repetido em um segundo computador, com 10 repetições (Seção 5.4 do TCC). O
valor padrão do script é `REPS=10`; para reproduzir os resultados principais,
use `REPS=5`.

## Como rodar

```bash
# 1. dentro da pasta do projeto (onde está o composer.json principal)
PGPASSWORD=secret psql -h localhost -U postgres -d tcc_orm -f sql/schema.sql

# 2. instalar Eloquent e Doctrine (só na pasta benchmark)
cd benchmark && composer install && cd ..

# 3. rodar os DOIS modos (leva um bom tempo; feche outros programas pesados)
REPS=5 php benchmark/benchmark.php           # modo padrão (uso idiomático)
REPS=5 TX=1 php benchmark/benchmark.php      # modo transação única por bloco
```

Saída em `benchmark/results/`: `results_padrao.md/.csv` e `results_tx.md/.csv`.
Teste rápido: `VOLUMES=100 REPS=2 php benchmark/benchmark.php`

Variáveis opcionais: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
VOLUMES, REPS, TOOLS (ex.: `TOOLS=pdo,micro`), TX (0 ou 1).

## Os dois modos
- **Padrão:** cada ferramenta é usada do jeito idiomático. O Doctrine agrupa
  as escritas num único `flush()` (uma transação); PDO, AsORM e Eloquent
  confirmam cada comando separadamente.
- **TX=1:** todas as ferramentas gravam dentro de uma transação única por
  bloco. Isola o custo do próprio ORM, sem a vantagem do agrupamento.

## Observações metodológicas
- Cada repetição reinicia a tabela (`TRUNCATE ... RESTART IDENTITY`).
- O script confere no banco que cada operação realmente aconteceu.
- Carregar as entidades antes de atualizar/remover NÃO entra no tempo.
- Os tempos absolutos variam entre computadores e entre execuções; compare as
  ferramentas dentro de uma mesma tabela (ver Seção 5.4 do TCC).

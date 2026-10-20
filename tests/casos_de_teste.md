| ID | Grupo | Caso de teste | Resultado esperado | Resultado |
|---|---|---|---|---|
| CT01 | Convenção | Nome da tabela inferido de "Product" | products | Passou |
| CT02 | Convenção | Nome em CamelCase vira snake_case ("OrderItem") | order_items | Passou |
| CT03 | Convenção | Plural com consoante + "y" ("Category") | categories | Passou |
| CT04 | Convenção | Plural com terminação "x" ("Box") | boxes | Passou |
| CT05 | Convenção | Nome da tabela sobrescrito em $table | tb_legado | Passou |
| CT06 | CRUD | save() em objeto novo executa INSERT e preenche o id (RETURNING) | id > 0 | Passou |
| CT07 | CRUD | Dois INSERTs seguidos geram ids distintos e crescentes | id2 > id1 | Passou |
| CT08 | CRUD | find() recupera o registro e converte os tipos (int, float, string) | tipos corretos | Passou |
| CT09 | CRUD | find() com id inexistente | null | Passou |
| CT10 | CRUD | save() em objeto com id executa UPDATE | preço atualizado | Passou |
| CT11 | CRUD | UPDATE não cria registro novo | contagem inalterada (1) | Passou |
| CT12 | CRUD | delete() remove o registro | find() retorna null | Passou |
| CT13 | CRUD | all() com a tabela vazia | array vazio | Passou |
| CT14 | CRUD | all() devolve todos os registros como objetos da entidade | 4 objetos Product | Passou |
| CT15 | CRUD | Construtor com array preenche propriedades e ignora chaves desconhecidas | name preenchido, sem erro | Passou |
| CT16 | Query Builder | where("price", ">", 1000) | 2 registros (Notebook, Monitor) | Passou |
| CT17 | Query Builder | Dois where() encadeados combinam com AND | 1 registro (Monitor) | Passou |
| CT18 | Query Builder | orderBy("name", "ASC") | Monitor, Mouse, Notebook, Teclado | Passou |
| CT19 | Query Builder | orderBy("price", "DESC") | Notebook primeiro | Passou |
| CT20 | Query Builder | limit(2) | 2 registros | Passou |
| CT21 | Query Builder | first() com resultado | objeto Product | Passou |
| CT22 | Query Builder | first() sem resultado | null | Passou |
| CT23 | Query Builder | where com LIKE | 1 registro (Notebook) | Passou |
| CT24 | Segurança | Operador inválido em where() ("DROP TABLE") | InvalidArgumentException | Passou |
| CT25 | Segurança | Nome de coluna inválido em where() | InvalidArgumentException | Passou |
| CT26 | Segurança | Identificador inválido em orderBy() | InvalidArgumentException | Passou |
| CT27 | Segurança | Direção inválida em orderBy() | InvalidArgumentException | Passou |
| CT28 | Segurança | limit() negativo | InvalidArgumentException | Passou |
| CT29 | Segurança | SQL Injection no VALOR do where() é tratado como texto | nenhum resultado e tabela intacta | Passou |
| CT30 | Segurança | SQL Injection no valor de find() ("1 OR 1=1") | valor rejeitado (PDOException) e tabela intacta | Passou |
| CT31 | Integridade | INSERT violando NOT NULL (sem "name") | PDOException | Passou |
| CT32 | Integridade | INSERT violando UNIQUE (e-mail repetido) | PDOException | Passou |
| CT33 | Integridade | Registro continua consistente após erro de UNIQUE | 1 usuário no banco | Passou |
| CT34 | Hidratação | hydrate() converte "t"/"f" do PostgreSQL para bool e mantém null | true, false e null | Passou |
| CT35 | Conexão | Falha de conexão (porta inválida) vira RuntimeException | RuntimeException | Passou |
| CT36 | Conexão | getInstance() devolve sempre a mesma conexão (Singleton) | mesmo objeto PDO | Passou |

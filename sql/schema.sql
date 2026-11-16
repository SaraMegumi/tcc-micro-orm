-- Esquema de exemplo utilizado nos testes e nas demonstrações do
-- AsORM (Seção 4.4 e 3.8 do TCC).

DROP TABLE IF EXISTS products;

CREATE TABLE products (
    id     SERIAL PRIMARY KEY,
    name   VARCHAR(255) NOT NULL,
    price  NUMERIC(10, 2) NOT NULL,
    stock  INTEGER NOT NULL DEFAULT 0
);

DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id    SERIAL PRIMARY KEY,
    name  VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
);

-- ===========================================================================
-- King Decants — migrations/002_conta_cliente.sql
-- ---------------------------------------------------------------------------
-- Endereços salvos do cliente (Minha conta -> Endereços e preenchimento do
-- checkout). Os pontos de fidelidade são calculados a partir dos pedidos pagos,
-- sem tabela própria.
--
-- OBSERVAÇÃO: desde a versão com inc/migrate.php o site aplica isto SOZINHO
-- na primeira visita. Importar manualmente é opcional (alternativa).
--
-- COMO APLICAR: phpMyAdmin -> seu banco -> Importar -> este arquivo. Uma vez só,
-- ANTES de enviar os arquivos novos. (Requer a 001 já aplicada.)
-- ===========================================================================

CREATE TABLE IF NOT EXISTS user_addresses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    label       VARCHAR(40)  NOT NULL DEFAULT 'Casa',     -- Casa, Trabalho...
    recipient   VARCHAR(150) NOT NULL,
    cep         CHAR(8)      NOT NULL,
    street      VARCHAR(180) NOT NULL,
    number      VARCHAR(20)  NOT NULL,
    complement  VARCHAR(120) NULL,
    district    VARCHAR(120) NOT NULL,
    city        VARCHAR(120) NOT NULL,
    state       CHAR(2)      NOT NULL,
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_addresses_user (user_id, is_default),
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

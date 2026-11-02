-- ===========================================================================
-- King Decants — schema.sql
-- ---------------------------------------------------------------------------
-- Cria o banco, todas as tabelas e os dados iniciais (4 produtos seed + 1 admin).
-- Compatível com MySQL 5.7+/8.0 e MariaDB 10.3+.
--
-- Como importar:
--   mysql -u root -p < schema.sql
--   (ou phpMyAdmin -> Importar -> selecione este arquivo)
--
-- Se sua hospedagem NÃO permite CREATE DATABASE (comum em cPanel), crie o
-- banco pelo painel, remova/ comente as 2 linhas abaixo e importe dentro dele.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS deckstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE deckstore;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users: clientes. Compras como convidado também geram um registro aqui
-- (is_guest = 1, password_hash NULL) para alimentar o mini-CRM. Se o convidado
-- se registrar depois com o mesmo e-mail, o registro é "promovido" a conta.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NULL,
    phone           VARCHAR(20)  NULL,
    cpf             CHAR(11)     NULL,
    is_guest        TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_cpf (cpf),
    KEY idx_users_phone (phone),
    KEY idx_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku             VARCHAR(40)   NOT NULL,
    name            VARCHAR(150)  NOT NULL,
    slug            VARCHAR(160)  NOT NULL,
    tagline         VARCHAR(160)  NULL,
    short_description VARCHAR(255) NOT NULL,
    description     TEXT          NULL,
    collection      VARCHAR(30)   NOT NULL DEFAULT 'assinatura',  -- floral|amadeirado|fresco|noturno|assinatura
    price           DECIMAL(10,2) NOT NULL,
    stock           INT           NOT NULL DEFAULT 0,
    image_url       VARCHAR(500)  NOT NULL,
    volume_ml       SMALLINT UNSIGNED NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order      INT           NOT NULL DEFAULT 0,
    sold_count      INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_sku (sku),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_collection (collection, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- cart_items: carrinho persistente de usuários logados.
-- (Convidados usam $_SESSION; ao logar, a sessão é mesclada aqui.)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    quantity    INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orders: dados do comprador e endereço são copiados (snapshot) no pedido.
-- status: pending | paid | shipped | delivered | cancelled
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NULL,
    is_guest_order      TINYINT(1)   NOT NULL DEFAULT 0,
    access_token        CHAR(40)     NOT NULL,              -- permite ao convidado ver o próprio pedido
    status              ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    payment_method      ENUM('pix','card') NOT NULL DEFAULT 'card',
    customer_name       VARCHAR(150) NOT NULL,
    customer_email      VARCHAR(190) NOT NULL,
    customer_phone      VARCHAR(20)  NOT NULL,
    customer_cpf        CHAR(11)     NOT NULL,
    cep                 CHAR(8)      NOT NULL,
    street              VARCHAR(180) NOT NULL,
    number              VARCHAR(20)  NOT NULL,
    complement          VARCHAR(120) NULL,
    district            VARCHAR(120) NOT NULL,
    city                VARCHAR(120) NOT NULL,
    state               CHAR(2)      NOT NULL,
    subtotal            DECIMAL(10,2) NOT NULL,
    shipping            DECIMAL(10,2) NOT NULL DEFAULT 0,
    total               DECIMAL(10,2) NOT NULL,
    mp_preference_id    VARCHAR(80)  NULL,
    mp_init_point       VARCHAR(500) NULL,
    tracking_code       VARCHAR(60)  NULL,
    stock_applied       TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 = estoque já baixado (evita baixa dupla)
    paid_at             DATETIME     NULL,
    shipped_at          DATETIME     NULL,
    delivered_at        DATETIME     NULL,
    cancelled_at        DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_token (access_token),
    KEY idx_orders_status_created (status, created_at),
    KEY idx_orders_user (user_id),
    KEY idx_orders_email (customer_email),
    KEY idx_orders_paid_at (paid_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_items: snapshot do produto no momento da compra.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NULL,
    sku          VARCHAR(40)  NOT NULL,
    name         VARCHAR(150) NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    line_total   DECIMAL(10,2) NOT NULL,
    KEY idx_items_order (order_id),
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- payments: um pedido pode ter várias tentativas de pagamento no Mercado Pago.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            INT UNSIGNED NOT NULL,
    provider            VARCHAR(30)  NOT NULL DEFAULT 'mercadopago',
    mp_payment_id       VARCHAR(40)  NULL,
    method              VARCHAR(40)  NULL,                -- pix, credit_card, account_money...
    status              VARCHAR(30)  NOT NULL,            -- status bruto do MP (approved, pending, rejected...)
    status_detail       VARCHAR(80)  NULL,
    amount              DECIMAL(10,2) NOT NULL,
    pix_qr_code         TEXT         NULL,                -- "copia e cola"
    pix_qr_code_base64  MEDIUMTEXT   NULL,                -- imagem PNG em base64
    pix_ticket_url      VARCHAR(500) NULL,
    pix_expires_at      DATETIME     NULL,
    raw_response        MEDIUMTEXT   NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_mp (mp_payment_id),
    KEY idx_payments_order (order_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_status_history: linha do tempo do pedido (sistema, webhook ou admin).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    from_status  VARCHAR(20)  NULL,
    to_status    VARCHAR(20)  NOT NULL,
    source       VARCHAR(20)  NOT NULL,         -- system | webhook | callback | admin
    admin_id     INT UNSIGNED NULL,
    note         VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_history_order (order_id),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- admin_users: login separado do painel /admin.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_notes: notas internas do admin por pedido.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    note        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_notes_order (order_id),
    CONSTRAINT fk_order_notes_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_notes_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- customer_notes: "últimos contatos" do mini-CRM (ligação, WhatsApp, e-mail...).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    channel     VARCHAR(20)  NOT NULL DEFAULT 'nota',   -- nota | telefone | whatsapp | email
    note        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_notes_user (user_id),
    CONSTRAINT fk_customer_notes_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_notes_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- admin_logs: auditoria — quem fez o quê e quando.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id     INT UNSIGNED NULL,
    action       VARCHAR(60)  NOT NULL,
    entity_type  VARCHAR(30)  NULL,
    entity_id    INT UNSIGNED NULL,
    details      VARCHAR(500) NULL,
    ip           VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_logs_entity (entity_type, entity_id),
    KEY idx_admin_logs_created (created_at),
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_attempts: rate limit simples (loja e admin).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope        VARCHAR(10)  NOT NULL,          -- 'store' | 'admin'
    ip           VARCHAR(45)  NOT NULL,
    email        VARCHAR(190) NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_lookup (scope, ip, attempted_at),
    KEY idx_attempts_email (scope, email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- webhook_logs: toda notificação recebida do Mercado Pago (auditoria/debug).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic        VARCHAR(40)  NULL,
    resource_id  VARCHAR(60)  NULL,
    signature_ok TINYINT(1)   NULL,
    processed    TINYINT(1)   NOT NULL DEFAULT 0,
    message      VARCHAR(255) NULL,
    payload      MEDIUMTEXT   NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_webhook_resource (resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- SEED: 4 produtos (Decants)
-- Imagens via placeholder (https://via.placeholder.com/). Se o serviço estiver
-- fora do ar, o front-end cai automaticamente em assets/img/product-placeholder.svg.
-- ===========================================================================
INSERT INTO products (sku, name, slug, tagline, short_description, description, collection, price, stock, image_url, volume_ml, sort_order, sold_count) VALUES
('KD-BAS-005',
 'Decants Básicos',
 'decants-basicos',
 'Coleção Essencial · 5 ml',
 'A porta de entrada para fragrâncias icônicas. Leve, prático e acessível.',
 'Decant de 5 ml para conhecer uma fragrância antes de investir no frasco completo. Ideal para a bolsa ou para viagens: cerca de 60 borrifadas.',
 'fresco', 49.90, 50,
 'https://via.placeholder.com/600x750/0b0b0b/d4af37?text=Decants+Basicos',
 5, 1, 128),

('KD-INT-010',
 'Decants Intermediários',
 'decants-intermediarios',
 'Coleção Ateliê · 10 ml',
 'Frasco de vidro borossilicato com atomizador de alumínio anodizado.',
 'Decant de 10 ml envasado em vidro borossilicato de alta resistência, atomizador de alumínio anodizado com névoa fina e vedação em polietileno de grau cosmético. Cerca de 120 borrifadas.',
 'floral', 89.90, 35,
 'https://via.placeholder.com/600x750/0b0b0b/d4af37?text=Decants+Intermediarios',
 10, 2, 96),

('KD-AVC-010',
 'Decants Avançados',
 'decants-avancados',
 'Coleção Noir · 10 ml',
 'Acabamento premium: vidro fumê, tampa metálica dourada e estojo aveludado.',
 'Decant de 10 ml com acabamento premium: vidro fumê lapidado, tampa metálica com banho dourado, gravação a laser do lote e estojo aveludado com fecho magnético. Perfeito para presentear.',
 'noturno', 149.90, 20,
 'https://via.placeholder.com/600x750/0b0b0b/d4af37?text=Decants+Avancados',
 10, 3, 74),

('KD-SIG-015',
 'Decants',
 'decants-assinatura-king',
 'Assinatura King · 15 ml',
 'Edição numerada com recursos exclusivos: curadoria personalizada e certificado.',
 'Nossa experiência máxima: decant de 15 ml em edição numerada, curadoria olfativa personalizada com um especialista, certificado de autenticidade do lote, refil com 20% de desconto e embalagem de colecionador em laca preta com detalhes em ouro.',
 'assinatura', 249.90, 10,
 'https://via.placeholder.com/600x750/0b0b0b/d4af37?text=Assinatura+King',
 15, 4, 52);

-- ===========================================================================
-- SEED: administrador inicial
--   E-mail: admin@kingdecants.local
--   Senha : TroqueEstaSenha!2026
-- !!! TROQUE A SENHA IMEDIATAMENTE após o primeiro acesso !!!
-- Para criar outro admin via terminal:  php tools/create_admin.php
-- ===========================================================================
INSERT INTO admin_users (name, email, password_hash) VALUES
('Administrador', 'admin@kingdecants.local', '$2y$10$gXqKTWaq6LrCe3.rYXmuXuyfz0c8pMG1X3Qla3U482wXXkROJROi.');

-- ===========================================================================
-- Página de produto: concentração, pirâmide olfativa, galeria, avaliações e
-- favoritos (mesmo conteúdo de migrations/001_pagina_produto.sql).
-- ===========================================================================
ALTER TABLE products
    ADD COLUMN concentration      VARCHAR(60)  NULL AFTER tagline,
    ADD COLUMN notes_top          VARCHAR(255) NULL AFTER description,
    ADD COLUMN notes_heart        VARCHAR(255) NULL AFTER notes_top,
    ADD COLUMN notes_base         VARCHAR(255) NULL AFTER notes_heart,
    ADD COLUMN notes_top_image    VARCHAR(500) NULL AFTER notes_base,
    ADD COLUMN notes_heart_image  VARCHAR(500) NULL AFTER notes_top_image,
    ADD COLUMN notes_base_image   VARCHAR(500) NULL AFTER notes_heart_image;

CREATE TABLE IF NOT EXISTS product_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    image_url   VARCHAR(500) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_images_product (product_id, sort_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,          -- 1 a 5
    comment     VARCHAR(1000) NULL,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,       -- admin pode ocultar
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_product (user_id, product_id),
    KEY idx_reviews_product (product_id, is_visible),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlists (
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_wishlist_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conteúdo de EXEMPLO para os 4 produtos iniciais (edite no admin -> Produtos).
-- Só preenche se o campo ainda estiver vazio.
UPDATE products SET concentration = COALESCE(concentration, 'Eau de Toilette'),
    notes_top = COALESCE(notes_top, 'Bergamota, Limão siciliano'),
    notes_heart = COALESCE(notes_heart, 'Lavanda, Neroli'),
    notes_base = COALESCE(notes_base, 'Almíscar branco, Cedro')
  WHERE sku = 'KD-BAS-005';
UPDATE products SET concentration = COALESCE(concentration, 'Eau de Parfum'),
    notes_top = COALESCE(notes_top, 'Pera, Mandarina'),
    notes_heart = COALESCE(notes_heart, 'Rosa, Jasmim, Peônia'),
    notes_base = COALESCE(notes_base, 'Baunilha, Almíscar')
  WHERE sku = 'KD-INT-010';
UPDATE products SET concentration = COALESCE(concentration, 'Eau de Parfum'),
    notes_top = COALESCE(notes_top, 'Bergamota, Pimenta-preta'),
    notes_heart = COALESCE(notes_heart, 'Rosa, Patchouli, Açafrão'),
    notes_base = COALESCE(notes_base, 'Oud, Âmbar, Baunilha')
  WHERE sku = 'KD-AVC-010';
UPDATE products SET concentration = COALESCE(concentration, 'Extrait de Parfum'),
    notes_top = COALESCE(notes_top, 'Cardamomo, Toranja'),
    notes_heart = COALESCE(notes_heart, 'Íris, Couro'),
    notes_base = COALESCE(notes_base, 'Âmbar cinzento, Sândalo, Fava-tonka')
  WHERE sku = 'KD-SIG-015';

-- ===========================================================================
-- Endereços salvos do cliente (mesmo conteúdo de migrations/002_conta_cliente.sql)
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

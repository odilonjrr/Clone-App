-- ===========================================================================
-- King Decants — migrations/001_pagina_produto.sql
-- ---------------------------------------------------------------------------
-- Atualiza um banco JÁ EXISTENTE para a nova página de produto:
--   - concentração (Eau de Parfum…), pirâmide olfativa (topo/coração/fundo)
--     com imagem opcional por nível
--   - galeria de fotos (product_images)
--   - avaliações de compradores verificados (product_reviews)
--   - favoritos (wishlists)
--
-- COMO APLICAR (Hostinger): phpMyAdmin -> selecione o banco -> Importar ->
-- escolha este arquivo -> Importar. Rode UMA ÚNICA VEZ, ANTES de enviar os
-- arquivos novos do site. Instalações novas não precisam (o schema.sql já inclui).
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

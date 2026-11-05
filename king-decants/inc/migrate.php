<?php
/**
 * King Decants — inc/migrate.php
 * ---------------------------------------------------------------------------
 * Atualização AUTOMÁTICA do banco de dados.
 *
 * Ao enviar uma versão nova do site, as tabelas/colunas que faltarem são
 * criadas sozinhas na primeira visita — não é preciso importar os arquivos de
 * migrations/ no phpMyAdmin (eles continuam lá como documentação/alternativa).
 *
 * Seguro para rodar várias vezes (idempotente): cada passo verifica antes se a
 * coluna/tabela já existe. Funciona em MySQL 5.7+/8 e MariaDB.
 *
 * Desempenho: depois de aplicar, grava storage/schema-version.txt e as
 * próximas requisições só leem esse arquivo (nenhuma consulta extra).
 * Requisito: o usuário do banco precisa de permissão ALTER/CREATE (padrão na
 * Hostinger e no cPanel para o usuário do próprio banco).
 * ---------------------------------------------------------------------------
 */

const KD_SCHEMA_VERSION = 7;

function kd_schema_marker(): string
{
    return dirname(__DIR__) . '/storage/schema-version.txt';
}

/** Chamada pelo bootstrap. Não faz nada se o banco já estiver na versão atual. */
function kd_ensure_schema(): void
{
    $marker = kd_schema_marker();
    if (is_file($marker) && (int) trim((string) @file_get_contents($marker)) >= KD_SCHEMA_VERSION) {
        return;
    }

    $pdo = db();
    $hasColumn = function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    };

    // ---------------- Versão 1: página de produto (migrations/001_pagina_produto.sql)
    $newColumns = [
        'concentration'     => "VARCHAR(60) NULL AFTER tagline",
        'notes_top'         => "VARCHAR(255) NULL AFTER description",
        'notes_heart'       => "VARCHAR(255) NULL AFTER notes_top",
        'notes_base'        => "VARCHAR(255) NULL AFTER notes_heart",
        'notes_top_image'   => "VARCHAR(500) NULL AFTER notes_base",
        'notes_heart_image' => "VARCHAR(500) NULL AFTER notes_top_image",
        'notes_base_image'  => "VARCHAR(500) NULL AFTER notes_heart_image",
    ];
    $addedConcentration = false;
    foreach ($newColumns as $col => $def) {
        if (!$hasColumn('products', $col)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN {$col} {$def}");
            $addedConcentration = $addedConcentration || $col === 'concentration';
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_product_images_product (product_id, sort_order),
        CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        comment VARCHAR(1000) NULL,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_review_user_product (user_id, product_id),
        KEY idx_reviews_product (product_id, is_visible),
        CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlists (
        user_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, product_id),
        CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Conteúdo de exemplo da pirâmide para os 4 produtos iniciais — só na primeira vez.
    if ($addedConcentration) {
        $seed = [
            'KD-BAS-005' => ['Eau de Toilette', 'Bergamota, Limão siciliano', 'Lavanda, Neroli', 'Almíscar branco, Cedro'],
            'KD-INT-010' => ['Eau de Parfum', 'Pera, Mandarina', 'Rosa, Jasmim, Peônia', 'Baunilha, Almíscar'],
            'KD-AVC-010' => ['Eau de Parfum', 'Bergamota, Pimenta-preta', 'Rosa, Patchouli, Açafrão', 'Oud, Âmbar, Baunilha'],
            'KD-SIG-015' => ['Extrait de Parfum', 'Cardamomo, Toranja', 'Íris, Couro', 'Âmbar cinzento, Sândalo, Fava-tonka'],
        ];
        $upd = $pdo->prepare('UPDATE products SET concentration = COALESCE(concentration, ?), notes_top = COALESCE(notes_top, ?),
                              notes_heart = COALESCE(notes_heart, ?), notes_base = COALESCE(notes_base, ?) WHERE sku = ?');
        foreach ($seed as $sku => $v) {
            $upd->execute([...$v, $sku]);
        }
    }

    // ---------------- Versão 2: endereços do cliente (migrations/002_conta_cliente.sql)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        label VARCHAR(40) NOT NULL DEFAULT 'Casa',
        recipient VARCHAR(150) NOT NULL,
        cep CHAR(8) NOT NULL,
        street VARCHAR(180) NOT NULL,
        number VARCHAR(20) NOT NULL,
        complement VARCHAR(120) NULL,
        district VARCHAR(120) NOT NULL,
        city VARCHAR(120) NOT NULL,
        state CHAR(2) NOT NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_addresses_user (user_id, is_default),
        CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------- Versão 3: configurações da loja (banner da home etc.)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------- Versão 4: InfinitePay (provedor no pedido, recibo e slug do pagamento)
    if (!$hasColumn('orders', 'payment_provider')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(20) NOT NULL DEFAULT 'mercadopago' AFTER payment_method");
    }
    $pdo->exec("ALTER TABLE orders MODIFY payment_method ENUM('pix','card','infinitepay') NOT NULL DEFAULT 'card'");
    if (!$hasColumn('payments', 'receipt_url')) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN receipt_url VARCHAR(500) NULL AFTER pix_expires_at");
    }
    if (!$hasColumn('payments', 'external_slug')) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN external_slug VARCHAR(80) NULL AFTER mp_payment_id");
    }

    // ---------------- Versão 5: variações de tamanho (5 ml, 10 ml...)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        label VARCHAR(40) NOT NULL,
        volume_ml SMALLINT UNSIGNED NULL,
        sku VARCHAR(40) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_variant_sku (sku),
        KEY idx_variants_product (product_id, is_active, sort_order),
        CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$hasColumn('cart_items', 'variant_id')) {
        // A sacola passa a identificar o item por produto + tamanho.
        $pdo->exec('ALTER TABLE cart_items ADD COLUMN variant_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER product_id,
                    DROP PRIMARY KEY, ADD PRIMARY KEY (user_id, product_id, variant_id)');
    }
    if (!$hasColumn('order_items', 'variant_id')) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_id INT UNSIGNED NULL AFTER product_id,
                    ADD COLUMN variant_label VARCHAR(40) NULL AFTER name');
    }

    // ---------------- Versão 6: cupons de desconto + desconto no pedido
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(30) NOT NULL,
        type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
        value DECIMAL(10,2) NOT NULL,
        min_subtotal DECIMAL(10,2) NULL,
        max_uses INT UNSIGNED NULL,
        used_count INT UNSIGNED NOT NULL DEFAULT 0,
        starts_at DATETIME NULL,
        expires_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coupons_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$hasColumn('orders', 'discount')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER subtotal,
                    ADD COLUMN coupon_code VARCHAR(30) NULL AFTER discount');
    }

    // ---------------- Versão 7: SuperFrete (serviço de entrega escolhido + etiqueta)
    if (!$hasColumn('orders', 'shipping_service_id')) {
        $pdo->exec('ALTER TABLE orders
            ADD COLUMN shipping_service_id INT NULL AFTER shipping,
            ADD COLUMN shipping_service VARCHAR(60) NULL AFTER shipping_service_id,
            ADD COLUMN shipping_package VARCHAR(255) NULL AFTER shipping_service,
            ADD COLUMN sf_order_id VARCHAR(40) NULL,
            ADD COLUMN sf_status VARCHAR(20) NULL,
            ADD COLUMN sf_price DECIMAL(10,2) NULL,
            ADD COLUMN sf_label_url VARCHAR(500) NULL,
            ADD INDEX idx_orders_sf (sf_order_id)');
    }

    @file_put_contents($marker, (string) KD_SCHEMA_VERSION, LOCK_EX);
    app_log('migrate', 'Banco atualizado para a versão ' . KD_SCHEMA_VERSION);
}

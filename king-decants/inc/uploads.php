<?php
/**
 * King Decants — inc/uploads.php
 * ---------------------------------------------------------------------------
 * Upload seguro de fotos de produto (usado por admin/product_edit.php).
 *
 * Camadas de proteção:
 *  1. Limite de tamanho (PRODUCT_IMAGE_MAX_BYTES) e verificação de erro do PHP.
 *  2. Tipo detectado pelo CONTEÚDO (finfo + getimagesize), nunca pelo nome ou
 *     pelo Content-Type enviado pelo navegador. Só JPEG, PNG e WebP.
 *  3. Nome do arquivo gerado aleatoriamente; extensão definida pelo tipo real.
 *  4. Quando a extensão GD está disponível (padrão na Hostinger), a imagem é
 *     RECODIFICADA: remove metadados (EXIF/GPS), descarta qualquer conteúdo
 *     embutido e reduz para no máximo PRODUCT_IMAGE_MAX_WIDTH px de largura.
 *  5. assets/uploads/.htaccess só serve imagens e nunca executa scripts.
 * ---------------------------------------------------------------------------
 */

const PRODUCT_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB (confira também upload_max_filesize no PHP)
const PRODUCT_IMAGE_MAX_WIDTH = 1200;
const PRODUCT_UPLOAD_DIR = 'assets/uploads/products';

/**
 * Processa $_FILES['image'] e devolve o caminho relativo salvo
 * (ex.: "assets/uploads/products/9f2c…e1.webp"), ou null se nenhum arquivo foi enviado.
 * @throws RuntimeException com mensagem amigável em caso de arquivo inválido.
 */
function product_image_upload(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('A imagem é maior que o limite permitido pelo servidor.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Falha no envio da imagem. Tente novamente.');
    }
    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        throw new RuntimeException('A imagem deve ter no máximo ' . (PRODUCT_IMAGE_MAX_BYTES / 1024 / 1024) . ' MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!isset($allowed[$mime]) || !$info || ($info['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Formato não suportado. Envie JPG, PNG ou WebP.');
    }
    [$width, $height] = $info;
    if ($width < 200 || $height < 200 || $width > 8000 || $height > 8000) {
        throw new RuntimeException('Use uma imagem entre 200 e 8000 pixels de lado.');
    }

    $dir = dirname(__DIR__) . '/' . PRODUCT_UPLOAD_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta de imagens. Verifique as permissões de assets/uploads.');
    }

    $base = bin2hex(random_bytes(16));

    // Recodificação com GD (recomendado). Sem GD, apenas move o arquivo validado.
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$src) {
            throw new RuntimeException('Imagem corrompida ou inválida.');
        }
        $newW = min($width, PRODUCT_IMAGE_MAX_WIDTH);
        $newH = (int) round($height * $newW / $width);
        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        if (function_exists('imagewebp')) {
            $name = $base . '.webp';
            $ok = imagewebp($dst, $dir . '/' . $name, 85);
        } else {
            $name = $base . '.jpg';
            $ok = imagejpeg($dst, $dir . '/' . $name, 88);
        }
        imagedestroy($dst);
        if (!$ok) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }
    } else {
        $name = $base . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }
    }
    @chmod($dir . '/' . $name, 0644);
    return PRODUCT_UPLOAD_DIR . '/' . $name;
}

/** Remove do disco uma imagem antiga, SOMENTE se ela estiver na pasta de uploads. */
function product_image_delete(?string $relativePath): void
{
    if (!$relativePath || !preg_match('#^' . preg_quote(PRODUCT_UPLOAD_DIR, '#') . '/[a-f0-9]{32}\.(webp|jpg|png)$#', $relativePath)) {
        return; // URLs externas ou caminhos inesperados nunca são apagados
    }
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

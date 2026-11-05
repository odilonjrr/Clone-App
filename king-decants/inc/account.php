<?php
/**
 * King Decants — inc/account.php
 * ---------------------------------------------------------------------------
 * Dados da área "Minha conta":
 *   - Programa de fidelidade: pontos e nível calculados dos pedidos PAGOS
 *     (pago/enviado/entregue) feitos com a conta. 1 ponto por R$ 1,00.
 *     Pedidos cancelados não contam. Não há tabela própria: é sempre o
 *     reflexo exato do histórico de compras.
 *   - Endereços salvos (user_addresses), usados no checkout.
 *
 * Para mudar as regras (pontos por real, faixas dos níveis), edite
 * LOYALTY_POINTS_PER_REAL e loyalty_tiers().
 * ---------------------------------------------------------------------------
 */

const LOYALTY_POINTS_PER_REAL = 1;

/** Níveis: nome => pontos mínimos (em ordem crescente). */
function loyalty_tiers(): array
{
    return [
        'Bronze'  => 0,
        'Prata'   => 500,
        'Ouro'    => 1500,
        'Platina' => 5000,
    ];
}

/**
 * Resumo de fidelidade do cliente.
 * @return array ['points','tier','next_tier','next_at','progress' (0-100),'paid_orders','total_spent']
 */
function loyalty_summary(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS qty, COALESCE(SUM(total), 0) AS spent FROM orders
          WHERE user_id = ? AND is_guest_order = 0 AND status IN ('paid','shipped','delivered')"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $points = (int) floor((float) $row['spent'] * LOYALTY_POINTS_PER_REAL);

    $tier = 'Bronze';
    $nextTier = null;
    $nextAt = null;
    $tierStart = 0;
    foreach (loyalty_tiers() as $name => $min) {
        if ($points >= $min) {
            $tier = $name;
            $tierStart = $min;
        } elseif ($nextTier === null) {
            $nextTier = $name;
            $nextAt = $min;
        }
    }
    $progress = $nextAt ? (int) round(($points - $tierStart) / max(1, $nextAt - $tierStart) * 100) : 100;

    return [
        'points'      => $points,
        'tier'        => $tier,
        'next_tier'   => $nextTier,
        'next_at'     => $nextAt,
        'progress'    => max(0, min(100, $progress)),
        'paid_orders' => (int) $row['qty'],
        'total_spent' => (float) $row['spent'],
    ];
}

function user_addresses(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function user_default_address(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Valida e normaliza um endereço vindo de formulário.
 * @return array [array $data, array $errors]
 */
function address_from_input(array $in): array
{
    $d = [
        'label'      => clean_string($in['label'] ?? '', 40) ?: 'Casa',
        'recipient'  => clean_string($in['recipient'] ?? '', 150),
        'cep'        => only_digits($in['cep'] ?? ''),
        'street'     => clean_string($in['street'] ?? '', 180),
        'number'     => clean_string($in['number'] ?? '', 20),
        'complement' => clean_string($in['complement'] ?? '', 120),
        'district'   => clean_string($in['district'] ?? '', 120),
        'city'       => clean_string($in['city'] ?? '', 120),
        'state'      => strtoupper(clean_string($in['state'] ?? '', 2)),
    ];
    $e = [];
    if (mb_strlen($d['recipient']) < 3) {
        $e['recipient'] = 'Informe quem vai receber.';
    }
    if (!validate_cep($d['cep'])) {
        $e['cep'] = 'CEP inválido.';
    }
    foreach (['street' => 'Logradouro', 'number' => 'Número', 'district' => 'Bairro', 'city' => 'Cidade'] as $k => $label) {
        if ($d[$k] === '') {
            $e[$k] = "{$label} é obrigatório.";
        }
    }
    if (!validate_uf($d['state'])) {
        $e['state'] = 'Selecione o estado.';
    }
    return [$d, $e];
}

/** Salva endereço (novo ou existente do próprio usuário). O primeiro vira padrão. */
function address_save(int $userId, array $d, ?int $addressId = null, bool $makeDefault = false): int
{
    $pdo = db();
    $count = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?');
    $count->execute([$userId]);
    $isFirst = (int) $count->fetchColumn() === 0;

    // Só edita endereço que pertence a este usuário; caso contrário, cria um novo.
    if ($addressId) {
        $own = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE id = ? AND user_id = ?');
        $own->execute([$addressId, $userId]);
        if (!(int) $own->fetchColumn()) {
            $addressId = null;
        }
    }
    if ($addressId) {
        $pdo->prepare('UPDATE user_addresses SET label=?, recipient=?, cep=?, street=?, number=?, complement=?, district=?, city=?, state=?
                       WHERE id = ? AND user_id = ?')
            ->execute([$d['label'], $d['recipient'], $d['cep'], $d['street'], $d['number'], $d['complement'] ?: null,
                       $d['district'], $d['city'], $d['state'], $addressId, $userId]);
    } else {
        $pdo->prepare('INSERT INTO user_addresses (user_id, label, recipient, cep, street, number, complement, district, city, state)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $d['label'], $d['recipient'], $d['cep'], $d['street'], $d['number'], $d['complement'] ?: null,
                       $d['district'], $d['city'], $d['state']]);
        $addressId = (int) $pdo->lastInsertId();
    }
    if ($makeDefault || $isFirst) {
        address_set_default($userId, $addressId);
    }
    return $addressId;
}

function address_set_default(int $userId, int $addressId): void
{
    db()->prepare('UPDATE user_addresses SET is_default = (id = ?) WHERE user_id = ?')->execute([$addressId, $userId]);
}

/** Exclui; se era o padrão, o mais recente restante passa a ser o padrão. */
function address_delete(int $userId, int $addressId): void
{
    db()->prepare('DELETE FROM user_addresses WHERE id = ? AND user_id = ?')->execute([$addressId, $userId]);
    $hasDefault = db()->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_default = 1');
    $hasDefault->execute([$userId]);
    if ((int) $hasDefault->fetchColumn() === 0 && ($first = user_default_address($userId))) {
        address_set_default($userId, (int) $first['id']);
    }
}

/** Iniciais para o avatar ("Ana Maria Souza" -> "AS"). */
function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}

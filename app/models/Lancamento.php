<?php
class Lancamento
{
    private PDO $db;
    private Conta $conta;

    public function __construct()
    {
        $this->db = Database::conexao();
        $this->conta = new Conta();
    }

    /** Efeito do lançamento no saldo da conta: receita soma, despesa subtrai. */
    public static function efeito(string $tipo, float $valor): float
    {
        return $tipo === 'R' ? $valor : -$valor;
    }

    /**
     * Registrar receita/despesa: INSERT + UPDATE do saldo em uma única transação
     * (passos 9 a 12 do diagrama de sequência).
     */
    public function criar(array $d): int
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare(
                'INSERT INTO lancamento (conta_id, categoria_id, tipo, valor, `data`, descricao)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $d['conta_id'], $d['categoria_id'], $d['tipo'],
                dinheiro($d['valor']), $d['data'], $d['descricao'] !== '' ? $d['descricao'] : null,
            ]);
            $id = (int) $this->db->lastInsertId();

            $this->conta->ajustarSaldo($d['conta_id'], self::efeito($d['tipo'], $d['valor']));

            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Editar lançamento: desfaz o efeito antigo e aplica o novo. */
    public function atualizar(int $id, array $antigo, array $d): void
    {
        $this->db->beginTransaction();
        try {
            $this->conta->ajustarSaldo((int) $antigo['conta_id'], -self::efeito($antigo['tipo'], (float) $antigo['valor']));

            $st = $this->db->prepare(
                'UPDATE lancamento
                    SET conta_id = ?, categoria_id = ?, tipo = ?, valor = ?, `data` = ?, descricao = ?
                  WHERE id = ?'
            );
            $st->execute([
                $d['conta_id'], $d['categoria_id'], $d['tipo'],
                dinheiro($d['valor']), $d['data'], $d['descricao'] !== '' ? $d['descricao'] : null, $id,
            ]);

            $this->conta->ajustarSaldo($d['conta_id'], self::efeito($d['tipo'], $d['valor']));
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Excluir lançamento: remove e estorna o saldo. */
    public function excluir(array $antigo): void
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('DELETE FROM lancamento WHERE id = ?');
            $st->execute([$antigo['id']]);
            $this->conta->ajustarSaldo((int) $antigo['conta_id'], -self::efeito($antigo['tipo'], (float) $antigo['valor']));
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Busca um lançamento garantindo que pertence ao usuário (via conta). */
    public function buscar(int $id, int $usuarioId): ?array
    {
        $st = $this->db->prepare(
            'SELECT l.* FROM lancamento l
               JOIN conta c ON c.id = l.conta_id
              WHERE l.id = ? AND c.usuario_id = ?'
        );
        $st->execute([$id, $usuarioId]);
        return $st->fetch() ?: null;
    }

    /**
     * Lista lançamentos com filtros opcionais:
     * inicio, fim (AAAA-MM-DD), tipo (R/D), conta_id, categoria_id, limite.
     */
    public function listar(int $usuarioId, array $f = []): array
    {
        [$where, $params] = $this->filtros($usuarioId, $f);
        $limite = isset($f['limite']) ? max(1, (int) $f['limite']) : 1000;

        $st = $this->db->prepare(
            "SELECT l.id, l.conta_id, l.categoria_id, l.tipo, l.valor, l.`data`, l.descricao,
                    c.nome AS conta_nome, cat.nome AS categoria_nome
               FROM lancamento l
               JOIN conta c       ON c.id = l.conta_id
               JOIN categoria cat ON cat.id = l.categoria_id
              WHERE $where
              ORDER BY l.`data` DESC, l.id DESC
              LIMIT $limite"
        );
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Total de receitas e despesas no período. */
    public function totais(int $usuarioId, array $f): array
    {
        [$where, $params] = $this->filtros($usuarioId, $f);
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN l.tipo = 'R' THEN l.valor END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN l.tipo = 'D' THEN l.valor END), 0) AS despesas,
                    COUNT(*) AS quantidade
               FROM lancamento l
               JOIN conta c ON c.id = l.conta_id
              WHERE $where"
        );
        $st->execute($params);
        $r = $st->fetch();
        return [
            'receitas'   => (float) $r['receitas'],
            'despesas'   => (float) $r['despesas'],
            'saldo'      => round((float) $r['receitas'] - (float) $r['despesas'], 2),
            'quantidade' => (int) $r['quantidade'],
        ];
    }

    /** Relatório por categoria. */
    public function totaisPorCategoria(int $usuarioId, array $f): array
    {
        [$where, $params] = $this->filtros($usuarioId, $f);
        $st = $this->db->prepare(
            "SELECT cat.id, cat.nome, cat.tipo, SUM(l.valor) AS total, COUNT(*) AS quantidade
               FROM lancamento l
               JOIN conta c       ON c.id = l.conta_id
               JOIN categoria cat ON cat.id = l.categoria_id
              WHERE $where
              GROUP BY cat.id, cat.nome, cat.tipo
              ORDER BY cat.tipo DESC, total DESC"
        );
        $st->execute($params);
        return array_map(function ($r) {
            $r['total'] = (float) $r['total'];
            $r['quantidade'] = (int) $r['quantidade'];
            return $r;
        }, $st->fetchAll());
    }

    /** Receitas x despesas agrupadas por mês. */
    public function evolucaoMensal(int $usuarioId, string $inicio, string $fim): array
    {
        $st = $this->db->prepare(
            "SELECT DATE_FORMAT(l.`data`, '%Y-%m') AS mes,
                    COALESCE(SUM(CASE WHEN l.tipo = 'R' THEN l.valor END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN l.tipo = 'D' THEN l.valor END), 0) AS despesas
               FROM lancamento l
               JOIN conta c ON c.id = l.conta_id
              WHERE c.usuario_id = ? AND l.`data` BETWEEN ? AND ?
              GROUP BY DATE_FORMAT(l.`data`, '%Y-%m')
              ORDER BY mes"
        );
        $st->execute([$usuarioId, $inicio, $fim]);
        return $st->fetchAll();
    }

    private function filtros(int $usuarioId, array $f): array
    {
        $where = ['c.usuario_id = ?'];
        $params = [$usuarioId];

        if (!empty($f['inicio']))       { $where[] = 'l.`data` >= ?';     $params[] = $f['inicio']; }
        if (!empty($f['fim']))          { $where[] = 'l.`data` <= ?';     $params[] = $f['fim']; }
        if (!empty($f['tipo']))         { $where[] = 'l.tipo = ?';        $params[] = $f['tipo']; }
        if (!empty($f['conta_id']))     { $where[] = 'l.conta_id = ?';    $params[] = (int) $f['conta_id']; }
        if (!empty($f['categoria_id'])) { $where[] = 'l.categoria_id = ?'; $params[] = (int) $f['categoria_id']; }

        return [implode(' AND ', $where), $params];
    }
}

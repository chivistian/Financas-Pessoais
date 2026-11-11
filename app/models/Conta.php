<?php
class Conta
{
    public const TIPOS = ['carteira', 'corrente', 'poupanca'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexao();
    }

    public function listar(int $usuarioId): array
    {
        $st = $this->db->prepare(
            'SELECT c.id, c.nome, c.tipo, c.saldo_inicial, c.saldo_atual,
                    (SELECT COUNT(*) FROM lancamento l WHERE l.conta_id = c.id) AS qtd_lancamentos
               FROM conta c
              WHERE c.usuario_id = ?
              ORDER BY c.nome'
        );
        $st->execute([$usuarioId]);
        return $st->fetchAll();
    }

    public function buscar(int $id, int $usuarioId): ?array
    {
        $st = $this->db->prepare(
            'SELECT id, nome, tipo, saldo_inicial, saldo_atual FROM conta WHERE id = ? AND usuario_id = ?'
        );
        $st->execute([$id, $usuarioId]);
        return $st->fetch() ?: null;
    }

    public function criar(int $usuarioId, string $nome, string $tipo, float $saldoInicial): int
    {
        $st = $this->db->prepare(
            'INSERT INTO conta (usuario_id, nome, tipo, saldo_inicial, saldo_atual) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$usuarioId, $nome, $tipo, dinheiro($saldoInicial), dinheiro($saldoInicial)]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza a conta. Se o saldo inicial mudar, o saldo atual é ajustado pela diferença
     * (o MySQL avalia as atribuições do SET da esquerda para a direita).
     */
    public function atualizar(int $id, int $usuarioId, string $nome, string $tipo, float $saldoInicial): void
    {
        $st = $this->db->prepare(
            'UPDATE conta
                SET nome = ?, tipo = ?,
                    saldo_atual = saldo_atual + (? - saldo_inicial),
                    saldo_inicial = ?
              WHERE id = ? AND usuario_id = ?'
        );
        $st->execute([$nome, $tipo, dinheiro($saldoInicial), dinheiro($saldoInicial), $id, $usuarioId]);
    }

    public function contarLancamentos(int $id): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM lancamento WHERE conta_id = ?');
        $st->execute([$id]);
        return (int) $st->fetchColumn();
    }

    public function excluir(int $id, int $usuarioId): void
    {
        $st = $this->db->prepare('DELETE FROM conta WHERE id = ? AND usuario_id = ?');
        $st->execute([$id, $usuarioId]);
    }

    /** Caso de uso "Atualizar saldo da conta" (incluído por registrar/editar/excluir lançamento). */
    public function ajustarSaldo(int $id, float $delta): void
    {
        $st = $this->db->prepare('UPDATE conta SET saldo_atual = saldo_atual + ? WHERE id = ?');
        $st->execute([dinheiro($delta), $id]);
    }
}

<?php
class Categoria
{
    /** Categorias criadas automaticamente para novos usuários. */
    public const PADRAO = [
        'R' => ['Salário', 'Freelance', 'Rendimentos', 'Outras receitas'],
        'D' => ['Alimentação', 'Moradia', 'Transporte', 'Saúde', 'Lazer', 'Educação', 'Outras despesas'],
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexao();
    }

    public function listar(int $usuarioId): array
    {
        $st = $this->db->prepare(
            'SELECT cat.id, cat.nome, cat.tipo,
                    (SELECT COUNT(*) FROM lancamento l WHERE l.categoria_id = cat.id) AS qtd_lancamentos
               FROM categoria cat
              WHERE cat.usuario_id = ?
              ORDER BY cat.tipo DESC, cat.nome'
        );
        $st->execute([$usuarioId]);
        return $st->fetchAll();
    }

    public function buscar(int $id, int $usuarioId): ?array
    {
        $st = $this->db->prepare('SELECT id, nome, tipo FROM categoria WHERE id = ? AND usuario_id = ?');
        $st->execute([$id, $usuarioId]);
        return $st->fetch() ?: null;
    }

    public function existeNome(int $usuarioId, string $nome, string $tipo, int $ignorarId = 0): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM categoria WHERE usuario_id = ? AND nome = ? AND tipo = ? AND id <> ?'
        );
        $st->execute([$usuarioId, $nome, $tipo, $ignorarId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function criar(int $usuarioId, string $nome, string $tipo): int
    {
        $st = $this->db->prepare('INSERT INTO categoria (usuario_id, nome, tipo) VALUES (?, ?, ?)');
        $st->execute([$usuarioId, $nome, $tipo]);
        return (int) $this->db->lastInsertId();
    }

    public function criarPadrao(int $usuarioId): void
    {
        foreach (self::PADRAO as $tipo => $nomes) {
            foreach ($nomes as $nome) {
                $this->criar($usuarioId, $nome, $tipo);
            }
        }
    }

    public function atualizar(int $id, int $usuarioId, string $nome, string $tipo): void
    {
        $st = $this->db->prepare('UPDATE categoria SET nome = ?, tipo = ? WHERE id = ? AND usuario_id = ?');
        $st->execute([$nome, $tipo, $id, $usuarioId]);
    }

    public function contarLancamentos(int $id): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM lancamento WHERE categoria_id = ?');
        $st->execute([$id]);
        return (int) $st->fetchColumn();
    }

    public function excluir(int $id, int $usuarioId): void
    {
        $st = $this->db->prepare('DELETE FROM categoria WHERE id = ? AND usuario_id = ?');
        $st->execute([$id, $usuarioId]);
    }
}

<?php
class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexao();
    }

    public function buscarPorEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuario WHERE email = ?');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, nome, email, criado_em FROM usuario WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function criar(string $nome, string $email, string $senha): int
    {
        $st = $this->db->prepare('INSERT INTO usuario (nome, email, senha_hash) VALUES (?, ?, ?)');
        $st->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        return (int) $this->db->lastInsertId();
    }
}

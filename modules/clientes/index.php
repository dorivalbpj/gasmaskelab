<?php
// modules/clientes/index.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$stmt = $pdo->query("SELECT id, nome, email, telefone, cpf_cnpj FROM clientes ORDER BY nome ASC");
$clientes = $stmt->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="flex-between mb-20">
    <div>
        <h2 class="page-title">Clientes</h2>
    </div>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Empresa / Nome</th>
                <th>Contato (E-mail)</th>
                <th>Telefone</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($cliente['nome'] ?? '') ?></strong><br>
                    <small><?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?></small>
                </td>
                <td><?= htmlspecialchars($cliente['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['telefone'] ?? '') ?></td>
                <td>
                    <a href="visualizar.php?id=<?= $cliente['id'] ?>" title="Visualizar"><i class="ph-eye"></i>visualizar</a>
                    <a href="editar.php?id=<?= $cliente['id'] ?>" title="Editar"><i class="ph-pencil"></i>editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
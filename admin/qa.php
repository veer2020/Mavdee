<?php

/**
 * admin/qa.php
 * Admin panel for managing product Q&A.
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_auth.php';
requireAdminLogin();

$pageTitle = 'Product Q&A';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $qaId   = (int)($_POST['qa_id'] ?? 0);
    $answer = trim(strip_tags($_POST['answer'] ?? ''));

    if ($qaId > 0) {
        try {
            if ($action === 'answer' && $answer !== '') {
                $adminId = (int)($_SESSION['admin_id'] ?? 0);
                db()->prepare(
                    "UPDATE product_qa SET answer = ?, answered_by = ?, answered_at = NOW() WHERE id = ?"
                )->execute([$answer, $adminId ?: null, $qaId]);
                logAdminActivity('answer_qa', "Q&A #$qaId answered");
                setFlash('success', 'Answer posted.');
            } elseif ($action === 'toggle_public') {
                db()->prepare("UPDATE product_qa SET is_public = 1 - is_public WHERE id = ?")->execute([$qaId]);
                setFlash('success', 'Visibility toggled.');
            } elseif ($action === 'delete') {
                db()->prepare("DELETE FROM product_qa WHERE id = ?")->execute([$qaId]);
                logAdminActivity('delete_qa', "Q&A #$qaId deleted");
                setFlash('success', 'Question deleted.');
            }
        } catch (Throwable $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
    header('Location: qa.php');
    exit;
}

$filter = trim($_GET['filter'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $where = [];
    $params = [];
    if ($filter === 'unanswered') {
        $where[] = "qa.answer IS NULL";
    } elseif ($filter === 'answered') {
        $where[] = "qa.answer IS NOT NULL";
    }

    $whereSQL  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = db()->prepare("SELECT COUNT(*) FROM product_qa qa $whereSQL");
    $countStmt->execute($params);
    $total     = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $rowsStmt = db()->prepare(
        "SELECT qa.*, p.name AS product_name, c.name AS customer_name
         FROM product_qa qa
         LEFT JOIN products p ON p.id = qa.product_id
         LEFT JOIN customers c ON c.id = qa.customer_id
         $whereSQL
         ORDER BY qa.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $rowsStmt->execute($params);
    $rows = $rowsStmt->fetchAll();
} catch (Throwable $e) {
    $rows = [];
    $total = $totalPages = 0;
    setFlash('warning', 'Q&A table may not exist yet. Run database_updates.sql first.');
}

require __DIR__ . '/_layout_head.php';
?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <a href="qa.php" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All (<?= $total ?>)</a>
    <a href="qa.php?filter=unanswered" class="btn btn-sm <?= $filter === 'unanswered' ? 'btn-primary' : 'btn-outline-secondary' ?>">Unanswered</a>
    <a href="qa.php?filter=answered" class="btn btn-sm <?= $filter === 'answered'  ? 'btn-primary' : 'btn-outline-secondary' ?>">Answered</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Public</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No questions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $q): ?>
                        <tr>
                            <td>#<?= (int)$q['id'] ?></td>
                            <td><small><?= h($q['product_name'] ?? '—') ?></small></td>
                            <td><small><?= h($q['customer_name'] ?? 'Guest') ?></small></td>
                            <td style="max-width:200px;"><?= h($q['question']) ?></td>
                            <td style="max-width:220px;">
                                <?php if ($q['answer']): ?>
                                    <span class="text-success small"><?= h(mb_strimwidth($q['answer'], 0, 80, '…')) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">No answer yet</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $q['is_public'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                            <td>
                                <button class="btn btn-xs btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#answerModal"
                                    data-id="<?= (int)$q['id'] ?>"
                                    data-question="<?= h($q['question']) ?>"
                                    data-answer="<?= h($q['answer'] ?? '') ?>">
                                    <?= $q['answer'] ? 'Edit' : 'Answer' ?>
                                </button>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_public">
                                    <input type="hidden" name="qa_id" value="<?= (int)$q['id'] ?>">
                                    <button class="btn btn-xs btn-outline-secondary" type="submit">Toggle</button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this question?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="qa_id" value="<?= (int)$q['id'] ?>">
                                    <button class="btn btn-xs btn-outline-danger" type="submit">Del</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?filter=<?= urlencode($filter) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Answer Modal -->
<div class="modal fade" id="answerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="qa.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="qa_id" id="answerQaId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Answer Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="answerQuestion"></p>
                    <label class="form-label fw-semibold">Your Answer</label>
                    <textarea name="answer" id="answerText" class="form-control" rows="4" required maxlength="1000" placeholder="Type your answer here…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Answer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    document.getElementById('answerModal')?.addEventListener('show.bs.modal', function(e) {
        var btn = e.relatedTarget;
        document.getElementById('answerQaId').value = btn.dataset.id || '';
        document.getElementById('answerQuestion').textContent = 'Q: ' + (btn.dataset.question || '');
        document.getElementById('answerText').value = btn.dataset.answer || '';
    });
</script>

<?php require __DIR__ . '/_layout_foot.php'; ?>
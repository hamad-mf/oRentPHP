<?php
require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    db()->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
}
flash('success', 'Lead deleted.');
redirect('index.php');

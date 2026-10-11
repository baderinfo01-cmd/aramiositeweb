<?php
require_once 'includes/init.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$urls = ['index.php' => 1.0, 'courses.php' => 0.9, 'about.php' => 0.7, 'contact.php' => 0.6, 'verify_certificate.php' => 0.5];
foreach ($pdo->query("SELECT id, created_at FROM courses WHERE status='published'") as $c) $urls['course.php?id=' . $c['id']] = 0.8;
foreach ($urls as $u => $p) echo '<url><loc>' . e(base_url($u)) . '</loc><priority>' . $p . "</priority></url>\n";
echo '</urlset>';

<?php
require_once '../includes/init.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $pdo->prepare("INSERT INTO certificate_exams (trainee_id, course_id, trainer_id, exam_date, notes) VALUES (?, ?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_POST['course_id'], $_POST['trainer_id'], $_POST['exam_date'], $_POST['notes']]);
    header("Location: my_courses.php?course_id=".$_POST['course_id']."&msg=success");
}
exit();
?>
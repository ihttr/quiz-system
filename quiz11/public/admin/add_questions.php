<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

$quizId = $_GET['quiz_id'] ?? null;
if (!$quizId) { header("Location: quizzes.php"); exit; }

// fetch quiz
$stmt = $pdo->prepare("
    SELECT q.*, u.username AS created_by_name
    FROM quizzes q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) { header("Location: quizzes.php"); exit; }

// existing questions + options (fetch options in one query)
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$existingQuestions = $stmt->fetchAll();

if ($existingQuestions) {
    $ids = array_column($existingQuestions, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id IN ($in) ORDER BY id");
    $stmt->execute($ids);
    $allOptions = $stmt->fetchAll();
    // map options into questions
    $optionsByQ = [];
    foreach ($allOptions as $o) $optionsByQ[$o['question_id']][] = $o;
    foreach ($existingQuestions as &$q) $q['options'] = $optionsByQ[$q['id']] ?? [];
    unset($q);
}

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // add questions
    if (isset($_POST['add_questions'])) {
        try {
            $pdo->beginTransaction();
            foreach ($_POST['questions'] ?? [] as $qd) {
                $text = trim($qd['text'] ?? '');
                if ($text === '') continue;
                $type = $qd['type'] ?? 'multiple_choice';
                $points = max(1, (int)($qd['points'] ?? 1));
                $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, points) VALUES (?, ?, ?, ?)");
                $stmt->execute([$quizId, $text, $type, $points]);
                $qid = $pdo->lastInsertId();

                if ($type === 'multiple_choice' && !empty($qd['options']) && is_array($qd['options'])) {
                    foreach ($qd['options'] as $idx => $opt) {
                        $opt = trim($opt);
                        if ($opt === '') continue;
                        $isCorrect = (isset($qd['correct_option']) && (string)$qd['correct_option'] === (string)$idx) ? 1 : 0;
                        $stmt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$qid, $opt, $isCorrect]);
                    }
                } elseif ($type === 'true_false') {
                    $correct = ($qd['correct_tf'] ?? 'true') === 'true' ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?), (?, ?, ?)");
                    // Insert True then False
                    $stmt->execute([$qid, 'True', $correct, $qid, 'False', $correct ? 0 : 1]);
                }
                // Note: Explanation type removed per request (no options created)
            }
            $pdo->commit();
            header("Location: edit_quiz.php?id={$quizId}&success=questions_added");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error adding questions: " . $e->getMessage();
        }
    }

    // delete question
    if (isset($_POST['delete_question'])) {
        $questionId = (int)($_POST['question_id'] ?? 0);
        if ($questionId) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM options WHERE question_id = ?");
                $stmt->execute([$questionId]);
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$questionId]);
                $pdo->commit();
                header("Location: add_questions.php?quiz_id={$quizId}&success=question_deleted");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error deleting question: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Questions</title>
<link rel="stylesheet" href="../assets/css/styles.css">
<style>
/* kept minimal styles from your original file */
.form-section{background:var(--card-bg);padding:1.5rem;border-radius:8px;margin-bottom:2rem}
.question-block{background:var(--bg-color);padding:1rem;border-radius:8px;margin-bottom:1rem;border-left:4px solid var(--primary-color)}
.option-row{display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem}
.remove-btn{background:var(--danger-color);color:#fff;border:0;padding:.4rem;border-radius:4px}
.add-btn{background:var(--success-color);color:#fff;border:0;padding:.4rem;border-radius:4px}
.points-input{width:80px}
</style>
</head>
<body>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <div class="nav-brand">Quiz App - Admin</div>
            <ul class="nav-links">
                <li><a href="../quiz.php">← Back to Quiz</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="quizzes.php">Quizzes</a></li>
                <li><a href="edit_quiz.php?id=<?php echo $quizId; ?>">Edit Quiz</a></li>
                <li><a href="add_questions.php?quiz_id=<?php echo $quizId; ?>" class="active">Manage Questions</a></li>
                <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="container">
    <h1>Manage Questions: <?php echo htmlspecialchars($quiz['title']); ?></h1>

    <?php if (!empty($error)): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
    <?php if (!empty($_GET['success'])): ?><div class="alert success"><?php echo ($_GET['success']==='questions_added')? 'New questions added!' : 'Question deleted!'; ?></div><?php endif; ?>

    <?php if ($existingQuestions): ?>
    <div class="form-section">
        <h2>Existing Questions (<?php echo count($existingQuestions); ?>)</h2>
        <?php foreach ($existingQuestions as $q): ?>
            <div class="question-block">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h4><?php echo htmlspecialchars($q['question_text']); ?></h4>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap">
                            <span>Type: <?php echo ucfirst(str_replace('_',' ',$q['question_type'])); ?></span>
                            <span>Points: <?php echo (int)$q['points']; ?></span>
                            <span>Options: <?php echo count($q['options'] ?? []); ?></span>
                            <?php
                                $correctOption = null;
                                foreach ($q['options'] ?? [] as $opt) if ($opt['is_correct']) { $correctOption = $opt; break; }
                                if ($correctOption) echo '<span style="color:var(--success-color);font-weight:600">Correct: '.htmlspecialchars($correctOption['option_text']).'</span>';
                            ?>
                        </div>
                    </div>
                    <form method="POST" onsubmit="return confirm('Delete this question?');">
                        <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                        <button type="submit" name="delete_question" class="remove-btn">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="questionsForm">
        <div class="form-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h2>Add New Questions</h2>
                <button type="button" id="addQuestion" class="add-btn">Add Question Form</button>
            </div>
            <div id="questionsContainer"></div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end">
            <button type="submit" name="add_questions" class="add-btn">Add Questions to Quiz</button>
            <a href="edit_quiz.php?id=<?php echo $quizId; ?>" class="btn">Back to Edit Quiz</a>
        </div>
    </form>
</div>


<script>
let qCount = 0;
document.getElementById('addQuestion').addEventListener('click', addQuestion);

function addQuestion(){
    qCount++;
    const id = qCount;
    const html = `
    <div class="question-block" id="q-${id}">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h3>New Question ${id}</h3>
        <button type="button" class="remove-btn" onclick="removeQuestion(${id})">Remove</button>
      </div>

      <label>Question Text *</label>
      <textarea name="questions[${id}][text]" rows="3" required></textarea>

      <div style="display:flex;gap:1rem;margin-top:.5rem;align-items:center">
        <div>
          <label>Type</label>
          <select name="questions[${id}][type]" onchange="toggleType(${id}, this.value)">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="true_false">True/False</option>
          </select>
        </div>
        <div>
          <label>Points</label>
          <input type="number" name="questions[${id}][points]" value="1" min="1" class="points-input">
        </div>
      </div>

      <div id="mc-${id}" style="margin-top:.75rem">
        <h4>Options</h4>
        <div id="opts-${id}">
          <div class="option-row">
            <input type="text" name="questions[${id}][options][]" placeholder="Option text" required>
            <input type="radio" name="questions[${id}][correct_option]" value="0" required>
            <button type="button" class="remove-btn" onclick="removeOption(this)">Remove</button>
          </div>
          <div class="option-row">
            <input type="text" name="questions[${id}][options][]" placeholder="Option text" required>
            <input type="radio" name="questions[${id}][correct_option]" value="1" required>
            <button type="button" class="remove-btn" onclick="removeOption(this)">Remove</button>
          </div>
        </div>
        <button type="button" class="add-btn" onclick="addOption(${id})">Add Option</button>
      </div>

      <div id="tf-${id}" style="display:none;margin-top:.75rem">
        <h4>True / False</h4>
        <label><input type="radio" name="questions[${id}][correct_tf]" value="true" checked required> True</label>
        <label><input type="radio" name="questions[${id}][correct_tf]" value="false" required> False</label>
      </div>
    </div>`;
    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
    // Ensure new MC inputs are enabled (they are visible by default)
    toggleType(id, 'multiple_choice');
}

function toggleType(id, val){
    const mc = document.getElementById(`mc-${id}`);
    const tf = document.getElementById(`tf-${id}`);

    if (!mc || !tf) return;

    if (val === 'multiple_choice') {
        mc.style.display = 'block';
        tf.style.display = 'none';
        setInputsEnabled(mc, true);
        setInputsEnabled(tf, false);
    } else {
        mc.style.display = 'none';
        tf.style.display = 'block';
        setInputsEnabled(mc, false);
        setInputsEnabled(tf, true);
    }
}

// enable/disable and add/remove required appropriately
function setInputsEnabled(container, enabled){
    const inputs = container.querySelectorAll('input, textarea, select');
    inputs.forEach(inp => {
        // If disabling, remove 'required' so browser validation ignores hidden fields
        if (!enabled) {
            inp.removeAttribute('required');
            inp.disabled = true;
        } else {
            // when enabling, restore required only for relevant fields:
            // - option text inputs should be required
            // - radio groups for correct option should be required (we'll set required on radios)
            if (inp.matches('input[type="text"]')) inp.required = true;
            if (inp.matches('input[type="radio"]')) inp.required = true;
            inp.disabled = false;
        }
    });
}

function addOption(qid){
    const optsContainer = document.getElementById(`opts-${qid}`);
    if (!optsContainer) return;
    const c = optsContainer.children.length;
    const html = `<div class="option-row">
        <input type="text" name="questions[${qid}][options][]" placeholder="Option text" required>
        <input type="radio" name="questions[${qid}][correct_option]" value="${c}" required>
        <button type="button" class="remove-btn" onclick="removeOption(this)">Remove</button>
    </div>`;
    optsContainer.insertAdjacentHTML('beforeend', html);
    // ensure radios/text are enabled only if MC is visible
    const mc = document.getElementById(`mc-${qid}`);
    if (mc && mc.style.display === 'none') {
        setInputsEnabled(optsContainer, false);
    } else {
        setInputsEnabled(optsContainer, true);
    }
}

function removeOption(btn){
    const row = btn.closest('.option-row');
    if (!row) return;
    const parent = row.parentElement;
    if (parent.children.length <= 2) { alert('At least 2 options required.'); return; }
    row.remove();
    // re-index radio values
    Array.from(parent.children).forEach((r,i)=> {
        const rad = r.querySelector('input[type="radio"]');
        if (rad) rad.value = i;
    });
}

function removeQuestion(id){ const el = document.getElementById('q-'+id); if (el) el.remove(); }

document.getElementById('questionsForm').addEventListener('submit', function(e){
    const blocks = document.querySelectorAll('.question-block');
    if (!blocks.length) { e.preventDefault(); alert('Add at least one question.'); return; }
    const errors = [];
    blocks.forEach((b, i) => {
        const text = b.querySelector('textarea');
        if (!text || !text.value.trim()) errors.push(`Question ${i+1}: text required`);
        const type = b.querySelector('select').value;
        if (type === 'multiple_choice') {
            const opts = b.querySelectorAll('input[name*="[options]"]');
            const vals = Array.from(opts).map(o=>o.value.trim()).filter(v=>v);
            if (vals.length < 2) errors.push(`Question ${i+1}: at least 2 options`);
            if (!b.querySelector('input[name*="[correct_option]"]:checked')) errors.push(`Question ${i+1}: select correct option`);
        } else if (type === 'true_false') {
            if (!b.querySelector('input[name*="[correct_tf]"]:checked')) errors.push(`Question ${i+1}: select True/False`);
        }
    });
    if (errors.length) { e.preventDefault(); alert('Fix errors:\n' + errors.join('\n')); return; }
    // Final safety: disable any inputs in hidden sections so browser won't validate them
    document.querySelectorAll('[id^="mc-"]').forEach(el => { if (el.style.display === 'none') setInputsEnabled(el, false); });
    document.querySelectorAll('[id^="tf-"]').forEach(el => { if (el.style.display === 'none') setInputsEnabled(el, false); });
});
</script>


</body>
</html>

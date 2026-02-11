<?php
$currentPosition = $_GET['position'] ?? 'cajero';

// Simulación de datos (reemplazar con conexión real a BD)
$questions = [
    'cajero' => [
        'yesno' => [
            ['id' => 1, 'question_text' => '¿Tiene experiencia en manejo de dinero?'],
            ['id' => 2, 'question_text' => '¿Puede trabajar en turnos rotativos?']
        ],
        'open' => [
            ['id' => 3, 'question_text' => '¿Cómo manejaría un cliente molesto?'],
            ['id' => 4, 'question_text' => 'Describa su experiencia en atención al cliente']
        ]
    ],
    'maestro_sanguchero' => [
        'yesno' => [
            ['id' => 5, 'question_text' => '¿Tiene experiencia en cocina?'],
            ['id' => 6, 'question_text' => '¿Puede trabajar bajo presión?']
        ],
        'open' => [
            ['id' => 7, 'question_text' => '¿Cuál es su especialidad culinaria?'],
            ['id' => 8, 'question_text' => 'Describa su experiencia preparando sándwiches']
        ]
    ]
];

$positionQuestions = $questions[$currentPosition] ?? ['yesno' => [], 'open' => []];

// Procesar formularios
if ($_POST['action'] ?? '' === 'add') {
    $type = $_POST['type'];
    $text = $_POST['question_text'];
    // Aquí agregar a BD
    header("Location: ?position=$currentPosition&added=1");
    exit;
}

if ($_POST['action'] ?? '' === 'delete') {
    $id = $_POST['id'];
    // Aquí eliminar de BD
    header("Location: ?position=$currentPosition&deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Preguntas - La Ruta 11</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">

<header class="bg-white shadow-sm border-b">
    <div class="container mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
            <button onclick="history.back()" class="p-2 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <img src="/icon.png" alt="La Ruta 11" class="w-8 h-8">
            <div>
                <h1 class="text-lg font-bold text-gray-900">Gestión de Preguntas</h1>
                <p class="text-sm text-gray-600">Administrar preguntas de entrevista</p>
            </div>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-6 max-w-6xl">
    
    <!-- Position Selector -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Seleccionar Posición</h2>
        <div class="flex gap-4">
            <a href="?position=cajero" class="px-4 py-2 rounded-lg <?= $currentPosition === 'cajero' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-700' ?>">
                Cajero
            </a>
            <a href="?position=maestro_sanguchero" class="px-4 py-2 rounded-lg <?= $currentPosition === 'maestro_sanguchero' ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-700' ?>">
                Maestro Sanguchero
            </a>
        </div>
    </div>

    <!-- Add New Question -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Agregar Nueva Pregunta</h3>
        <form method="POST" class="flex gap-4">
            <input type="hidden" name="action" value="add">
            <select name="type" class="border border-gray-300 rounded px-3 py-2">
                <option value="yesno">Sí/No</option>
                <option value="open">Abierta</option>
            </select>
            <input type="text" name="question_text" placeholder="Escriba la nueva pregunta..." class="flex-1 border border-gray-300 rounded px-3 py-2" required>
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                Agregar
            </button>
        </form>
    </div>

    <!-- Questions Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Yes/No Questions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Preguntas Sí/No</h3>
            <div class="space-y-3">
                <?php if (empty($positionQuestions['yesno'])): ?>
                    <p class="text-gray-500 text-center py-4">No hay preguntas Sí/No configuradas.</p>
                <?php else: ?>
                    <?php foreach ($positionQuestions['yesno'] as $q): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($q['question_text']) ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="editQuestion(<?= $q['id'] ?>, '<?= addslashes($q['question_text']) ?>')" class="text-blue-600 hover:text-blue-800 text-xs">✏️</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta pregunta?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs">❌</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Open Questions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Preguntas Abiertas</h3>
            <div class="space-y-3">
                <?php if (empty($positionQuestions['open'])): ?>
                    <p class="text-gray-500 text-center py-4">No hay preguntas abiertas configuradas.</p>
                <?php else: ?>
                    <?php foreach ($positionQuestions['open'] as $q): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($q['question_text']) ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="editQuestion(<?= $q['id'] ?>, '<?= addslashes($q['question_text']) ?>')" class="text-blue-600 hover:text-blue-800 text-xs">✏️</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta pregunta?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs">❌</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function editQuestion(id, currentText) {
    const newText = prompt('Editar pregunta:', currentText);
    if (newText && newText.trim() !== currentText) {
        // Crear formulario dinámico para editar
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="question_text" value="${newText.trim()}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>
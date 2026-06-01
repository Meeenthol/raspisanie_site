<?php
// Кэширование на 1 день для статических ресурсов
header("Cache-Control: public, max-age=86400");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");

// ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ
$conn = new mysqli('localhost', 'root', '', 'raspisanie');
if ($conn->connect_error) die("Ошибка подключения");

// СПИСОК ПРЕДМЕТОВ ДЛЯ РАНДОМА
$subjects = [
    'ирвр',
    'инф.без',
    'кс',
    'основы права',
    'Оаип',
    'рисвр',
    'Нет пары'
];

// ОБРАБОТКА ФОРМ
$message = '';

// Обработка рандомного заполнения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['random'])) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $random_day = $days[array_rand($days)];
    
    $times = ['08-10', '10-12', '12-14', '14-16', '16-18'];
    $random_time = $times[array_rand($times)];
    
    $random_subject = $subjects[array_rand($subjects)];
    
    if ($random_subject == 'Нет пары') {
        $stmt = $conn->prepare("UPDATE list SET $random_day = NULL WHERE time = ?");
        $stmt->bind_param("s", $random_time);
    } else {
        $stmt = $conn->prepare("UPDATE list SET $random_day = ? WHERE time = ?");
        $stmt->bind_param("ss", $random_subject, $random_time);
    }
    
    if ($stmt->execute()) {
        $day_names = [
            'monday' => 'понедельник',
            'tuesday' => 'вторник', 
            'wednesday' => 'среду',
            'thursday' => 'четверг',
            'friday' => 'пятницу',
            'saturday' => 'субботу'
        ];
        $message = "Случайно добавлено: '$random_subject' на $day_names[$random_day] в $random_time";
    } else {
        $message = "Ошибка при случайном заполнении";
    }
}

// Обычная обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $day = $_POST['day'];
    $time = $_POST['time'];
    $subject = trim($_POST['subject']);
    
    if ($day && $time && $subject) {
        if ($subject == 'Нет пары') {
            $stmt = $conn->prepare("UPDATE list SET $day = NULL WHERE time = ?");
            $stmt->bind_param("s", $time);
        } else {
            $stmt = $conn->prepare("UPDATE list SET $day = ? WHERE time = ?");
            $stmt->bind_param("ss", $subject, $time);
        }
        
        if ($stmt->execute()) {
    $message = "Предмет сохранён";
} else {
    $message = "Ошибка сохранения";
}
    } else {
        $message = "Заполните все поля";
    }
}

// ПОЛУЧАЕМ ДАННЫЕ
$result = $conn->query("SELECT * FROM list ORDER BY FIELD(time, '08-10','10-12','12-14','14-16','16-18')");

// ПОДСЧЁТ СТАТИСТИКИ ПО ДНЯМ
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$day_names_ru = [
    'monday' => 'Понедельник',
    'tuesday' => 'Вторник',
    'wednesday' => 'Среда',
    'thursday' => 'Четверг',
    'friday' => 'Пятница',
    'saturday' => 'Суббота'
];

$stats = [];
foreach ($days as $day) {
    $result_stats = $conn->query("SELECT $day FROM list WHERE $day IS NOT NULL AND $day != ''");
    $count_subjects = $result_stats->num_rows;
    $total_hours = $count_subjects * 2;
    
    $stats[$day] = [
        'count' => $count_subjects,
        'hours' => $total_hours
    ];
}

// Возвращаем указатель результата на начало
$result = $conn->query("SELECT * FROM list ORDER BY FIELD(time, '08-10','10-12','12-14','14-16','16-18')");

// Общая статистика за неделю
$total_pairs = 0;
$total_hours = 0;
foreach ($stats as $day_stats) {
    $total_pairs += $day_stats['count'];
    $total_hours += $day_stats['hours'];
}

// Преобразуем данные для Vue (в JSON)
$schedule_data = [];
while($row = $result->fetch_assoc()) {
    $schedule_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расписание</title>
    <!-- Подключаем Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
    body {
        background: #935d5d;
        font-family: 'Georgia', 'Times New Roman', serif;
        margin: 0;
        padding: 40px 20px;
        color: #4a4430;
        background-image: radial-gradient(#cbc49a 1px, transparent 1px);
        background-size: 22px 22px;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: #f9f4d7;
        padding: 30px;
        border: 1px solid #b2a87a;
        box-shadow: 8px 8px 20px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 252, 230, 0.8);
        position: relative;
    }

    .container::before {
        content: "";
        position: absolute;
        top: 8px;
        left: 8px;
        right: 8px;
        bottom: 8px;
        border: 1px solid #e0d8b8;
        pointer-events: none;
    }

    h1 {
        color: #6b4a3a;
        font-size: 28px;
        letter-spacing: 6px;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px double #c49a8a;
        font-weight: bold;
        font-family: 'Georgia', 'Times New Roman', serif;
        text-transform: uppercase;
        text-align: center;
        font-style: italic;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 30px 0;
        background: #fefcf2;
        box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.05);
    }

    th {
        background: #bc9588;
        padding: 15px;
        text-align: center;
        border: 1px solid #b0a878;
        font-weight: bold;
        letter-spacing: 2px;
        color: #4a3a2a;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-size: 13px;
        text-transform: uppercase;
        font-style: normal;
    }

    td {
        padding: 12px;
        text-align: center;
        border: 1px solid #d4cca8;
        color: #4a4430;
        background: #fffef8;
        font-family: 'Georgia', 'Times New Roman', serif;
    }

    td:first-child {
        background: #ece6c8;
        font-weight: bold;
        color: #7a6a48;
        font-family: 'Georgia', 'Times New Roman', serif;
        border-right: 2px solid #c4a88a;
        font-style: italic;
    }

    td[data-subject="ирвр"] { background-color: #cbd299; color: #3a401f; }
    td[data-subject="инф.без"] { background-color: #bcc68a; color: #3a401f; }
    td[data-subject="кс"] { background-color: #aab878; color: #2a3518; }
    td[data-subject="основы права"] { background-color: #c2c999; color: #3a401f; }
    td[data-subject="оаип"] { background-color: #b9c585; color: #2f3a1a; }
    td[data-subject="рисвр"] { background-color: #abb975; color: #2a3518; }

    td[data-subject="Нет пары"] {
        background-color: #f7f4e6;
        color: #c0b088;
        font-style: italic;
    }

    td:empty,
    td[data-subject=""] {
        background-color: #fff3db;
        color: #d4cca8;
        font-style: italic;
    }

    tr:hover td {
        background: #ffdbbc;
    }

    tr:hover td:first-child {
        background: #e6dfc2;
    }

    .day-stats {
        display: block;
        font-size: 9px;
        font-weight: normal;
        color: #6f1d1d;
        margin-top: 6px;
        letter-spacing: 1px;
        font-family: 'Georgia', 'Times New Roman', serif;
        border-top: 1px dotted #6b4848;
        padding-top: 5px;
        font-style: italic;
    }

    .time-cell {
        font-weight: bold;
        background-color: #ece6c8;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-style: italic;
    }

    td[data-subject]:hover {
        filter: brightness(0.96);
        cursor: pointer;
        box-shadow: inset 0 0 0 2px #c48a7a;
        transition: all 0.15s;
    }

    .filter-btn.active {
        background: #765f3f;
        color: #faf0e8;
        border-color: #9a6a5a;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
    }

    button {
        background: #b8a878;
        border: 1px solid #9a8a60;
        color: #3a3020;
        font-size: 14px;
        cursor: pointer;
        font-family: 'Georgia', 'Times New Roman', serif;
        letter-spacing: 2px;
        text-transform: uppercase;
        transition: all 0.2s ease;
        padding: 10px 16px;
        font-style: italic;
    }

    button:hover {
        background: #c48a7a;
        border-color: #b06a5a;
        color: #faf0e8;
        box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.1);
    }

    .random-btn {
        background: #9a8a60;
        border-color: #7a6a48;
        color: #faf7ea;
    }

    .random-btn:hover {
        background: #c48a7a;
        color: #faf0e8;
    }

    .form-container {
        background: #efebd4;
        border: 1px solid #c4ba90;
        padding: 25px;
        margin-top: 30px;
        box-shadow: inset 0 0 0 1px #fefcf2;
    }

    .form-container h3 {
        color: #7a6848;
        border-bottom: 1px dotted #c48a7a;
        display: inline-block;
        padding-bottom: 6px;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-style: italic;
        letter-spacing: 1px;
        font-weight: bold;
    }

    select, input {
        width: 100%;
        padding: 10px;
        margin: 8px 0 15px;
        background: #fefcf5;
        border: 1px solid #d4cca8;
        color: #5a4a30;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-size: 13px;
    }

    select:focus, input:focus {
        border-color: #c48a7a;
        outline: none;
        box-shadow: 0 0 4px #e0b0a0;
    }

    .total-stats {
        background: #ece6c8;
        border: 1px solid #c4ba90;
        padding: 15px;
        text-align: center;
        margin: 20px 0;
        color: #7a6848;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-style: italic;
    }

    .message {
        background: #fbf7e8;
        border-left: 4px solid #c48a7a;
        padding: 12px 15px;
        margin: 15px 0;
        color: #7a6848;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-style: italic;
    }

    .search-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .search-input {
        flex: 1;
        padding: 10px;
        font-family: 'Georgia', 'Times New Roman', serif;
        background: #fefcf5;
        border: 1px solid #d4cca8;
        color: #5a4a30;
    }

    .filter-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 20px 0;
    }

    .filter-btn {
        background: #ded8b0;
        border: 1px solid #c4ba90;
        color: #5a4a30;
        padding: 6px 14px;
        font-size: 12px;
        width: auto;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-family: 'Georgia', 'Times New Roman', serif;
    }

    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #7a6848;
        color: #faf7ea;
        padding: 12px 20px;
        z-index: 1000;
        font-family: 'Georgia', 'Times New Roman', serif;
        font-size: 12px;
        border-left: 4px solid #c48a7a;
        animation: slideIn 0.3s ease;
        font-style: italic;
    }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .highlight {
        background-color: #f0e0c8 !important;
        box-shadow: inset 0 0 0 2px #c48a7a !important;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(60, 45, 35, 0.7);
    }

    .modal-content {
        background-color: #fefcf5;
        margin: 15% auto;
        padding: 25px;
        width: 90%;
        max-width: 400px;
        border: 2px solid #c48a7a;
        box-shadow: 10px 10px 20px rgba(0, 0, 0, 0.2);
        font-family: 'Georgia', 'Times New Roman', serif;
    }

    .close {
        color: #c48a7a;
        float: right;
        font-size: 28px;
        cursor: pointer;
        font-family: 'Georgia', 'Times New Roman', serif;
    }

    .close:hover {
        color: #9a6a5a;
    }

    hr {
        border: none;
        border-top: 1px dotted #d4cca8;
        margin: 20px 0;
    }

    .note {
        color: #b0a078;
        font-style: italic;
        font-size: 11px;
        text-align: center;
        margin-top: 15px;
        font-family: 'Georgia', 'Times New Roman', serif;
    }

    button, .filter-btn, .random-btn {
        width: auto;
    }
    
    .today-highlight {
        background-color: #ffe0b5 !important;
        box-shadow: inset 0 0 0 2px #c48a7a !important;
        position: relative;
    }

    /* Стили для скрытых колонок */
    .col-hidden {
        display: none !important;
    }
    </style>
</head>
<body>
    <div class="container">
        <h1>Расписание занятий</h1>
        
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Поиск -->
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Поиск предметов...">
            <button onclick="searchSubject()" style="width: auto; padding: 10px 20px;">Найти</button>
            <button onclick="clearSearch()" style="width: auto; padding: 10px 20px;">Сброс</button>
        </div>

        <!-- VUE ПРИЛОЖЕНИЕ -->
        <div id="vueApp">
            <div class="filter-buttons">
                <button 
                    v-for="day in daysList" 
                    :key="day.value"
                    class="filter-btn"
                    :class="{ active: selectedDay === day.value }"
                    @click="selectedDay = day.value">
                    {{ day.name }}
                </button>
            </div>
            
            <table id="scheduleTable">
                <thead>
                    <tr>
                        <th>Время</th>
                        <th 
                            v-for="day in weekDays" 
                            :key="day.key"
                            :class="{ 'col-hidden': isDayHidden(day.key) }">
                            {{ day.name }}
                            <br>
                            <span class="day-stats">{{ getDayStats(day.key).count }} пар / {{ getDayStats(day.key).hours }} ч</span>
                        </th>
                    </tr>
                </thead>
                <tbody id="scheduleBody">
                    <tr v-for="row in scheduleData" :key="row.time">
                        <td class="time-cell">{{ row.time }}</td>
                        <td 
                            v-for="day in weekDays" 
                            :key="day.key"
                            class="subject-cell"
                            :class="{ 'col-hidden': isDayHidden(day.key) }"
                            :data-day="day.key"
                            :data-subject="row[day.key] || ''"
                            @click="openEditModal(day.key, row.time, row[day.key])"
                            :style="getCellStyle(row[day.key])">
                            {{ row[day.key] === null || row[day.key] === '' ? '—' : row[day.key] }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="total-stats">
                <strong>Всего за неделю:</strong> {{ totalPairs }} пар ({{ totalHours }} часов)
                <br>
                <strong>Загруженность:</strong> {{ loadPercentage }}%
            </div>
        </div>
        
        <div class="form-container">
            <h3>Добавить или заменить предмет</h3>
            
            <form method="POST" id="randomForm">
                <input type="hidden" name="random" value="1">
                <button type="submit" class="random-btn"> Заполнить случайными значениями</button>
            </form>
            
            <hr>
            
            <form method="POST" id="saveForm">
                <select name="day" id="daySelect" required>
                    <option value="">Выберите день</option>
                    <option value="monday">Понедельник</option>
                    <option value="tuesday">Вторник</option>
                    <option value="wednesday">Среда</option>
                    <option value="thursday">Четверг</option>
                    <option value="friday">Пятница</option>
                    <option value="saturday">Суббота</option>
                </select>
                
                <select name="time" id="timeSelect" required>
                    <option value="">Выберите время</option>
                    <option value="08-10">08:00 - 10:00</option>
                    <option value="10-12">10:00 - 12:00</option>
                    <option value="12-14">12:00 - 14:00</option>
                    <option value="14-16">14:00 - 16:00</option>
                    <option value="16-18">16:00 - 18:00</option>
                </select>
                
                <select name="subject" id="subjectSelect" required>
                    <option value="">Выберите предмет</option>
                    <option value="ирвр">ирвр</option>
                    <option value="инф.без">инф.без</option>
                    <option value="кс">кс</option>
                    <option value="основы права">основы права</option>
                    <option value="оаип">оаип</option>
                    <option value="рисвр">рисвр</option>
                    <option value="Нет пары">Нет пары</option>
                </select>
                
                <input type="hidden" name="save" value="1">
                <button type="submit"> Сохранить</button>
            </form>
            
            <div class="note"> Кликните на ячейку с предметом для быстрого редактирования</div>
        </div>
    </div>

    <!-- Модальное окно для быстрого редактирования -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3> Редактирование предмета</h3>
            <p>День: <strong id="modalDay"></strong></p>
            <p>Время: <strong id="modalTime"></strong></p>
            <select id="modalSubject" style="width: 100%;">
                <option value="ирвр">ирвр</option>
                <option value="инф.без">инф.без</option>
                <option value="кс">кс</option>
                <option value="основы права">основы права</option>
                <option value="оаип">оаип</option>
                <option value="рисвр">рисвр</option>
                <option value="Нет пары">Нет пары</option>
            </select>
            <button id="saveModalBtn" style="margin-top: 15px;"> Сохранить</button>
        </div>
    </div>

    <script>
        // ========== ЧИСТЫЙ JS ==========
        
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.background = isError ? '#f44336' : '#4caf50';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function searchSubject() {
            const searchText = document.getElementById('searchInput').value.toLowerCase().trim();
            const cells = document.querySelectorAll('.subject-cell');
            if (!searchText) { clearSearch(); return; }
            clearSearch();
            let found = false;
            cells.forEach(cell => {
                const subject = cell.getAttribute('data-subject');
                if (subject && subject.toLowerCase().includes(searchText)) {
                    cell.classList.add('highlight');
                    found = true;
                }
            });
            if (!found) showToast(`Предмет "${searchText}" не найден`, true);
            else showToast(`Найдено ${document.querySelectorAll('.highlight').length} совпадений`);
        }

        function clearSearch() {
            document.querySelectorAll('.highlight').forEach(cell => cell.classList.remove('highlight'));
            document.getElementById('searchInput').value = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') { e.preventDefault(); document.getElementById('searchInput').focus(); }
            if (e.key === 'Escape') clearSearch();
        });

        document.getElementById('randomForm')?.addEventListener('submit', function(e) {
            if (!confirm('Случайное заполнение изменит расписание. Продолжить?')) e.preventDefault();
        });

        // ========== VUE.JS - ФИЛЬТРАЦИЯ ПО КОЛОНКАМ ==========
        const { createApp, ref, computed } = Vue;

        createApp({
            setup() {
                const scheduleData = ref(<?php echo json_encode($schedule_data); ?>);
                const statsData = ref(<?php echo json_encode($stats); ?>);
                const selectedDay = ref('all');
                
                const weekDays = [
                    { key: 'monday', name: 'Понедельник' },
                    { key: 'tuesday', name: 'Вторник' },
                    { key: 'wednesday', name: 'Среда' },
                    { key: 'thursday', name: 'Четверг' },
                    { key: 'friday', name: 'Пятница' },
                    { key: 'saturday', name: 'Суббота' }
                ];
                
                const daysList = [
                    { value: 'all', name: ' Все дни' },
                    { value: 'monday', name: 'Понедельник' },
                    { value: 'tuesday', name: 'Вторник' },
                    { value: 'wednesday', name: 'Среда' },
                    { value: 'thursday', name: 'Четверг' },
                    { value: 'friday', name: 'Пятница' },
                    { value: 'saturday', name: 'Суббота' }
                ];
                
                // Функция для определения, нужно ли скрыть колонку
                const isDayHidden = (dayKey) => {
                    if (selectedDay.value === 'all') return false;
                    return selectedDay.value !== dayKey;
                };
                
                const getDayStats = (day) => {
                    return statsData.value[day] || { count: 0, hours: 0 };
                };
                
                const totalPairs = computed(() => {
                    let total = 0;
                    for (let day in statsData.value) {
                        total += statsData.value[day].count;
                    }
                    return total;
                });
                
                const totalHours = computed(() => totalPairs.value * 2);
                const loadPercentage = computed(() => Math.round((totalPairs.value / (6 * 5)) * 100));
                
                const getCellStyle = (subject) => {
                    if (!subject || subject === 'Нет пары') return {};
                    return { cursor: 'pointer' };
                };
                
                const openEditModal = (day, time, subject) => {
                    const modal = document.getElementById('editModal');
                    const modalDay = document.getElementById('modalDay');
                    const modalTime = document.getElementById('modalTime');
                    const modalSubject = document.getElementById('modalSubject');
                    
                    const dayNames = { monday: 'Понедельник', tuesday: 'Вторник', wednesday: 'Среда', thursday: 'Четверг', friday: 'Пятница', saturday: 'Суббота' };
                    
                    modalDay.textContent = dayNames[day];
                    modalTime.textContent = time;
                    modalSubject.value = (subject && subject !== '—') ? subject : '';
                    modal.setAttribute('data-day', day);
                    modal.setAttribute('data-time', time);
                    modal.style.display = 'block';
                };
                
                return {
                    scheduleData,
                    weekDays,
                    daysList,
                    selectedDay,
                    isDayHidden,
                    getDayStats,
                    totalPairs,
                    totalHours,
                    loadPercentage,
                    getCellStyle,
                    openEditModal
                };
            }
        }).mount('#vueApp');
        
        // ========== ОБРАБОТЧИКИ МОДАЛКИ (ВНЕ VUE) ==========
        const saveModalBtn = document.getElementById('saveModalBtn');
        if (saveModalBtn) {
            const newBtn = saveModalBtn.cloneNode(true);
            saveModalBtn.parentNode.replaceChild(newBtn, saveModalBtn);
            
            newBtn.addEventListener('click', function() {
                const modal = document.getElementById('editModal');
                const day = modal.getAttribute('data-day');
                const time = modal.getAttribute('data-time');
                const subject = document.getElementById('modalSubject').value;
                
                if (!subject) { showToast('Выберите предмет', true); return; }
                
                const formData = new FormData();
                formData.append('day', day);
                formData.append('time', time);
                formData.append('subject', subject);
                formData.append('save', '1');
                
                showToast('Сохранение...');
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(() => { showToast(' Сохранено! Обновляем...'); setTimeout(() => location.reload(), 500); })
                    .catch(() => showToast(' Ошибка сохранения', true));
            });
        }
        
        const closeBtn = document.querySelector('.close');
        if (closeBtn) {
            const newCloseBtn = closeBtn.cloneNode(true);
            closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
            newCloseBtn.addEventListener('click', () => { document.getElementById('editModal').style.display = 'none'; });
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) modal.style.display = 'none';
        };
        
        // ========== ДОПОЛНИТЕЛЬНЫЙ ФУНКЦИОНАЛ ==========

        function addScrollToTopButton() {
            if (document.getElementById('scrollTopBtn')) return;
            
            const btn = document.createElement('button');
            btn.id = 'scrollTopBtn';
            btn.textContent = '⬆ Наверх';
            btn.style.position = 'fixed';
            btn.style.bottom = '20px';
            btn.style.left = '20px';
            btn.style.zIndex = '999';
            btn.style.background = '#7a6848';
            btn.style.color = '#faf7ea';
            btn.style.border = 'none';
            btn.style.padding = '10px 15px';
            btn.style.borderRadius = '30px';
            btn.style.cursor = 'pointer';
            btn.style.fontFamily = 'Georgia, Times New Roman, serif';
            btn.style.opacity = '0.7';
            btn.style.transition = 'opacity 0.3s';
            
            btn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
            
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    btn.style.display = 'block';
                    btn.style.opacity = '0.7';
                } else {
                    btn.style.opacity = '0';
                    setTimeout(() => { if (window.scrollY <= 300) btn.style.display = 'none'; }, 300);
                }
            });
            
            btn.style.display = 'none';
            document.body.appendChild(btn);
        }

        function typeWriterEffectOnTitle() {
            const titleElement = document.querySelector('h1');
            if (!titleElement) return;
            if (titleElement.getAttribute('data-typed') === 'true') return;
            
            const originalText = titleElement.textContent;
            titleElement.textContent = '';
            titleElement.setAttribute('data-typed', 'true');
            
            let i = 0;
            function type() {
                if (i < originalText.length) {
                    titleElement.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(type, 40);
                }
            }
            type();
        }

        window.addEventListener('load', function() {
            addScrollToTopButton();
            typeWriterEffectOnTitle();
            showToast(' Расписание загружено!');
        });

        function shareSchedule() {
            const url = new URL(window.location.href);
            if (navigator.share) {
                navigator.share({ title: 'Моё расписание', url: url.toString() }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url.toString()).then(() => { showToast(' Ссылка скопирована!'); }).catch(() => { showToast(' Ошибка', true); });
            }
        }

        function addShareButton() {
            const filterContainer = document.querySelector('.filter-buttons');
            if (filterContainer && !document.getElementById('shareBtn')) {
                const shareBtn = document.createElement('button');
                shareBtn.id = 'shareBtn';
                shareBtn.textContent = ' Поделиться';
                shareBtn.className = 'filter-btn';
                shareBtn.style.background = '#7a6848';
                shareBtn.style.color = '#faf7ea';
                shareBtn.onclick = shareSchedule;
                filterContainer.appendChild(shareBtn);
            }
        }

        function highlightTodayLessons() {
            document.querySelectorAll('.today-highlight').forEach(cell => { cell.classList.remove('today-highlight'); cell.style.backgroundColor = ''; });
            
            const daysMap = { 1: 'monday', 2: 'tuesday', 3: 'wednesday', 4: 'thursday', 5: 'friday', 6: 'saturday' };
            const todayIndex = new Date().getDay();
            const today = daysMap[todayIndex];
            
            if (today && today !== 'sunday') {
                const todayCells = document.querySelectorAll(`.subject-cell[data-day="${today}"]`);
                const validCells = [];
                todayCells.forEach(cell => {
                    const subject = cell.getAttribute('data-subject');
                    if (subject && subject !== '' && subject !== '—' && subject !== 'Нет пары') {
                        cell.classList.add('today-highlight');
                        cell.style.backgroundColor = '#ffe0b5';
                        cell.style.boxShadow = 'inset 0 0 0 2px #c48a7a';
                        validCells.push(cell);
                    }
                });
                if (validCells.length > 0) { showToast(` Сегодня ${getDayNameRu(today)} — ${validCells.length} пар(ы)`); }
            }
        }

        function getDayNameRu(day) {
            const days = { 'monday': 'Понедельник', 'tuesday': 'Вторник', 'wednesday': 'Среда', 'thursday': 'Четверг', 'friday': 'Пятница', 'saturday': 'Суббота' };
            return days[day] || day;
        }

        let draggedCell = null;

        function makeCellsDraggable() {
            const cells = document.querySelectorAll('.subject-cell');
            cells.forEach(cell => {
                const subject = cell.getAttribute('data-subject');
                if (subject && subject !== '' && subject !== '—' && subject !== 'Нет пары') {
                    cell.setAttribute('draggable', 'true');
                    cell.style.cursor = 'grab';
                    cell.removeEventListener('dragstart', dragStartHandler);
                    cell.removeEventListener('dragend', dragEndHandler);
                    cell.removeEventListener('dragover', dragOverHandler);
                    cell.removeEventListener('dragleave', dragLeaveHandler);
                    cell.removeEventListener('drop', dropHandler);
                    cell.addEventListener('dragstart', dragStartHandler);
                    cell.addEventListener('dragend', dragEndHandler);
                    cell.addEventListener('dragover', dragOverHandler);
                    cell.addEventListener('dragleave', dragLeaveHandler);
                    cell.addEventListener('drop', dropHandler);
                } else {
                    cell.setAttribute('draggable', 'false');
                }
            });
        }

        function dragStartHandler(e) { draggedCell = this; e.dataTransfer.setData('text/plain', this.getAttribute('data-subject')); this.style.opacity = '0.5'; }
        function dragEndHandler(e) { this.style.opacity = ''; draggedCell = null; }
        function dragOverHandler(e) { e.preventDefault(); this.style.boxShadow = 'inset 0 0 0 2px #c48a7a'; }
        function dragLeaveHandler(e) { this.style.boxShadow = ''; }
        function dropHandler(e) {
            e.preventDefault();
            this.style.boxShadow = '';
            if (!draggedCell || draggedCell === this) return;
            
            const sourceSubject = draggedCell.getAttribute('data-subject');
            const sourceDay = draggedCell.getAttribute('data-day');
            const sourceTime = draggedCell.parentElement.querySelector('.time-cell').textContent.trim();
            const targetSubject = this.getAttribute('data-subject');
            const targetDay = this.getAttribute('data-day');
            const targetTime = this.parentElement.querySelector('.time-cell').textContent.trim();
            
            const formData1 = new FormData();
            formData1.append('day', targetDay);
            formData1.append('time', sourceTime.split(' ')[0]);
            formData1.append('subject', (targetSubject === '—' || !targetSubject) ? 'Нет пары' : targetSubject);
            formData1.append('save', '1');
            
            const formData2 = new FormData();
            formData2.append('day', sourceDay);
            formData2.append('time', targetTime.split(' ')[0]);
            formData2.append('subject', (sourceSubject === '—' || !sourceSubject) ? 'Нет пары' : sourceSubject);
            formData2.append('save', '1');
            
            showToast(' Перемещаем предметы...');
            fetch(window.location.href, { method: 'POST', body: formData1 })
                .then(() => fetch(window.location.href, { method: 'POST', body: formData2 }))
                .then(() => { showToast(' Предметы переставлены!'); setTimeout(() => location.reload(), 300); })
                .catch(() => showToast(' Ошибка при перестановке', true));
        }

        setTimeout(() => {
            addShareButton();
            makeCellsDraggable();
            highlightTodayLessons();
        }, 500);

        const originalObserver = new MutationObserver(() => { makeCellsDraggable(); });
        const scheduleTable = document.getElementById('scheduleTable');
        if (scheduleTable) { originalObserver.observe(scheduleTable, { childList: true, subtree: true }); }
    </script>
</body>
</html>

<?php $conn->close(); ?>
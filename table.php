<?php
include 'includes/db.php';


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Таблица участников</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
		  @media print {
            body::after {
                content: "Hackathon «Сәтбаев ізбасарлары II»";
                position: fixed;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
                font-size: 20px;
                color: rgba(0, 0, 0, 0.1);
                font-weight: bold;
                text-transform: uppercase;
                z-index: 9999;
                pointer-events: none;
            }
        }
 .signature {
            height: 20px; /* Еще больше места для подписи */
            padding-top: 40px; /* Добавляем отступ сверху */
            font-size: 18px;
            font-style: italic;
            color: gray;
        }
        th, td {
            border: 1px solid black;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>

<table>
    <thead>
        <tr>
     <th style="width: 5%;">№</th>
            <th style="width: 35%;">Ф.И.О. участника</th>
            <th style="width: 20%;">Название команды</th>
            <th style="width: 40%;">Подпись</th>
        </tr>
    </thead>
    <tbody>
          <?php
      

        $participants = R::findAll('team_users', 'ORDER BY id ASC');
        $index = 1;

        foreach ($participants as $participant) :
            $team = R::findOne('teams', 'id = ?', [$participant->team_id]);
        ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-800"><?= $index++ ?></td>
                <td class="px-6 py-4 text-gray-800"><?= htmlspecialchars($participant->full_name) ?></td>
                <td class="px-6 py-4 text-gray-800"><?= htmlspecialchars($team->name ?? 'Без команды') ?></td>
                <td class="px-6 py-4 text-gray-800 signature"><span class="italic text-gray-500"> </span></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

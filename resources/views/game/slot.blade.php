<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>🎰 Slot Mania</title>
    <style>
        body {
            background: #121212;
            color: #fff;
            text-align: center;
            font-family: sans-serif;
        }

        #slots {
            font-size: 60px;
            margin: 40px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        button {
            font-size: 22px;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            background: #28a745;
            color: white;
            cursor: pointer;
            margin: 5px;
        }

        #balance,
        #bonus {
            margin-top: 20px;
            font-size: 22px;
        }

        #result {
            margin-top: 20px;
            font-size: 24px;
        }
    </style>
</head>

<body>
    <h1>🎰 Slot Mania</h1>
    <div id="slots" style="display:grid; grid-template-columns:repeat(3, 80px); justify-content:center; gap:10px;">
        <span>🍒</span><span>🍋</span><span>🍇</span>
        <span>🍒</span><span>🍋</span><span>🍇</span>
        <span>🍒</span><span>🍋</span><span>🍇</span>
    </div>

    <div>
        <button onclick="spin(10)">Ставка 10</button>
        <button onclick="spin(50)">Ставка 50</button>
        <button onclick="spin(100)">Ставка 100</button>
    </div>

    <h3 id="result"></h3>
    <div id="balance">Баланс: {{ $player->balance }}</div>
    <div id="bonus">Бонус: {{ $player->bonus }}</div>

    <script>
        
        const symbols = ['🍒', '🍋', '🍇', '🍊', '⭐', '🍉'];
       
        const chatId = '{{ $player->telegram_chat_id }}';
        
 
       
        // Индексы элементов в #slots: [0,1,2] - верхняя строка, [3,4,5] - средняя, [6,7,8] - нижняя
        const lines = [
            [0, 1, 2], // верхняя горизонталь
            [3, 4, 5], // средняя горизонталь
            [6, 7, 8], // нижняя горизонталь
            [0, 4, 8], // диагональ слева-направо вниз
            [2, 4, 6]  // диагональ справа-направо вниз
        ];
         
        function getRandomSymbol() {
            return symbols[Math.floor(Math.random() * symbols.length)];
        }

        async function spin(bet) {
            const reelEls = document.querySelectorAll('#slots span');
            let final = [];

            // Анимация вращения
            for (let i = 0; i < reelEls.length; i++) {
                let count = 0;
                const interval = setInterval(() => {
                    reelEls[i].innerText = getRandomSymbol();
                    if (++count > 15) clearInterval(interval);
                }, 50);
            }

            // Финальные символы через 800ms
            setTimeout(async () => {
                for (let i = 0; i < reelEls.length; i++) {
                    final[i] = getRandomSymbol();
                    reelEls[i].innerText = final[i];
                }

                // Проверка линий
                let win = 0;
                lines.forEach(line => {
                    if (final[line[0]] === final[line[1]] && final[line[1]] === final[line[2]]) win += bet * 10;
                });

                document.getElementById('result').innerText = win ? `🎉 Вы выиграли ${win}!` : '😅 Попробуй снова!';

                // Отправка результата на сервер
                const res = await fetch('https://cf134ad85c9a48.lhr.life/game/slot/result', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        player: chatId,
                        win: win,
                        bet: bet
                    })
                });
                const data = await res.json();
                
                document.getElementById('balance').innerText = 'Баланс: ' + data.balance;
                document.getElementById('bonus').innerText = 'Бонус: ' + data.bonus;
            }, 800);
        }
    </script>

</body>

</html>
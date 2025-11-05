@extends('layouts.app')

@section('content')
    <style>
        /* Основной контейнер */
        .slot-container {
            background: #121212;
            padding: 5px 15px;
            text-align: center;
            color: white;
            font-family: sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center; /* Центрируем по вертикали */
            align-items: center;
            min-height: 100vh; /* Занимает весь экран */
            overflow-y: hidden; /* Никакого скролла! */
            box-sizing: border-box;
        }

        /* Заголовок */
        .slot-container h1 {
            font-size: 18px;
            margin: 5px 0;
        }

        /* Барабаны — УМЕНЬШАЕМ РАЗМЕРЫ, но сохраняем читаемость */
        #slots {
            display: grid;
            grid-template-columns: repeat(3, 50px); /* Было 80px → 50px */
            gap: 5px; /* Было 10px → 5px */
            font-size: 35px; /* Было 60px → 35px */
            margin: 10px auto;
        }

        /* Элементы управления */
        .controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            width: 100%;
            max-width: 280px;
            padding: 7px 0;
        }

        .controls label {
            font-size: 11px;
            margin: 0 0 2px 0;
            display: block;
            width: 100%;
            text-align: center;
        }

        .controls input[type="range"],
        .controls select {
            width: 100%;
            max-width: 240px;
            height: 23px;
            padding: 2px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 4px;
            background: #333;
            color: white;
        }

        /* Кнопки — оставляем нормальные размеры для тапа */
        .glow-on-hover {
            width: 150px;
            height: 30px;
            border: none;
            outline: none;
            color: #33213eff;
            background: #f9fcfbff;
            cursor: pointer;
            position: relative;
            z-index: 0;
            border-radius: 10px;
            font-size: 16px;
            margin: 5px 0;
        }

        .glow-on-hover:before {
            content: '';
            background: linear-gradient(45deg, #ff0000, #ff7300, #fffb00, #48ff00, #00ffd5, #002bff, #7a00ff, #ff00c8, #ff0000);
            position: absolute;
            top: -2px;
            left: -2px;
            background-size: 400%;
            z-index: -1;
            filter: blur(5px);
            width: calc(100% + 4px);
            height: calc(100% + 4px);
            animation: glowing 20s linear infinite;
            opacity: 0;
            transition: opacity .3s ease-in-out;
            border-radius: 9px;
        }

        .glow-on-hover:active {
            color: #833e3eff;
        }

        .glow-on-hover:active:after {
            background: transparent;
        }

        .glow-on-hover:hover:before {
            opacity: 1;
        }

        .glow-on-hover:after {
            z-index: -1;
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: #579870ff;
            left: 0;
            top: 0;
            border-radius: 9px;
        }

        @keyframes glowing {
            0% { background-position: 0 0; }
            50% { background-position: 400% 0; }
            100% { background-position: 0 0; }
        }

        /* Статус */
        #result {
            font-size: 13px;
            margin: 8px 0 3px 0;
            word-wrap: break-word;
        }

        #balance, #bonus, #free-spins {
            font-size: 13px;
            margin: 1px 0;
        }

        /* Адаптивность для очень маленьких экранов */
        @media (max-width: 360px) {
            .slot-container {
                padding: 8px 8px;
                min-height: 95vh; /* Если нужно чуть меньше */
            }
            #slots {
                grid-template-columns: repeat(3, 45px);
                font-size: 30px;
                gap: 3px;
            }
            .glow-on-hover {
                width: 180px;
                height: 45px;
                font-size: 14px;
            }
            .controls input[type="range"], .controls select {
                max-width: 200px;
                font-size: 12px;
            }
        }
    </style>

    <div class="slot-container">
        <h1>🎰 Slot Mania</h1>

        {{-- Board --}}
        <div id="slots">
            @for($i = 0; $i < 9; $i++)
                <span>🍒</span>
            @endfor
        </div>

        {{-- Controls --}}
        <div class="controls">
            <div>
                <label>Bet: <span id="bet-value">10</span></label>
                <input id="bet" type="range" min="10" max="1000" step="10" value="10">
            </div>
            <div>
                <label>Line:</label>
                <select id="lines">
                    <option value="1">1 line</option>
                    <option value="3">3 lines</option>
                    <option value="5" selected>5 lines</option>
                    <option value="9">9 lines</option>
                </select>
            </div>
            <div>
                <label>Volume 🔊</label>
                <input id="volume" type="range" min="0" max="1" step="0.1" value="0.5">
            </div>
            <div style="padding: 5px; ">
                <button id="spinBtn" class="glow-on-hover" style="margin-bottom: 5px;"> Spin</button>
                <button id="autoplayBtn" class="glow-on-hover"> Autoplay</button>
            </div>
        </div>

        {{-- Status --}}
        <h3 id="result"></h3>
        <div id="balance">Balance: {{ $player->balance }}</div>
        <div id="bonus">Bonus: {{ $player->bonus }}</div>
        <div id="free-spins"></div>
    </div>

    {{-- Sounds --}}
    <audio id="spin-sound" src="{{ asset('sounds/spin.mp3') }}"></audio>
    <audio id="win-sound" src="{{ asset('sounds/win.mp3') }}"></audio>
    <audio id="bonus-sound" src="{{ asset('sounds/bonus.mp3') }}"></audio>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const symbols = ['🍒', '🍋', '🍇', '🍊', '⭐', '🍉', '💎'];
            const chatId = '{{ $player->telegram_chat_id }}';

            let selectedLines = 9;
            let baseBet = 10;
            let volume = 0.5;
            let freeSpins = 0;
            let multiplier = 1;
            let isSpinning = false;
            let autoplay = false;
            let autoplayInterval = null;

            const spinSound = document.getElementById('spin-sound');
            const winSound = document.getElementById('win-sound');
            const bonusSound = document.getElementById('bonus-sound');

            spinSound.volume = winSound.volume = bonusSound.volume = volume;

            const spinBtn = document.getElementById('spinBtn');
            const autoplayBtn = document.getElementById('autoplayBtn');
            const inputs = document.querySelectorAll('input, select, #spinBtn');

            const lines = [
                [0, 1, 2], [3, 4, 5], [6, 7, 8],
                [0, 3, 6], [1, 4, 7], [2, 5, 8],
                [0, 4, 8], [2, 4, 6], [0, 4, 6]
            ];

            function setControlsDisabled(state) {
                inputs.forEach(el => el.disabled = state);
                autoplayBtn.disabled = false;
            }

            document.getElementById('volume').addEventListener('input', e => {
                volume = e.target.value;
                spinSound.volume = winSound.volume = bonusSound.volume = volume;
            });

            document.getElementById('bet').addEventListener('input', e => {
                baseBet = parseInt(e.target.value);
                document.getElementById('bet-value').innerText = baseBet;
            });

            document.getElementById('lines').addEventListener('change', e => {
                selectedLines = parseInt(e.target.value);
            });

            async function spin() {
                if (isSpinning) return;
                isSpinning = true;
                setControlsDisabled(true);

                const reelEls = document.querySelectorAll('#slots span');
                let final = [];
                spinSound.currentTime = 0;
                spinSound.play();

                for (let i = 0; i < reelEls.length; i++) {
                    let count = 0;
                    const interval = setInterval(() => {
                        reelEls[i].innerText = symbols[Math.floor(Math.random() * symbols.length)];
                        if (++count > 15) clearInterval(interval);
                    }, 50);
                }

                await new Promise(r => setTimeout(r, 1000));

                for (let i = 0; i < reelEls.length; i++) {
                    final[i] = symbols[Math.floor(Math.random() * symbols.length)];
                    reelEls[i].innerText = final[i];
                }

                let win = 0;
                let bonusTriggered = false;
                let totalBet = baseBet * selectedLines;

                lines.slice(0, selectedLines).forEach(line => {
                    const [a, b, c] = line;
                    if (final[a] === final[b] && final[b] === final[c]) {
                        win += baseBet * 10;
                        if (final[a] === '💎') bonusTriggered = true;
                    }
                });

                if (!bonusTriggered && Math.random() < 0.05) bonusTriggered = true;

                if (bonusTriggered && freeSpins === 0) {
                    freeSpins = 10;
                    multiplier = 3;
                    bonusSound.play();
                    document.getElementById('result').innerText = '💎 BONUS! 10 free spins, all winnings multiplied by three';
                    document.getElementById('free-spins').innerText = 'There are still free spins left: ' + freeSpins;
                    stopAutoplay();
                }

                if (freeSpins > 0) {
                    win *= multiplier;
                    freeSpins--;
                    document.getElementById('free-spins').innerText = freeSpins > 0 ? 'There are still free spins left: ' + freeSpins : '';
                    if (freeSpins === 0) {
                        multiplier = 1;
                        document.getElementById('result').innerText += ' 🎉 The bonus is over!';
                    }
                }

                if (win > 0) winSound.play();
                if (freeSpins === 0 && !bonusTriggered && win === 0) {
                    document.getElementById('result').innerText = '😅 Try again.!';
                } else if (win > 0) {
                    document.getElementById('result').innerText += ' 🎉 Winning: ' + win;
                }

                const res = await fetch('https://f1cdf41528c6e8.lhr.life/game/slot/result', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ player: chatId, win, bet: totalBet, bonus: bonusTriggered, free_spins: freeSpins, multiplier })
                });
                const data = await res.json();
                document.getElementById('balance').innerText = 'Balance: ' + data.balance;
                document.getElementById('bonus').innerText = 'Bonus: ' + data.bonus;

                isSpinning = false;

                if (!autoplay && freeSpins === 0) {
                    setControlsDisabled(false);
                }

                if (freeSpins > 0) {
                    setTimeout(spin, 1200);
                }
            }

            function toggleAutoplay() {
                autoplay = !autoplay;
                autoplayBtn.textContent = autoplay ? '⏸ Stop it AutoPlay' : ' Auto-play';
                if (autoplay) {
                    setControlsDisabled(true);
                    autoplayInterval = setInterval(() => {
                        if (!isSpinning && freeSpins === 0) spin();
                    }, 1000);
                } else stopAutoplay();
            }

            function stopAutoplay() {
                autoplay = false;
                clearInterval(autoplayInterval);
                autoplayBtn.textContent = '▶️ Auto-play';
                if (freeSpins === 0) setControlsDisabled(false);
            }

            spinBtn.addEventListener('click', spin);
            autoplayBtn.addEventListener('click', toggleAutoplay);
        });
    </script>
@endsection
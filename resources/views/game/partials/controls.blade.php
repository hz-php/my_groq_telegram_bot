<div class="controls mt-4">
    <div style="margin:10px;">
        <label>Ставка: <span id="bet-value">10</span></label>
        <input id="bet" type="range" min="10" max="1000" step="10" value="10">
    </div>

    <div style="margin:10px;">
        <label>Линий: </label>
        <select id="lines">
            <option value="1">1 линия</option>
            <option value="3">3 линии</option>
            <option value="5">5 линий</option>
            <option value="9" selected>9 линий</option>
        </select>
    </div>

    <div style="margin:10px;">
        <label>Громкость 🔊</label>
        <input id="volume" type="range" min="0" max="1" step="0.1" value="0.5">
    </div>

    <button id="spinBtn" style="background:#28a745; padding:10px 30px; font-size:22px; border-radius:8px;">▶️ Крутить</button>
    <button id="autoplayBtn" style="background:#ff9800; padding:10px 30px; font-size:22px; border-radius:8px; margin-left:10px;">▶️ Автоигра</button>
</div>

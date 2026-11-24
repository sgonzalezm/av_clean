<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';

$accion = trivia_get('accion', 'home');

/* ───────────── Helpers locales ───────────── */

function cargar_quiz($pdo, $codigo) {
    $q = $pdo->prepare("SELECT * FROM trivia_quizzes WHERE codigo=?");
    $q->execute([$codigo]);
    return $q->fetch();
}

function ranking_pregunta($pdo, $quiz, $pregunta_id) {
    $r = $pdo->prepare("
        SELECT pa.nombre, pa.puntaje, r.tiempo_ms, r.es_correcta
        FROM trivia_participantes pa
        LEFT JOIN trivia_respuestas r
          ON r.participante_id = pa.id AND r.pregunta_id = ?
        WHERE pa.quiz_id = ?
        ORDER BY r.es_correcta DESC, r.tiempo_ms ASC
    ");
    $r->execute([$pregunta_id, $quiz['id']]);
    return $r->fetchAll();
}

// Normaliza texto para comparación (abierta)
function normalizar($txt) {
    $txt = trim(mb_strtolower($txt, 'UTF-8'));
    $txt = strtr($txt, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $txt = preg_replace('/\s+/', ' ', $txt);
    return $txt;
}

/* ═══════════════════════════════════════════════════════════════ */

switch ($accion) {

// ───────────────────────── HOME: creador visual ─────────────────────────
case 'home':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = trivia_codigo();
        $pdo->prepare("INSERT INTO trivia_quizzes (codigo, titulo) VALUES (?,?)")
            ->execute([$codigo, trivia_post('titulo')]);
        $quizId = $pdo->lastInsertId();

        $preguntas = json_decode(trivia_post('preguntas_json', '[]'), true) ?: [];
        $orden = 0;
        foreach ($preguntas as $p) {
            if (empty(trim($p['enunciado'] ?? ''))) continue;
            $orden++;
            $tipo = $p['tipo'] ?? 'multiple';

            $pdo->prepare("INSERT INTO trivia_preguntas
                (quiz_id, orden, enunciado, tipo, respuesta_abierta) VALUES (?,?,?,?,?)")
                ->execute([
                    $quizId, $orden, trim($p['enunciado']), $tipo,
                    $tipo === 'abierta' ? trim($p['respuesta_abierta'] ?? '') : null
                ]);
            $pid = $pdo->lastInsertId();

            if ($tipo === 'abierta') continue;

            $ops = $p['opciones'] ?? [];
            foreach ($ops as $op) {
                $txt = trim($op['texto'] ?? '');
                if ($txt === '') continue;
                $pdo->prepare("INSERT INTO trivia_opciones (pregunta_id, texto, es_correcta) VALUES (?,?,?)")
                    ->execute([$pid, $txt, !empty($op['correcta']) ? 1 : 0]);
            }
        }

        header("Location: ?accion=admin&codigo=$codigo");
        exit;
    }
    ?>
    <!doctype html><html lang="es"><head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Crear trivia</title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background:#f2f4f7; color:#111; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.5em; margin: 0 0 16px; }
        .card { background:#fff; border-radius: 14px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        label { display:block; font-weight:600; margin: 12px 0 6px; font-size: .95em; }
        input[type=text], textarea, select {
          width: 100%; padding: 12px; font-size: 1em;
          border: 1px solid #d0d5dd; border-radius: 10px;
          font-family: inherit; background: #fff;
        }
        textarea { min-height: 70px; resize: vertical; }
        select { appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12'><path d='M2 4l4 4 4-4' stroke='%23666' fill='none' stroke-width='1.5'/></svg>"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        .btn {
          display: inline-flex; align-items: center; justify-content: center; gap: 6px;
          padding: 14px 18px; font-size: 1em; border: none; border-radius: 10px;
          cursor: pointer; font-weight: 600; text-decoration: none;
        }
        .btn-primary { background:#0066cc; color:#fff; width: 100%; }
        .btn-primary:active { background:#004999; }
        .btn-add { background:#e8f1ff; color:#0066cc; width: 100%; border: 2px dashed #a8c7f0; margin-top: 8px; }
        .btn-sec { background:#eee; color:#333; }
        .btn-danger { background:#fee; color:#b00; padding: 8px 12px; font-size: .9em; }
        .btn-sm { padding: 8px 12px; font-size: .9em; }
        .pregunta { background: #fafbff; border: 2px solid #e4e9f2; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
        .pregunta-head { display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; }
        .num { background:#0066cc; color:#fff; width: 28px; height: 28px; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size: .9em; }
        .opcion-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .opcion-row input[type=text] { flex: 1; }
        .correcta-label { display:flex; align-items:center; gap:6px; font-size:.9em; white-space: nowrap; cursor: pointer; user-select: none; }
        .correcta-label input { width: 20px; height: 20px; accent-color: #0a0; }
        .tipo-tabs { display:flex; gap:6px; margin-bottom: 10px; flex-wrap: wrap; }
        .tipo-tab { flex: 1; min-width: 100px; padding: 10px 8px; border-radius: 8px; text-align:center; font-size:.85em; cursor:pointer; border: 2px solid #e4e9f2; background:#fff; color:#555; font-weight:600; }
        .tipo-tab.active { border-color:#0066cc; background:#e8f1ff; color:#0066cc; }
        .hint { font-size:.85em; color:#666; margin-top:4px; }
        .empty { text-align:center; color:#888; padding: 30px 0; font-style: italic; }
        .footer-space { height: 90px; }
        .fixed-bottom { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px; background:#fff; border-top:1px solid #e4e9f2; z-index: 100; }
        .fixed-bottom .btn { max-width: 720px; margin: 0 auto; display: flex; }
      </style>
    </head><body>
      <div class="wrap">
        <h1>🎯 Crear trivia</h1>

        <form method="post" id="quizForm">
          <input type="hidden" name="preguntas_json" id="preguntasJson">

          <div class="card">
            <label for="titulo">Título de la trivia</label>
            <input type="text" id="titulo" name="titulo" required placeholder="Ej. Trivia de cultura general">
          </div>

          <div id="preguntas"></div>

          <button type="button" class="btn btn-add" onclick="addPregunta()">+ Agregar pregunta</button>

          <div class="footer-space"></div>
          <div class="fixed-bottom">
            <button type="submit" class="btn btn-primary">Crear trivia y abrir panel</button>
          </div>
        </form>
      </div>

    <script>
    const preguntasEl = document.getElementById('preguntas');
    let contador = 0;

    function htmlPregunta(idx, tipo) {
      const tabs = ['multiple','vf','abierta'].map(t => {
        const label = {multiple:'Opción múltiple', vf:'V / F', abierta:'Abierta'}[t];
        const active = t === tipo ? 'active' : '';
        return `<div class="tipo-tab ${active}" onclick="cambiarTipo(${idx},'${t}')">${label}</div>`;
      }).join('');

      return `
      <div class="pregunta" data-idx="${idx}">
        <div class="pregunta-head">
          <div style="display:flex; align-items:center; gap:10px">
            <div class="num">${idx+1}</div>
            <strong>Pregunta</strong>
          </div>
          <button type="button" class="btn btn-danger btn-sm" onclick="borrar(${idx})">🗑</button>
        </div>

        <div class="tipo-tabs">${tabs}</div>

        <input type="text" class="enunciado" placeholder="Escribe la pregunta..." oninput="sync()">

        <div class="opciones-wrap"></div>
      </div>`;
    }

    function htmlOpcionesMultiple() {
      // Generamos un id único para los radio buttons de esta pregunta
      const radioName = 'correcta_' + Math.random().toString(36).slice(2);
      return `
        <label style="margin-top:12px">Opciones (marca la correcta)</label>
        <div class="opciones-multi">
          ${opcionRow(0, '', radioName)}
          ${opcionRow(1, '', radioName)}
        </div>
        <button type="button" class="btn btn-add btn-sm" onclick="addOpcion(this)" style="margin-top:6px">+ Opción</button>
        <div class="hint">Marca el círculo de la respuesta correcta.</div>
      `;
    }

    function opcionRow(i, val, radioName = 'correcta_' + Math.random().toString(36).slice(2), correcta=false) {
      return `
        <div class="opcion-row">
          <label class="correcta-label">
            <input type="radio" name="${radioName}" ${correcta?'checked':''} onchange="sync(); marcarRadio(this)">
          </label>
          <input type="text" class="opcion-txt" placeholder="Opción ${i+1}" value="${val.replace(/"/g,'&quot;')}" oninput="sync()">
          <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.opcion-row').remove(); sync()">×</button>
        </div>`;
    }

    function htmlVF() {
      return `
        <label style="margin-top:12px">Respuesta correcta</label>
        <div class="opciones-vf">
          <label class="correcta-label" style="padding:10px; background:#fff; border-radius:8px; border:2px solid #e4e9f2; width:100%; margin-bottom:6px">
            <input type="radio" name="vf_${Math.random().toString(36).slice(2)}" data-vf="Verdadero" onchange="sync(); marcarRadioVF(this)">
            ✅ Verdadero
          </label>
          <label class="correcta-label" style="padding:10px; background:#fff; border-radius:8px; border:2px solid #e4e9f2; width:100%">
            <input type="radio" name="vf_${Math.random().toString(36).slice(2)}" data-vf="Falso" onchange="sync(); marcarRadioVF(this)">
            ❌ Falso
          </label>
        </div>`;
    }

    function htmlAbierta() {
      return `
        <label style="margin-top:12px">Respuesta correcta (texto)</label>
        <input type="text" class="respuesta-abierta" placeholder="Ej. París" oninput="sync()">
        <div class="hint">Se compara sin distinguir mayúsculas ni acentos.</div>
      `;
    }

    function marcarRadio(el) {
      const wrap = el.closest('.pregunta');
      wrap.querySelectorAll('.correcta-label input[type=radio]').forEach(r => r.checked = false);
      el.checked = true;
      sync();
    }

    function marcarRadioVF(el) {
      const wrap = el.closest('.pregunta');
      wrap.querySelectorAll('.opciones-vf input[type=radio]').forEach(r => r.checked = false);
      el.checked = true;
      sync();
    }

    function addPregunta() {
      const idx = contador++;
      const div = document.createElement('div');
      div.innerHTML = htmlPregunta(idx, 'multiple');
      const nodo = div.firstElementChild;
      nodo.querySelector('.opciones-wrap').innerHTML = htmlOpcionesMultiple();
      preguntasEl.appendChild(nodo);
      renumera();
      sync();
    }

    function addOpcion(btn) {
      // Busca el contenedor de opciones dentro de la misma tarjeta de pregunta
      const wrap = btn.closest('.pregunta').querySelector('.opciones-multi');
      const n = wrap.querySelectorAll('.opcion-row').length;
      const div = document.createElement('div');
      div.innerHTML = opcionRow(n, '');
      wrap.appendChild(div.firstElementChild);
      sync();
    }

    function cambiarTipo(idx, tipo) {
      const wrap = document.querySelector(`.pregunta[data-idx="${idx}"]`);
      if (!wrap) return;
      wrap.querySelectorAll('.tipo-tab').forEach(t => t.classList.remove('active'));
      wrap.querySelector(`.tipo-tab:nth-child(${{multiple:1,vf:2,abierta:3}[tipo]})`).classList.add('active');

      const opWrap = wrap.querySelector('.opciones-wrap');
      if (tipo === 'multiple') opWrap.innerHTML = htmlOpcionesMultiple();
      else if (tipo === 'vf') opWrap.innerHTML = htmlVF();
      else opWrap.innerHTML = htmlAbierta();
      sync();
    }

    function borrar(idx) {
      const wrap = document.querySelector(`.pregunta[data-idx="${idx}"]`);
      if (wrap) { wrap.remove(); renumera(); sync(); }
    }

    function renumera() {
      document.querySelectorAll('.pregunta').forEach((p, i) => {
        p.querySelector('.num').textContent = i + 1;
      });
    }

    function sync() {
      const out = [];
      document.querySelectorAll('.pregunta').forEach(wrap => {
        const enunciado = wrap.querySelector('.enunciado').value.trim();
        const activeTab = wrap.querySelector('.tipo-tab.active').textContent.trim();
        const tipo = activeTab === 'V / F' ? 'vf' : (activeTab === 'Abierta' ? 'abierta' : 'multiple');

        const data = { tipo, enunciado };

        if (tipo === 'multiple') {
          data.opciones = [];
          wrap.querySelectorAll('.opcion-row').forEach(row => {
            const txt = row.querySelector('.opcion-txt').value.trim();
            const correcta = row.querySelector('input[type=radio]').checked;
            if (txt) data.opciones.push({ texto: txt, correcta });
          });
        } else if (tipo === 'vf') {
          const marcado = wrap.querySelector('.opciones-vf input[type=radio]:checked');
          const val = marcado ? marcado.dataset.vf : '';
          data.opciones = [
            { texto: 'Verdadero', correcta: val === 'Verdadero' },
            { texto: 'Falso', correcta: val === 'Falso' }
          ];
        } else {
          data.respuesta_abierta = wrap.querySelector('.respuesta-abierta').value.trim();
        }

        out.push(data);
      });
      document.getElementById('preguntasJson').value = JSON.stringify(out);
    }

    document.getElementById('quizForm').addEventListener('submit', e => {
      sync();
      const arr = JSON.parse(document.getElementById('preguntasJson').value);
      if (!arr.length) { e.preventDefault(); alert('Agrega al menos una pregunta'); return; }
      const sinEnunciado = arr.findIndex(p => !p.enunciado);
      if (sinEnunciado >= 0) { e.preventDefault(); alert(`Falta el enunciado de la pregunta ${sinEnunciado+1}`); return; }
      const sinCorrecta = arr.findIndex(p => {
        if (p.tipo === 'abierta') return !p.respuesta_abierta;
        return !p.opciones?.some(o => o.correcta);
      });
      if (sinCorrecta >= 0) { e.preventDefault(); alert(`Falta marcar la respuesta correcta en la pregunta ${sinCorrecta+1}`); return; }
    });

    addPregunta();
    </script>
    </body></html>
    <?php
    break;

// ───────────────────────── LISTA: quizzes guardados ─────────────────────────
case 'lista':
    $quizzes = $pdo->query("
        SELECT q.*,
               (SELECT COUNT(*) FROM trivia_preguntas WHERE quiz_id=q.id) AS total_preg,
               (SELECT COUNT(*) FROM trivia_participantes WHERE quiz_id=q.id) AS total_part
        FROM trivia_quizzes q
        ORDER BY q.creado_en DESC
    ")->fetchAll();
    ?>
    <!doctype html><html lang="es"><head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Mis trivias</title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background:#f2f4f7; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.5em; margin: 0 0 16px; }
        .card { background:#fff; border-radius: 14px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .code { font-family: monospace; font-size: 1.3em; font-weight: bold; color:#0066cc; }
        .title { font-weight: 600; margin: 4px 0; }
        .meta { font-size: .85em; color:#666; margin: 4px 0 12px; }
        .btn { display: inline-flex; align-items:center; gap:6px; padding: 12px 16px; border-radius: 10px;
               background:#0066cc; color:#fff; text-decoration:none; font-size: .95em; font-weight:600;
               border: none; cursor: pointer; margin: 4px 6px 4px 0; }
        .btn.sec { background:#666; }
        .btn-empty { text-align:center; color:#888; padding: 40px 0; font-style: italic; }
      </style>
    </head><body>
      <div class="wrap">
        <h1>📚 Mis trivias</h1>
        <a href="?accion=home" class="btn">+ Crear nueva</a>
        <br><br>

        <?php if (!$quizzes): ?>
          <div class="btn-empty">Aún no hay trivias. Crea la primera.</div>
        <?php endif; ?>

        <?php foreach ($quizzes as $q): ?>
          <div class="card">
            <div class="code"><?= htmlspecialchars($q['codigo']) ?></div>
            <div class="title"><?= htmlspecialchars($q['titulo']) ?></div>
            <div class="meta">
              <?= $q['total_preg'] ?> preguntas ·
              <?= $q['total_part'] ?> participantes ·
              Estado: <b><?= $q['estado'] ?></b>
            </div>
            <a href="?accion=admin&codigo=<?= $q['codigo'] ?>" class="btn">Abrir panel</a>
            <a href="?accion=jugar&codigo=<?= $q['codigo'] ?>" class="btn sec" target="_blank">Ver participante</a>
          </div>
        <?php endforeach; ?>
      </div>
    </body></html>
    <?php
    break;

// ───────────────────────── ADMIN: panel ─────────────────────────
case 'admin':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) die('Quiz no encontrado');
    $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $url  = $base . $_SERVER['SCRIPT_NAME'] . '?accion=jugar&codigo=' . $quiz['codigo'];
    ?>
    <!doctype html><html lang="es"><head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Quizzy · Panel de control</title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background:#f2f4f7; color:#111; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.3em; margin: 0 0 14px; }
        .card { background:#fff; border-radius: 14px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .code { font-family: monospace; font-size: 1.8em; font-weight: bold; color:#0066cc; text-align:center; margin: 4px 0 6px; letter-spacing: 3px; }
        .status { text-align:center; font-size: .9em; color:#666; }
        .qr { display:block; margin: 14px auto; max-width: 220px; width: 100%; border-radius: 8px; }
        .btn {
          display: block; width: 100%; padding: 14px; font-size: 1.05em;
          border: none; border-radius: 10px; margin: 8px 0; cursor: pointer;
          color:#fff; background:#0066cc; font-weight: 600;
          text-align: center; text-decoration: none;
        }
        .btn.sec { background:#5a6474; }
        .btn.warn { background:#b33; }
        .btn:active { opacity: .85; }
        .url-box { word-break: break-all; font-size: .85em; background:#f0f2f5; padding: 10px; border-radius: 8px; margin: 10px 0; color:#333; }
        .item { padding: 10px 0; border-bottom: 1px solid #eee; font-size: .95em; display:flex; justify-content:space-between; }
        .item:last-child { border-bottom: none; }
        .correct { color:#080; font-weight:600; }
        .wrong { color:#b00; }
        .pending { color:#888; }
        .pregunta-label { background:#e8f1ff; padding: 8px 12px; border-radius: 8px; font-size: .9em; color:#0066cc; margin-bottom: 12px; font-weight: 600; }
      </style>
    </head><body>
      <div class="wrap">
        <div style="text-align:center; margin-bottom: 20px;">
        <div style="font-size: 2em; font-weight: 800; color: #0066cc; letter-spacing: 1px; margin-bottom: 4px;">Quizzy</div>
        <h1 style="margin: 0; font-size: 1.2em; color: #555;"><?= htmlspecialchars($quiz['titulo']) ?></h1>
        </div>

        <div class="card">
          <div class="code"><?= $quiz['codigo'] ?></div>
          <div class="status">
            Estado: <b id="estado"><?= $quiz['estado'] ?></b> ·
            Pregunta <b id="orden"><?= $quiz['pregunta_actual'] ?></b>
          </div>
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($url) ?>" alt="QR" class="qr">
          <div class="url-box"><?= htmlspecialchars($url) ?></div>
          <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?= urlencode($url) ?>"
             download="qr-<?= $quiz['codigo'] ?>.png" class="btn sec">⬇ Descargar QR</a>
          <button class="btn sec" onclick="compartir()">📤 Compartir link</button>
        </div>

        <div class="card">
          <button class="btn" onclick="ctrl('siguiente')">▶ Siguiente pregunta</button>
          <button class="btn sec" onclick="ctrl('finalizar')">⏹ Finalizar</button>
          <button class="btn warn" onclick="reset()">↺ Reiniciar trivia</button>
            <div style="display: flex; gap: 10px; margin-bottom: 16px;">
            <a href="?accion=home" class="btn" style="flex: 1; margin: 0;">+ Crear nueva trivia</a>
            <a href="?accion=lista" class="btn sec" style="flex: 1; margin: 0;">📚 Ver todas</a>
            </div>
        </div>

        <div class="card">
          <h3 style="margin-top:0">👥 Participantes en vivo</h3>
          <div id="res">Cargando...</div>
        </div>
      </div>

    <script>
    const codigo = <?= json_encode($quiz['codigo']) ?>;
    const shareUrl = <?= json_encode($url) ?>;

    async function compartir() {
      if (navigator.share) {
        try { await navigator.share({ title: <?= json_encode($quiz['titulo']) ?>, url: shareUrl }); }
        catch(e) {}
      } else {
        try { await navigator.clipboard.writeText(shareUrl); alert('Link copiado'); }
        catch(e) { prompt('Copia este link:', shareUrl); }
      }
    }

    async function ctrl(op) {
      await fetch(`?accion=control&codigo=${codigo}&op=${op}`, {method:'POST'});
      cargar();
    }

    async function reset() {
      if (!confirm('¿Borrar todas las respuestas y participantes?')) return;
      await fetch(`?accion=reset&codigo=${codigo}`, {method:'POST'});
      cargar();
    }

    async function cargar() {
      const r = await fetch(`?accion=resultados&codigo=${codigo}`);
      const d = await r.json();
      const res = document.getElementById('res');
      if (d.error) { res.textContent = d.error; return; }
      document.getElementById('estado').textContent = d.estado || '';
      document.getElementById('orden').textContent  = d.orden;

        if (d.finalizado) {
        res.innerHTML = '<h3>🏆 Ranking final</h3>' +
          (d.ranking.length
            ? d.ranking.map((p,i)=>{
                // Convertir ms a segundos con 1 decimal
                let seg = (p.tiempo_total / 1000).toFixed(1);
                return `<div class="item">
                          <span>${i+1}. <b>${p.nombre}</b></span>
                          <span><b>${p.puntaje} pts</b> <small style="color:#666">(${seg}s)</small></span>
                        </div>`;
              }).join('')
            : '<p style="color:#888;text-align:center;padding:20px 0">Sin participantes</p>');
        return;
      }

      let html = d.enunciado
        ? `<div class="pregunta-label">Pregunta #${d.orden}: ${d.enunciado}</div>`
        : '<div class="pregunta-label">Aún no hay pregunta publicada</div>';

      if (d.participantes.length) {
        html += d.participantes.map(p => {
          let status;
          if (!p.respondio) status = '<span class="pending">⏳ esperando</span>';
          else if (p.correcta) status = `<span class="correct">✅ ${p.tiempo_ms} ms</span>`;
          else status = '<span class="wrong">❌ incorrecta</span>';
          return `<div class="item"><span>${p.nombre}</span>${status}</div>`;
        }).join('');
      } else {
        html += '<p style="color:#888;text-align:center;padding:20px 0">Aún nadie conectado</p>';
      }
      res.innerHTML = html;
      setTimeout(cargar, 1500);
    }
    cargar();
    </script>
    </body></html>
    <?php
    break;

// ───────────────────────── PARTICIPANTE: jugar ─────────────────────────
case 'jugar':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) die('Código inválido');
    ?>
    <!doctype html><html lang="es"><head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
      <title>Quizzy · <?= htmlspecialchars($quiz['titulo']) ?></title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background:#f2f4f7; color:#111; }
        .wrap { max-width: 540px; margin: 0 auto; }
        h1 { font-size: 1.4em; margin: 0 0 16px; text-align:center; }
        input[type=text] {
          width: 100%; padding: 14px; font-size: 1.1em;
          border: 1px solid #d0d5dd; border-radius: 10px; background:#fff;
        }
        button {
          width: 100%; padding: 16px; font-size: 1.05em; font-weight: 600;
          border: none; border-radius: 10px; cursor: pointer;
          background: #0066cc; color: #fff; margin-top: 10px;
          text-align: left;
        }
        button:active { background:#004999; }
        .opt { background:#fff; color:#111; border: 2px solid #d0d5dd; font-weight: 500; }
        .opt:active { background:#e8f1ff; border-color:#0066cc; }
        .opt:disabled { opacity: .5; }
        .card { background:#fff; border-radius: 14px; padding: 18px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .center { text-align: center; }
        .enunciado { font-size: 1.15em; font-weight: 600; line-height: 1.4; margin: 0 0 16px; }
        .err { color: #b00; margin-top: 8px; font-size: .9em; }
        .emoji { font-size: 2em; display:block; margin-bottom: 8px; }
        .status { color:#666; margin-top: 8px; font-size: .95em; }
      </style>
    </head><body>
      <div class="wrap">
        <div style="text-align:center; margin-bottom: 16px;">
        <h1 style="margin:0; font-size: 1.2em; color:#0066cc; letter-spacing: 1px;">Quizzy</h1>
        <h2 style="margin: 4px 0 0; font-size: 1.3em;"><?= htmlspecialchars($quiz['titulo']) ?></h2>
        </div>
        <div id="app">Cargando...</div>
      </div>

    <script>
    const codigo = <?= json_encode($quiz['codigo']) ?>;
    let token = localStorage.getItem('tk_' + codigo) || '';
    let ultimaPregunta = null;

    function el(html) { document.getElementById('app').innerHTML = html; }

    function renderUnir() {
      el(`<div class="card">
        <label style="font-weight:600; display:block; margin-bottom:10px">¿Cómo te llamas?</label>
        <input id="nom" type="text" maxlength="50" autocomplete="off" placeholder="Escribe tu nombre">
        <button onclick="unir()">Entrar a la trivia</button>
        <div id="err" class="err"></div>
      </div>`);
      setTimeout(() => document.getElementById('nom')?.focus(), 100);
    }

    function renderEspera(nombre) {
      el(`<div class="card center">
        <span class="emoji">⏳</span>
        <div style="font-size:1.2em; font-weight:600">Hola ${nombre}</div>
        <div class="status">Esperando que empiece la trivia...</div>
      </div>`);
    }

    function renderEnviado(enunciado) {
      el(`<div class="card">
        <div class="enunciado">${enunciado}</div>
        <div class="center">
          <span class="emoji">✅</span>
          <div style="color:#080; font-weight:600">Respuesta enviada</div>
          <div class="status">Esperando siguiente pregunta...</div>
        </div>
      </div>`);
    }

    function renderPregunta(preg, tipo) {
      let opcionesHtml = '';
      if (tipo === 'abierta') {
        opcionesHtml = `
          <input id="respAbierta" type="text" placeholder="Tu respuesta" autocomplete="off">
          <button onclick="responderAbierta()">Enviar respuesta</button>`;
      } else {
        opcionesHtml = preg.opciones.map(o =>
          `<button class="opt" onclick="responder(${o.id})">${o.texto}</button>`
        ).join('');
      }
      el(`<div class="card">
        <div class="enunciado">${preg.enunciado}</div>
        ${opcionesHtml}
      </div>`);
      if (tipo === 'abierta') setTimeout(() => document.getElementById('respAbierta')?.focus(), 100);
    }

    function renderFin(puntaje) {
      el(`<div class="card center">
        <span class="emoji">🏁</span>
        <h2 style="margin: 8px 0">Fin de la trivia</h2>
        <div style="font-size:1.5em">Tu puntaje: <b>${puntaje ?? 0}</b></div>
      </div>`);
    }

    async function loop() {
      const r = await fetch(`?accion=estado&codigo=${codigo}&token=${encodeURIComponent(token)}`);
      const d = await r.json();
      if (d.error) { el(`<div class="card err">${d.error}</div>`); return; }
      if (d.token && d.token !== token) {
        token = d.token;
        localStorage.setItem('tk_' + codigo, token);
      }

      if (d.finalizado) return renderFin(d.puntaje);

      if (!d.pregunta) {
        if (!d.nombre) return renderUnir();
        renderEspera(d.nombre);
        return setTimeout(loop, 1500);
      }

      if (d.ya_respondio) {
        renderEnviado(d.pregunta.enunciado);
        return setTimeout(loop, 1500);
      }

      if (d.orden !== ultimaPregunta) {
        ultimaPregunta = d.orden;
        renderPregunta(d.pregunta, d.pregunta.tipo || 'multiple');
      } else {
        setTimeout(loop, 1500);
      }
    }

    async function unir() {
      const nombre = document.getElementById('nom').value.trim();
      if (!nombre) { document.getElementById('err').textContent = 'Escribe tu nombre'; return; }
      const fd = new FormData();
      fd.append('nombre', nombre);
      const r = await fetch(`?accion=unirse&codigo=${codigo}`, {method:'POST', body: fd});
      const d = await r.json();
      if (d.error) { document.getElementById('err').textContent = d.error; return; }
      token = d.token;
      localStorage.setItem('tk_' + codigo, token);
      loop();
    }

    async function responder(op) {
      document.querySelectorAll('.opt').forEach(b => b.disabled = true);
      const fd = new FormData();
      fd.append('opcion', op);
      fd.append('token', token);
      await fetch(`?accion=responder&codigo=${codigo}`, {method:'POST', body: fd});
      loop();
    }

    async function responderAbierta() {
      const txt = document.getElementById('respAbierta').value.trim();
      if (!txt) return;
      document.querySelectorAll('button, input').forEach(b => b.disabled = true);
      const fd = new FormData();
      fd.append('respuesta_texto', txt);
      fd.append('token', token);
      await fetch(`?accion=responder&codigo=${codigo}`, {method:'POST', body: fd});
      loop();
    }

    loop();
    </script>
    </body></html>
    <?php
    break;

// ───────────────────────── API: unirse ─────────────────────────
case 'unirse':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) trivia_json(['error' => 'Quiz no encontrado']);

    $nombre = trim(trivia_post('nombre', ''));
    if ($nombre === '' || mb_strlen($nombre) > 50) {
        trivia_json(['error' => 'Nombre inválido']);
    }

    $dup = $pdo->prepare("SELECT COUNT(*) FROM trivia_participantes WHERE quiz_id=? AND nombre=?");
    $dup->execute([$quiz['id'], $nombre]);
    if ((int)$dup->fetchColumn() > 0) {
        $nombre .= ' ' . random_int(2, 99);
    }

    $token = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO trivia_participantes (quiz_id, nombre, token) VALUES (?,?,?)")
        ->execute([$quiz['id'], $nombre, $token]);

    trivia_json(['token' => $token, 'nombre' => $nombre]);
    break;

// ───────────────────────── API: estado (polling participante) ─────────────────────────
case 'estado':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) trivia_json(['error' => 'Quiz no encontrado']);
    $out = ['finalizado' => $quiz['estado'] === 'finalizado', 'pregunta' => null];

    $part = null;
    if (trivia_get('token')) {
        $p = $pdo->prepare("SELECT * FROM trivia_participantes WHERE token=?");
        $p->execute([trivia_get('token')]);
        $part = $p->fetch();
        if ($part) {
            $out['nombre']  = $part['nombre'];
            $out['puntaje'] = (int)$part['puntaje'];
        }
    }

    if ($quiz['pregunta_actual'] > 0) {
        $pr = $pdo->prepare("SELECT * FROM trivia_preguntas WHERE quiz_id=? AND orden=?");
        $pr->execute([$quiz['id'], $quiz['pregunta_actual']]);
        $preg = $pr->fetch();
        if ($preg) {
            $pregunta = [
                'enunciado' => $preg['enunciado'],
                'tipo'      => $preg['tipo'] ?? 'multiple',
            ];
            if ($preg['tipo'] !== 'abierta') {
                $ops = $pdo->prepare("SELECT id, texto FROM trivia_opciones WHERE pregunta_id=?");
                $ops->execute([$preg['id']]);
                $pregunta['opciones'] = $ops->fetchAll();
            }
            $out['pregunta'] = $pregunta;
            $out['orden'] = (int)$preg['orden'];
            if ($part) {
                $r = $pdo->prepare("SELECT 1 FROM trivia_respuestas WHERE participante_id=? AND pregunta_id=?");
                $r->execute([$part['id'], $preg['id']]);
                $out['ya_respondio'] = (bool)$r->fetch();
            }
        }
    }
    trivia_json($out);
    break;

// ───────────────────────── API: responder ─────────────────────────
case 'responder':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) trivia_json(['error' => 'Quiz no encontrado']);

    $p = $pdo->prepare("SELECT * FROM trivia_participantes WHERE token=? AND quiz_id=?");
    $p->execute([trivia_post('token'), $quiz['id']]);
    $part = $p->fetch();
    if (!$part) trivia_json(['error' => 'Participante no encontrado']);

    $pr = $pdo->prepare("SELECT * FROM trivia_preguntas WHERE quiz_id=? AND orden=?");
    $pr->execute([$quiz['id'], $quiz['pregunta_actual']]);
    $preg = $pr->fetch();
    if (!$preg) trivia_json(['error' => 'Sin pregunta activa']);

    $tiempo = $preg['publicada_en']
        ? (int)((microtime(true) - strtotime($preg['publicada_en'])) * 1000)
        : 0;

    $es_correcta = 0;
    $opcion_id = null; // Null es mejor que 0 para el INT
    $respuesta_texto = null;

    if ($preg['tipo'] === 'abierta') {
        $respuesta_texto = trim(trivia_post('respuesta_texto', ''));
        if ($respuesta_texto === '') trivia_json(['error' => 'Respuesta vacía']);
        $es_correcta = (normalizar($respuesta_texto) === normalizar($preg['respuesta_abierta'] ?? '')) ? 1 : 0;
    } else {
        $opcion_id = (int)trivia_post('opcion', 0);
        $o = $pdo->prepare("SELECT es_correcta FROM trivia_opciones WHERE id=? AND pregunta_id=?");
        $o->execute([$opcion_id, $preg['id']]);
        $op = $o->fetch();
        if (!$op) trivia_json(['error' => 'Opción inválida']);
        $es_correcta = (int)$op['es_correcta'];
    }

    try {
        $pdo->prepare("INSERT INTO trivia_respuestas
            (participante_id, pregunta_id, opcion_id, es_correcta, tiempo_ms, respuesta_texto)
            VALUES (?,?,?,?,?,?)")
            ->execute([
                $part['id'], $preg['id'], $opcion_id, $es_correcta, $tiempo, $respuesta_texto
            ]);
        if ($es_correcta) {
            $pdo->prepare("UPDATE trivia_participantes SET puntaje = puntaje + 100 WHERE id=?")
                ->execute([$part['id']]);
        }
    } catch (PDOException $e) {
        // Si hay un error SQL real, lo devolvemos como JSON en vez de tragárnoslo
        trivia_json(['error' => 'Error BD: ' . $e->getMessage()]);
    }

    trivia_json(['ok' => true]);
    break;

// ───────────────────────── API: control admin ─────────────────────────
case 'control':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) die('Quiz no encontrado');
    $op = trivia_get('op');

    if ($op === 'siguiente') {
        $nuevo = $quiz['pregunta_actual'] + 1;
        $total = $pdo->prepare("SELECT COUNT(*) FROM trivia_preguntas WHERE quiz_id=?");
        $total->execute([$quiz['id']]);
        if ($nuevo > (int)$total->fetchColumn()) {
            $pdo->prepare("UPDATE trivia_quizzes SET estado='finalizado' WHERE id=?")->execute([$quiz['id']]);
        } else {
            $pdo->prepare("UPDATE trivia_quizzes SET estado='en_curso', pregunta_actual=? WHERE id=?")
                ->execute([$nuevo, $quiz['id']]);
            $ahora = date('Y-m-d H:i:s');
            $pdo->prepare("UPDATE trivia_preguntas SET publicada_en=? WHERE quiz_id=? AND orden=?")
                ->execute([$ahora, $quiz['id'], $nuevo]);
        }
    } elseif ($op === 'finalizar') {
        $pdo->prepare("UPDATE trivia_quizzes SET estado='finalizado' WHERE id=?")->execute([$quiz['id']]);
    }
    trivia_json(['ok' => true]);
    break;

// ───────────────────────── API: resultados (polling admin) ─────────────────────────
case 'resultados':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) trivia_json(['error' => 'Quiz no encontrado']);
    
    $out = [
        'finalizado'    => $quiz['estado'] === 'finalizado',
        'estado'        => $quiz['estado'],
        'orden'         => (int)$quiz['pregunta_actual'],
        'enunciado'     => '',
        'participantes' => [],
    ];

        if ($out['finalizado']) {
        // Buscamos el puntaje y sumamos el tiempo de las respuestas correctas
        $sql = "
            SELECT pa.nombre, pa.puntaje, 
                   COALESCE(SUM(CASE WHEN r.es_correcta = 1 THEN r.tiempo_ms ELSE 0 END), 0) AS tiempo_total
            FROM trivia_participantes pa
            LEFT JOIN trivia_respuestas r ON r.participante_id = pa.id
            WHERE pa.quiz_id = ?
            GROUP BY pa.id
            ORDER BY pa.puntaje DESC, tiempo_total ASC, pa.nombre ASC
        ";
        $r = $pdo->prepare($sql);
        $r->execute([$quiz['id']]);
        $out['ranking'] = $r->fetchAll();
        trivia_json($out);
    }

    $pr = $pdo->prepare("SELECT * FROM trivia_preguntas WHERE quiz_id=? AND orden=?");
    $pr->execute([$quiz['id'], $quiz['pregunta_actual']]);
    $preg = $pr->fetch();
    
    $out['enunciado'] = $preg['enunciado'] ?? '';

    // Traemos todos los participantes y verificamos si respondieron esta pregunta
    $stmt = $pdo->prepare("
        SELECT pa.nombre, r.tiempo_ms, r.es_correcta 
        FROM trivia_participantes pa
        LEFT JOIN trivia_respuestas r 
            ON r.participante_id = pa.id AND r.pregunta_id = ?
        WHERE pa.quiz_id = ?
    ");
    $stmt->execute([$preg['id'] ?? 0, $quiz['id']]);
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($participantes as $row) {
        $out['participantes'][] = [
            'nombre'    => $row['nombre'],
            'respondio' => $row['tiempo_ms'] !== null,
            'correcta'  => (bool)$row['es_correcta'],
            'tiempo_ms' => (int)$row['tiempo_ms'],
        ];
    }
    trivia_json($out);
    break;

// ───────────────────────── API: reset ─────────────────────────
case 'reset':
    $quiz = cargar_quiz($pdo, trivia_get('codigo'));
    if (!$quiz) trivia_json(['error' => 'Quiz no encontrado']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE r FROM trivia_respuestas r
                       INNER JOIN trivia_participantes p ON p.id = r.participante_id
                       WHERE p.quiz_id = ?")->execute([$quiz['id']]);
        $pdo->prepare("DELETE FROM trivia_participantes WHERE quiz_id=?")->execute([$quiz['id']]);
        $pdo->prepare("UPDATE trivia_preguntas SET publicada_en=NULL WHERE quiz_id=?")->execute([$quiz['id']]);
        $pdo->prepare("UPDATE trivia_quizzes SET estado='esperando', pregunta_actual=0 WHERE id=?")
            ->execute([$quiz['id']]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        trivia_json(['error' => 'No se pudo reiniciar']);
    }
    trivia_json(['ok' => true]);
    break;

default:
    http_response_code(404);
    echo '404';
}
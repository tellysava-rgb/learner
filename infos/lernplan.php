<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quellen-daten.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lernplan-Rechner — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', 'wissen.php'], ['Lernplan-Rechner', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1"><i class="bi bi-clock-history text-primary"></i> Lernplan-Rechner</h1>
    <p class="text-muted mb-4">
        Wähle Zielgruppe, Niveau und verfügbare Zeit — und erhalte einen konkreten Aktivitätenplan. Die Aufteilung
        basiert auf den evidenzbasierten Zeitbudgets aus der Studienlage (siehe „Studienlage im Überblick"), nicht auf
        einer experimentell exakt bewiesenen Formel — die Forschung zeigt gut, <em>welche</em> Aktivitäten wirken,
        weniger gut die exakte optimale Minutenverteilung.
    </p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">

            <label class="form-label small fw-semibold mb-1">Für wen?</label>
            <div class="btn-group w-100 mb-3" role="group" aria-label="Zielgruppe">
                <input type="radio" class="btn-check" name="zielgruppe" id="zg-erwachsen" value="erwachsen" checked>
                <label class="btn btn-outline-primary" for="zg-erwachsen"><i class="bi bi-person"></i> Erwachsene</label>
                <input type="radio" class="btn-check" name="zielgruppe" id="zg-kind" value="kind">
                <label class="btn btn-outline-primary" for="zg-kind"><i class="bi bi-emoji-smile"></i> Kinder (6–12)</label>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold mb-1" for="lp-level">Niveau</label>
                    <select class="form-select" id="lp-level"></select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold mb-1" for="lp-minuten">Verfügbare Zeit (Minuten)</label>
                    <input type="number" class="form-control" id="lp-minuten" value="30" min="5" max="180" step="1">
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary lp-quick" data-min="10">10 Min.</button>
                <button type="button" class="btn btn-sm btn-outline-secondary lp-quick" data-min="15">15 Min.</button>
                <button type="button" class="btn btn-sm btn-outline-secondary lp-quick" data-min="30">30 Min.</button>
                <button type="button" class="btn btn-sm btn-outline-secondary lp-quick" data-min="60">60 Min.</button>
                <button type="button" class="btn btn-sm btn-outline-secondary lp-quick" data-min="90">90 Min.</button>
            </div>

            <button type="button" id="lp-erstellen" class="btn btn-primary w-100"><i class="bi bi-magic"></i> Plan erstellen</button>
        </div>
    </div>

    <div id="lp-ergebnis"></div>

    <div class="alert alert-light border small mt-3" id="lp-hinweis" style="display:none;"></div>

    <?= render_quellenliste([
        'kim-webb-spacing', 'webb-yanagisawa-intentional', 'webb-uchihara-yanagisawa-incidental',
        'kang-sok-han-formfocus', 'lyster-saito-feedback', 'sangers-extensive-reading',
        'zhang-zhang-vocab-comprehension', 'lee-jang-plonsky-pronunciation', 'yao-he-phonetic-training',
        'vos-spoken-input', 'yi-zhong-multiword', 'schulz-multiword-review',
    ], 'accQuellenLernplan') ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
// Prozentuale Zeitverteilung je Zielgruppe/Niveau — abgeleitet aus den Beispiel-Lernplänen und
// Prozenttabellen der Studienlage (siehe studien.php). Bewusst als Heuristik gekennzeichnet, nicht
// als experimentell exakt bewiesene Formel (siehe Hinweistext oben auf dieser Seite).
var PLAENE = {
    erwachsen: {
        levels: [
            { value: 'A1', label: 'A1 — absoluter Anfang' },
            { value: 'A2', label: 'A2 — Grundlagen' },
            { value: 'B1', label: 'B1 — Mittelstufe' },
            { value: 'B2', label: 'B2 — obere Mittelstufe' },
            { value: 'C1', label: 'C1 — fortgeschritten' },
            { value: 'C2', label: 'C2 — annähernd muttersprachlich' },
        ],
        buckets: {
            A1: 'a1a2', A2: 'a1a2', B1: 'b1b2', B2: 'b1b2', C1: 'c1plus', C2: 'c1plus',
        },
        plans: {
            a1a2: [
                { key: 'wortschatz', label: 'Wörter & feste Wendungen üben', icon: 'bi-card-text', pct: 25,
                  desc: 'Karteikarten (digital oder auf Papier): erst die alten Wörter ohne Vorlage abrufen — am besten in beide Richtungen, z.B. Englisch→Deutsch UND Deutsch→Englisch — danach ein paar neue Wörter dazunehmen.' },
                { key: 'grammatik', label: 'Grammatik / Zeitform', icon: 'bi-diagram-3', pct: 15,
                  desc: 'Eine einzelne Regel anschauen (z.B. „ich bin" vs. „du bist"), dazu 3–5 Beispielsätze lesen — danach selbst 2–3 eigene Sätze mit dieser Regel bilden.' },
                { key: 'lesen', label: 'Lesen', icon: 'bi-book', pct: 15,
                  desc: 'Einen leichten Text lesen, den du fast komplett verstehst, ohne ständig nachzuschlagen — lieber ein zu einfaches Buch/ein zu einfacher Artikel als ein zu schwerer.' },
                { key: 'hoeren', label: 'Hören', icon: 'bi-headphones', pct: 15,
                  desc: 'Einen Podcast oder ein Hörbuch auf deinem Niveau hören — aktiv, z.B. mit der Aufgabe, danach in 1–2 eigenen Sätzen zusammenzufassen, worum es ging.' },
                { key: 'sprechen', label: 'Sprechen', icon: 'bi-chat-dots', pct: 15,
                  desc: 'Ein paar kurze, vorbereitete Sätze laut aussprechen — z.B. mit einem Sprachpartner, einer Sprach-KI (Sprachmodus von Claude/ChatGPT) oder einfach laut für dich selbst üben.' },
                { key: 'schreiben', label: 'Schreiben', icon: 'bi-pencil', pct: 5,
                  desc: 'Ein bis zwei eigene Sätze mit den heute gelernten Wörtern aufschreiben.' },
                { key: 'aussprache', label: 'Aussprache', icon: 'bi-mic', pct: 10,
                  desc: 'Einen Laut üben, der dir schwerfällt (z.B. das englische „th" in „think") — ein Wort mehrmals von einer Aufnahme anhören und nachsprechen.' },
            ],
            b1b2: [
                { key: 'wortschatz', label: 'Wörter & feste Wendungen üben', icon: 'bi-card-text', pct: 15,
                  desc: 'Nicht mehr nur einzelne Wörter üben, sondern zunehmend ganze Redewendungen (z.B. „sich Sorgen machen um" statt nur „Sorgen") und eigene Fehler aus früheren Übungen — wieder am besten in beide Richtungen.' },
                { key: 'grammatik', label: 'Grammatik / Zeitform', icon: 'bi-diagram-3', pct: 10,
                  desc: 'Eine Grammatikstruktur kurz auffrischen (z.B. eine Vergangenheitsform), danach sofort in einem eigenen Satz oder einer kleinen Übung anwenden.' },
                { key: 'lesen', label: 'Lesen', icon: 'bi-book', pct: 20,
                  desc: 'Meistens viel und flüssig lesen (Bücher, Artikel) ohne jedes unbekannte Wort nachzuschlagen. Ab und zu stattdessen einen kurzen Text ganz genau durcharbeiten: jedes unklare Wort und jeden unklaren Satz nachschlagen und verstehen.' },
                { key: 'hoeren', label: 'Hören', icon: 'bi-headphones', pct: 20,
                  desc: 'Podcasts oder Videos hören, die zunehmend näher am normalen Sprechtempo sind. Verstehst du eine Stelle nicht: viele Podcast-Apps bieten die schriftliche Abschrift des Gesagten (das „Transkript") an — die unklare Stelle dort nachlesen, danach nochmal anhören.' },
                { key: 'sprechen', label: 'Sprechen', icon: 'bi-chat-dots', pct: 20,
                  desc: 'Echte Gespräche führen, oder eine gelesene/gehörte Geschichte in eigenen Worten nacherzählen, oder über ein Thema diskutieren — am besten mit jemandem, der deine Fehler korrigiert.' },
                { key: 'schreiben', label: 'Schreiben', icon: 'bi-pencil', pct: 10,
                  desc: 'Einen kurzen Text schreiben (z.B. deine Meinung zu einem Thema), Fehler korrigieren lassen — danach die gleiche Kernaussage nochmal frei, ohne den korrigierten Text vor Augen, mündlich erklären.' },
                { key: 'aussprache', label: 'Aussprache', icon: 'bi-mic', pct: 5,
                  desc: 'An den Lauten arbeiten, die dir immer wieder schwerfallen, und daran, wie die Stimme innerhalb eines Satzes steigt und fällt (z.B. bei einer Frage) — am besten mit einer Aufnahme vergleichen und nachahmen.' },
            ],
            c1plus: [
                { key: 'wortschatz', label: 'Wörter & feste Wendungen üben', icon: 'bi-card-text', pct: 10,
                  desc: 'Gezielt das üben, was dir beim eigenen Sprechen/Schreiben zuletzt gefehlt hat — seltenere Wörter, oder die passende Sprachebene für eine Situation (z.B. locker unter Freunden vs. förmlich im Beruf).' },
                { key: 'grammatik', label: 'Grammatik / Präzision', icon: 'bi-diagram-3', pct: 5,
                  desc: 'Nicht Grundlagen wiederholen, sondern gezielt die Fehler angehen, die dir selbst immer wieder passieren — z.B. eine Struktur auswählen, bei der du oft unsicher bist, und bewusst üben.' },
                { key: 'lesen', label: 'Lesen', icon: 'bi-book', pct: 22,
                  desc: 'Anspruchsvolle Texte lesen, die eigentlich für Muttersprachler gemacht sind — am besten zu einem Thema, das dich wirklich interessiert oder mit deinem Beruf zu tun hat.' },
                { key: 'hoeren', label: 'Hören', icon: 'bi-headphones', pct: 23,
                  desc: 'Podcasts, Nachrichtensendungen oder Vorträge hören, die für Muttersprachler gemacht sind — im normalen, nicht extra verlangsamten Sprechtempo.' },
                { key: 'sprechen', label: 'Sprechen', icon: 'bi-chat-dots', pct: 20,
                  desc: 'Anspruchsvolle Diskussionen führen und bewusst die Sprachebene wechseln (z.B. dasselbe Thema einmal locker, einmal förmlich erklären) — dir dazu Feedback von jemandem geben lassen, der die Sprache sehr gut beherrscht.' },
                { key: 'schreiben', label: 'Schreiben', icon: 'bi-pencil', pct: 15,
                  desc: 'Einen längeren Text schreiben (z.B. einen Blogartikel oder Aufsatz), bewusst auf Stil und Wortwahl achten, und dir dazu Feedback geben lassen.' },
                { key: 'aussprache', label: 'Aussprache', icon: 'bi-mic', pct: 5,
                  desc: 'Feinschliff: an der Satzmelodie und den letzten einzelnen Lauten arbeiten, die noch auffallen — sowie bewusst die passende Sprachebene für verschiedene Situationen üben.' },
            ],
        },
    },
    kind: {
        levels: [
            { value: '6-8', label: '6–8 Jahre' },
            { value: '9-12', label: '9–12 Jahre' },
        ],
        buckets: { '6-8': '6-8', '9-12': '9-12' },
        plans: {
            '6-8': [
                { key: 'alt', label: 'Alte Wörter wiederholen', icon: 'bi-arrow-repeat', pct: 16.7,
                  desc: 'Karten/Bilder zeigen, die laut Karteikarten-System gerade wieder dran sind (nicht die neuesten) — z.B. ein Bild zeigen und das Kind sagen lassen, was es bedeutet, oder ein Wort vorspielen und das passende Bild suchen lassen.' },
                { key: 'neu', label: 'Neue Wörter lernen', icon: 'bi-card-text', pct: 16.7,
                  desc: 'Ein paar neue Wörter einführen: Wort hören + Bild dazu sehen + kurz die Bedeutung erklären, bei Bedarf mit der deutschen Übersetzung.' },
                { key: 'story', label: 'Hörspiel / Mini-Geschichte', icon: 'bi-book-half', pct: 13.3,
                  desc: 'Eine kurze Geschichte hören oder anschauen, in der die neuen Wörter mehrmals vorkommen — einfach zuhören/zuschauen und die Wörter im Zusammenhang wiedererkennen.' },
                { key: 'abruf', label: 'Ohne Hilfe antworten', icon: 'bi-lightning', pct: 13.3,
                  desc: 'Nicht mehr nur wiedererkennen, sondern selbst antworten: eine einfache Frage stellen (z.B. „How does Ben feel?") und das Kind aus dem Kopf antworten lassen, ohne Bild oder Vorlage.' },
                { key: 'aussprache', label: 'Nachsprechen & Aussprache', icon: 'bi-mic', pct: 13.3,
                  desc: 'Ein Wort oder einen kurzen Satz vorsprechen (z.B. von einer Aufnahme), das Kind spricht es nach — danach noch einmal, aber diesmal ohne die Vorlage zu hören.' },
                { key: 'transfer', label: 'In neuer Situation anwenden', icon: 'bi-chat-dots', pct: 13.3,
                  desc: 'Das gelernte Wort/den Satz nicht am gleichen Beispiel wiederholen, sondern in einer neuen Situation benutzen lassen, z.B. mit einem anderen Bild, einer anderen Person oder einem anderen Spielzeug.' },
                { key: 'mix', label: 'Alles gemischt wiederholen', icon: 'bi-shuffle', pct: 13.3,
                  desc: 'Zum Schluss der Übungseinheit heutige und ältere Wörter durcheinander abfragen — das sorgt dafür, dass alles noch ein zweites Mal im Kopf abgerufen wird.' },
            ],
            '9-12': [
                { key: 'review', label: 'Alles gemischt wiederholen', icon: 'bi-arrow-repeat', pct: 20,
                  desc: 'Karteikarten/Wörter wiederholen, die laut System gerade wieder dran sind — abwechselnd anhören, laut sagen und kurz aufschreiben, damit es länger im Gedächtnis bleibt.' },
                { key: 'neu', label: 'Neue Wörter/Sätze lernen', icon: 'bi-card-text', pct: 16.7,
                  desc: 'Neue Wörter oder eine neue Satzform einführen: Bedeutung erklären und zeigen, wie man sie richtig bildet/benutzt.' },
                { key: 'input', label: 'Text, Dialog oder Video', icon: 'bi-book', pct: 13.3,
                  desc: 'Einen kurzen, verständlichen Text lesen oder ein kurzes Video/Gespräch anhören, in dem die gelernten Wörter im Zusammenhang vorkommen.' },
                { key: 'abruf', label: 'Ohne Hilfe antworten', icon: 'bi-lightning', pct: 13.3,
                  desc: 'Fragen zum gerade Gelernten beantworten oder Lücken in einem Satz ausfüllen — ohne Vorlage, nur aus dem Kopf.' },
                { key: 'sprechen', label: 'Sprechen & Aussprache üben', icon: 'bi-mic', pct: 13.3,
                  desc: 'Laut sprechen: kurze Antworten selbst formulieren und dabei auf die Aussprache achten — am besten mit jemandem, der Rückmeldung gibt, ob es richtig klingt.' },
                { key: 'grammatik', label: 'Neue Satzform anwenden', icon: 'bi-diagram-3', pct: 13.3,
                  desc: 'Die gerade gelernte Regel oder Redewendung sofort in einem eigenen kurzen Satz oder Mini-Gespräch benutzen, statt sie nur auswendig zu wissen.' },
                { key: 'spacing', label: 'Heutiges nochmal abrufen', icon: 'bi-hourglass-split', pct: 10,
                  desc: 'Am Ende der Übungseinheit die heute neu gelernten Wörter/Sätze noch einmal ohne jede Hilfe abfragen, um zu prüfen, ob sie wirklich hängengeblieben sind.' },
            ],
        },
    },
};

var levelSelect = document.getElementById('lp-level');
var minutenInput = document.getElementById('lp-minuten');
var ergebnisDiv = document.getElementById('lp-ergebnis');
var hinweisDiv = document.getElementById('lp-hinweis');

function aktuelleZielgruppe() {
    return document.querySelector('input[name="zielgruppe"]:checked').value;
}

function levelOptionenAktualisieren() {
    var zg = aktuelleZielgruppe();
    var levels = PLAENE[zg].levels;
    levelSelect.innerHTML = '';
    levels.forEach(function (l) {
        var opt = document.createElement('option');
        opt.value = l.value;
        opt.textContent = l.label;
        levelSelect.appendChild(opt);
    });
}

document.querySelectorAll('input[name="zielgruppe"]').forEach(function (radio) {
    radio.addEventListener('change', levelOptionenAktualisieren);
});
document.querySelectorAll('.lp-quick').forEach(function (btn) {
    btn.addEventListener('click', function () {
        minutenInput.value = btn.dataset.min;
    });
});

// Grösster-Rest-Verfahren: rundet Minuten je Kategorie so, dass die Summe exakt der
// eingegebenen Gesamtzeit entspricht (statt durch simples Runden pro Zeile Minuten zu verlieren
// oder zu viele zu erzeugen).
function minutenVerteilen(kategorien, gesamtMinuten) {
    var roh = kategorien.map(function (k) { return gesamtMinuten * k.pct / 100; });
    var abgerundet = roh.map(Math.floor);
    var rest = gesamtMinuten - abgerundet.reduce(function (a, b) { return a + b; }, 0);
    var reste = roh.map(function (v, i) { return { i: i, rest: v - abgerundet[i] }; });
    reste.sort(function (a, b) { return b.rest - a.rest; });
    for (var j = 0; j < rest; j++) {
        abgerundet[reste[j % reste.length].i] += 1;
    }
    return abgerundet;
}

document.getElementById('lp-erstellen').addEventListener('click', function () {
    var zg = aktuelleZielgruppe();
    var level = levelSelect.value;
    var minuten = parseInt(minutenInput.value, 10);
    if (!minuten || minuten < 1) { minuten = 1; }

    var bucketKey = PLAENE[zg].buckets[level];
    var kategorien = PLAENE[zg].plans[bucketKey];
    var minutenListe = minutenVerteilen(kategorien, minuten);

    var html = '<div class="card border-0 shadow-sm"><div class="card-body">';
    html += '<h2 class="h6 card-title mb-3"><i class="bi bi-list-check text-primary"></i> Dein Plan für ' + minuten + ' Minuten</h2>';
    html += '<div class="list-group list-group-flush">';
    kategorien.forEach(function (k, i) {
        var min = minutenListe[i];
        if (min <= 0) { return; }
        html += '<div class="list-group-item px-0">';
        html += '<div class="d-flex justify-content-between align-items-start gap-2">';
        html += '<div><i class="bi ' + k.icon + ' text-primary me-1"></i><strong>' + min + ' Min. — ' + k.label + '</strong>';
        html += '<div class="text-muted small">' + k.desc + '</div></div>';
        html += '</div></div>';
    });
    html += '</div></div></div>';
    ergebnisDiv.innerHTML = html;

    if (minuten < 15) {
        hinweisDiv.style.display = 'block';
        hinweisDiv.innerHTML = '<i class="bi bi-lightbulb"></i> Bei sehr wenig Zeit lieber weniger Kategorien dafür regelmässiger durchziehen, als jeden Tag alle Bereiche in wenigen Minuten anzureissen — Regelmässigkeit über Tage/Wochen zählt mehr als die Zusammensetzung einer einzelnen kurzen Session.';
    } else {
        hinweisDiv.style.display = 'none';
    }
});

// Initialisierung
levelOptionenAktualisieren();
document.getElementById('lp-erstellen').click();
</script>
</body>
</html>

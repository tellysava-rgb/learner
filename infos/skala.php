<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quellen-daten.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
}

/**
 * Rendert einen einklappbaren Bereich (eigenständiges Bootstrap-Accordion-Item).
 * $inhaltHtml ist hier ein leerer Platzhalter-Container, den JS mit den
 * niveau-abhängigen Methoden-Zeilen befüllt (siehe <script> unten).
 */
function skala_bereich(string $titel, string $icon, string $bodyId, string $inhaltHtml, bool $offen = false): string {
    $html = '<div class="accordion mb-3" id="' . htmlspecialchars($bodyId, ENT_QUOTES) . '-acc">';
    $html .= '<div class="accordion-item">';
    $html .= '<h2 class="accordion-header">';
    $html .= '<button class="accordion-button' . ($offen ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . htmlspecialchars($bodyId, ENT_QUOTES) . '">';
    $html .= '<i class="bi ' . htmlspecialchars($icon, ENT_QUOTES) . ' me-2"></i> ' . htmlspecialchars($titel);
    $html .= '</button></h2>';
    $html .= '<div id="' . htmlspecialchars($bodyId, ENT_QUOTES) . '" class="accordion-collapse collapse' . ($offen ? ' show' : '') . '">';
    $html .= '<div class="accordion-body">' . $inhaltHtml . '</div>';
    $html .= '</div></div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Methoden-Skala 1–10 — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', 'wissen.php'], ['Methoden-Skala', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1"><i class="bi bi-bar-chart-steps text-primary"></i> Methoden-Skala 1–10</h1>
    <p class="text-muted mb-3">1 = kaum messbare Wirkung laut Forschung · 10 = durchgehend starke, gut belegte Wirkung.
        Wähle unten dein Niveau — die Werte passen sich an, weil manche Methoden bei Anfängern besser wirken und
        andere erst mit mehr Erfahrung.</p>

    <div class="card border-primary-subtle bg-primary-subtle mb-3 sticky-top shadow-sm" style="top:0;">
        <div class="card-body py-3">
            <h2 class="h6 card-title mb-2"><i class="bi bi-signpost-2 text-primary"></i> Dein Niveau</h2>
            <div class="btn-group flex-wrap" role="group" aria-label="Niveau wählen" id="lp-level-group">
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="a1a2" data-cefr="A1">A1</button>
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="a1a2" data-cefr="A2">A2</button>
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="b1b2" data-cefr="B1">B1</button>
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="b1b2" data-cefr="B2">B2</button>
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="c1plus" data-cefr="C1">C1</button>
                <button type="button" class="btn btn-outline-primary skala-level-btn" data-level="c1plus" data-cefr="C2">C2</button>
            </div>
            <p class="small text-muted mt-2 mb-0">A = Anfänger (A1–A2) · B = Mittel (B1–B2) · C = Fortgeschritten (C1–C2).
                Die Forschung unterscheidet selten präziser als in diesen drei Gruppen — A1 und A2 zeigen deshalb
                bewusst dieselben Werte, ebenso B1/B2 und C1/C2.</p>
        </div>
    </div>

    <?php
    $hinweiseHtml = '<p class="small mb-1"><i class="bi bi-diagram-3 text-primary"></i> <strong>Methoden schliessen sich nicht '
        . 'gegenseitig aus.</strong> Die Liste ist ein Werkzeugkasten, kein Entweder-oder — die meisten guten '
        . 'Lernpläne kombinieren mehrere davon gleichzeitig, z.B. Chunks lernen UND Podcasts hören UND gezieltes '
        . 'Aussprachetraining. Ein einzelner Wert zeigt nur, wie stark diese eine Zutat für sich beiträgt, nicht '
        . 'welche Methode du stattdessen wählen sollst.</p>'
        . '<hr class="my-2">'
        . '<p class="small mb-1"><i class="bi bi-exclamation-triangle text-warning"></i> <strong>Eigene, '
        . 'kommunikative Vereinfachung</strong> — keine wissenschaftlich validierte Kennzahl. Die zugrunde '
        . 'liegenden Meta-Analysen verwenden unterschiedliche Kontrollgruppen, Tests und Studiendesigns; ihre '
        . 'Effektstärken direkt gegeneinander zu ranken, wäre methodisch nicht sauber (das betonen die '
        . 'Originalstudien selbst ausdrücklich). Die Zahlen fassen zusammen, <em>wie konsistent und wie gut '
        . 'belegt</em> eine Methode abschneidet — als grobe Orientierung, nicht als Präzisionsmessung. Auch die '
        . 'Niveau-abhängigen Werte sind eine plausible, aus der Studienlage abgeleitete Einschätzung — so gut wie '
        . 'keine Studie misst dieselbe Methode separat für jedes CEFR-Niveau. Wo die Forschung keinen '
        . 'Anhaltspunkt für einen Unterschied liefert, bleibt der Wert über alle Niveaus gleich.</p>'
        . '<hr class="my-2">'
        . '<p class="small mb-2"><i class="bi bi-search text-primary"></i> <strong>Zwei getrennte Anzeigen pro '
        . 'Methode, die nichts miteinander zu tun haben:</strong> der farbige Balken zeigt nur die Zahl 1–10 '
        . 'selbst — <em>wie stark die Wirkung ist</em>. Das blaue/graue Badge zeigt dagegen, <em>wie verlässlich '
        . 'diese Einschätzung ist</em> — ob viele/grosse, konsistente Studien dahinterstehen oder nur '
        . 'wenige/kleine. Beispiel:</p>'
        . '<div class="border rounded p-2 bg-white mb-1">'
        . '<div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1">'
        . '<strong class="small">Beispiel-Methode</strong>'
        . '<span class="text-nowrap"><span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-search"></i> Evidenz: hoch</span> <span class="fw-bold small">7/10</span></span>'
        . '</div>'
        . '<div class="progress" style="height:8px;"><div class="progress-bar bg-warning" style="width:70%"></div></div>'
        . '</div>'
        . '<p class="text-muted small mb-0">Gelber Balken = mittelstarke Wirkung (7/10) — <strong>gleichzeitig</strong> '
        . 'blaues Badge = hohe Evidenz. Die Kombination ist bewusst möglich: eine Methode mit nur mittlerer '
        . 'Wirkung kann trotzdem sehr verlässlich gemessen sein (viele gute Studien), genauso wie eine Methode '
        . 'mit hoher Wirkung nur schwach belegt sein kann (nur eine kleine Studie).</p>';
    ?>
    <?= skala_bereich('Hinweise zur Skala', 'bi-info-circle', 'accSkalaHinweise', $hinweiseHtml, false) ?>

    <?php
    $chunksIntro = '<div class="card border-0 bg-light mb-3">'
        . '<div class="card-body">'
        . '<h3 class="h6 card-title"><i class="bi bi-info-circle text-primary"></i> Was sind „Chunks"?</h3>'
        . '<p class="small mb-0">Ein <strong>Chunk</strong> (auch Kollokation oder Multi-Word-Unit) ist eine feste, '
        . 'natürlich zusammen auftretende Wortgruppe, die als Ganzes gespeichert und abgerufen wird — statt jedes '
        . 'Wort einzeln zusammenzusetzen. Beispiele: <em>„eine Entscheidung treffen"</em> statt nur '
        . '<em>„Entscheidung"</em>, <em>„to make a decision"</em>, <em>„I was wondering whether …"</em>, '
        . '<em>„it depends on …"</em>. Der Vorteil: Chunks werden nachweislich schneller verarbeitet als frei neu '
        . 'zusammengesetzte Wortfolgen — man muss beim Sprechen nicht mehr jedes Einzelteil grammatisch korrekt '
        . 'zusammenbauen, sondern ruft den ganzen Baustein ab. Ein Chunk deckt zudem gleich mehrere Wörter UND ihre '
        . 'richtige Kombination auf einmal ab, statt nur ein einzelnes Wort. Bei A1/A2 fehlt dafür oft noch die '
        . 'nötige Grundlage an Einzelwörtern — deshalb lohnt sich dort zuerst reines Wörterlernen mehr. Sobald der '
        . 'Grundwortschatz sitzt, kippt das: Ab B1/B2 bringt derselbe Lernaufwand in Chunks mehr als in weiteren '
        . 'Einzelwörtern (siehe Werte unten).</p>'
        . '</div></div>'
        . '<div id="content-chunks"></div>';
    ?>

    <?= skala_bereich('Wörter lernen', 'bi-card-text', 'accSkalaWoerter', '<div id="content-woerter"></div>', false) ?>
    <?= skala_bereich('Chunks lernen', 'bi-link-45deg', 'accSkalaChunks', $chunksIntro, false) ?>
    <?= skala_bereich('Lesen', 'bi-book', 'accSkalaLesen', '<div id="content-lesen"></div>', false) ?>
    <?= skala_bereich('Hören', 'bi-headphones', 'accSkalaHoeren', '<div id="content-hoeren"></div>', false) ?>
    <?= skala_bereich('Sprechen', 'bi-chat-dots', 'accSkalaSprechen', '<div id="content-sprechen"></div>', false) ?>
    <?= skala_bereich('Weitere Methoden im Vergleich', 'bi-grid-3x3-gap', 'accSkalaWeitere', '<div id="content-weitere"></div>', false) ?>

    <?= render_quellenliste([
        'webb-yanagisawa-intentional', 'kim-webb-spacing', 'vos-spoken-input',
        'tonzar-picture-translation', 'kim-lee-lee-l1l2gloss', 'oppici-gestures',
        'webb-uchihara-yanagisawa-incidental',
        'yi-zhong-multiword', 'schulz-multiword-review',
        'lee-jang-plonsky-pronunciation', 'kang-sok-han-formfocus', 'lyster-saito-feedback',
        'mackey-goo-interaction', 'sangers-extensive-reading', 'liu-zhang-dai-mobilegames',
        'tseng-liu-hsu-chu-studyabroad', 'sutton-webb-audiovisual',
        'yao-he-phonetic-training', 'uchihara-karas-thomson-hvpt',
    ], 'accQuellenSkala') ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
// Jede Methode mit Wert je Niveau-Bucket (a1a2 / b1b2 / c1plus). Wo die Forschung keinen
// Anhaltspunkt für einen Niveau-Unterschied liefert, ist der Wert bewusst über alle drei
// Buckets identisch (siehe Warnhinweis oben auf der Seite).
var METHODEN = [
    // ---- Wörter lernen ----
    { bereich: 'woerter', methode: 'Leitner-System / Spaced-Repetition-Karteikarten', beleg: 'hoch',
      anwendung: 'Begriff auf der einen Seite, Übersetzung auf der anderen. Du versuchst die Bedeutung zuerst selbst aus dem Kopf zu sagen, bevor du umdrehst — richtig beantwortete Karten wandern seltener dran, falsch beantwortete öfter. Ob als Papier-Kartei oder App gemacht, spielt keine Rolle. Zusätzlich hilfreich: ein Bild statt/neben der Übersetzung bei konkreten Begriffen, und den Begriff beim Lernen zusätzlich zu hören (Audio).',
      begruendung: 'Der am robustesten belegte Wortschatz-Mechanismus überhaupt — sowohl der aktive Abruf (sich selbst prüfen statt nur durchlesen) als auch die gestaffelte Wiederholung sind unabhängig voneinander stark belegt; nur durchlesen ohne sich selbst zu prüfen ist deutlich schwächer. Bilder helfen zusätzlich besonders bei konkreten Begriffen für Anfänger, bei abstrakterem Wortschatz auf höherem Niveau weniger.',
      w: { a1a2: 9, b1b2: 9, c1plus: 9 } },
    { bereich: 'woerter', methode: 'Beiläufiges Wortschatzlernen (aus Kontext beim Lesen/Hören)', beleg: 'mittel',
      anwendung: 'Einfach viel lesen oder hören und Wörter aus dem Zusammenhang erschliessen, statt sie separat zu pauken.',
      begruendung: 'Real messbar, aber pro investierter Minute langsamer als gezieltes Lernen. Bei A1/A2 bleibt kaum etwas hängen, weil zu wenig verständlich ist — ab B1 wird es brauchbar, ab C1 sogar zu einer der Hauptquellen für neuen Wortschatz, weil man dann grosse Mengen verständlichen Text/Audio konsumiert.',
      w: { a1a2: 3, b1b2: 6, c1plus: 8 } },

    // ---- Chunks lernen ----
    { bereich: 'chunks', methode: 'Chunks/Redewendungen statt Einzelwörter lernen', beleg: 'mittel',
      anwendung: 'Statt Einzelwörtern ganze Redewendungen lernen und abfragen, z.B. „to make a decision" statt nur „decision" — am besten wieder mit Karteikarten und aktivem Abruf in beide Richtungen (en→de UND de→en).',
      begruendung: 'Guter, gut belegter Verarbeitungsvorteil bekannter Chunks. Bei A1/A2 helfen v.a. kurze, fixe Alltagsfloskeln wie „Can I have …?" — für einen vollen Chunk-Fokus fehlt oft noch die Wortschatzbasis, Einzelwörter bringen dort mehr. Sobald der Grundwortschatz sitzt (ab B1), lohnt sich derselbe Lernaufwand mehr in Chunks statt in weiteren Einzelwörtern — ein Chunk deckt gleich mehrere Wörter UND ihre richtige Kombination auf einmal ab. Deshalb überholt „Chunks lernen" ab B1/B2 das reine Einzelwörter-Pauken und bleibt bis C1/C2 vorne.',
      w: { a1a2: 5, b1b2: 8, c1plus: 9 } },

    // ---- Lesen ----
    { bereich: 'lesen', methode: 'Extensives Lesen (viel, leicht, im Lesefluss)', beleg: 'hoch',
      anwendung: 'Bücher/Texte lesen, die du zu ca. 98 % ohne Nachschlagen verstehst, und dabei NICHT jedes unbekannte Wort nachschlagen — einfach weiterlesen und den Lesefluss geniessen.',
      begruendung: 'Positive Effekte über mehrere Sprachbereiche, aber nur wenn genug verständlich ist. Bei A1/A2 gibt es kaum passende, wirklich verständliche Texte — der Nutzen ist deshalb gering. Ab A2/B1 wird es sinnvoll, ab B2/C1 eine der wertvollsten Methoden überhaupt.',
      w: { a1a2: 3, b1b2: 7, c1plus: 8 } },
    { bereich: 'lesen', methode: 'Intensives Lesen (wenige Texte gründlich durcharbeiten)', beleg: 'gering',
      anwendung: 'Ab und zu einen kurzen Text ganz genau bearbeiten: jedes unklare Wort und jeden unklaren Satz nachschlagen und wirklich verstehen — als Ergänzung zum vielen, leichten Lesen, nicht als Ersatz.',
      begruendung: 'Sinnvolle Ergänzung für gezielte Grammatik- und Wortschatzarbeit. Braucht genug Grundwortschatz, um nicht bei jedem zweiten Wort nachschlagen zu müssen — deshalb erst ab B1 wirklich ergiebig. Nicht selbst Gegenstand eigener Meta-Analysen, sondern eine plausible Ergänzung aus der Fachdiskussion.',
      w: { a1a2: 3, b1b2: 6, c1plus: 6 } },

    // ---- Hören ----
    { bereich: 'hoeren', methode: 'Aktives, aufgabenbasiertes Hören', beleg: 'hoch',
      anwendung: 'Beim Hören/Schauen ein konkretes Ziel oder eine Aufgabe haben, statt nur berieseln zu lassen — z.B. gezielt ein Erklärvideo oder einen Sprachlern-Podcast zum eigenen Niveau nutzen, danach eine Frage dazu beantworten, eine Anweisung befolgen, ein Bild zuordnen.',
      begruendung: 'Deutlicher Vorteil gegenüber passivem Zuhören — sowohl weil eine Aufgabe die Aufmerksamkeit hält, als auch weil lehrorientierte Inhalte (Doku, Erklärvideo) bei vergleichbarer Länge wirksamer sind als reine Unterhaltung. Besonders wichtig bei A1/A2, wo man sonst schnell den Anschluss verliert; ab B1 kann man Bedeutung zunehmend auch ohne feste Aufgabe erschliessen.',
      w: { a1a2: 8, b1b2: 6, c1plus: 6 } },
    { bereich: 'hoeren', methode: 'Shadowing (Gesprochenes gleichzeitig/direkt danach nachsprechen)', beleg: 'gering',
      anwendung: 'Eine Aufnahme abspielen und praktisch gleichzeitig mitsprechen (oder Satz für Satz sofort danach nachsprechen) — Fokus auf Rhythmus und Klang, nicht auf einzelne Wörter.',
      begruendung: 'Sinnvolles Zusatzwerkzeug, aber schwächere Evidenzbasis als gezieltes Aussprachetraining mit Feedback. Bei A1/A2 ist das Sprechtempo für die meisten noch zu hoch, um mitzuhalten — erst ab B1 wirklich machbar.',
      w: { a1a2: 2, b1b2: 5, c1plus: 6 } },
    { bereich: 'hoeren', methode: 'Passives Hintergrund-Hören (ohne Aufgabe, ohne passendes Niveau)', beleg: 'gering',
      anwendung: 'Fremdsprachiges Audio/Video nebenbei laufen lassen, ohne bewusst zuzuhören oder eine Aufgabe zu haben — z.B. Radio beim Kochen, oder eine Serie weit über dem eigenen Niveau schauen nach dem Motto „irgendwann gewöhnt sich das Ohr schon".',
      begruendung: 'Ohne Aufmerksamkeit, Aufgabe oder passendes Niveau bleibt kaum etwas hängen — auf allen Niveaus schwach, besonders wirkungslos bei Anfängern, die noch keine Anhaltspunkte zum Andocken haben. Mit steigendem Niveau etwas weniger schwach, weil die Lücke zu echtem Muttersprachler-Tempo kleiner wird. Siehe Mythos-Check.',
      w: { a1a2: 1, b1b2: 3, c1plus: 4 } },

    // ---- Sprechen ----
    { bereich: 'sprechen', methode: 'Gezieltes Aussprachetraining (Laute hören, nachsprechen, Feedback bekommen)', beleg: 'hoch',
      anwendung: 'Im Grunde ein klassischer Sprachkurs mit Lehrperson oder Sprachtandem: gezielt einzelne Laute oder Satzmelodien hören, selbst nachsprechen und eine Rückmeldung bekommen, ob es richtig klingt — auch Apps mit Ausspracheerkennung können den Feedback-Teil übernehmen.',
      begruendung: 'Grosse, verlässliche Effekte in mehreren Meta-Analysen — auf allen Niveaus wirksam, nur das Ziel ändert sich: bei A1/A2 Grundlaute, später feinere Unterschiede und Satzmelodie.',
      w: { a1a2: 9, b1b2: 9, c1plus: 9 } },
    { bereich: 'sprechen', methode: 'Konversation/Interaktion mit Fehlerkorrektur', beleg: 'hoch',
      anwendung: 'Echte Gespräche führen und dabei Fehler korrigiert bekommen — von einer Lehrperson, einem Sprachtandem oder einem Muttersprachler, der bewusst korrigiert statt nur weiterzuplaudern. Die Korrektur danach aktiv selbst nochmal richtig verwenden, nicht nur passiv registrieren.',
      begruendung: 'Eine der am zuverlässigsten belegten Methoden überhaupt: sowohl der reine Effekt von Fehlerkorrektur als auch echte Interaktion mit Rückmeldung sind auf allen Niveaus stark belegt. Bei A1 ist die Gesprächsfähigkeit noch eingeschränkt, was den Gesamtnutzen leicht senkt — ab A2/B1 voll wirksam.',
      w: { a1a2: 7, b1b2: 9, c1plus: 9 } },
    { bereich: 'sprechen', methode: 'Freies Sprechen/Reden ohne Korrektur', beleg: 'gering',
      anwendung: 'Einfach drauflosreden (z.B. im Café, im Urlaub), ohne dass jemand Fehler korrigiert.',
      begruendung: 'Sprechzeit allein korreliert nicht zuverlässig mit Fortschritt — ohne Korrektur werden Fehler eher gefestigt als abgebaut, auf allen Niveaus gleich schwach. Siehe Mythos-Check.',
      w: { a1a2: 4, b1b2: 4, c1plus: 4 } },
    { bereich: 'sprechen', methode: 'Immersion / aktive Sprachverwendung im Alltag (z.B. Auslandsaufenthalt)', beleg: 'hoch',
      anwendung: 'Möglichst viel echten Alltag in der Zielsprache erleben — im Ausland leben, oder auch zuhause aktiv Gelegenheiten suchen (Vereine, Tandems, Communities) — mit dem Anspruch, aktiv mitzureden, nicht nur passiv dabei zu sein.',
      begruendung: 'Mittelgrosser bis grosser Effekt bei aktiver Sprachverwendung vor Ort. Bei reinem A1 oft überfordernd ohne zusätzliche Unterstützung — ab A2/B1 am ergiebigsten, weil man dann genug Grundlage hat, um aktiv am Alltag teilzunehmen.',
      w: { a1a2: 4, b1b2: 8, c1plus: 8 } },

    // ---- Weitere Methoden im Vergleich ----
    { bereich: 'weitere', methode: 'Formfokussierte/explizite Grammatik-Instruktion', beleg: 'hoch',
      anwendung: 'Eine Grammatikregel gezielt erklärt bekommen (z.B. Bildung des Present Perfect) und danach in eigenen Sätzen anwenden üben — nicht nur die Regel auswendig lernen.',
      begruendung: 'Einer der grössten gemessenen Effekte in der gesamten Sprachlernforschung — besonders wirksam bei A1–B1, wo Grundstrukturen noch fehlen. Ab B2/C1 verschiebt sich der Fokus zunehmend auf Feinheiten statt neuer Grundregeln, der Zusatznutzen wird kleiner.',
      w: { a1a2: 9, b1b2: 9, c1plus: 6 } },
    { bereich: 'weitere', methode: 'Game-Based Learning (Spielhandlung = Sprachaufgabe)', beleg: 'mittel',
      anwendung: 'Ein Spiel spielen, bei dem man die Fremdsprache aktiv braucht, um voranzukommen (z.B. Anweisungen verstehen, Dialoge führen) — nicht nur ein Quiz mit Vokabeln nebenbei. Punkte/Badges/Streaks (Gamification) können zusätzlich helfen, regelmässig dranzubleiben — sie ersetzen aber keine der Methoden hier, weil sie nicht die Sprachverarbeitung selbst verbessern, sondern nur die Motivation.',
      begruendung: 'Grosser Effekt in aktueller Meta-Analyse — für alle Niveaus geeignet, solange die Spielsprache zum eigenen Niveau passt.',
      w: { a1a2: 7, b1b2: 7, c1plus: 7 } },
];

var BEREICHE = ['woerter', 'chunks', 'lesen', 'hoeren', 'sprechen', 'weitere'];

function skalaZeileHtml(m, bucket) {
    var wert = m.w[bucket];
    var pct = wert * 10;
    var farbe = wert >= 8 ? 'bg-success' : (wert >= 5 ? 'bg-warning' : 'bg-danger');
    var belegBadges = {
        hoch:   '<span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-search"></i> Evidenz: hoch</span>',
        mittel: '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-search"></i> Evidenz: durchwachsen/indirekt</span>',
        gering: '<span class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-search"></i> Evidenz: schwach/fehlend</span>',
    };
    var html = '<div class="mb-3">';
    html += '<div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1">';
    html += '<strong>' + m.methode + '</strong>';
    html += '<span class="text-nowrap">' + belegBadges[m.beleg] + ' <span class="fw-bold">' + wert + '/10</span></span>';
    html += '</div>';
    html += '<div class="progress mb-1" style="height:8px;"><div class="progress-bar ' + farbe + '" style="width:' + pct + '%"></div></div>';
    html += '<div class="small mb-1"><i class="bi bi-arrow-right-circle text-primary"></i> <strong>So wendest du es an:</strong> ' + m.anwendung + '</div>';
    html += '<div class="text-muted small">' + m.begruendung + '</div>';
    html += '</div>';
    return html;
}

function skalaRender(bucket) {
    BEREICHE.forEach(function (bereich) {
        var container = document.getElementById('content-' + bereich);
        if (!container) return;
        var html = '';
        METHODEN.filter(function (m) { return m.bereich === bereich; }).forEach(function (m) {
            html += skalaZeileHtml(m, bucket);
        });
        container.innerHTML = html;
    });
}

document.querySelectorAll('.skala-level-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.skala-level-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        skalaRender(btn.dataset.level);
    });
});

// Start: A1 aktiv
document.querySelector('.skala-level-btn[data-cefr="A1"]').classList.add('active');
skalaRender('a1a2');
</script>
</body>
</html>

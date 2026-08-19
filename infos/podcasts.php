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
    <title>Passende Podcasts finden — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', 'wissen.php'], ['Podcasts finden', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1"><i class="bi bi-headphones text-primary"></i> Passende Podcasts finden</h1>
    <p class="text-muted mb-4">
        Hören ist eine der wirksamsten Ergänzungen zum gezielten Lernen — aber nur, wenn der Inhalt zum eigenen Niveau
        passt. Zu schwerer Input lässt kaum Aufmerksamkeit für Sprache übrig; zu leichter Input bringt nach einer
        Weile kaum noch neue Begegnungen. Hier ein paar Wege, um passendes Material zu finden.
    </p>

    <h2 class="h5">Suchbegriffe, die meist funktionieren</h2>
    <p>In der Such- oder Podcast-App direkt mit Niveau- und Tempo-Begriffen suchen statt nur mit dem Sprachnamen:</p>
    <ul>
        <li><code>English learning podcast beginner</code> / <code>intermediate</code> / <code>advanced</code></li>
        <li><code>British English slow easy</code> — für langsameres, klar artikuliertes britisches Englisch</li>
        <li><code>English learning podcast weather / news / everyday topics</code> — Alltagsthemen sind meist
            zugänglicher als Fachthemen</li>
        <li><code>graded podcast [Sprache]</code> oder <code>learn [Sprache] with transcript</code> — Podcasts mit
            verfügbarem Transkript lassen sich bei unklaren Stellen gezielt nachlesen</li>
        <li><code>shadowing podcast [Sprache]</code> — kurze Sätze zum Nachsprechen, meist bewusst langsam gesprochen</li>
        <li><code>[Sprache] podcast for kids</code> — für Kinder: oft einfacherer Wortschatz und klarere Aussprache
            als Erwachsenen-Formate, unabhängig vom Alter als Einstieg nutzbar</li>
    </ul>

    <h2 class="h5 mt-4">Wo suchen</h2>
    <ul>
        <li><strong>Spotify / Apple Podcasts / Google Podcasts:</strong> direkt mit obigen Suchbegriffen probieren —
            die Podcast-Suche der Plattformen selbst reicht für die meisten Sprachlern-Anfragen aus.</li>
        <li><strong>Listen Notes</strong> (<a href="https://www.listennotes.com" target="_blank" rel="noopener">listennotes.com</a>):
            eine der grössten unabhängigen Podcast-Suchmaschinen — durchsucht auch Episodentitel und teils
            Transkripte, gut um sehr spezifisch zu suchen (z.B. nach Thema + Niveau).</li>
        <li><strong>Podchaser</strong> (<a href="https://www.podchaser.com" target="_blank" rel="noopener">podchaser.com</a>):
            Podcast-Datenbank mit Kategorien/Tags — hilfreich, um innerhalb einer Sprache nach Kategorie
            "Language Learning" zu filtern.</li>
    </ul>

    <h2 class="h5 mt-4">Woran man ein passendes Niveau erkennt</h2>
    <p>Statt nach einer festen CEFR-Angabe zu suchen (die auf Podcast-Plattformen ohnehin selten zuverlässig gepflegt
        ist), lieber die ersten 1–2 Minuten einer Episode probehören:</p>
    <ul>
        <li><strong>Verständlichkeits-Faustregel:</strong> Etwa 90–95 % sollten ohne Nachschlagen verständlich sein.
            Deutlich weniger verständlich lässt kaum Aufmerksamkeit für Sprache übrig; nahezu 100 % verständlich
            bringt kaum noch neue Begegnungen mit unbekanntem Wortschatz.</li>
        <li><strong>Sprechtempo:</strong> Für A1–B1 eignen sich meist explizit für Lernende produzierte,
            verlangsamte Formate besser als reguläre Nachrichten- oder Interview-Podcasts für Muttersprachler.</li>
        <li><strong>Themenwahl:</strong> Ein vertrautes Thema (Alltag, bekannte Nachrichtenlage, eigenes Fachgebiet)
            ist leichter zu verstehen als ein unbekanntes Thema in derselben Sprache — Themenwahl kann Niveau-Lücken
            teilweise ausgleichen.</li>
        <li><strong>Lehrorientiert statt reine Unterhaltung:</strong> Für gezielten Fortschritt sind Formate, die
            bewusst fürs Sprachenlernen gemacht sind (mit Erklärungen, Wiederholungen, Transkript), im Schnitt
            wirksamer als normale Unterhaltungspodcasts auf Muttersprachniveau — letztere eignen sich gut als
            zusätzlicher Mengeninput, sobald das Niveau reicht.</li>
    </ul>

    <div class="alert alert-light border small mt-4">
        <i class="bi bi-info-circle"></i> Diese Seite nennt bewusst keine konkreten Podcast-Titel — Angebot und
        Qualität ändern sich laufend, und „der beste Podcast" hängt stark vom eigenen Niveau und Interesse ab. Die
        obigen Suchbegriffe und Kriterien helfen, selbst laufend passendes, aktuelles Material zu finden.
    </div>

    <?= render_quellenliste([
        'sutton-webb-audiovisual', 'dalman-plonsky-listening-strategy', 'zhang-zhang-vocab-comprehension',
    ], 'accQuellenPodcasts') ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

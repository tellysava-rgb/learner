<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

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
    <title>Wissenschaftlich Sprachen lernen — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:900px;">

    <h1 class="h4 mb-1"><i class="bi bi-mortarboard text-primary"></i> Wissenschaftlich Sprachen lernen</h1>
    <p class="text-muted mb-4">
        Sprachlern-Apps versprechen gern „fliessend in 3 Monaten". Was die Forschung tatsächlich zeigt, ist nüchterner —
        aber verlässlicher. Diese Seiten fassen zusammen, was in kontrollierten Studien und Meta-Analysen wirklich
        funktioniert, ohne Marketing-Versprechen und mit nachprüfbaren Quellen.
    </p>

    <div class="row g-3">

        <div class="col-md-6">
            <a href="mythen.php" class="text-decoration-none text-body">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title mb-1"><i class="bi bi-patch-question text-primary"></i> Mythos-Check</h2>
                        <p class="text-muted small mb-0">Café-Plaudern, Sprachduschen, Filme schauen, im Schlaf hören,
                            Birkenbihl — was davon hält was es verspricht, und was passiert stattdessen wirklich.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <a href="studien.php" class="text-decoration-none text-body">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title mb-1"><i class="bi bi-file-earmark-text text-primary"></i> Studienlage im Überblick</h2>
                        <p class="text-muted small mb-0">Was die Forschung zu Wortschatz, Lesen, Hören, Sprechen,
                            Schreiben, Grammatik und Aussprache zeigt — als Fliesstext, mit Quellen am Ende statt
                            ständigem Zitieren.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <a href="skala.php" class="text-decoration-none text-body">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title mb-1"><i class="bi bi-bar-chart-steps text-primary"></i> Methoden-Skala 1–10</h2>
                        <p class="text-muted small mb-0">Wie hilfreich sind Karteikarten, Chunks, Extensive Reading,
                            Shadowing & Co. wirklich? Eine eingeordnete Skala — inklusive der Unterscheidung
                            Wörter lernen vs. Chunks lernen.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <a href="lernplan.php" class="text-decoration-none text-body">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title mb-1"><i class="bi bi-clock-history text-primary"></i> Lernplan-Rechner</h2>
                        <p class="text-muted small mb-0">Level und verfügbare Zeit eingeben — und einen konkreten
                            Aktivitätenplan erhalten, z.B. „6 Min. Wörter lernen / 8 Min. Hören". Für Kinder und
                            Erwachsene.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <a href="podcasts.php" class="text-decoration-none text-body">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title mb-1"><i class="bi bi-headphones text-primary"></i> Passende Podcasts finden</h2>
                        <p class="text-muted small mb-0">Wie man Hörmaterial auf dem eigenen Niveau findet —
                            Suchbegriffe, Kriterien und geeignete Suchmaschinen/Plattformen.</p>
                    </div>
                </div>
            </a>
        </div>

    </div>

    <p class="text-muted small mt-4 mb-0">
        Grundlage sind zwei ausführliche Recherchen zum wissenschaftlich fundierten Sprachenlernen bei Erwachsenen
        und bei Kindern (6–12 Jahre), jeweils mit Verweisen auf Meta-Analysen und Studien. Jede zitierte Quelle
        wurde einzeln recherchiert und wo möglich gegen die Originalpublikation geprüft — Details dazu direkt auf
        den jeweiligen Unterseiten im Bereich „Quellen zu dieser Seite".
    </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

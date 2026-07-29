<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

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
    <title>Hilfe — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Hilfe', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1">Hilfe & Handbuch</h1>
    <p class="text-muted small mb-4">Kurzanleitung zu <?= htmlspecialchars(APP_NAME) ?> — was die Funktionen tun und wie das Lernen dahinter funktioniert.</p>

    <div class="accordion" id="helpAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#h1">
                    Einstieg: Login & Person
                </button>
            </h2>
            <div id="h1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Der Login erfolgt mit deinem Namen und deinem eigenen Passwort — jede Person hat ihren eigenen Lernfortschritt, auch wenn mehrere Personen dieselbe Liste nutzen.</p>
                    <p>Über das 🔑-Icon oben in der Navbar kannst du dein eigenes Passwort ändern und optional eine E-Mail-Adresse hinterlegen — damit kannst du dein Passwort selbst zurücksetzen, falls du es vergisst (Link "Passwort vergessen?" auf der Login-Seite).</p>
                    <p class="mb-0">Neue Personen anlegen sowie Passwörter zurücksetzen kann nur ein <strong>Admin</strong> (Benutzerverwaltung).</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h2">
                    Wortlisten verwalten
                </button>
            </h2>
            <div id="h2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Eine <strong>Liste</strong> bündelt Karten zu einem Sprachenpaar (z.B. Deutsch/Englisch) und legt fest, welche Seite welche Sprache ist. Unter <em>Meine Listen</em> kannst du Listen erstellen, umbenennen, öffentlich stellen und löschen.</p>
                    <ul class="mb-2">
                        <li><strong>Öffentlich vs. privat:</strong> Öffentliche Listen erscheinen bei anderen Personen unter <em>Entdecken</em> und können dort kopiert werden.</li>
                        <li><strong>Liste migrieren:</strong> Karten (inkl. Lernfortschritt) lassen sich in eine andere eigene Liste verschieben, z.B. um zwei Listen zusammenzulegen.</li>
                        <li><strong>Aussprache-Dialekt:</strong> Optional lässt sich pro Liste ein Dialekt für Sprache B hinterlegen (z.B. <code>en-GB</code>) — aktiviert den 🔊-Ausspracheknopf und Lautschrift, siehe unten.</li>
                    </ul>
                    <p class="mb-0">Auf der Startseite zeigt jede Liste zusätzlich, wie viele Karten aktuell in der <strong>Warteschlange</strong> warten und wie viele heute im Leitner-System fällig sind.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h3">
                    Wörter hinzufügen
                </button>
            </h2>
            <div id="h3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Drei Wege, um Karten in eine Liste zu bringen:</p>
                    <ul class="mb-2">
                        <li><strong>Manuell:</strong> auf der Kartenübersicht einer Liste einzeln hinzufügen, bearbeiten oder archivieren.</li>
                        <li><strong>CSV-Import:</strong> Datei mit mehreren Karten auf einmal hochladen (Format wird auf der Import-Seite erklärt). Dort steht auch ein fertiger <strong>Prompt für eine KI</strong> bereit, mit dem sich passende Wortlisten zu einem Thema generieren lassen — einfach kopieren, Thema ergänzen und einer KI wie Claude oder ChatGPT geben.</li>
                        <li><strong>Entdecken:</strong> öffentliche Listen anderer Personen komplett in die eigene Sammlung kopieren.</li>
                    </ul>
                    <p class="mb-0">Neue Karten landen zunächst in der <strong>Warteschlange</strong> (siehe Leitner-Modus) statt sofort aktiv zu sein — so wird man nicht mit zu vielen neuen Wörtern auf einmal überfordert.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h4">
                    Leitner-Modus
                </button>
            </h2>
            <div id="h4" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Das Leitner-System arbeitet mit <strong>5 Fächern</strong>. Jedes Fach hat ein Wiederholungs-Intervall (Fach 1 = morgen, Fach 2 = in 2 Tagen, bis Fach 5 = alle 30 Tage). Eine Karte rückt bei richtiger Antwort ein Fach auf, bei falscher Antwort fällt sie zurück auf Fach 1.</p>
                    <ul class="mb-2">
                        <li><strong>Warteschlange:</strong> Neue Karten werden nicht alle auf einmal aktiv — pro Tag wird nur eine begrenzte Anzahl aus der Warteschlange in Fach 1 aufgenommen (Anzahl einstellbar).</li>
                        <li><strong>Session:</strong> Eine Lernrunde zeigt fällige Karten (aus einer oder mehreren ausgewählten Listen gemischt); Sprachrichtung ist wählbar (A→B, B→A oder gemischt).</li>
                        <li><strong>Fach 5:</strong> gilt als "gut gelernt", wird aber weiterhin alle 30 Tage zur Auffrischung gezeigt.</li>
                    </ul>
                    <p class="mb-0">Geeignet für <strong>längerfristiges</strong> Lernen mit wenigen Karten pro Tag, dafür über lange Zeit verteilt.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h5">
                    Drill-Modus
                </button>
            </h2>
            <div id="h5" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Kurze, intensive Trainingsrunde (Standard: ca. 10 Minuten) mit wenigen Karten gleichzeitig im Umlauf — neue und bereits bekannte Karten werden gemischt gezeigt, mit deutlichem Übergewicht bekannter Karten.</p>
                    <ul class="mb-2">
                        <li>Bei richtiger Antwort mehrmals in Folge gilt eine Karte als <strong>gemeistert</strong> und wechselt danach automatisch ins Leitner-System.</li>
                        <li>Bei einem Fehler kommt nur diese eine Karte ans Ende der aktuellen Runde — nicht die ganze Runde von vorne.</li>
                    </ul>
                    <p class="mb-0">Geeignet für <strong>intensives Kurzzeit-Pauken</strong>, z.B. vor einem Test — als Ergänzung zum Leitner-System, nicht als Ersatz.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h6">
                    Aussprache: Audio & Lautschrift
                </button>
            </h2>
            <div id="h6" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p class="mb-0">Hat eine Liste einen Aussprache-Dialekt hinterlegt (z.B. <code>en-GB</code>), erscheint bei Karten in Sprache B ein 🔊-Knopf, der das Wort per Sprachausgabe des Geräts vorliest. Zusätzlich kann pro Karte eine vereinfachte <strong>Lautschrift</strong> hinterlegt sein (in eckigen Klammern angezeigt) — beide Hilfen sind unabhängig voneinander nutzbar.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h7">
                    Statistik & Streak
                </button>
            </h2>
            <div id="h7" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p class="mb-0">Die Statistik-Seite zeigt Lernverlauf und Fortschritt pro Liste. Das 🔥-Abzeichen in der Navbar zeigt die aktuelle <strong>Streak</strong> (Anzahl Tage in Folge mit mindestens einer abgeschlossenen Lernsession) — verschwindet, sobald ein Tag ausgelassen wird.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h8">
                    Für Admins: Einstellungen & Benutzerverwaltung
                </button>
            </h2>
            <div id="h8" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Manche Bereiche sind nur für Personen mit <strong>Admin-Status</strong> sichtbar und zugänglich — erkennbar am zusätzlichen "Einstellungen"-Link und "Person wechseln" in der Navbar der Startseite.</p>
                    <ul class="mb-2">
                        <li><strong>Einstellungen:</strong> Seitentitel, Session-Timeout, tägliches Karten-Limit sowie Timer und Schwellenwerte für den Drill-Modus.</li>
                        <li><strong>Benutzerverwaltung:</strong> von den Einstellungen aus erreichbar — neue Personen anlegen, Passwörter zurücksetzen, E-Mail-Adressen einer Person setzen, Admin-Status vergeben oder entziehen (der letzte verbleibende Admin kann nicht entfernt werden).</li>
                        <li><strong>Person wechseln:</strong> ein Admin kann vorübergehend als eine andere Person agieren (z.B. für Support), ohne sich neu einzuloggen — der eigene Admin-Status bleibt dabei erhalten.</li>
                    </ul>
                    <p class="mb-0">Alle Personen — auch ohne Admin-Status — können ihr eigenes Passwort und ihre eigene E-Mail-Adresse selbst über das 🔑-Icon verwalten (siehe "Einstieg: Login & Person").</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h9">
                    Für Technik-Fans: Karten per KI-Agent verwalten
                </button>
            </h2>
            <div id="h9" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p class="mb-0">Für technisch versierte Nutzer bietet die App eine Schnittstelle (MCP), über die ein KI-Agent (z.B. Claude) Personen und Listen abfragen sowie Karten hinzufügen oder bestehende Karten korrigieren kann — praktisch, um z.B. grössere Wortlisten im Gespräch mit einer KI zu erstellen. Der Agent zeigt vorgeschlagene Änderungen immer erst zur Bestätigung an, bevor etwas gespeichert wird. Damit das funktioniert, muss der MCP-Server separat eingerichtet und konfiguriert werden (Zugangs-Token, Verbindung im jeweiligen KI-Tool) — Details dazu bei Bedarf beim Administrator erfragen.</p>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

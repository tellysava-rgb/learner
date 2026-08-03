<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
}

// Aktuell konfigurierte Werte für die Hilfetexte (Leitner/Drill) — passen sich automatisch an,
// falls die Werte in den Einstellungen geändert werden.
$li               = LEITNER_INTERVALS;
$daily_limit      = DAILY_CARD_LIMIT;
$default_cards    = LEITNER_DEFAULT_CARDS;
$drill_minutes    = (int) round(DRILL_SESSION_SECONDS / 60);
$drill_mastery    = DRILL_MASTERY_THRESHOLD;
$drill_too_hard   = DRILL_TOO_HARD_LIMIT;
$drill_ratio      = DRILL_KNOWN_RATIO;
$pin_mode         = DRILL_PIN_MODE;
$pin_ratio        = DRILL_PIN_RATIO;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hilfe — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Hilfe', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1">Hilfe & Handbuch</h1>
    <p class="text-muted small mb-3">Kurzanleitung zu <?= htmlspecialchars(APP_NAME) ?> — was die Funktionen tun und wie das Lernen dahinter funktioniert.</p>

    <div class="accordion" id="helpAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#h1">
                    Einleitung
                </button>
            </h2>
            <div id="h1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p class="mb-1"><?= htmlspecialchars(APP_NAME) ?> ist ein Vokabeltrainer mit diesen Grundfunktionen:</p>
                    <ul class="mb-0">
                        <li>Eigene Wortlisten erstellen und pflegen</li>
                        <li>Öffentliche Wortlisten anderer Personen kopieren</li>
                        <li>Wortlisten lernen — wahlweise mit dem Leitner-System (Karteikarten) oder im Drill-Modus</li>
                        <li>Das kleine 1×1 der Mathematik lernen (Multiplikation und Division)</li>
                        <li>Wortlisten werden durch Audioaussprache unterstützt, sofern für die jeweilige Liste ein Aussprache-Dialekt konfiguriert ist</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h2">
                    Login & Person
                </button>
            </h2>
            <div id="h2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Der Login erfolgt mit deinem Namen und deinem eigenen Passwort — jede Person hat ihren eigenen Lernfortschritt, auch wenn mehrere Personen dieselbe Liste nutzen.</p>
                    <p>Über das 🔑-Icon oben in der Navbar kannst du dein eigenes Passwort ändern und optional eine E-Mail-Adresse hinterlegen — damit kannst du dein Passwort selbst zurücksetzen, falls du es vergisst (Link "Passwort vergessen?" auf der Login-Seite).</p>
                    <p class="mb-0">Neue Personen anlegen sowie Passwörter zurücksetzen kann nur ein <strong>Admin</strong> (Benutzerverwaltung).</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h3">
                    Wortlisten verwalten
                </button>
            </h2>
            <div id="h3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
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
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h4">
                    Wörter hinzufügen
                </button>
            </h2>
            <div id="h4" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Drei Wege, um Karten in eine Liste zu bringen:</p>
                    <ul class="mb-2">
                        <li><strong>Manuell:</strong> auf der Kartenübersicht einer Liste einzeln hinzufügen, bearbeiten oder archivieren.</li>
                        <li><strong>CSV-Import:</strong> Datei mit mehreren Karten auf einmal hochladen (Format wird auf der Import-Seite erklärt). Dort steht auch ein fertiger <strong>Prompt für eine KI</strong> bereit, mit dem sich passende Wortlisten zu einem Thema generieren lassen — einfach kopieren, Thema ergänzen und einer KI wie Claude oder ChatGPT geben.</li>
                        <li><strong>Entdecken:</strong> öffentliche Listen anderer Personen komplett in die eigene Sammlung kopieren.</li>
                    </ul>
                    <div class="text-center mb-3">
                        <img src="img/neues-wort.png" alt="Formular zum manuellen Hinzufügen einer neuen Karte auf der Kartenübersicht: Begriff in beiden Sprachen (Pflichtfelder), Beschreibung und Lautschrift als Zusatz, daneben die Buttons für CSV Import und Export"
                             class="img-fluid rounded border shadow-sm" style="max-width:600px;">
                        <p class="text-muted small mt-1 mb-0">So sieht das Formular für "Manuell" auf der Kartenübersicht einer Liste aus.</p>
                    </div>
                    <p class="mb-0">Neue Karten landen zunächst in der <strong>Warteschlange</strong> (siehe Leitner-Modus) statt sofort aktiv zu sein — so wird man nicht mit zu vielen neuen Wörtern auf einmal überfordert.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h5">
                    Aufbau einer Lernkarte
                </button>
            </h2>
            <div id="h5" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Egal ob Leitner oder Drill — die Lernkarte selbst sieht in beiden Modi gleich aus:</p>
                    <div class="text-center mb-3">
                        <img src="img/learner-karte.png" alt="Beispiel einer Lernkarte: oben die Frageseite mit Begriff und Beschreibung, unten die aufgedeckte Antwortseite mit Lautschrift und Ausspracheknopf, oben links das Pin-Symbol"
                             class="img-fluid rounded border shadow-sm" style="max-width:340px;">
                    </div>
                    <ul class="mb-2">
                        <li><strong>Oberer Teil:</strong> die Frageseite — Sprachname, der Begriff sowie optional eine Beschreibung oder ein Beispielsatz dazu.</li>
                        <li><strong>Antippen/Anklicken der Karte</strong> deckt darunter die Antwortseite auf, farblich grün hervorgehoben — gleicher Aufbau: Sprachname, Begriff, optionale Beschreibung.</li>
                        <li><strong>Lautschrift</strong> (in eckigen Klammern, z.B. <code>[tuh-DAY]</code>) und der <strong>🔊-Ausspracheknopf</strong> gehören immer zur fremdsprachigen Seite (Sprache B) einer Karte — je nach gewählter Lernrichtung kann das die obere oder untere Hälfte sein. Beide erscheinen nur, wenn für die Liste ein Aussprache-Dialekt hinterlegt ist bzw. eine Lautschrift zur Karte gepflegt wurde (siehe Abschnitt "Aussprache: Audio & Lautschrift").</li>
                        <li><strong>Pin-Symbol</strong> oben links (<i class="bi bi-pin-angle"></i> / ausgefüllt <i class="bi bi-pin-angle-fill"></i>): merkt die Karte einzeln "für Drill vormerken" — unabhängig davon, ob man sich gerade im Leitner- oder Drill-Modus befindet. Näheres dazu im Abschnitt "Drill-Modus".</li>
                    </ul>
                    <p class="mb-0">Welche Sprache oben und welche unten erscheint, hängt von der gewählten <strong>Lernrichtung</strong> ab (A→B, B→A oder gemischt — in Leitner und Drill gleichermassen auf der Konfigurationsseite vor dem Start wählbar).</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h6">
                    Leitner-Modus
                </button>
            </h2>
            <div id="h6" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Das Leitner-System arbeitet mit <strong>5 Fächern</strong>. Jedes Fach hat ein festes Wiederholungs-Intervall: Fach 1 = morgen, Fach 2 = in <?= $li[2] ?> Tagen, Fach 3 = in <?= $li[3] ?> Tagen, Fach 4 = in <?= $li[4] ?> Tagen, Fach 5 = alle <?= $li[5] ?> Tage. Eine Karte rückt bei richtiger Antwort ein Fach auf, bei falscher Antwort fällt sie zurück auf Fach 1.</p>
                    <div class="text-center mb-3">
                        <img src="img/neue-session-leitner.png" alt="Startseite einer Leitner-Session: gewählte Liste, Lernrichtung als Auswahlknöpfe (z.B. Deutsch → Englisch, Englisch → Deutsch, Gemischt) und Kartenanzahl mit ±5-Knöpfen, darunter der Button „Session starten“"
                             class="img-fluid rounded border shadow-sm" style="max-width:400px;">
                        <p class="text-muted small mt-1 mb-0">So sieht die Startseite einer Leitner-Session aus, bevor es losgeht.</p>
                    </div>
                    <ul class="mb-2">
                        <li><strong>Warteschlange:</strong> Neue Karten werden nicht alle auf einmal aktiv — aktuell werden pro Tag <?= $daily_limit ?> Karten aus der Warteschlange in Fach 1 aufgenommen (in den Einstellungen anpassbar).</li>
                        <li><strong>Session:</strong> Eine Lernrunde zeigt standardmässig bis zu <?= $default_cards ?> fällige Karten (beim Start der Session anpassbar), aus einer oder mehreren ausgewählten Listen gemischt; Sprachrichtung ist wählbar (A→B, B→A oder gemischt).</li>
                        <li><strong>Fach 5:</strong> gilt als "gut gelernt", wird aber weiterhin alle <?= $li[5] ?> Tage zur Auffrischung gezeigt.</li>
                        <li>Oben links auf jeder Karte lässt sich über das runde Pin-Symbol (<i class="bi bi-pin-angle"></i> / ausgefüllt <i class="bi bi-pin-angle-fill"></i>) die Karte direkt "für Drill vormerken" — ein Klick schaltet um, ohne die laufende Session zu unterbrechen. Betrifft nur den Drill-Modus, der Leitner-Ablauf hier läuft davon völlig unberührt normal weiter (siehe Abschnitt "Drill-Modus").</li>
                    </ul>
                    <p class="mb-0">Geeignet für <strong>längerfristiges</strong> Lernen mit wenigen Karten pro Tag, dafür über lange Zeit verteilt.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h7">
                    Drill-Modus
                </button>
            </h2>
            <div id="h7" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Kurze, intensive Trainingsrunde (Standard <?= $drill_minutes ?> Minuten aus den Einstellungen, beim Start jeder Session frei anpassbar) mit wenigen Karten gleichzeitig im Umlauf — neue und bereits bekannte Karten werden im Verhältnis <?= $drill_ratio ?>:1 (bekannt:neu) gemischt gezeigt, mit deutlichem Übergewicht bekannter Karten.</p>
                    <div class="text-center mb-3">
                        <img src="img/neue-session-drill.png" alt="Startseite einer Drill-Session: gewählte Liste, Lernrichtung als Auswahlknöpfe und Timer in Minuten mit ±5-Knöpfen, darunter der Button „Drill starten“"
                             class="img-fluid rounded border shadow-sm" style="max-width:400px;">
                        <p class="text-muted small mt-1 mb-0">So sieht die Startseite einer Drill-Session aus — hier ebenfalls Lernrichtung und Timer wählbar.</p>
                        <p class="small mt-1 mb-0"><strong>Tipp:</strong> Der Timer sollte mindestens <strong>5 Minuten</strong> laufen. Bei weniger Zeit sieht man dieselbe Karte meist nicht oft genug, um sie wirklich zu lernen.</p>
                    </div>
                    <ul class="mb-2">
                        <li><strong>Vor dem Start wählbar</strong> (analog zum Leitner-System): Lernrichtung (A→B, B→A oder gemischt) und Timer in Minuten — beide gelten nur für diese eine Session und werden nicht dauerhaft gespeichert.</li>
                        <li>Bei <?= $drill_mastery ?>× richtiger Antwort <strong>in Folge</strong> gilt eine Karte als <strong>gemeistert</strong> und wechselt danach automatisch ins Leitner-System (ein einzelner Fehler setzt diese Zählung zurück auf 0).</li>
                        <li>Bei einem Fehler kommt nur diese eine Karte ans Ende der aktuellen Runde — nicht die ganze Runde von vorne.</li>
                        <li>Wird eine Karte <?= $drill_too_hard ?>× als "musste nachdenken" bewertet, wird sie für den Rest dieser Session pausiert und taucht erst am nächsten Tag wieder auf.</li>
                        <li>Bei einer <strong>neuen Liste</strong> mit lauter neuen Karten kann es am Anfang vorkommen, dass dieselbe Karte mehrmals kurz hintereinander gezeigt wird, bevor eine zweite dazukommt (bis zu <?= $drill_ratio ?>× — entsprechend dem Bekannt/Neu-Verhältnis). Das ist <strong>kein Fehler, sondern beabsichtigt</strong> und hilft, sich die Karte einzuprägen. Je mehr Karten im Umlauf sind, desto mehr vermischen sie sich.</li>
                        <li>Es gibt <strong>keine Leitner-Fach-Obergrenze</strong> für den Drill-Modus — auch Karten, die bereits in einem höheren Leitner-Fach sind, können weiterhin im Drill auftauchen, solange sie nicht archiviert sind.</li>
                        <li><strong>Für Drill vormerken:</strong> Jede Karte lässt sich einzeln über das Pin-Icon (<i class="bi bi-pin-angle"></i>) "für Drill vormerken" — entweder auf der Kartenübersicht (Kartenansicht über das Augen-Icon "Karte ansehen") oder direkt während einer laufenden Leitner-Session (Symbol oben links auf der Lernkarte). Vorgemerkte Karten werden im Drill <?= $pin_mode === 'absolute' ? 'immer zuerst gezeigt, solange mindestens eine vorgemerkte Karte übrig ist' : "bevorzugt eingeschoben (aktuell etwa jede {$pin_ratio}. Karte)" ?> — Priorität in den Einstellungen anpassbar. Erkennbar an einem ausgefüllten Pin-Symbol (<i class="bi bi-pin-angle-fill"></i>) oben links auf der Karte sowie am Badge "Für Drill vorgemerkt" in der Kartenübersicht. Das Leitner-System läuft für diese Karte währenddessen völlig unverändert weiter (kein Einfrieren, kein Fach-Sprung). Bei <?= $drill_mastery ?>× richtiger Antwort in Folge seit dem Vormerken wird die Vormerkung automatisch entfernt, kann aber auch jederzeit manuell wieder entfernt werden.</li>
                    </ul>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="img/drill-timer.png" alt="Ausschnitt aus der Navbar während einer laufenden Drill-Session: links ein X-Symbol zum Abbrechen der Session, rechts daneben der Countdown-Timer, hier 9:50"
                             class="img-fluid rounded border shadow-sm" style="width:105px;">
                        <p class="mb-0 small text-muted">Während der Session zählt der Timer oben rückwärts. Über das <i class="bi bi-x-lg"></i>-Symbol lässt sich jederzeit abbrechen.</p>
                    </div>
                    <p class="mb-0">Geeignet für <strong>intensives Kurzzeit-Pauken</strong>, z.B. vor einem Test — als Ergänzung zum Leitner-System, nicht als Ersatz.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h8">
                    Aussprache: Audio & Lautschrift
                </button>
            </h2>
            <div id="h8" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Hat eine Liste einen Aussprache-Dialekt hinterlegt (z.B. <code>en-GB</code>), erscheint bei Karten in Sprache B ein 🔊-Knopf, der das Wort per Sprachausgabe des Geräts vorliest. Zusätzlich kann pro Karte eine vereinfachte <strong>Lautschrift</strong> hinterlegt sein (in eckigen Klammern angezeigt) — beide Hilfen sind unabhängig voneinander nutzbar.</p>
                    <p class="mb-1">Zum Ausprobieren, wie das in etwa klingt:</p>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="speakWord(this)"
                                data-speak="Can you hear me?" data-lang="en-GB">
                            <i class="bi bi-volume-up-fill"></i> Klick mich
                        </button>
                        <span class="text-muted small">Begriff: „Can you hear me?" · Sprache: <code>en-GB</code></span>
                    </div>
                    <p class="mb-0">Die Stimme und ihr Klang kommen dabei <strong>nicht von der Anwendung selbst</strong>, sondern von der Sprachausgabe des jeweiligen Geräts bzw. Betriebssystems — sie kann daher je nach Gerät unterschiedlich und mitunter mechanisch klingen. Dennoch hilft sie dabei, auf die richtige <strong>Betonung</strong> zu achten, also welche Silbe bzw. welches Wort stärker betont wird.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h9">
                    Statistik & Streak
                </button>
            </h2>
            <div id="h9" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Die Statistik-Seite zeigt Lernverlauf und Fortschritt pro Liste. Das 🔥-Abzeichen in der Navbar zeigt die aktuelle <strong>Streak</strong> (Anzahl Tage in Folge mit mindestens einer abgeschlossenen Lernsession) — verschwindet, sobald ein Tag ausgelassen wird.</p>
                    <p class="mb-1">Ganz oben auf der Statistik-Seite zeigt die Karte <strong>"Lernaktivität"</strong> (listenübergreifend, unabhängig vom Listen-Filter darunter):</p>
                    <ul class="mb-2">
                        <li>Drei Kennzahlen: 🔥 aktueller Streak, Lerntage insgesamt, sowie die beste Woche (meiste Lerntage in einer einzelnen Kalenderwoche)</li>
                        <li>Eine <strong>Jahres-Heatmap</strong> der letzten 52 Wochen, im Stil von GitHubs Beitrags-Übersicht: jede Spalte eine Kalenderwoche (links die älteste), jede Zeile ein Wochentag (Mo–So). Je mehr Karten an einem Tag gelernt wurden, desto dunkler das Grün — ein leeres/graues Feld bedeutet, dass an diesem Tag nicht gelernt wurde</li>
                        <li>Beim Hovern über ein Feld zeigt ein Tooltip das genaue Datum und die Anzahl gelernter Karten an diesem Tag</li>
                        <li>Die Heatmap passt sich der <strong>Bildschirmbreite</strong> deines Geräts an: auf dem Handy sind so nur die letzten paar Monate zu sehen, am Computer alle 52 Wochen — es wird nicht kleiner gequetscht, sondern zeigt einfach einen kürzeren, aktuelleren Zeitraum</li>
                    </ul>
                    <p class="mb-0">Ein Lerntag zählt, sobald mindestens eine Karte beantwortet wurde — egal ob im Leitner- oder Drill-Modus.</p>
                </div>
            </div>
        </div>

        <?php if (!empty($_SESSION['is_admin'])): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h10">
                    Für Admins: Einstellungen & Benutzerverwaltung
                </button>
            </h2>
            <div id="h10" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Manche Bereiche sind nur für Personen mit <strong>Admin-Status</strong> sichtbar und zugänglich — erkennbar am zusätzlichen "Einstellungen"-Link und "Person wechseln" in der Navbar der Startseite.</p>
                    <ul class="mb-2">
                        <li><strong>Einstellungen:</strong> Seitentitel, Session-Timeout, tägliches Karten-Limit sowie Timer und Schwellenwerte für den Drill-Modus.</li>
                        <li><strong>Benutzerverwaltung:</strong> von den Einstellungen aus erreichbar — neue Personen anlegen, Passwörter zurücksetzen, E-Mail-Adressen einer Person setzen, Admin-Status vergeben oder entziehen (der letzte verbleibende Admin kann nicht entfernt werden).</li>
                        <li><strong>Person wechseln:</strong> ein Admin kann vorübergehend als eine andere Person agieren (z.B. für Support), ohne sich neu einzuloggen — der eigene Admin-Status bleibt dabei erhalten.</li>
                        <li><strong>Debug-Modus:</strong> eigener Schalter unter Einstellungen → Debug. Zeigt danach in Leitner und Drill nach jeder beantworteten Karte ein Info-Panel mit dem genauen Vorher/Nachher-Status (Fach, Fälligkeit, Zähler) — nützlich zum Nachvollziehen der Leitner-/Drill-Logik. Der Schalter ist zwar global, das Panel selbst sehen aber weiterhin nur Admins.</li>
                    </ul>
                    <p class="mb-0">Alle Personen — auch ohne Admin-Status — können ihr eigenes Passwort und ihre eigene E-Mail-Adresse selbst über das 🔑-Icon verwalten (siehe "Login & Person").</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#h11">
                    Für Technik-Fans: Karten per KI-Agent verwalten
                </button>
            </h2>
            <div id="h11" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <p>Für technisch versierte Nutzer bietet die App eine Schnittstelle (MCP), über die ein KI-Agent (z.B. Claude) direkt mit den eigenen Wortlisten arbeiten kann — praktisch, um z.B. grössere Wortlisten im Gespräch mit einer KI zu erstellen oder zu pflegen. Der Agent zeigt vorgeschlagene Änderungen immer erst zur Bestätigung an, bevor etwas gespeichert wird.</p>
                    <p class="mb-1">Damit ein KI-Agent so arbeiten kann:</p>
                    <ul class="mb-2">
                        <li>Der MCP-Server muss einmalig separat eingerichtet werden — die verwendete KI-Anwendung (z.B. Claude Code oder Claude Desktop) wird dafür mit der Server-Adresse der App verbunden</li>
                        <li>Details zum Zugang (Adresse, Zugangs-Token) bei Bedarf beim Administrator erfragen — diese Hilfeseite nennt bewusst keine technischen Zugangsdaten</li>
                    </ul>
                    <p class="mb-1">Was der Agent über die Schnittstelle tun kann:</p>
                    <ul class="mb-2">
                        <li>Personen und deren Wortlisten abfragen</li>
                        <li>Neue Vokabelkarten zu einer Liste hinzufügen</li>
                        <li>Bestehende Karten korrigieren (z.B. Rechtschreibung, fehlende Lautschrift)</li>
                    </ul>
                    <p class="mb-0">Auch ganz ohne die MCP-Schnittstelle kann ein KI-Agent im Gespräch helfen, eine <strong>Import-Liste im richtigen CSV-Format</strong> zu erstellen (siehe Abschnitt "Wortlisten verwalten"/`import.php`) — diese lässt sich danach ganz normal manuell hochladen.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
function speakWord(btn) {
    if (!('speechSynthesis' in window)) return;
    var text = btn.dataset.speak;
    var lang = btn.dataset.lang;
    var u = new SpeechSynthesisUtterance(text);
    u.lang = lang;
    // utterance.lang allein wird von manchen Browsern/Geräten ignoriert und fällt auf die
    // Standardstimme des Systems zurück — passende Stimme explizit suchen und setzen.
    var voices = window.speechSynthesis.getVoices();
    var match = voices.find(function (v) { return v.lang === lang; })
             || voices.find(function (v) { return v.lang.split('-')[0] === lang.split('-')[0]; });
    if (match) u.voice = match;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(u);
}
</script>
</body>
</html>

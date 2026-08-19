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
    <title>Mythos-Check — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', 'wissen.php'], ['Mythos-Check', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1"><i class="bi bi-patch-question text-primary"></i> Mythos-Check</h1>
    <p class="text-muted mb-4">
        Sprachlern-Apps und Ratgeber versprechen gern, dass man mit der richtigen Methode „in wenigen Wochen fliessend"
        wird. Die Forschung sagt etwas anderes: Es gibt keine einzelne beste Methode, sondern ein Zusammenspiel aus
        verständlichem Input, gezieltem Lernen, aktivem Abruf, Produktion, Feedback und verteilter Wiederholung. Abkürzungen,
        die eines dieser Elemente ganz auslassen, wirken meist schwächer als beworben — hier die häufigsten davon im Detail.
    </p>

    <div class="accordion" id="accMythen">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#my-cafe">
                    „Einfach drauflos plaudern reicht" — im Café mit Muttersprachlern quatschen
                </button>
            </h2>
            <div id="my-cafe" class="accordion-collapse collapse show">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Wer nur genug spricht, „pickt sich die Sprache von selbst auf" — Grammatik
                        lernen sei unnötig, solange man sich nur traut zu reden.</p>
                    <p><strong>Was die Forschung zeigt:</strong> Interaktion ist tatsächlich wichtig — Studien zu Sprachlern-Interaktion
                        finden grosse Vorteile gegenüber reiner Beobachtung, besonders auf späteren Tests. Aber der
                        entscheidende Mechanismus ist nicht die Anzahl gesprochener Minuten, sondern ob eine Lücke im eigenen
                        Wissen bemerkt, korrigiert und die korrigierte Form danach erneut produziert wird. Ohne Korrektur
                        wiederholt man denselben Fehler einfach öfter — er wird nicht besser, sondern automatisierter.
                        Korrektives Feedback zeigt in Studien deutliche, dauerhafte Effekte auf die Sprachentwicklung.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Gespräche führen <em>plus</em> Fehler markieren
                        lassen, die korrigierte Form bewusst noch einmal selbst sagen, und die Struktur später erneut
                        anwenden. Freies Plaudern ist eine gute Ergänzung — kein Ersatz für Instruktion und Feedback.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-dusche">
                    „Sprachduschen" — einfach nur berieseln lassen
                </button>
            </h2>
            <div id="my-dusche" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Fremdsprachiges Audio im Hintergrund laufen lassen, ohne aktiv
                        hinzuhören oder mitzudenken — das Gehirn „gewöhne sich" an die Sprache und lerne nebenbei.</p>
                    <p><strong>Was die Forschung zeigt:</strong> Bedeutungsvoller, verständlicher Input kann durchaus
                        Wortschatz aufbauen — aber interaktive und aufmerksamkeitsfordernde Aufgaben sind dabei deutlich
                        wirksamer als rein passives Zuhören ohne Verarbeitung. Beim beiläufigen Lernen aus Lesen oder Hören
                        werden typischerweise nur rund 9–18 % der vorkommenden Zielwörter überhaupt gelernt — Wiederholung
                        und Aufmerksamkeit sind entscheidend, nicht Beschallung an sich.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Aktives Zuhören mit einer Aufgabe (z.B. eine
                        Frage beantworten, eine Handlung ausführen, Notizen machen) statt Nebenbei-Berieselung. Audio ist
                        wertvoll als Mengeninput — aber als aktive Tätigkeit, nicht als Hintergrundgeräusch.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-filme">
                    Filme & Serien in der Fremdsprache — mit oder ohne Untertitel
                </button>
            </h2>
            <div id="my-filme" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Einfach Serien in der Zielsprache schauen ersetze das „mühsame" Lernen —
                        am besten gleich ohne Untertitel, für den „vollen Immersions-Effekt".</p>
                    <p><strong>Was die Forschung zeigt:</strong> Audiovisueller Input hat nachweisbares Lernpotenzial über
                        Wortschatz, Grammatik, Aussprache, Sprechen und Hören hinweg — allerdings meist gemessen als
                        Vorher-Nachher-Vergleich ohne Kontrollgruppe, was die berichteten Effekte nicht direkt mit anderen
                        Methoden vergleichbar macht. Wichtiger: <strong>lehrorientierte</strong> Inhalte (z.B. Erklärvideos,
                        Dokumentationen) zeigen deutlich grössere Lerneffekte als reine Unterhaltungsinhalte. Zu schwerer,
                        kaum verständlicher Input lässt zudem kaum noch Aufmerksamkeit für die Sprache selbst übrig.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Inhalte wählen, die schon grösstenteils
                        verständlich sind (nicht die schwersten verfügbaren). Kurze Ausschnitte wiederholt anschauen,
                        mit Untertitel gezielt unklare Stellen klären, danach nochmal ohne. Serien und Filme sind sinnvoll
                        als Mengeninput und Motivation — aber kein Ersatz für gezieltes Lernen.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-schlaf">
                    „Im Schlaf lernen" — Vokabel-Podcast über Nacht laufen lassen
                </button>
            </h2>
            <div id="my-schlaf" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Kopfhörer aufsetzen, Vokabel-Audio starten, einschlafen — am Morgen
                        sitzen die neuen Wörter.</p>
                    <p><strong>Was die Forschung zeigt:</strong> Es gibt tatsächlich eine Studie, die implizites
                        Vokabellernen im Tiefschlaf nachweisen konnte — aber unter Bedingungen, die mit „Podcast nebenbei
                        laufen lassen" nichts zu tun haben: Die Wortpaare wurden per EEG-Messung exakt auf die sogenannten
                        Up-States des Tiefschlafs synchronisiert abgespielt, Phasen von etwa einer halben Sekunde Länge,
                        die sich mit inaktiven Phasen abwechseln. Ohne dieses präzise Timing gibt es keinen Beleg, dass
                        einfaches Audio-Abspielen während des Schlafs neue Fremdsprachenwörter vermittelt.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Schlaf ist trotzdem wichtig fürs Sprachenlernen —
                        aber als Konsolidierung von tagsüber aktiv Gelerntem, nicht als Ersatz für aktives Lernen selbst.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-natuerlich">
                    „Einfach eintauchen, Grammatik kommt von allein" — die Ganz-natürlich-Methode
                </button>
            </h2>
            <div id="my-natuerlich" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Kinder lernen ihre Muttersprache doch auch ohne Grammatikunterricht —
                        also sollten Erwachsene Grammatik komplett weglassen und nur „eintauchen".</p>
                    <p><strong>Was die Forschung zeigt:</strong> Formfokussierte Instruktion — also gezielt auf sprachliche
                        Formen und Regeln aufmerksam machen — zeigt in grossen Meta-Analysen durchgehend starke, positive
                        Effekte, deutlich grösser als rein implizites „Aufschnappen". Das gilt sowohl für Erwachsene als
                        auch, mit gewissen Einschränkungen der direkten Evidenz, für Kinder.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Kurze, gezielte Grammatikarbeit (Bedeutung →
                        Form → Beispiele → eigener Abruf) plus sofortige Anwendung in echtem Sprachgebrauch — nicht
                        wochenlanges isoliertes Regelpauken, aber auch nicht komplettes Weglassen.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-fruehbeginn">
                    „Je früher, desto besser" — ein früher Beginn garantiert automatisch einen dauerhaften Vorsprung
                </button>
            </h2>
            <div id="my-fruehbeginn" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Fremdsprachenunterricht möglichst früh beginnen (z.B. schon in
                        Klasse 1 statt Klasse 3) bringe automatisch einen bleibenden Vorsprung — je früher, desto
                        besser, ganz unabhängig von Unterrichtsqualität und -menge.</p>
                    <p><strong>Was die Forschung zeigt:</strong> Die Befundlage aus dem deutschen Schulkontext ist
                        tatsächlich kontrovers — einzelne Untersuchungen zu frühem Englischbeginn kommen zu recht
                        unterschiedlichen Ergebnissen, teils wurde sogar kein dauerhafter Vorsprung gefunden. Ein
                        wiederkehrendes Muster über mehrere Studien hinweg: Zusätzliche frühe Lernjahre allein
                        garantieren keinen bleibenden Vorsprung — Unterrichtsintensität, Anschlussfähigkeit an die
                        weiterführende Schule und die Qualität späterer Lerngelegenheiten scheinen eine grössere
                        Rolle zu spielen als das reine Startalter.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Bei der Wahl des Startzeitpunkts weniger
                        auf „so früh wie möglich" achten als auf durchgehend guten, intensiven und gut angeschlossenen
                        Unterricht über die gesamte Schulzeit hinweg.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#my-birkenbihl">
                    Die Birkenbihl-Methode
                </button>
            </h2>
            <div id="my-birkenbihl" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p><strong>Der Mythos:</strong> Die Birkenbihl-Methode (Dekodieren, aktives/passives Hören) wird oft
                        als „wissenschaftlich erwiesen" beworben.</p>
                    <p><strong>Was die Recherche zeigt:</strong> Für das Gesamtpaket der Methode liess sich keine
                        belastbare Meta-Analyse, kein systematischer Wirksamkeits-Review und keine kontrollierte
                        Interventionsstudie finden. Die auffindbaren wissenschaftlichen Katalogeinträge sind Methodenbücher,
                        keine Wirksamkeitsstudien. Das bedeutet nicht zwingend, dass einzelne Bestandteile nutzlos sind —
                        manche sind mit etablierten Methoden kompatibel (z.B. Wort-für-Wort-Dekodierung ähnelt gezieltem
                        Wortschatzlernen) — aber die Behauptung „wissenschaftlich bewiesen" für die Methode als Ganzes
                        ist durch nichts belegt, was diese Recherche finden konnte.</p>
                    <p class="mb-0"><strong>Was stattdessen hilft:</strong> Die einzelnen sinnvollen Bestandteile (aktiver
                        Abruf, wiederholtes Hören) bewusst in einen Lernzyklus mit Feedback und Spacing einbauen, statt
                        auf das Gesamtpaket als geprüfte Methode zu vertrauen.</p>
                </div>
            </div>
        </div>

    </div>

    <?= render_quellenliste([
        'lyster-saito-feedback', 'mackey-goo-interaction',
        'webb-uchihara-yanagisawa-incidental', 'vos-spoken-input',
        'sutton-webb-audiovisual',
        'zuest-sleep',
        'kang-sok-han-formfocus', 'spada-tomita-explicit',
        'birkenbihl-note', 'fruehbeginn-note',
    ], 'accQuellenMythen') ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

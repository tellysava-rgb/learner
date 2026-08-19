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
    <title>Studienlage im Überblick — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '../home.php'], ['Wissenschaftlich Sprachen lernen', 'wissen.php'], ['Studienlage', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:860px;">

    <h1 class="h4 mb-1"><i class="bi bi-file-earmark-text text-primary"></i> Studienlage im Überblick</h1>
    <p class="text-muted mb-4">
        Diese Seite fasst zusammen, was die Forschung zu den einzelnen Sprachfertigkeiten zeigt — sowohl bei Erwachsenen
        als auch bei Kindern. Statt jeden Satz einzeln zu belegen, sind die konkreten Studien am Seitenende gesammelt
        aufgeführt ("Quellen zu dieser Seite").
    </p>

    <div class="alert alert-light border small">
        <strong>Kernaussage vorab:</strong> Es gibt nicht die eine beste Sprachlernmethode. Die Forschung stützt
        stattdessen eine Kombination aus sechs Mechanismen: verständlicher Input, gezieltes Lernen, aktiver Abruf,
        aktive Produktion, Korrektur/Feedback und zeitlich verteilte Wiederholung. Wer eines dieser Elemente komplett
        weglässt — nur Input, nur Grammatik, nur freies Sprechen — verschenkt Potenzial.
    </div>

    <h2 class="h5 mt-4">Wortschatz</h2>
    <p><strong>Wozu das gut ist:</strong> Wortschatz ist die Grundlage für alles Weitere — ohne genug Wörter kann man
        weder verstehen noch selbst etwas sagen, egal wie gut die Grammatik sitzt. Die Kompetenz, die man dabei
        aufbaut, ist der schnelle, zuverlässige Zugriff auf die Bedeutung eines Wortes, ohne lange nachdenken zu
        müssen. Gezieltes Lernen (Karteikarten, Wortlisten mit aktivem Abruf) bringt unmittelbar nach dem Lernen die höchste
        Trefferquote — im Schnitt lässt sich direkt danach ein grosser Teil der gelernten Wörter abrufen, mit
        deutlichem Rückgang bei späteren Tests ohne Wiederholung. Beiläufiges Lernen aus Lesen und Hören ist spürbar
        langsamer (grössenordnungsmässig 10–20 % der vorkommenden Zielwörter pro Durchgang), dafür entsteht dabei
        reichhaltigeres, kontextuelles Wissen. Beide Wege ergänzen sich: gezielt lernen → abrufen → im Kontext
        wiedererkennen → selbst verwenden → erneut abrufen. Bei jüngeren und unsicheren Lernenden sind deutsche
        Erklärungen oder Übersetzungen kein Problem — sie sind bei Anfängern sogar wirksamer als rein
        fremdsprachige Erklärungen, solange danach echte Verarbeitung in der Zielsprache folgt. Bilder helfen
        besonders bei konkreten Begriffen; bei mehrdeutigen oder abstrakten Wörtern ist eine kurze Übersetzung oft
        schneller und eindeutiger. Mehr Darstellungsformen gleichzeitig (Bild + Ton + Text + Übersetzung + Animation…)
        sind dabei nicht automatisch besser — ab einer dritten Modalität bringt zusätzlicher Aufwand kaum noch Vorteile.</p>

    <h2 class="h5 mt-4">Chunks & Kollokationen</h2>
    <p><strong>Wozu das gut ist:</strong> Statt ein einzelnes Wort zu lernen, lernt man gleich einen ganzen Baustein
        für eine bestimmte Situation — z.B. nicht nur „Entscheidung", sondern gleich „eine Entscheidung treffen".
        Der Nutzen: Beim Sprechen muss dieser Baustein nicht mehr Wort für Wort aus Grammatikregeln zusammengebaut
        werden, sondern wird als Ganzes direkt abgerufen. Die Kompetenz, die daraus entsteht, ist flüssigeres,
        schnelleres Sprechen mit weniger Grammatikfehlern — vor allem in Situationen, die man schon oft gebraucht
        hat (Small Talk, Bestellen, Meinungen äussern). Mehrwort-Einheiten wie <em>„to make a decision"</em> oder
        <em>„I was wondering whether …"</em> werden
        nachweislich schneller verarbeitet als frei neu zusammengesetzte Wortfolgen — das spricht dafür, sie gezielt
        zu lernen statt nur Einzelwörter. Für Erwachsene ist das gut belegt. Bei Kindern ist die direkte Evidenz
        dagegen erstaunlich dünn: Ein systematischer Review, der gezielt nach Studien zu Chunk-Unterricht bei
        Fünf- bis Zwölfjährigen suchte, fand unter über 2.200 Treffern nur zwei passende Studien — zu wenig für eine
        verlässliche Wirksamkeitsaussage. Chunks gehören trotzdem sinnvoll in einen Lernplan, sollten bei Kindern aber
        nicht als „wissenschaftlich bewiesene beste Methode" beworben werden.</p>

    <h2 class="h5 mt-4">Lesen</h2>
    <p><strong>Wozu das gut ist:</strong> Lesen trainiert zwei Dinge gleichzeitig — Leseverständnis (wie schnell und
        sicher man einen Text versteht) und nebenbei Wortschatz, weil man Wörter im echten Zusammenhang wiederholt
        antrifft statt isoliert. Die Kompetenz, die dabei wächst, ist die Fähigkeit, längere, zusammenhängende Texte
        flüssig zu lesen, ohne bei jedem unbekannten Wort steckenzubleiben. Umfangreiches, leichtes Lesen (Extensive Reading) gehört zu den am besten untersuchten Methoden überhaupt und
        zeigt durchgehend positive, kleine bis mittlere Effekte auf Wortschatz, Leseverstehen und -geschwindigkeit —
        deutlich stärker, wenn die Texte zum eigenen Niveau passen und eine Anschlussaktivität folgt (z.B. kurz
        zusammenfassen). Intensives Lesen (wenige Texte gründlich bearbeiten) ergänzt das sinnvoll für gezielte
        Grammatik- und Wortschatzarbeit, sollte das umfangreiche Lesen aber nicht verdrängen — wer jedes unbekannte
        Wort nachschlägt, zerstört den Fluss- und Mengenvorteil. Für Kinder, die noch nicht ausreichend lesen können,
        ist systematischer Aufbau der Laut-Schrift-Zuordnung (Phonics) zunächst wichtiger als reine Lesemenge.</p>

    <h2 class="h5 mt-4">Vorlesen & Geschichten (Kinder)</h2>
    <p><strong>Wozu das gut ist:</strong> Kinder, die selbst noch nicht lesen können, bekommen über Vorlesen trotzdem
        Zugang zu Wortschatz und Satzbau in einem natürlichen, motivierenden Zusammenhang. Die Kompetenz, die dabei
        aufgebaut wird, ist Hörverstehen und ein wachsender passiver Wortschatz — die Grundlage, auf der später das
        eigenständige Lesen und Sprechen aufbaut. Gemeinsames Vorlesen/Anhören von Geschichten zeigt kurzfristig einen soliden positiven Effekt auf Sprache und
        Literalität bei ESL/EFL-Kindern — langfristig ist der Effekt in derselben Untersuchung jedoch deutlich
        kleiner und statistisch nicht mehr eindeutig nachweisbar. Das spricht dafür, Geschichten nicht als reines
        Berieselungsformat zu nutzen, sondern mit Fragen, aktivem Abruf, Wiederholung und anschliessender eigener
        Sprachproduktion zu verbinden — eine Geschichte plus „Weiter"-Knopf allein reicht nicht für nachhaltiges
        Lernen.</p>

    <h2 class="h5 mt-4">Hören</h2>
    <p><strong>Wozu das gut ist:</strong> Hörverstehen ist eine eigene Fähigkeit, die sich vom Lesen unterscheidet —
        beim Hören hat man keine Zeit, in Ruhe nachzudenken oder zurückzublättern, man muss in Echtzeit verstehen.
        Die Kompetenz, die man dabei aufbaut, ist deshalb: gesprochene Sprache im normalen Tempo verstehen — im
        Gespräch, am Telefon, im Film, im Urlaub. Wortschatz und Hörverstehen hängen eng zusammen — wer mehr Wörter kennt, versteht spürbar mehr gesprochene
        Sprache; Wortschatzaufbau ist deshalb praktisch immer auch Hörtraining. Gezieltes Hörstrategie-Training
        (z.B. bewusst auf Schlüsselwörter, Tonfall oder Vorwissen achten) zeigt zusätzlich einen soliden mittleren
        Effekt. Video und audiovisuelles Material helfen ebenfalls, allerdings deutlich stärker bei lehrorientierten
        Inhalten als bei reiner Unterhaltung — und die zugrundeliegenden Studien vergleichen meist nur „vorher" mit
        „nachher", nicht mit einer echten Kontrollgruppe. Zehn Minuten Serie schauen ist deshalb nicht automatisch
        zehn Minuten Sprachunterricht.</p>

    <h2 class="h5 mt-4">Sprechen & Interaktion</h2>
    <p><strong>Wozu das gut ist:</strong> Sprechen ist die Fähigkeit, Wissen tatsächlich in Echtzeit anzuwenden — Wörter
        und Grammatik zu kennen reicht nicht, wenn man sie im Gespräch nicht schnell genug abrufen kann. Die
        Kompetenz, die hier trainiert wird, ist es, sich spontan verständlich zu machen und eigene Fehler zunehmend
        selbst zu bemerken und zu korrigieren. Für aktive Sprachproduktion ist echte Interaktion wichtiger als reine Sprechzeit: Der entscheidende Mechanismus
        ist, eine Bedeutungslücke zu bemerken, umzuformulieren, Feedback zu verarbeiten und danach erneut zu
        produzieren. Mündliches korrektives Feedback ist gut belegt und wirkt nachhaltig — Hinweise, die Lernende
        selbst zur Korrektur bringen (statt die Lösung sofort vorzusagen), zeigen dabei grössere Effekte als reine
        Wiederholung der korrekten Form. Auch digitale Dialogsysteme (Chat- bzw. Sprach-KI) zeigen einen soliden
        mittleren Effekt auf die Sprechentwicklung — als zusätzliche Übungsmöglichkeit, nicht zwingend als Ersatz
        für menschliche Interaktion.</p>

    <h2 class="h5 mt-4">Schreiben</h2>
    <p><strong>Wozu das gut ist:</strong> Schreiben zwingt dazu, sich Zeit zu nehmen und Sätze bewusst korrekt zu
        formulieren — anders als beim spontanen Sprechen fällt hier Zeitdruck als Ausrede weg. Die Kompetenz, die
        dabei entsteht, ist präzise, korrekte schriftliche Ausdrucksfähigkeit (E-Mails, Nachrichten, Aufsätze,
        Prüfungen) und indirekt auch saubereres Sprechen, weil man dieselben Strukturen bewusster übt. Für Schreiben gilt eine ähnliche Schleife wie beim Sprechen: selbst schreiben → Korrektur erhalten →
        verstehen, was korrigiert wurde → den Text ohne Vorlage neu formulieren. Eine grosse aktuelle Meta-Analyse
        zu schriftlichem korrektivem Feedback findet robuste, moderate und über die Zeit anhaltende
        Genauigkeitsverbesserungen — direkte Korrektur, indirekte Korrektur und metasprachliche Hinweise liefern
        dabei ähnlich grosse Effekte. Nur die Korrektur zu lesen bringt wenig; sie muss wieder in eigene Produktion
        umgewandelt werden.</p>

    <h2 class="h5 mt-4">Grammatik & Zeitformen</h2>
    <p><strong>Wozu das gut ist:</strong> Grammatik ist das, was einzelne Wörter erst zu einem verständlichen Satz mit
        klarer Bedeutung macht — z.B. der Unterschied zwischen „ich habe gegessen" und „ich esse" oder „ich werde
        essen". Die Kompetenz, die man dabei aufbaut, ist die Fähigkeit, auch komplexere, mehrteilige Gedanken
        korrekt und für andere eindeutig verständlich auszudrücken, statt nur einzelne Wörter aneinanderzureihen.
        Formfokussierte Instruktion — gezielt auf sprachliche Formen aufmerksam machen, statt nur auf zufälliges
        Entdecken zu hoffen — zeigt in grossen Meta-Analysen einen der grössten gemessenen Effekte überhaupt, sowohl
        bei Erwachsenen als auch (mit dünnerer direkter Evidenz) bei Kindern. Wichtig ist die Reihenfolge: Bedeutung
        verstehen → Form kurz erklären → mit einer verwandten Form kontrastieren → aktiv abrufen → in echter
        Kommunikation anwenden → Fehler korrigieren lassen → erneut anwenden. Wochenlanges isoliertes Regelpauken
        ohne Anwendung ist ebenso wenig optimal wie komplettes Weglassen von Erklärungen.</p>

    <h2 class="h5 mt-4">Aussprache</h2>
    <p><strong>Wozu das gut ist:</strong> Gute Aussprache wirkt in zwei Richtungen: Man wird selbst leichter
        verstanden, und man versteht umgekehrt auch andere leichter, weil man die Laute, auf die es ankommt, im
        eigenen Ohr besser unterscheiden kann. Die Kompetenz, die hier aufgebaut wird, ist deshalb nicht nur
        „schöner klingen", sondern echtes gegenseitiges Verstehen im Gespräch. Gezieltes Aussprachetraining zeigt in Meta-Analysen grosse, verlässliche Effekte — deutlich robuster als z.B.
        Shadowing (kontinuierliches Nachsprechen), dessen Evidenzbasis kleiner und heterogener ist. Besonders wirksam
        ist Wahrnehmungstraining: Zielkontraste von verschiedenen Sprecherinnen und Sprechern und in wechselnden
        Kontexten unterscheiden lernen, mit Feedback — das generalisiert teilweise sogar auf neue, unbekannte
        Stimmen. Bei Kindern gilt zusätzlich: kurze, präzise Imitation plus Wahrnehmungstraining ist besser belegt
        als langes freies Nachsprechen.</p>

    <h2 class="h5 mt-4">Immersion, Auslandsaufenthalt & CLIL</h2>
    <p><strong>Wozu das gut ist:</strong> Immersion bringt in kurzer Zeit sehr viel natürlichen, verständlichen
        Kontakt mit der Sprache in echten Situationen — und zwingt dazu, das Gelernte sofort anzuwenden statt es nur
        theoretisch zu kennen. Die Kompetenz, die dabei entsteht, ist Sprache schnell und automatisch abzurufen,
        ohne im Kopf erst zu übersetzen — ähnlich wie ein Muttersprachler es tut. Ein Auslandsaufenthalt zeigt in einer aktuellen Meta-Analyse einen mittelgrossen bis grossen Effekt auf den
        Spracherwerb — aber „einfach hinfahren" reicht nicht automatisch: aktive Sprachverwendung und strukturierter
        Unterricht bleiben auch im Ausland wichtig. Bei zweisprachigem Fachunterricht (CLIL) in der Primarschule
        deuten mehrere Studien auf positive Effekte hin, besonders beim Sprechen — allerdings mit methodischen
        Einschränkungen (z.B. Gruppen, die sich schon vor Beginn unterschieden). Die robuste Schlussfolgerung lautet
        deshalb eher „viel hochwertiger, verständlicher Kontakt mit der Sprache ist wertvoll" als „Immersion wirkt
        automatisch".</p>

    <h2 class="h5 mt-4">Spiele, Apps & Technologie</h2>
    <p><strong>Wozu das gut ist:</strong> Der eigentliche Nutzen von Spielen/Apps ist Motivation — sie machen es
        leichter, regelmässig am Ball zu bleiben, was bei jeder Methode die wichtigste Zutat für Fortschritt ist.
        Eine sprachliche Kompetenz entsteht daraus aber nur, wenn man beim Spielen wirklich Sprache verarbeiten
        muss (verstehen, was gesagt/geschrieben steht, und selbst reagieren) — nicht schon durch Punkte und
        Abzeichen allein. Mobile Sprachlernspiele zeigen in einer aktuellen Meta-Analyse einen grossen Gesamteffekt, mit deutlich
        klareren Ergebnissen für Wortschatz und Aussprache als für Grammatik, Sprechen, Lesen und Schreiben — bei
        allerdings sehr unterschiedlichen Ergebnissen zwischen einzelnen Studien. Wichtig ist die Unterscheidung
        zwischen echtem <strong>Game-Based Learning</strong> (die Spielhandlung selbst erfordert Sprachverarbeitung,
        z.B. ein Objekt nach gesprochener Anweisung richtig platzieren) und reiner <strong>Gamification</strong>
        (Punkte, Badges, Streaks um Sprachübungen herum) — Letzteres beeinflusst vor allem das Nutzungsverhalten,
        nicht zuverlässig die sprachliche Verarbeitung selbst. Technologie ist insgesamt ein Transportmittel für
        bewährte Lernmechanismen, keine eigene Lernmethode.</p>

    <?= render_quellenliste([
        'webb-yanagisawa-intentional', 'webb-uchihara-yanagisawa-incidental',
        'kim-lee-lee-l1l2gloss', 'tonzar-picture-translation',
        'yi-zhong-multiword', 'schulz-multiword-review',
        'sangers-extensive-reading', 'nakanishi-extensive-reading', 'odo-phonics',
        'zhang-zhang-vocab-comprehension', 'dalman-plonsky-listening-strategy', 'sutton-webb-audiovisual',
        'mackey-goo-interaction', 'lyster-saito-feedback', 'hou-min-ai-dialogue',
        'brown-liu-norouzian-wcf',
        'kang-sok-han-formfocus', 'spada-tomita-explicit',
        'lee-jang-plonsky-pronunciation', 'yao-he-phonetic-training', 'uchihara-karas-thomson-hvpt',
        'tseng-liu-hsu-chu-studyabroad', 'clil-primary-unclear',
        'liu-zhang-dai-mobilegames',
        'kim-webb-spacing', 'shared-book-reading-unclear',
    ], 'accQuellenStudien') ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

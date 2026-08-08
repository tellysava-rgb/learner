# Personas

Dieses Dokument beschreibt die zwei zentralen Nutzergruppen des Vokabeltrainers. Es dient als Referenz für Produktentscheidungen: Bei neuen Funktionen oder UX-Änderungen hilft der Abgleich mit diesen Personas einzuschätzen, wem eine Änderung nützt, wem sie im Weg steht — und ob eine Funktion als Standardverhalten oder als optionale Erweiterung gehört.

Beide Personas sind fiktiv, aber aus wiederkehrenden Mustern in Anforderungen und Nutzungskontext abgeleitet (Kinder/Jugendliche als primäre Zielgruppe, Schule/Zuhause als Umfeld, Vokabeln + Mathe-Aufgaben als Lerninhalte).

---

## Persona 1: Der Einsteiger

**"Noah", 10 Jahre**
Schüler, nutzt die App zum ersten Mal

> „Ich will einfach schnell ein paar Wörter lernen können — nicht erst lange rausfinden müssen, wie das Ding funktioniert."

### Kontext & Rahmenbedingungen
- Erhält ein Konto von einem Elternteil oder einer Lehrperson (Name + Passwort sind bereits eingerichtet)
- Nutzt meist ein Smartphone oder Tablet, oft nur für ein paar Minuten zwischendurch (vor/nach den Hausaufgaben)
- Erste Sitzung überhaupt — kein Vorwissen über Leitner-System, Drill-Modus oder die App-spezifischen Begriffe
- Geringe Geduld für Text: liest keine ausführliche Hilfeseite, bevor er/sie loslegt

### Ziele
- Rasch eine **neue eigene Wortliste erstellen** (z. B. für ein aktuelles Schulthema) — oder noch schneller: eine **öffentliche Liste kopieren**, statt selbst Wörter einzutippen
- Alternativ eine **Mathe-Liste** anlegen (z. B. das 7er-Einmaleins) und direkt üben
- So schnell wie möglich mit dem eigentlichen Lernen starten — **Drill** (kurz, überschaubar, klare Rückmeldung) oder **Leitner**
- Ein sichtbares Erfolgserlebnis nach der ersten Session (z. B. "gemeistert", Streak-Badge)

### Bedürfnisse
- Klare, grosse, eindeutige Bedienelemente ohne Interpretationsspielraum (Liste erstellen, Drill starten, Leitner starten)
- Sinnvolle Voreinstellungen, die ihm Entscheidungen abnehmen (z. B. vorausgewählte zuletzt genutzte Liste, Zufalls-Lernrichtung als Default)
- Sofortiges, eindeutiges Feedback beim Lernen (Flip-Animation, grüner/oranger Button, motivierender Abschlusstext)
- Ein Login, das ohne technisches Verständnis funktioniert, und ein einfacher Weg zurück ins Konto, falls das Passwort vergessen wird

### Frustrationen / Pain Points
- Zu viele Optionen auf einer Konfigurationsseite wirken abschreckend, bevor überhaupt gelernt wird
- Fachbegriffe wie "Fach", "Warteschlange" oder "Mastery" sind ohne Erklärung nicht selbsterklärend
- Versteht nicht auf Anhieb, warum nicht sofort alle importierten/kopierten Karten verfügbar sind (Warteschlangen-Mechanik)
- Ist auf Hilfe von Erwachsenen angewiesen, sobald etwas ausserhalb des Kernablaufs schiefgeht (Passwort-Reset, versehentlich gelöschte Liste)

### Technische Kompetenz
- Im Umgang mit Apps und Touch-Bedienung generell geübt (Alltagserfahrung mit Smartphone/Tablet)
- Wenig Erfahrung mit textlastigen Hilfeseiten oder mehrstufigen Formularen
- Altersgemäss begrenzte Lesegeschwindigkeit — Bildsprache und kurze Texte wirken stärker als Fliesstext

### Typisches Nutzungsszenario (User Journey)
1. Login mit Name + Passwort (von einem Erwachsenen eingerichtet)
2. Landet auf der Startseite, sieht den Bereich "Entdecken" und kopiert eine öffentliche Liste — **oder** klickt "Neue Liste erstellen" und wählt zwischen Wortliste und Mathe-Aufgabe
3. Bei einer eigenen Liste: trägt ein paar Wörter über ein einfaches Formular ein
4. Klickt den grossen "Drill"-Button auf der Listen-Karte — Konfigurationsseite erscheint mit bereits sinnvoll vorausgewählten Werten, ein Klick auf "Start" genügt
5. Lernt einige Karten: antippen zum Aufdecken, "Gewusst" oder "Musste nachdenken" bewerten
6. Sitzungsende mit motivierender Zusammenfassung (Anzahl gemeistert, aktueller Streak)
7. Kommt am nächsten Tag zurück, sieht das Streak-Badge in der Navigation und macht weiter

### Erfolgskriterien (aus Sicht von Noah)
- "Ich konnte ohne fremde Hilfe eine Liste erstellen oder kopieren und sofort lernen."
- "Ich habe jederzeit verstanden, was als Nächstes zu tun ist."
- "Ich sehe, dass ich Fortschritte mache (Streak, gemeisterte Karten)."

### Nicht-Ziele
Statistik-Details, Einstellungen, CSV-Import/-Export, Migration zwischen Listen, Feinheiten der Aussprache-Konfiguration — all das liegt ausserhalb dessen, was diese Persona in der ersten Phase der Nutzung sucht oder braucht.

---

## Persona 2: Die Geübte

**"Sofia", 14 Jahre**
Nutzt die App seit mehreren Wochen regelmässig

> „Ich will verstehen, warum eine Karte gerade dran ist — und die App so einstellen, dass sie zu meinem Lernrhythmus passt, nicht umgekehrt."

### Kontext & Rahmenbedingungen
- Hat bereits mehrere eigene Listen (Vokabeln in mind. einer Fremdsprache sowie Mathe-Aufgaben)
- Nutzt die App auf mehreren Geräten (Smartphone unterwegs, Tablet/Laptop zuhause)
- Lernt regelmässig, der tägliche Streak ist ihr wichtig
- Tauscht sich teilweise mit Mitschüler:innen aus (z. B. wer welche Liste erstellt oder öffentlich geteilt hat)

### Ziele
- Mehr Kontrolle über den Lernprozess: Lernrichtung gezielt wählen, Kartenanzahl anpassen, **mehrere Listen gleichzeitig** in einer Session lernen
- Bestehende Wortlisten effizient **importieren** (CSV — teils von einer Lehrperson erhalten, teils selbst mit einem KI-Tool anhand der in der App bereitgestellten Vorlage erzeugt)
- Einzelne schwierige Karten gezielt **für den Drill vormerken**, um Problemwörter zusätzlich zu üben
- Den eigenen Fortschritt über Zeit nachvollziehen (Statistik-Dashboard, Heatmap, Streak, Verteilung über die Leitner-Fächer)
- Eigene Listen sauber organisieren — umbenennen, zusammenführen (migrieren), nicht mehr benötigte Listen inaktiv setzen statt zu löschen
- Aussprache und Lautschrift für Fremdsprachen nutzen, nicht nur für Englisch

### Bedürfnisse
- Nachvollziehbarkeit der Mechanik: warum ist eine Karte gerade in einem bestimmten Fach, wann kommt sie wieder dran
- Effizienz bei grösseren Mengen: mehrere Listen kombinieren, grosse Wortmengen auf einmal importieren statt einzeln zu erfassen
- Selbstständigkeit: eigenes Passwort und eigene E-Mail-Adresse selbst verwalten, Passwort-Reset ohne Rückgriff auf einen Erwachsenen
- Rückmeldung, die über kindliche Belohnungssymbole hinausgeht — nachvollziehbare Kennzahlen statt reinem Sticker-Gefühl

### Frustrationen / Pain Points
- Das tägliche Limit für neue Karten aus der Warteschlange wirkt zunächst wie eine unnötige Bremse, wenn der Grund dahinter nicht ersichtlich ist
- Der Unterschied zwischen Leitner und Drill — und wann welcher Modus sinnvoll ist — ist nicht auf den ersten Blick klar
- Wunsch nach noch feinerer Auswertung (z. B. welche einzelnen Wörter besonders oft falsch beantwortet werden) geht über das aktuell Gebotene hinaus
- Viele eigene Listen ohne Sortier- oder Suchmöglichkeit werden mit der Zeit unübersichtlich

### Technische Kompetenz
- Sicher im Umgang mit Dateien (CSV öffnen/bearbeiten, Datei-Uploads)
- Bereits Erfahrung mit KI-Tools (z. B. ChatGPT, Claude) zur Texterzeugung — nutzt vorgefertigte Prompts, um sich Vokabellisten erzeugen zu lassen
- Versteht das Grundprinzip von Karteikarten-Lernsystemen bereits aus der Schule, auch ohne die App-spezifischen Begriffe zu kennen

### Typisches Nutzungsszenario (User Journey)
1. Login, prüft Streak-Badge und die "heute fällig"-Anzeige auf der Startseite
2. Wählt mehrere Listen gleichzeitig für eine Leitner-Session aus (Checkbox-Mehrfachauswahl), passt die Kartenanzahl an
3. Merkt sich beim Lernen auffällig schwierige Karten direkt für den Drill vor
4. Startet später gezielt eine Drill-Session mit eigenem Timer, um die vorgemerkten Problemkarten zu üben
5. Importiert eine neue Vokabelliste per CSV — mit einer KI anhand der in der App bereitgestellten Vorlage und des Prompt-Textes erzeugt
6. Prüft nach einigen Wochen die Statistikseite: Heatmap der Lernaktivität, Verteilung über die Fächer, beste Lernwoche
7. Räumt die eigene Listenübersicht auf: migriert Karten in eine andere Liste, setzt eine nicht mehr gebrauchte Liste inaktiv statt sie zu löschen

### Erfolgskriterien (aus Sicht von Sofia)
- "Ich verstehe, warum eine Karte gerade fällig ist und wann sie wiederkommt."
- "Ich sehe meinen Fortschritt über Zeit und bleibe dadurch motiviert dranzubleiben."
- "Ich kann meine Listen auch dann noch effizient verwalten, wenn es viele werden."

### Nicht-Ziele
Administrative Funktionen (Benutzerverwaltung, globale Einstellungen, Deployment) sind nicht Teil ihres Bedarfs, sofern sie nicht selbst Admin ist. Technische Details wie Datenbankfelder oder Konfigurationskonstanten interessieren sie nicht — nur deren Auswirkung auf das eigene Lernerlebnis.

---

## Verwendung dieser Personas

Die beiden Personas stehen bewusst in einem Spannungsfeld: **Noah** braucht Einfachheit und sinnvolle Vorgaben, **Sofia** braucht Kontrolle und Transparenz über dieselbe Mechanik. Für Produktentscheidungen leitet sich daraus ein Grundprinzip ab, das sich in der bestehenden Anwendung bereits wiederfindet und bei künftigen Änderungen leiten sollte:

- **Neue Funktionen sollten standardmässig unsichtbar oder unaufdringlich sein**, wenn sie primär die Geübte betreffen (z. B. Mehrfachauswahl von Listen, manuelle Vormerkung für den Drill, Debug-Informationen) — der Einsteiger darf davon nicht überfordert werden, ohne sie je zu benötigen
- **Voreinstellungen sollten immer für den Einsteiger funktionieren**, auch ohne dass er sie versteht oder anpasst (z. B. zufällige Lernrichtung als Default, automatisch vorausgewählte zuletzt genutzte Liste)
- **Erklärungen/Hilfetexte dürfen knapp bleiben, solange die zugrunde liegende Mechanik für die Geübte an anderer Stelle nachvollziehbar bleibt** (z. B. über die Statistik oder ein Debug-Panel) — nicht jede Funktion muss für den Einsteiger im Detail erklärt werden, wenn sie ihn nicht aktiv betrifft

Bei einer Anforderung, die sich nicht klar einer der beiden Personas zuordnen lässt, ist zu prüfen, ob sie das Grundprinzip "einfacher Einstieg, optionale Tiefe" verletzt.

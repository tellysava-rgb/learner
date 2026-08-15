# MCP-Server Einrichtung

## Voraussetzungen

1. `includes/mcp-config.php` aus `includes/mcp-config.example.php` erstellen und Token setzen:
   ```
   php -r "echo bin2hex(random_bytes(32));"
   ```
   Denselben Token auf Dev und Prod verwenden (einfachste Lösung).

2. Auf Produktion: HTTPS ist Pflicht. HTTP-Requests werden mit HTTP 403 abgewiesen.

3. Die `includes/mcp-config.php` ist gitignored — manuell per FTP auf den Produktionsserver kopieren.

---

## Claude Code / VS Code

`.mcp.json.example` als `.mcp.json` im Projektverzeichnis kopieren und Tokens eintragen.
`.mcp.json` ist in `.gitignore` (Tokens nicht committen).

```json
{
  "mcpServers": {
    "learner-dev": {
      "type": "http",
      "url": "http://localhost/learner/mcp-server.php",
      "headers": { "Authorization": "Bearer DEIN_TOKEN" }
    },
    "learner-prod": {
      "type": "http",
      "url": "https://lernen.springpunkt.ch/mcp-server.php",
      "headers": { "Authorization": "Bearer DEIN_TOKEN" }
    }
  }
}
```

### Workflow in Claude Code

Der Agent arbeitet interaktiv. Die vollständigen Feld-Regeln stehen in den `initialize`-Instructions und den Tool-Beschreibungen des Servers selbst (siehe `docs/ANFORDERUNGEN.md`, Abschnitt "MCP-Server") — hier nur die grobe Reihenfolge:

1. `list_persons` aufrufen → Person per Name auflösen (oder direkt im Prompt mitgeben)
2. `list_lists(person_id)` aufrufen → Listen anzeigen, User wählt. Anhand `language_a`/`language_b` bestimmen, welche Seite Deutsch ist (relevant für Rechtschreibung — die Rollen von Beschreibung A/B sind davon unabhängig fest)
3. Karten aufbereiten (Begriff A/B als Chunk mit Kontext, Beschreibung A = Hinweis, Beschreibung B = Beispielsatz mit dem exakten Begriff, ggf. Phonetik, ggf. Tags — vor dem Setzen eines Tags `list_person_tags(person_id)` prüfen) und dem User vollständig zur Bestätigung zeigen, inkl. sichtbarer Rückübersetzung von Begriff B
4. Nach Bestätigung `add_cards`/`update_card` aufrufen. Enthält die Antwort `warnings` (z.B. Kernbegriff aus Begriff A/B in Beschreibung A gefunden, unbekannter Tag gesetzt, unbekannter Parametername), diese dem User zeigen statt zu übergehen. Bei `update_card` zusätzlich `changed_fields` prüfen, um zu bestätigen dass die Änderung wie erwartet ankam

Bei einer **Duplikat-Warnung** (`status: "duplicate"`) fragt der Agent erst nach, bevor er mit `force=true` erneut aufruft.

---

## ChatGPT / claude.ai Browser

Diese Clients unterstützen keinen Authorization-Header für eigene Konnektoren.
Als Workaround: Token als Query-Parameter in der URL:

```
https://lernen.springpunkt.ch/mcp-server.php?token=DEIN_TOKEN
```

**Hinweis:** claude.ai erfordert OAuth für Browser-Konnektoren — funktioniert dort aktuell nicht ohne OAuth-Implementierung.

---

## Claude Desktop App (Mac)

Claude Desktop unterstützt HTTP-MCP nicht nativ. Workaround via `mcp-remote` (Node.js erforderlich):

In `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "learner": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://lernen.springpunkt.ch/mcp-server.php",
               "--header", "Authorization:Bearer DEIN_TOKEN"]
    }
  }
}
```

---

## n8n Cloud — AI Agent Node als MCP Client

**Verbindung:**
- Node: **MCP Client Tool**
- Transport: **HTTP**
- URL: `https://lernen.springpunkt.ch/mcp-server.php`
- Authentication: **Header Auth** → `Authorization: Bearer DEIN_TOKEN`

**Agent Instructions (System Prompt):**

```
Du bist ein Vokabelkarten-Assistent für den Learner-Vokabeltrainer.

Workflow zum Hinzufügen von Karten:
1. Rufe list_persons auf und löse die Person per Name auf.
2. Rufe list_lists(person_id) auf und löse die Ziel-Liste per Name auf. Bestimme anhand
   language_a/language_b, welche Seite Deutsch ist (relevant für Rechtschreibung — die
   Rollen von Beschreibung A/B unten sind davon unabhängig fest).
3. Begriff A und Begriff B: KEIN isoliertes Einzelwort, sondern eine natürliche Phrase/
   ein Chunk mit realistischem Verwendungskontext (z.B. nicht "Entscheid" sondern "einen
   wichtigen Entscheid treffen"). Der jeweils andere Begriff darf im Chunk nicht so
   vorkommen, dass er die Antwort preisgibt. WICHTIGSTE Regel: Begriff A und Begriff B
   müssen exakt dieselbe Bedeutung tragen (Fundament des Sprachenlernens) — Ausnahme nur
   bei Sprichwörtern/Redewendungen ohne wörtliche Entsprechung (z.B. "once in a blue moon"
   ↔ "alle Jubeljahre"), dort sinngemäss statt wörtlich, aber die Kernaussage muss
   weiterhin exakt übereinstimmen. Rückübersetzung von Begriff B muss bedeutungsgleich mit
   Begriff A sein, sonst vor dem Aufruf von add_cards korrigieren. Bei Verben als
   Kernbegriff in der Fremdsprache: Grundform, bei unregelmässigen Verben alle drei Formen
   (z.B. "go / went / gone"). Deutscher Anteil: de-CH-Rechtschreibung (NIE "ß", immer
   "ss"), Nomen immer gross, Rest klein.
4. Beschreibung A und Beschreibung B haben feste Rollen, unabhängig von der Sprache:
   - Beschreibung A = kognitiver Hinweis zur Selbstkorrektur, KEINE direkte Lösung,
     der Begriff selbst darf nicht erscheinen.
   - Beschreibung B = natürlicher Beispielsatz mit dem EXAKTEN Begriff aus Begriff B,
     kein Lehrbuchsatz.
   WICHTIG: Der Begriff darf NIEMALS in Beschreibung A wiederholt werden.
5. Phonetik (phonetik_b) nur befüllen wenn die Liste ein speech_lang_b gesetzt hat.
   Stil (vereinfacht oder IPA) aus vorhandenen phonetik_b-Werten der Liste ableiten
   (list_cards aufrufen) oder, falls keine vorhanden, "einfach" annehmen (keine
   Rückfrage möglich in diesem automatisierten Workflow).
6. Tags (optional, mehrere möglich, Format "#Tag1 #Tag2"): rufe zuerst
   list_person_tags(person_id) auf und verwende einen passenden vorhandenen Tag wieder,
   statt einen neuen zu erfinden. Nur setzen wenn sinnvoll.
7. Rufe add_cards auf. Enthält die Antwort ein "warnings"-Feld pro Karte (z.B. Kernbegriff
   aus Begriff A/B in Beschreibung A gefunden, unbekannter Tag gesetzt, unbekannter
   Parametername in der eigenen Anfrage): da in diesem automatisierten Workflow kein Mensch
   die Warnung sieht, selbst korrigieren und die betroffene Karte per update_card
   nachbessern, statt die Warnung zu ignorieren.

WICHTIG – Duplikate:
Wenn add_cards eine Duplikat-Warnung zurückgibt (status: "duplicate"), rufe
add_cards SOFORT erneut mit force=true auf, ohne Rückfrage. In diesem
automatisierten Workflow ist kein Mensch anwesend um zu bestätigen.
```

**Unterschied zu Claude Code:** In n8n wird bei Duplikaten immer sofort mit `force=true` forciert (kein Mensch beaufsichtigt den Workflow). In Claude Code wird erst nach Rückfrage forciert.

---

## Apache — Authorization-Header

Falls der `Authorization`-Header nicht ankommt (HTTP 401 obwohl Token korrekt), in `.htaccess` ergänzen:

```apache
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

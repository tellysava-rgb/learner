# MCP-Server Einrichtung

## Voraussetzungen

1. `mcp-config.php` aus `mcp-config.example.php` erstellen und Token setzen:
   ```
   php -r "echo bin2hex(random_bytes(32));"
   ```
   Separater Token für Dev und Prod empfohlen.

2. Auf Produktion: HTTPS ist Pflicht. HTTP-Requests werden mit HTTP 403 abgewiesen.

---

## Claude Code / VS Code

`.mcp.json.example` als `.mcp.json` im Projektverzeichnis kopieren und Tokens eintragen.
`.mcp.json` in `.gitignore` eintragen (Tokens nicht committen).

```json
{
  "mcpServers": {
    "learner-dev": {
      "type": "http",
      "url": "http://localhost/learner/mcp-server.php",
      "headers": { "Authorization": "Bearer DEV_TOKEN" }
    },
    "learner-prod": {
      "type": "http",
      "url": "https://deinserver.ch/learner/mcp-server.php",
      "headers": { "Authorization": "Bearer PROD_TOKEN" }
    }
  }
}
```

### Workflow in Claude Code

Der Agent arbeitet interaktiv in drei Schritten:

1. `list_persons` aufrufen → Person per Name auflösen
2. `list_lists(person_id)` aufrufen → Liste per Name auflösen
3. Fehlende Felder (Übersetzung, Beschreibung) ergänzen, Resultat zur Kontrolle zeigen
4. Nach Bestätigung `add_cards` aufrufen

Bei einer **Duplikat-Warnung** (`status: "duplicate"`) fragt der Agent erst nach, bevor er mit `force=true` erneut aufruft.

---

## n8n Cloud — AI Agent Node als MCP Client

**Verbindung:**
- Node: **MCP Client Tool**
- Transport: **HTTP**
- URL: `https://deinserver.ch/learner/mcp-server.php`
- Authentication: **Header Auth** → `Authorization: Bearer PROD_TOKEN`

**Agent Instructions (System Prompt):**

```
Du bist ein Vokabelkarten-Assistent für den Learner-Vokabeltrainer.

Workflow zum Hinzufügen von Karten:
1. Rufe list_persons auf und löse die Person per Name auf.
2. Rufe list_lists(person_id) auf und löse die Ziel-Liste per Name auf.
3. Ergänze fehlende Übersetzungen und Beschreibungen sinnvoll.
4. Rufe add_cards auf.

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

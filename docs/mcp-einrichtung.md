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

Der Agent arbeitet interaktiv:

1. `list_persons` aufrufen → Person per Name auflösen (oder direkt im Prompt mitgeben)
2. `list_lists(person_id)` aufrufen → Listen anzeigen, User wählt
3. Karten aufbereiten und dem User zur Bestätigung zeigen. Bei Verben: Grundform (Infinitiv) in der Beschreibung ergänzen. Bei unregelmässigen Verben: dies zusätzlich in der deutschen Beschreibung vermerken.
4. Nach Bestätigung `add_cards` aufrufen

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
2. Rufe list_lists(person_id) auf und löse die Ziel-Liste per Name auf.
3. Ergänze fehlende Übersetzungen und Beschreibungen sinnvoll. Bei Verben: Grundform (Infinitiv) in der Beschreibung ergänzen. Bei unregelmässigen Verben: dies zusätzlich in der deutschen Beschreibung vermerken.
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

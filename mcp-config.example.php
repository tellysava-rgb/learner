<?php
// MCP-Server Zugangsdaten — Vorlage
// Als mcp-config.php kopieren und Token eintragen.
// mcp-config.php ist in .gitignore (nie committen).
//
// Token generieren:
//   php -r "echo bin2hex(random_bytes(32));"

define('MCP_TOKEN', 'SICHERES_ZUFALLS_TOKEN_HIER_EINTRAGEN');

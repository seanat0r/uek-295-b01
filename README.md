# üK 295: Online-Shop API

REST-API zur Verwaltung von Produkten und Kategorien mit Slim, MySQL und JWT-Authentifizierung. Die
Schnittstellendokumentation wird als OpenAPI-YAML ausgegeben und über Swagger UI angezeigt.

## Voraussetzungen

- XAMPP oder MAMP (evtl. Port anpassen!)
- PHP ab 8.1
- MySQL mit den Tabellen `product` und `category` gemäss Datenmodell
- Composer

Der Inhalt dieses Repositorys wird direkt im `htdocs`-Ordner des Webservers abgelegt. Die folgenden URLs gehen davon
aus, dass das Projekt ohne zusätzlichen Unterordner erreichbar ist.

## Abhängigkeiten installieren

Im Projektverzeichnis ausführen:

```bash
composer install
```

Dadurch wird der Ordner `vendor` mit den benötigten PHP-Bibliotheken erstellt.

## Konfiguration einrichten

Die Datei [config/config.example.json](config/config.example.json) dient als Vorlage. Erstelle davon eine Kopie mit dem
Namen `config.json` im gleichen Ordner:

```bash
cp config/config.example.json config/config.json
```

Trage in `config/config.json` die Werte deiner Umgebung ein:

| Einstellung     | Bedeutung                                                                         |
|-----------------|-----------------------------------------------------------------------------------|
| `auth_username` | Benutzername für die Anmeldung an der API                                         |
| `auth_password` | Passwort für die Anmeldung; wird auch zum Signieren und Prüfen der JWTs verwendet |
| `db_servername` | Hostname des MySQL-Servers, beispielsweise `localhost`                            |
| `db_username`   | Benutzername für die Datenbankverbindung                                          |
| `db_password`   | Passwort des Datenbankbenutzers, falls kein Passwort verwendet wird: `""`         |
| `db_dbname`     | Name der vorhandenen Projektdatenbank                                             |

Falls MySQL einen anderen Port verwendet, kann dieser bei `db_servername` ergänzt werden, beispielsweise
`127.0.0.1;port=8889`. Der Datenbankport ist unabhängig vom HTTP-Port des Webservers.

## API aufrufen

Apache und MySQL starten. Die Basisadresse lautet:

```text
http://localhost/api/v1
```

Die Anmeldung erfolgt mit `POST /authenticate`. Verwende die Werte aus `auth_username` und `auth_password`:

```json
{
  "username": "dein-benutzername",
  "password": "dein-passwort"
}
```

Den Header `Content-Type: application/json` setzen. Bei erfolgreicher Anmeldung setzt die API das Cookie `jwt_token`.
Dieses muss bei den anschliessenden Produkt- und Kategorieabrufen mitgesendet werden. In Bruno die Cookie-Verwaltung
aktiviert lassen und zuerst den Authenticate-Request ausführen.

## Swagger UI einrichten

Swagger UI liegt im Ordner `public/swagger-ui`. Dieser Ordner wird durch `.gitignore` ausgeschlossen und muss nach dem
Klonen gegebenenfalls separat bereitgestellt werden. Die Swagger-UI-Dateien müssen direkt darin liegen, sodass
`public/swagger-ui/index.html` vorhanden ist.

Öffne die Datei `public/swagger-ui/swagger-initializer.js`. Die Einstellung `url` muss auf den YAML-Exporter dieses
Projekts zeigen, evtl. Port anpassen:

```javascript
url: "http://localhost:8888/public/swagger-php.php"
```

Passe den Hostnamen und den Port an deinen Webserver an. Läuft Apache beispielsweise auf Port `80`, lautet die
Einstellung:

```javascript
url: "http://localhost/public/swagger-php.php"
```

Danach Swagger UI im Browser öffnen:

```text
http://localhost:8888/public/swagger-ui/index.html
```

Auch bei dieser Adresse den Port anpassen. Der YAML-Export ist direkt unter folgender Adresse erreichbar:

```text
http://localhost:8888/public/swagger-php.php
```

Wenn Swagger UI die Dokumentation nicht lädt, zuerst die Exportadresse direkt im Browser prüfen. Anschliessend die `url`
im Initializer kontrollieren und Swagger UI neu laden. Bei der vorgegebenen Bruno-Collection müssen die Request-URLs
ebenfalls den HTTP-Port deiner Umgebung verwenden.

# üK 295: Online-Shop API

Diese REST-API verwaltet Produkte und Kategorien eines Online-Shops. Sie verwendet PHP, Slim, MySQL mit PDO und JWT zur
Authentifizierung. Die Schnittstelle ist mit OpenAPI dokumentiert und kann über Swagger UI eingesehen und getestet
werden.

## Voraussetzungen

- XAMPP oder MAMP mit Apache und MySQL
- PHP ab Version 8.1 mit den Erweiterungen `pdo_mysql` und `mbstring`
- Composer
- SQL-Dump mit der Struktur und den Daten der Tabellen `product` und `category`
- Bruno für die vorgegebene Request-Collection

Lege den Inhalt des Repositorys direkt im `htdocs`-Ordner des Webservers ab. Die folgenden Beispiele verwenden
`http://localhost:8888`. Falls Dein Webserver einen anderen Port verwendet, passe die Adressen entsprechend an.

Die Pfade gehen davon aus, dass das Projekt ohne zusätzlichen Unterordner erreichbar ist. Apache muss die Weiterleitung
aus der vorhandenen `.htaccess` verarbeiten können.

## Projekt einrichten

### 1. Abhängigkeiten installieren

Führe im Projektverzeichnis folgenden Befehl aus:

```bash
composer install
```

Composer installiert die benötigten Bibliotheken im Ordner `vendor`.

### 2. Datenbank vorbereiten

Starte MySQL und importiere den zugehörigen SQL-Dump, beispielsweise über phpMyAdmin. Der Dump wird separat benötigt.
Die Tabellen `product` und `category` müssen dem vorgegebenen Datenmodell entsprechen.

### 3. Konfiguration erstellen

Kopiere [config/config.example.json](config/config.example.json) nach `config/config.json`:

```bash
cp config/config.example.json config/config.json
```

Passe die Werte an Deine Umgebung an:

| Einstellung     | Bedeutung                                                        |
|-----------------|------------------------------------------------------------------|
| `auth_username` | Benutzername für die Anmeldung an der API                        |
| `auth_password` | Passwort für die Anmeldung und zum Signieren und Prüfen der JWTs |
| `db_servername` | Hostname des MySQL-Servers, beispielsweise `localhost`           |
| `db_username`   | Benutzername für die Datenbankverbindung                         |
| `db_password`   | Passwort des Datenbankbenutzers; ohne Passwort `""` verwenden    |
| `db_dbname`     | Name der importierten Datenbank                                  |

Falls MySQL einen anderen Port verwendet, kannst Du diesen bei `db_servername` ergänzen, beispielsweise mit
`127.0.0.1;port=8889`. Der Datenbankport ist unabhängig vom HTTP-Port des Webservers.

Die Datei `config/config.json` ist von Git ausgeschlossen. Verwende darin Deine lokalen Zugangsdaten.

### 4. API starten

Starte Apache und MySQL. Die Basisadresse lautet in den folgenden Beispielen:

```text
http://localhost:8888/api/v1
```

## Anmelden und API verwenden

Rufe zuerst `POST /api/v1/authenticate` auf. Setze den Header `Content-Type: application/json` und übergib die in
`config/config.json` hinterlegten Zugangsdaten:

```json
{
  "username": "dein-benutzername",
  "password": "dein-passwort"
}
```

Bei erfolgreicher Anmeldung erhältst Du diese Antwort:

```json
{
  "message": "Authenticated successfully"
}
```

Der JWT wird im Cookie `jwt_token` gesetzt und ist eine Stunde gültig. Sende dieses Cookie bei allen Produkt- und
Kategorieanfragen mit. Der Token steht nicht im JSON-Body.

## Endpoints

Alle Pfade in dieser Tabelle beginnen bei `/api/v1`. Nur die Anmeldung ist ohne JWT erreichbar.

| Methode | Pfad                      | Zweck                                           |
|---------|---------------------------|-------------------------------------------------|
| POST    | `/authenticate`           | Anmelden und JWT-Cookie erhalten                |
| GET     | `/products`               | Alle Produkte auflisten                         |
| GET     | `/product/{sku}`          | Ein Produkt anhand seiner SKU abrufen           |
| PUT     | `/product/{sku}`          | Ein Produkt erstellen oder vollständig ersetzen |
| DELETE  | `/product/{sku}`          | Ein Produkt löschen                             |
| GET     | `/categories`             | Alle Kategorien auflisten                       |
| GET     | `/category/{category_id}` | Eine Kategorie anhand ihrer ID abrufen          |
| POST    | `/category`               | Eine Kategorie erstellen                        |
| PATCH   | `/category/{category_id}` | Einzelne Felder einer Kategorie ändern          |
| DELETE  | `/category/{category_id}` | Eine Kategorie löschen                          |

Bei PUT sind `name`, `active`, `price` und `stock` erforderlich. Fehlende optionale Felder `id_category`, `image` und
`description` werden auf `null` gesetzt. Der Preis wird auf zwei Nachkommastellen gerundet und muss danach grösser als
null sein.

Bei PATCH muss mindestens `name` oder `active` übergeben werden. Nicht übergebene Felder bleiben unverändert. Die
vollständigen Feldbeschreibungen und Beispielwerte findest Du in Swagger UI.

## Antworten und Statuscodes

Erfolgreiche Datenabfragen liefern direkt ein Objekt oder eine Liste. Eine Liste ohne Einträge wird als `[]`
zurückgegeben. Fehlerantworten enthalten einheitlich `error` und `code`, beispielsweise:

```json
{
  "error": "Product not found.",
  "code": 404
}
```

## OpenAPI und Swagger UI

Die OpenAPI-Beschreibung steht als PHP-Attribute im Quellcode. [public/swagger-php.php](public/swagger-php.php) fasst
diese Angaben zusammen und gibt sie als YAML aus.

- [Swagger UI öffnen](http://localhost:8888/public/swagger-ui/index.html)
- [OpenAPI-YAML öffnen](http://localhost:8888/public/swagger-php.php)

Swagger UI liegt im Ordner `public/swagger-ui` und ist im Repository enthalten.
In [swagger-initializer.js](public/swagger-ui/swagger-initializer.js) muss `url` auf den YAML-Exporter zeigen. Mit
diesem Pfad verwendet Swagger UI automatisch denselben Host und Port wie die geöffnete Seite:

```javascript
url: "/public/swagger-php.php"
```


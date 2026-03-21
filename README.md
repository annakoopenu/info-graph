# Info-Graph

Info-Graph is a lightweight PHP app for tracking personal items such as books, music, paintings, links, or anything else you want to collect and revisit.

It includes:

- A server-rendered web UI for browsing, creating, editing, and deleting items
- JSON-backed storage using `app/info_graph_47_rows.json`
- Search and filtering by text, tag, and flag
- CSV import with validation and preview
- CSV export
- A JSON API for programmatic access
- MCP-style tool endpoints for AI/tool integrations

## What an item looks like

Each item can store:

- `item_name` - required title or name
- `author_name` - creator, author, or artist
- `category` - optional grouping such as music, film, or book
- `link` - related URL
- `link_image` - image URL shown in the UI
- `tags` - comma- or semicolon-separated labels
- `notes` - free text
- `rating` - integer from `1` to `100`
- `flag` - optional status such as `revisit` or `completed`

## Project structure

```text
app/
  index.php              Main entry point
  src/
    Controllers/         Web, API, CSV, and MCP controllers
    Repositories/        JSON file access
    Services/            CSV import/export logic
  templates/             PHP templates for the UI
  assets/                Frontend assets
  openapi.json           OpenAPI spec for the JSON API
  info_graph_47_rows.json Primary data file
  info_graph_47_rows.csv  Import/export-friendly dataset
req/
  definition.txt         Original feature outline
```

## Requirements

- PHP 8+

There is no Composer dependency and no database requirement in the current app. Everything runs with plain PHP and a local JSON file.

## Local setup

### 1. Configure environment variables

Create `app/.env`:

```env
APP_BASE_PATH=
APP_DATA_FILE=info_graph_47_rows.json
```

Notes:

- `APP_BASE_PATH` controls URL generation and route matching
- For local `php -S` usage from the `app/` directory, leave `APP_BASE_PATH=` empty
- If the app is hosted at `/info-graph`, set `APP_BASE_PATH=/info-graph`
- `APP_DATA_FILE` defaults to `app/info_graph_47_rows.json`

### 2. Start the app

Run PHP's built-in server from the `app/` directory:

```bash
cd app
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000/items
```

## Main routes

### Web UI

- `GET /items` - list items
- `GET /items/new` - create form
- `POST /items` - create item
- `GET /items/{id}` - item details
- `GET /items/{id}/edit` - edit form
- `POST /items/{id}` - update item
- `POST /items/{id}/delete` - delete item
- `GET /import` - CSV upload form
- `POST /import` - upload and preview CSV
- `POST /import/confirm` - confirm CSV import
- `GET /export` - download CSV export

### JSON API

- `GET /api/items`
- `POST /api/items`
- `GET /api/items/{id}`
- `PATCH /api/items/{id}`
- `POST /api/items/{id}` as a PATCH-compatible fallback
- `DELETE /api/items/{id}`
- `POST /api/items/{id}/delete` as a DELETE-compatible fallback

### MCP endpoints

- `GET /mcp/tools` - list available tools and input schemas
- `POST /mcp/call` - call a tool by name

## API examples

Assuming the app is running locally with an empty base path:

### List items

```bash
curl http://127.0.0.1:8000/api/items
```

With filters:

```bash
curl "http://127.0.0.1:8000/api/items?search=coltrane&tag=music&flag=revisit"
```

### Create an item

```bash
curl -X POST http://127.0.0.1:8000/api/items \
  -H "Content-Type: application/json" \
  -d '{
    "item_name": "Blue Train",
    "author_name": "John Coltrane",
    "category": "music",
    "link": "https://example.com/blue-train",
    "link_image": "https://example.com/blue-train.jpg",
    "tags": "music; jazz",
    "notes": "Classic hard bop album",
    "rating": 92,
    "flag": "completed"
  }'
```

### Update an item

```bash
curl -X PATCH http://127.0.0.1:8000/api/items/1 \
  -H "Content-Type: application/json" \
  -d '{
    "rating": 95,
    "flag": "revisit"
  }'
```

If your client cannot send `PATCH`, use:

```bash
curl -X POST http://127.0.0.1:8000/api/items/1 \
  -H "Content-Type: application/json" \
  -d '{"rating":95}'
```

### Delete an item

```bash
curl -X DELETE http://127.0.0.1:8000/api/items/1
```

## CSV import format

Expected columns:

- `id` optional, used to update an existing item
- `item_name` required
- `author_name`
- `category`
- `link`
- `link_image`
- `tags`
- `notes`
- `rating`
- `flag`

Example:

```csv
item_name,author_name,category,link,tags,notes,rating,flag,link_image
Blue Train,John Coltrane,music,https://example.com/blue-train,"music; jazz",Classic album,92,completed,https://example.com/blue-train.jpg
```

Import behavior:

- Rows with an `id` are treated as updates
- Rows without an `id` are treated as creates
- The upload step validates the CSV before import
- File size is limited to 2 MB

## MCP tools

The MCP controller currently exposes these tools:

- `items.list`
- `items.get`
- `items.create`
- `items.update`
- `items.delete`
- `items.export_csv`

Example call:

```bash
curl -X POST http://127.0.0.1:8000/mcp/call \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "items.list",
    "arguments": {
      "tag": "music"
    }
  }'
```

## OpenAPI

The JSON API spec lives at `app/openapi.json`.

If you deploy the app under a different host or base path, update the `servers` section in that file to match your environment.

## Testing

The repository includes a self-contained CSV test script:

```bash
php app/tests/csv-test.php
```

It verifies parsing, validation, BOM handling, and export formatting without requiring PHPUnit.

## Current implementation notes

- Routing is custom and intentionally small
- The UI uses plain PHP templates and server-side rendering
- The source of truth is the JSON file at `app/info_graph_47_rows.json`
- CRUD actions update that JSON file directly

## Future improvements

- Add authentication if the app will be exposed publicly
- Add automated integration tests for the web and API layers
- Add pagination for larger collections
- Add stricter enum validation for `flag`
- Add a backup/versioning flow for JSON data edits

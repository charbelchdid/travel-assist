# Postman Collection Update Guide

## Quick Reference for Adding New Endpoints

When adding a new API endpoint to the Laravel application, follow these steps to update the Postman collection:

## Purpose

Keep `Laravel-API-Collection.postman_collection.json` aligned with the actual API routes and auth modes in this repo.

## How to use this project

See [`README.md`](README.md) for setup/running and base URLs.

## How to develop

- Routes are defined in `src/routes/api.php`
- Regenerate route docs using `.\tests\generate-api-docs.ps1` (updates `API_ROUTES.md`)

### 1. Locate the Collection File
File: `Laravel-API-Collection.postman_collection.json`

### 2. Add New Endpoint Structure

For each new endpoint, add this structure to the appropriate folder in the collection:

```json
{
  "name": "Endpoint Name",
  "request": {
    "method": "GET|POST|PUT|DELETE",
    "header": [
      {
        "key": "Content-Type",
        "value": "application/json"
      }
    ],
    "body": {
      "mode": "raw",
      "raw": "{\n    \"field\": \"value\"\n}"
    },
    "url": {
      "raw": "{{baseUrl}}/your/endpoint/path",
      "host": ["{{baseUrl}}"],
      "path": ["your", "endpoint", "path"],
      "query": [
        {
          "key": "param_name",
          "value": "param_value",
          "description": "Parameter description"
        }
      ]
    },
    "description": "Endpoint description and usage"
  }
}
```

### 3. Authentication Requirements

#### For Public Endpoints
Add this to the request:
```json
"auth": {
  "type": "noauth"
}
```

#### For Protected Endpoints
The collection default auth will be used (Bearer token with `{{authToken}}`)

### 4. Add Test Scripts (Optional)

For endpoints that return important data to save:

```json
"event": [
  {
    "listen": "test",
    "script": {
      "exec": [
        "if (pm.response.code === 200) {",
        "    const response = pm.response.json();",
        "    // Save important data",
        "    pm.collectionVariables.set('variableName', response.data.field);",
        "}"
      ],
      "type": "text/javascript"
    }
  }
]
```

### 5. Update README.md

After adding endpoints to Postman collection:

1. Update the "Current API Coverage" section
2. Add endpoint to the API Endpoints table
3. Document any new parameters or response formats

## Common Patterns

### GET Request (List with Pagination)
```json
{
  "name": "Get Items List",
  "request": {
    "method": "GET",
    "url": {
      "raw": "{{baseUrl}}/items?page=1&per_page=15",
      "host": ["{{baseUrl}}"],
      "path": ["items"],
      "query": [
        {"key": "page", "value": "1"},
        {"key": "per_page", "value": "15"}
      ]
    }
  }
}
```

### POST Request (Create Resource)
```json
{
  "name": "Create Item",
  "request": {
    "method": "POST",
    "header": [
      {"key": "Content-Type", "value": "application/json"}
    ],
    "body": {
      "mode": "raw",
      "raw": "{\n    \"name\": \"Item Name\",\n    \"value\": 100\n}"
    },
    "url": {
      "raw": "{{baseUrl}}/items",
      "host": ["{{baseUrl}}"],
      "path": ["items"]
    }
  }
}
```

### PUT Request (Update Resource)
```json
{
  "name": "Update Item",
  "request": {
    "method": "PUT",
    "header": [
      {"key": "Content-Type", "value": "application/json"}
    ],
    "body": {
      "mode": "raw",
      "raw": "{\n    \"name\": \"Updated Name\"\n}"
    },
    "url": {
      "raw": "{{baseUrl}}/items/{{itemId}}",
      "host": ["{{baseUrl}}"],
      "path": ["items", "{{itemId}}"]
    }
  }
}
```

### DELETE Request
```json
{
  "name": "Delete Item",
  "request": {
    "method": "DELETE",
    "url": {
      "raw": "{{baseUrl}}/items/{{itemId}}",
      "host": ["{{baseUrl}}"],
      "path": ["items", "{{itemId}}"]
    }
  }
}
```

## Validation Rules

Before committing updates:

1. ✅ Test the endpoint in Postman
2. ✅ Verify authentication requirements
3. ✅ Include all required parameters
4. ✅ Add meaningful descriptions
5. ✅ Update README.md documentation
6. ✅ Ensure consistent response format

## Collection Variables

Current variables in use:
- `baseUrl`: API base URL (default: http://localhost:8080/api)
- `authToken`: JWT token (automatically set after login)

Add new variables as needed for dynamic values.

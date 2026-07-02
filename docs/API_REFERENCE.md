# API Reference - Admin Console Endpoints

## Base URL
```
http://localhost:8001/api/admin
```

## Authentication
All endpoints require:
- Header: `Authorization: Bearer YOUR_JWT_TOKEN`
- Header: `Content-Type: application/json` (for POST/PUT)

## Database Connections

### List All Connections
```
GET /database-connections
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Production PostgreSQL",
      "type": "postgresql",
      "host": "db.prod.com",
      "port": 5432,
      "database": "myapp",
      "username": "dbadmin",
      "active": true,
      "tested": true,
      "lastTestedAt": "2026-07-02T14:00:00+00:00",
      "createdAt": "2026-07-01T10:00:00+00:00"
    }
  ],
  "count": 1
}
```

### Get Connection Details
```
GET /database-connections/:id
```

### Create Connection
```
POST /database-connections
Content-Type: application/json

{
  "name": "Production PostgreSQL",
  "type": "postgresql",
  "host": "db.prod.com",
  "port": 5432,
  "database": "myapp",
  "username": "dbadmin",
  "password": "SecurePassword123!",
  "advancedOptions": {
    "ssl": true,
    "timeout": 30,
    "pool_size": 10
  }
}
```

**Valid Types**: `mysql`, `postgresql`, `neo4j`, `arangodb`

### Update Connection
```
PUT /database-connections/:id
Content-Type: application/json

{
  "name": "Production PostgreSQL",
  "host": "db.prod.com",
  "port": 5432,
  "database": "myapp",
  "username": "dbadmin",
  "password": "NewPassword123!",  # Optional
  "advancedOptions": {...}
}
```

### Delete Connection
```
DELETE /database-connections/:id
```

### Test Connection
```
POST /database-connections/:id/test
```

**Response**:
```json
{
  "success": true,
  "message": "Connexion testée avec succès"
}
```

---

## Database Browser

### List Database Structures
```
GET /database/:id/structures
```

**Response** (PostgreSQL):
```json
{
  "success": true,
  "data": {
    "public": ["users", "orders", "products", "inventory"],
    "archive": ["old_users", "old_orders"]
  }
}
```

### List Table/Collection Data
```
GET /database/:id/data?structure=users&limit=50&offset=0
```

**Query Parameters**:
- `structure` (required): Table or collection name
- `limit` (optional): Max rows (default 50, max 1000)
- `offset` (optional): Pagination offset (default 0)

**Response**:
```json
{
  "success": true,
  "data": [
    {"id": 1, "name": "John Doe", "email": "john@example.com"},
    {"id": 2, "name": "Jane Smith", "email": "jane@example.com"}
  ],
  "metadata": {
    "limit": 50,
    "offset": 0,
    "count": 2
  }
}
```

### Execute Custom Query
```
POST /database/:id/query
Content-Type: application/json

{
  "query": "SELECT id, name FROM users WHERE status = ? LIMIT ?",
  "params": ["active", 100]
}
```

**Response** (SELECT):
```json
{
  "success": true,
  "data": [
    {"id": 1, "name": "John"},
    {"id": 2, "name": "Jane"}
  ]
}
```

**Response** (UPDATE/DELETE/INSERT):
```json
{
  "success": true,
  "affectedRows": 5
}
```

---

## Audit Logs

### List Audit Logs
```
GET /audit/logs?limit=50&offset=0
```

**Query Parameters**:
- `limit` (optional): Max results (default 50, max 500)
- `offset` (optional): Pagination offset (default 0)
- `action` (optional): Filter by action
- `userId` (optional): Filter by user ID
- `resourceType` (optional): Filter by resource type
- `status` (optional): Filter by status (success, failure, partial)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1001,
      "action": "database_query_executed",
      "user": {"id": 5, "email": "admin@example.com"},
      "resourceType": "DatabaseConnection",
      "resourceId": 42,
      "description": "Exécution de requête personnalisée",
      "status": "success",
      "ipAddress": "192.168.1.100",
      "httpMethod": "POST",
      "endpoint": "/api/admin/database/42/query",
      "oldValues": null,
      "newValues": null,
      "errorMessage": null,
      "createdAt": "2026-07-02T14:30:00+00:00"
    }
  ],
  "count": 1
}
```

### Get User Activity
```
GET /audit/user/:userId?limit=50
```

**Response**:
```json
{
  "success": true,
  "user": {"id": 5, "email": "admin@example.com"},
  "data": [...],
  "count": 20
}
```

### Get Access Denied Attempts
```
GET /audit/access-denied?hours=24&limit=100
```

**Query Parameters**:
- `hours` (optional): Look back period (default 24)
- `limit` (optional): Max results (default 100, max 500)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 999,
      "action": "access_denied",
      "user": {"id": 7, "email": "user@example.com"},
      "description": "Tentative d'accès refusée",
      "ipAddress": "192.168.1.50",
      "endpoint": "/api/admin/database/42/query",
      "errorMessage": "Insufficient permissions",
      "createdAt": "2026-07-02T10:15:00+00:00"
    }
  ],
  "count": 1
}
```

### Get Resource History
```
GET /audit/resource/:resourceType/:resourceId?limit=50
```

**Example**:
```
GET /audit/resource/DatabaseConnection/42
```

**Response**:
```json
{
  "success": true,
  "resource": {
    "type": "DatabaseConnection",
    "id": 42
  },
  "data": [
    {
      "id": 500,
      "action": "update",
      "user": {"id": 5, "email": "admin@example.com"},
      "description": "Connexion de base de données mise à jour",
      "status": "success",
      "oldValues": {"host": "old.db.com"},
      "newValues": {"host": "new.db.com"},
      "createdAt": "2026-07-02T11:00:00+00:00"
    }
  ],
  "count": 1
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "error": "Le champ 'name' est requis"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "error": "Authentification requise"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "error": "Opérations destructrices interdites sur la base de données de production"
}
```

### 404 Not Found
```json
{
  "success": false,
  "error": "Ressource non trouvée"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "error": "Erreur lors de l'exécution: ..."
}
```

---

## Required Permissions

### Database Connections
- GET: `admin.manage_database_connections`
- POST: `admin.manage_database_connections`
- PUT: `admin.manage_database_connections`
- DELETE: `admin.manage_database_connections`
- POST /test: `admin.manage_database_connections`

### Database Browser
- GET /structures: `admin.execute_queries`
- GET /data: `admin.execute_queries`
- POST /query: `admin.execute_queries`

### Audit Logs
- GET /logs: `admin.view_audit`
- GET /user/:userId: `admin.view_audit`
- GET /access-denied: `admin.view_audit`
- GET /resource/:type/:id: `admin.view_audit`

---

## Rate Limiting

- Limit per endpoint: 100 requests per minute per user
- Limit per IP: 1000 requests per minute
- Timeout per query: 30 seconds max
- Max rows per query: 1000 rows

---

## Examples Using curl

### Create and Test a Connection
```bash
# Create
CONN_ID=$(curl -s -X POST http://localhost/api/admin/database-connections \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test DB",
    "type": "postgresql",
    "host": "localhost",
    "port": 5432,
    "database": "testdb",
    "username": "testuser",
    "password": "testpass"
  }' | jq -r '.data.id')

# Test
curl -X POST http://localhost/api/admin/database-connections/$CONN_ID/test \
  -H "Authorization: Bearer $TOKEN"
```

### Query a Database
```bash
curl -X POST http://localhost/api/admin/database/1/query \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "SELECT COUNT(*) as total FROM users",
    "params": []
  }' | jq '.'
```

### Export Audit Logs as CSV
```bash
curl "http://localhost/api/admin/audit/logs?limit=500" \
  -H "Authorization: Bearer $TOKEN" | \
  jq -r '.data[] | "\(.id),\(.action),\(.user.email),\(.status),\(.createdAt)"' > audit.csv
```

---

## Webhook Events (Future)

The following audit events could trigger webhooks:
- `database_connection_created`
- `database_connection_deleted`
- `database_query_executed`
- `access_denied`

---

## WebSocket Support (Future)

Real-time updates for:
- Live audit log streaming
- Database connection status changes
- Query execution progress

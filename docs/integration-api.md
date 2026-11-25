# Item Tracking Module – Integration API (v1)

For the developer of the existing inventory system. This describes how your system and the item tracking module exchange data.

**Status: draft.** Field names and endpoints may still change once we have reviewed your database schema. We will tell you before anything changes.

## How it works

- Each system keeps its own database.
- **Your system sends** its records (branches, users, products, services, stock levels, purchase orders, requisitions) to the module whenever they are created or changed.
- **Your system collects** what happened in the module (items dispatched, received, missing, finished, refilled) from the updates feed, and applies it to stock.
- Both sides use **your IDs**. The module never asks you to store its IDs, except item codes such as `SP-000123`, which are printed on labels.

| Data | Owned by | Direction |
|---|---|---|
| Branches, users, products, services, purchase orders, requisitions, official stock levels | Your system | Your system → module |
| Item barcodes, scans, dispatches, receipts, missing items, finished items, refills | Module | Module → your system |

## Basics

- Base URL: `https://<module-host>/api/integration/v1`
- Authentication: every request sends the shared token we give you:
  `Authorization: Bearer <token>`
- Content type: `application/json`
- IDs: your IDs, as numbers or strings. Letters, digits and `. _ : -` only, up to 64 characters.
- Limits: up to 500 records per call, and 240 calls per minute.
- Every call is logged on our side with its time, endpoint, result and number of records.

Check your token:

```http
GET /ping
→ 200 {"ok": true, "api_version": "v1", "time": "2026-11-25T10:00:00+03:00"}
```

## Sending records to the module

Every endpoint below is a `PUT` that takes a batch:

```json
{ "data": [ { ...record }, { ...record } ] }
```

- A record is **created** if its ID is new and **updated** if the module already has it, so sending the same batch twice is safe.
- A batch is saved **whole or not at all**. If any record is invalid, nothing in the batch is saved, and the response says why.
- Fields you leave out keep their current value.
- Records are never deleted. Send `"active": false` instead.
- **Order matters:** send branches and products before anything that refers to them. If a referenced ID is unknown, the error names the endpoint to call first.

Successful response:

```json
{ "received": 2, "created": 1, "updated": 1 }
```

Error response (HTTP 422):

```json
{
  "message": "Unknown product id(s): 999. Send them to /products first.",
  "errors": { "product_id": ["Unknown product id(s): 999. Send them to /products first."] }
}
```

### PUT /branches

| Field | Type | Required | Notes |
|---|---|---|---|
| id | id | yes | |
| name | string | yes | |
| phone | string | no | |
| address | string | no | |
| is_head_office | boolean | no | Only one branch can be Head Office |
| active | boolean | no | Default `true` |

```json
{ "data": [
  { "id": 1, "name": "Head Office", "is_head_office": true },
  { "id": 2, "name": "Kansanga" }
] }
```

### PUT /users

| Field | Type | Required | Notes |
|---|---|---|---|
| id | id | yes | |
| name | string | yes | |
| email | string | no | Must be unique |
| phone | string | no | |
| role | string | no | Your role name. We map it to the module's roles once we have your role list |
| branch_ids | array of ids | no | The branches the user works at. Sending it replaces the list |
| active | boolean | no | |

```json
{ "data": [
  { "id": 7, "name": "Sarah N.", "email": "sarah@example.com", "role": "Branch Manager", "branch_ids": [2] }
] }
```

### PUT /products

| Field | Type | Required | Notes |
|---|---|---|---|
| id | id | yes | |
| name | string | yes | |
| category | string | no | Category name |
| unit | string | no | e.g. `bottle`, `tub` |
| grams_per_unit | number | no | Grams in one full unit, e.g. `1000` for a 1 kg tub |
| cost | number | no | Unit cost in UGX, used to value missing items |
| price | number | no | |
| is_refillable | boolean | no | If your system doesn't keep this, leave it out and we set it in the module |
| active | boolean | no | |

```json
{ "data": [
  { "id": 45, "name": "Shampoo 1L", "category": "Hair care", "unit": "bottle", "grams_per_unit": 1000, "cost": 30000 }
] }
```

### PUT /services

The products each service uses, and how many grams of each. Sending a service replaces its product list.

```json
{ "data": [
  { "id": 12, "name": "Wash & Treatment", "products": [
    { "product_id": 45, "grams": 20 },
    { "product_id": 46, "grams": 15 }
  ] }
] }
```

### PUT /stock-levels

Your official stock levels per branch. The module uses them for low-stock alerts, stock-takes and the daily check that both systems agree. Each branch and product pair appears once per batch.

| Field | Type | Required | Notes |
|---|---|---|---|
| branch_id | id | yes | |
| product_id | id | yes | |
| quantity | number | yes | Units in stock |
| grams | number | no | Total grams in stock, if you keep it |
| as_of | datetime | no | When this level applied. Default: now |

```json
{ "data": [
  { "branch_id": 2, "product_id": 45, "quantity": 12, "grams": 11850 }
] }
```

### PUT /purchase-orders

Items arriving at Head Office are scanned in against these. Sending an order replaces its lines.

```json
{ "data": [
  { "id": "PO-100", "reference": "PO-100", "supplier_name": "Beauty Supplies Ltd", "branch_id": 1,
    "status": "approved", "ordered_at": "2026-11-20",
    "lines": [ { "product_id": 45, "quantity": 24 }, { "product_id": 46, "quantity": 6 } ] }
] }
```

### PUT /requisitions

Requisitions and transfers, from Head Office or between branches. The module matches dispatch scans to them. `from_branch_id` may be left out if it isn't decided yet. Sending one replaces its lines.

```json
{ "data": [
  { "id": "RQ-5", "reference": "RQ-5", "from_branch_id": 1, "to_branch_id": 2, "status": "approved",
    "requested_at": "2026-11-24",
    "lines": [ { "product_id": 45, "quantity": 4 } ] }
] }
```

## Collecting updates from the module

```http
GET /updates?after=<last id you applied>&limit=100
```

The response lists updates oldest first:

```json
{
  "data": [
    { "id": 101, "type": "transfer.dispatched", "occurred_at": "2026-11-25T09:12:00+03:00", "data": { ... } }
  ],
  "next_after": 101,
  "has_more": false
}
```

**How to use it:**

1. Store the `id` of the last update you applied. Start with `0`.
2. Call `/updates?after=<that id>`, for example every minute.
3. Apply each update in order. Save its `id` in the same database transaction as the stock change, so a crash can never apply an update twice or skip one.
4. If `has_more` is `true`, call again straight away with `after=<next_after>`.

### Update types

Every payload refers to branches, products and users by **your IDs**. Item codes (e.g. `SP-000123`) are the module's labels.

**`transfer.dispatched`**: items scanned out at the sending location.

```json
{
  "transfer_id": "9b1e…", "reference": "TRF-261125-AB12", "requisition_id": "RQ-5",
  "from_branch_id": "1", "to_branch_id": "2",
  "dispatched_at": "2026-11-25T09:12:00+03:00", "dispatched_by": "7",
  "lines": [ { "product_id": "45", "quantity": 3, "item_codes": ["SP-000121", "SP-000122", "SP-000123"] } ],
  "empty_containers": []
}
```

**`transfer.received`**: the receiving branch confirmed what arrived. Anything not scanned in is listed under `missing`.

```json
{
  "transfer_id": "9b1e…", "reference": "TRF-261125-AB12", "requisition_id": "RQ-5",
  "from_branch_id": "1", "to_branch_id": "2",
  "received_at": "2026-11-25T14:40:00+03:00", "received_by": "8",
  "complete": false,
  "lines":   [ { "product_id": "45", "quantity": 2, "item_codes": ["SP-000121", "SP-000122"] } ],
  "missing": [ { "product_id": "45", "quantity": 1, "item_codes": ["SP-000123"] } ],
  "empty_containers": [], "missing_empty_containers": []
}
```

**`transfer.cancelled`**: a dispatch was cancelled before it was received. The items are back at the sending location. Its payload has the same transfer fields, plus `cancelled_by` and `reason`.

**`item.disposed`**: an item was used up and thrown away. It leaves stock.
**`item.needs_refill`**: a refillable item was emptied. It leaves stock until it is refilled.

```json
{ "item_code": "SP-000121", "product_id": "45", "branch_id": "2",
  "customers_served": 48, "grams_per_unit": 1000, "scanned_by": "9" }
```

**`item.refilled`**: an empty container was refilled and is back in stock. Its payload has `item_code`, `product_id`, `branch_id`, `refill_count` and `scanned_by`.

`empty_containers` lists refillable containers travelling empty for refilling. They are **not** stock.

### Planned update types (not live yet)

| Type | When |
|---|---|
| `service.completed` | A worker completed a service and scanned the items used: the service, worker, branch, and grams per product, so you can deduct grams |
| `emergency_purchase.approved` | A branch emergency purchase was recorded: item, quantity, price paid, reference price, receipt |
| `stock_take.adjustment_approved` | Head Office approved a stock-take difference |

## To agree before going live

1. **When stock changes.** Each stock movement must be applied by **one** system only. For example: does your system reduce Head Office stock when a transfer is approved, or should it wait for `transfer.dispatched`? We need your current rules to settle this.
2. **Role mapping.** Your role names and which module role each one gets.
3. **Signing in.** Whether staff can use one login for both systems.
4. **First load.** A one-time send of all current branches, users, products, services, open purchase orders, open requisitions and stock levels.
5. **Testing.** Both systems connected in a test environment before going live.

## Errors

| Status | Meaning |
|---|---|
| 200 | Saved, or updates returned |
| 401 | Missing or wrong token |
| 422 | Invalid data. Nothing in the batch was saved, and `errors` says why |
| 429 | Too many calls. Wait and retry |
| 503 | The API is not switched on yet |
| 5xx | Our problem. Retry later. The batch can safely be sent again |
